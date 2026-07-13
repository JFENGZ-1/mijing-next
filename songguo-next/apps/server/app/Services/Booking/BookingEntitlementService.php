<?php

namespace App\Services\Booking;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\Staff;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

class BookingEntitlementService
{
    /**
     * @return array{ledgerEntryId: int, created: bool}
     */
    public function deductForBooking(
        MemberCard $memberCard,
        Site $site,
        string $commandKey,
        CardType $cardType,
        ?int $countDelta,
        ?string $amountDelta,
        ?int $actorAccountId = null,
        ?int $actorStaffId = null,
        string $reason = '预约扣费',
    ): array {
        return DB::transaction(function () use (
            $memberCard,
            $site,
            $commandKey,
            $cardType,
            $countDelta,
            $amountDelta,
            $actorAccountId,
            $actorStaffId,
            $reason,
        ) {
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                in_array($locked->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)
                    || $locked->archived_at !== null,
                409,
                'MEMBER_CARD_ARCHIVED_MUTATION_BLOCKED',
            );
            abort_if($locked->status === MemberCardStatus::Frozen, 409, 'FROZEN_CARD_DEBIT_BLOCKED');
            abort_unless($locked->status === MemberCardStatus::Active, 409, 'BOOKING_CARD_NOT_PAYABLE');

            if ($cardType === CardType::Count) {
                $delta = (int) $countDelta;
                abort_unless($locked->card_type === CardType::Count, 409, 'MEMBER_CARD_COUNT_ADJUST_INVALID');
                abort_if((int) ($locked->cached_remaining_count ?? 0) < $delta, 409, 'INSUFFICIENT_COUNT');
                $locked->cached_remaining_count = (int) ($locked->cached_remaining_count ?? 0) - $delta;
                $entryType = EntitlementLedgerEntryType::CountDeduct;
                $amountField = null;
                $countField = $delta;
            } elseif ($cardType === CardType::Period) {
                abort_unless($locked->card_type === CardType::Period, 409, 'BOOKING_CARD_NOT_PAYABLE');
                $entryType = EntitlementLedgerEntryType::PeriodUse;
                $amountField = null;
                $countField = null;
            } else {
                $delta = number_format((float) $amountDelta, 2, '.', '');
                abort_unless($locked->card_type === CardType::StoredValue, 409, 'MEMBER_CARD_BALANCE_ADJUST_INVALID');
                abort_if((float) ($locked->cached_balance ?? 0) < (float) $delta, 409, 'INSUFFICIENT_BALANCE');
                $locked->cached_balance = number_format((float) $locked->cached_balance - (float) $delta, 2, '.', '');
                $entryType = EntitlementLedgerEntryType::BalanceAdjust;
                $amountField = $delta;
                $countField = null;
            }

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $locked->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => $entryType,
                'direction' => EntitlementLedgerDirection::Debit,
                'amount_delta' => $amountField,
                'count_delta' => $countField,
                'command_key' => $commandKey,
                'reason' => $reason,
                'actor_account_id' => $actorAccountId,
                'actor_staff_id' => $actorStaffId,
                'occurred_at' => now(),
            ]);

            $locked->save();

            return ['ledgerEntryId' => $entry->id, 'created' => true];
        });
    }

    /**
     * @return array{ledgerEntryId: int|null, created: bool}
     */
    public function refundForCancellation(
        EntitlementLedgerEntry $original,
        Site $site,
        string $commandKey,
        ?int $actorAccountId = null,
        ?int $actorStaffId = null,
    ): array {
        return DB::transaction(function () use ($original, $site, $commandKey, $actorAccountId, $actorStaffId) {
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $original->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $original->tenant_id)
                ->whereKey($original->member_card_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reversalDirection = match ($original->direction) {
                EntitlementLedgerDirection::Debit => EntitlementLedgerDirection::Credit,
                EntitlementLedgerDirection::Credit => EntitlementLedgerDirection::Debit,
                EntitlementLedgerDirection::Neutral => EntitlementLedgerDirection::Neutral,
            };

            if ($original->amount_delta !== null) {
                $current = (float) ($locked->cached_balance ?? 0);
                $locked->cached_balance = $reversalDirection === EntitlementLedgerDirection::Credit
                    ? number_format($current + (float) $original->amount_delta, 2, '.', '')
                    : number_format($current - (float) $original->amount_delta, 2, '.', '');
            }

            if ($original->count_delta !== null) {
                $current = (int) ($locked->cached_remaining_count ?? 0);
                $locked->cached_remaining_count = $reversalDirection === EntitlementLedgerDirection::Credit
                    ? $current + (int) $original->count_delta
                    : $current - (int) $original->count_delta;
            }

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $original->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::Reversal,
                'direction' => $reversalDirection,
                'amount_delta' => $original->amount_delta,
                'count_delta' => $original->count_delta,
                'reversal_of_id' => $original->id,
                'command_key' => $commandKey,
                'reason' => '取消预约返还',
                'actor_account_id' => $actorAccountId,
                'actor_staff_id' => $actorStaffId,
                'occurred_at' => now(),
            ]);

            $locked->save();

            return ['ledgerEntryId' => $entry->id, 'created' => true];
        });
    }

    /**
     * Records an absent penalty on the member card. When the card is active, freezes it.
     * Card-product threshold rules (week/month absent limits) are deferred — site policy toggle only.
     *
     * @return array{ledgerEntryId: int, created: bool, cardFrozen: bool}
     */
    public function applyAbsentPenalty(
        MemberCard $memberCard,
        Site $site,
        string $commandKey,
        int $appointmentId,
        ?Staff $actorStaff = null,
    ): array {
        return DB::transaction(function () use ($memberCard, $site, $commandKey, $appointmentId, $actorStaff) {
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'ledgerEntryId' => $existing->id,
                    'created' => false,
                    'cardFrozen' => $memberCard->fresh()->status === MemberCardStatus::Frozen,
                ];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                in_array($locked->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)
                    || $locked->archived_at !== null,
                409,
                'MEMBER_CARD_ARCHIVED_MUTATION_BLOCKED',
            );

            $cardFrozen = false;

            if ($locked->status === MemberCardStatus::Active) {
                $locked->status = MemberCardStatus::Frozen;
                $locked->freeze_state = [
                    'frozenAt' => now()->toIso8601String(),
                    'frozenByStaffId' => $actorStaff?->id,
                    'reason' => '旷课处罚',
                    'source' => 'absent_penalty',
                    'appointmentId' => $appointmentId,
                ];
                $locked->save();
                $cardFrozen = true;

                EntitlementLedgerEntry::create([
                    'tenant_id' => $locked->tenant_id,
                    'site_id' => $site->id,
                    'member_card_id' => $locked->id,
                    'member_id' => $locked->member_id,
                    'entry_type' => EntitlementLedgerEntryType::Freeze,
                    'direction' => EntitlementLedgerDirection::Neutral,
                    'command_key' => $this->deriveChildCommandKey($commandKey, 'freeze'),
                    'reason' => '旷课处罚冻结',
                    'metadata' => ['appointmentId' => $appointmentId, 'source' => 'absent_penalty'],
                    'actor_staff_id' => $actorStaff?->id,
                    'occurred_at' => now(),
                ]);
            }

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $locked->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::Penalty,
                'direction' => EntitlementLedgerDirection::Neutral,
                'command_key' => $commandKey,
                'reason' => '旷课处罚',
                'metadata' => ['appointmentId' => $appointmentId, 'cardFrozen' => $cardFrozen],
                'actor_staff_id' => $actorStaff?->id,
                'occurred_at' => now(),
            ]);

            return ['ledgerEntryId' => $entry->id, 'created' => true, 'cardFrozen' => $cardFrozen];
        });
    }

    private function deriveChildCommandKey(string $parentKey, string $suffix): string
    {
        $hash = md5($parentKey.':'.$suffix);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
