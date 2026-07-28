<?php

namespace App\Services\Cards;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 会员卡自动激活（对标原版开卡时间五种模式的自动触发器）。
 *
 * - first-use   ：首次用卡预约时激活
 * - first-class ：首次上课（签到）时激活；预约阶段允许待激活扣费
 * - delayed     ：购卡满 N 天自动激活（定时任务 + 用卡时懒激活兜底）
 * - immediate / manual：不在本服务职责内（发卡即激活 / 永远人工）
 */
class MemberCardAutoActivationService
{
    /**
     * 实时读卡种激活模式（回退发卡快照），保证改配置立即生效。
     */
    public function activationMode(MemberCard $card): string
    {
        $card->loadMissing('cardProduct');
        $mode = $card->cardProduct?->activation_mode
            ?? ($card->product_snapshot['activationMode'] ?? null);

        return is_string($mode) && $mode !== '' ? $mode : 'immediate';
    }

    public function activationDelayDays(MemberCard $card): int
    {
        $card->loadMissing('cardProduct');
        $rules = $card->cardProduct?->booking_rules;
        if (! is_array($rules)) {
            $rules = $card->product_snapshot['bookingRules'] ?? [];
        }

        return (int) ($rules['activationDays'] ?? 0);
    }

    public function delayedActivationDue(MemberCard $card): bool
    {
        $days = $this->activationDelayDays($card);
        if ($days < 1 || $card->issued_at === null) {
            return false;
        }

        return Carbon::parse($card->issued_at)->addDays($days)->isPast();
    }

    /**
     * 预约用卡时的自动激活钩子：
     * first-use → 激活；delayed 已到期 → 懒激活兜底（调度器故障也不影响会员使用）。
     * 返回处理后的最新卡模型。
     */
    public function maybeActivateForBooking(MemberCard $card, ?int $actorAccountId = null): MemberCard
    {
        if ($card->status !== MemberCardStatus::PendingActivation) {
            return $card;
        }

        $mode = $this->activationMode($card);
        if ($mode === 'first-use' || ($mode === 'delayed' && $this->delayedActivationDue($card))) {
            return $this->activateNow($card, match ($mode) {
                'first-use' => '首次使用自动开卡',
                default => '购卡满期自动开卡',
            }, actorAccountId: $actorAccountId);
        }

        return $card;
    }

    /**
     * 预约扣费是否允许待激活状态（仅 first-class：约课不计时、上课才开卡）。
     */
    public function allowsPendingDeduction(MemberCard $card): bool
    {
        return $card->status === MemberCardStatus::PendingActivation
            && $this->activationMode($card) === 'first-class';
    }

    /**
     * 签到（实际上课）时的自动激活钩子：first-class 主触发；
     * first-use / delayed 到期 在此兜底，保证任何路径下卡不会卡死在待激活。
     */
    public function maybeActivateForAttendance(MemberCard $card, ?int $actorStaffId = null): MemberCard
    {
        if ($card->status !== MemberCardStatus::PendingActivation) {
            return $card;
        }

        $mode = $this->activationMode($card);
        if ($mode === 'first-class' || $mode === 'first-use'
            || ($mode === 'delayed' && $this->delayedActivationDue($card))) {
            return $this->activateNow($card, '首次上课自动开卡', actorStaffId: $actorStaffId);
        }

        return $card;
    }

    /**
     * 定时任务：激活所有已到期的 delayed 卡。逐卡独立处理，单卡失败不影响其余。
     *
     * @return array{activated: int, failed: int}
     */
    public function activateDueDelayedCards(int $chunkSize = 100): array
    {
        $activated = 0;
        $failed = 0;

        MemberCard::query()
            ->where('status', MemberCardStatus::PendingActivation)
            ->whereNotNull('issued_at')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($cards) use (&$activated, &$failed) {
                foreach ($cards as $card) {
                    try {
                        if ($this->activationMode($card) !== 'delayed' || ! $this->delayedActivationDue($card)) {
                            continue;
                        }
                        $this->activateNow($card, '购卡满期自动开卡');
                        $activated++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        report($exception);
                    }
                }
            });

        return ['activated' => $activated, 'failed' => $failed];
    }

    /**
     * 统一激活原语：幂等、行锁；期限卡自激活日起算有效期（与手动激活行为一致）。
     */
    public function activateNow(
        MemberCard $card,
        string $reason,
        ?int $actorAccountId = null,
        ?int $actorStaffId = null,
    ): MemberCard {
        return DB::transaction(function () use ($card, $reason, $actorAccountId, $actorStaffId) {
            $locked = MemberCard::query()
                ->where('tenant_id', $card->tenant_id)
                ->whereKey($card->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== MemberCardStatus::PendingActivation) {
                return $locked; // 幂等：并发下另一请求已激活
            }

            $snapshot = $locked->product_snapshot ?? [];
            $cardType = CardType::tryFrom($snapshot['cardType'] ?? '') ?? $locked->card_type;
            $updates = ['status' => MemberCardStatus::Active];
            $validFrom = null;
            $validUntil = null;

            if ($cardType === CardType::Period) {
                $days = (int) ($snapshot['validityDays'] ?? 0);
                abort_if($days < 1, 409, 'MEMBER_CARD_ACTIVATION_INVALID');
                $validFrom = now()->toDateString();
                $validUntil = now()->addDays($days)->toDateString();
                $updates['valid_from'] = $validFrom;
                $updates['valid_until'] = $validUntil;
            }

            $locked->update($updates);

            if ($cardType === CardType::Period) {
                EntitlementLedgerEntry::create([
                    'tenant_id' => $locked->tenant_id,
                    'site_id' => $locked->site_id,
                    'member_card_id' => $locked->id,
                    'member_id' => $locked->member_id,
                    'entry_type' => EntitlementLedgerEntryType::ValidityChange,
                    'direction' => EntitlementLedgerDirection::Neutral,
                    'valid_from_after' => $validFrom,
                    'valid_until_after' => $validUntil,
                    'command_key' => (string) Str::uuid(),
                    'reason' => $reason,
                    'actor_account_id' => $actorAccountId,
                    'actor_staff_id' => $actorStaffId,
                    'occurred_at' => now(),
                ]);
            }

            return $locked->fresh();
        });
    }
}
