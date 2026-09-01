<?php

namespace App\Services\Booking;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Compensation\MemberCardValueLotService;
use App\Support\Finance\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingEntitlementService
{
    public function __construct(private readonly MemberCardValueLotService $valueLots) {}

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
        bool $allowPendingActivation = false,
    ): array {
        $amountCents = $cardType === CardType::StoredValue ? Money::decimalToCents($amountDelta) : null;
        $fingerprint = $this->fingerprint([
            'operation' => 'booking_deduct', 'cardId' => $memberCard->id, 'siteId' => $site->id,
            'cardType' => $cardType->value, 'count' => $countDelta, 'amountCents' => $amountCents,
            'actorAccountId' => $actorAccountId, 'actorStaffId' => $actorStaffId,
            'reason' => $reason, 'allowPendingActivation' => $allowPendingActivation,
        ]);

        return DB::transaction(function () use (
            $memberCard,
            $site,
            $commandKey,
            $cardType,
            $countDelta,
            $actorAccountId,
            $actorStaffId,
            $reason,
            $allowPendingActivation,
            $amountCents,
            $fingerprint,
        ) {
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertReplay($existing, $memberCard, $site, $fingerprint);

                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)->where('command_key', $commandKey)
                ->lockForUpdate()->first();
            if ($existing) {
                $this->assertReplay($existing, $locked, $site, $fingerprint);

                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            abort_if(
                in_array($locked->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)
                    || $locked->archived_at !== null,
                409,
                'MEMBER_CARD_ARCHIVED_MUTATION_BLOCKED',
            );
            abort_if($locked->status === MemberCardStatus::Frozen, 409, 'FROZEN_CARD_DEBIT_BLOCKED');
            // first-class 开卡模式：约课不开卡、上课才开卡，允许待激活扣费
            $payableStatuses = $allowPendingActivation
                ? [MemberCardStatus::Active, MemberCardStatus::PendingActivation]
                : [MemberCardStatus::Active];
            abort_unless(in_array($locked->status, $payableStatuses, true), 409, 'BOOKING_CARD_NOT_PAYABLE');

            if ($cardType === CardType::Count) {
                $delta = (int) $countDelta;
                abort_unless($locked->card_type === CardType::Count, 409, 'MEMBER_CARD_COUNT_ADJUST_INVALID');
                abort_if((int) ($locked->cached_remaining_count ?? 0) < $delta, 409, 'INSUFFICIENT_COUNT');
                $locked->cached_remaining_count = (int) ($locked->cached_remaining_count ?? 0) - $delta;
                $entryType = EntitlementLedgerEntryType::CountDeduct;
                $amountField = null;
                $countField = $delta;
                $countValue = $this->valueLots->allocateCountValue($locked, $delta);
            } elseif ($cardType === CardType::Period) {
                abort_unless($locked->card_type === CardType::Period, 409, 'BOOKING_CARD_NOT_PAYABLE');
                $entryType = EntitlementLedgerEntryType::PeriodUse;
                $amountField = null;
                $countField = null;
                $countValue = null;
            } else {
                $delta = (int) $amountCents;
                abort_unless($locked->card_type === CardType::StoredValue, 409, 'MEMBER_CARD_BALANCE_ADJUST_INVALID');
                $balanceCents = Money::decimalToCents($locked->cached_balance ?? '0');
                abort_if($balanceCents < $delta, 409, 'INSUFFICIENT_BALANCE');
                $locked->cached_balance = Money::centsToDecimal($balanceCents - $delta);
                $entryType = EntitlementLedgerEntryType::BalanceAdjust;
                $amountField = Money::centsToDecimal($delta);
                $countField = null;
                $countValue = null;
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
                'metadata' => [
                    'commandFingerprint' => $fingerprint,
                    'countValueAllocations' => $countValue['allocations'] ?? [],
                    'knownValueCents' => $countValue['knownValueCents'] ?? null,
                    'unknownCount' => $countValue['unknownCount'] ?? 0,
                    'valueProvenance' => $countValue['provenance'] ?? null,
                    'reservedValueCents' => $countValue['valueCents'] ?? null,
                    'valueLotId' => $countValue['valueLotId'] ?? null,
                ],
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
        string $reason = '取消预约返还',
        array $metadata = [],
    ): array {
        $fingerprint = $this->fingerprint([
            'operation' => 'booking_refund', 'originalId' => $original->id,
            'cardId' => $original->member_card_id, 'siteId' => $site->id,
            'actorAccountId' => $actorAccountId, 'actorStaffId' => $actorStaffId,
            'reason' => $reason, 'metadata' => $metadata,
        ]);

        return DB::transaction(function () use ($original, $site, $commandKey, $actorAccountId, $actorStaffId, $reason, $metadata, $fingerprint) {
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $original->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertReplay($existing, $original->memberCard, $site, $fingerprint);

                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $original->tenant_id)
                ->whereKey($original->member_card_id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $original->tenant_id)->where('command_key', $commandKey)
                ->lockForUpdate()->first();
            if ($existing) {
                $this->assertReplay($existing, $locked, $site, $fingerprint);

                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            $reversalDirection = match ($original->direction) {
                EntitlementLedgerDirection::Debit => EntitlementLedgerDirection::Credit,
                EntitlementLedgerDirection::Credit => EntitlementLedgerDirection::Debit,
                EntitlementLedgerDirection::Neutral => EntitlementLedgerDirection::Neutral,
            };

            if ($original->amount_delta !== null) {
                $current = Money::decimalToCents($locked->cached_balance ?? '0');
                $delta = Money::decimalToCents($original->amount_delta);
                $next = $reversalDirection === EntitlementLedgerDirection::Credit ? $current + $delta : $current - $delta;
                abort_if($next < 0, 409, 'INSUFFICIENT_BALANCE');
                $locked->cached_balance = Money::centsToDecimal($next);
            }

            if ($original->count_delta !== null) {
                $current = (int) ($locked->cached_remaining_count ?? 0);
                $locked->cached_remaining_count = $reversalDirection === EntitlementLedgerDirection::Credit
                    ? $current + (int) $original->count_delta
                    : $current - (int) $original->count_delta;
                abort_if((int) $locked->cached_remaining_count < 0, 409, 'INSUFFICIENT_COUNT');
                if ($reversalDirection === EntitlementLedgerDirection::Credit) {
                    $this->valueLots->restoreCountAllocations(
                        $locked,
                        $original->metadata['countValueAllocations'] ?? [],
                    );
                }
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
                'reason' => $reason,
                'metadata' => [
                    ...$metadata,
                    'commandFingerprint' => $fingerprint,
                    'restoredValueLotAllocations' => $original->metadata['countValueAllocations'] ?? [],
                ],
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
    /**
     * 旷课处罚「扣除」动作（原版：储值卡扣X元 / 次卡扣X次 / 期限卡扣X天）。
     * 幂等；余额/次数不足按可扣上限执行，不阻断旷课标记。
     *
     * @param  array{kind: 'money'|'count'|'days', value: float}  $spec
     * @return array{ledgerEntryId: int|null, created: bool}
     */
    public function applyAbsentDeduction(
        MemberCard $memberCard,
        Site $site,
        string $commandKey,
        array $spec,
        ?Staff $actorStaff = null,
    ): array {
        $amountCents = $spec['kind'] === 'money' ? Money::decimalToCents($spec['value']) : null;
        $fingerprint = $this->fingerprint([
            'operation' => 'absent_deduction', 'cardId' => $memberCard->id, 'siteId' => $site->id,
            'kind' => $spec['kind'], 'value' => $spec['kind'] === 'money' ? $amountCents : (int) $spec['value'],
            'actorStaffId' => $actorStaff?->id,
        ]);

        return DB::transaction(function () use ($memberCard, $site, $commandKey, $spec, $actorStaff, $amountCents, $fingerprint) {
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertReplay($existing, $memberCard, $site, $fingerprint);

                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)->where('command_key', $commandKey)
                ->lockForUpdate()->first();
            if ($existing) {
                $this->assertReplay($existing, $locked, $site, $fingerprint);

                return ['ledgerEntryId' => $existing->id, 'created' => false];
            }

            if (in_array($locked->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)
                || $locked->archived_at !== null) {
                return ['ledgerEntryId' => null, 'created' => false];
            }

            $amountField = null;
            $countField = null;
            $validUntilAfter = null;
            $countValue = null;

            if ($spec['kind'] === 'money') {
                $available = Money::decimalToCents($locked->cached_balance ?? '0');
                $delta = min($available, (int) $amountCents);
                if ($delta <= 0) {
                    return ['ledgerEntryId' => null, 'created' => false];
                }
                $locked->cached_balance = Money::centsToDecimal($available - $delta);
                $amountField = Money::centsToDecimal($delta);
            } elseif ($spec['kind'] === 'count') {
                $available = (int) ($locked->cached_remaining_count ?? 0);
                $delta = min($available, (int) $spec['value']);
                if ($delta <= 0) {
                    return ['ledgerEntryId' => null, 'created' => false];
                }
                $locked->cached_remaining_count = $available - $delta;
                $countField = $delta;
                $countValue = $this->valueLots->allocateCountValue($locked, $delta);
            } else {
                if ($locked->valid_until === null) {
                    return ['ledgerEntryId' => null, 'created' => false];
                }
                $days = (int) $spec['value'];
                if ($days <= 0) {
                    return ['ledgerEntryId' => null, 'created' => false];
                }
                $locked->valid_until = Carbon::parse($locked->valid_until)->subDays($days)->toDateString();
                $validUntilAfter = $locked->valid_until;
            }

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $locked->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::Penalty,
                'direction' => EntitlementLedgerDirection::Debit,
                'amount_delta' => $amountField,
                'count_delta' => $countField,
                'valid_until_after' => $validUntilAfter,
                'command_key' => $commandKey,
                'reason' => '旷课处罚扣除',
                'metadata' => [
                    'commandFingerprint' => $fingerprint,
                    'countValueAllocations' => $countValue['allocations'] ?? [],
                    'knownValueCents' => $countValue['knownValueCents'] ?? null,
                    'unknownCount' => $countValue['unknownCount'] ?? 0,
                    'valueProvenance' => $countValue['provenance'] ?? null,
                    'penaltyDoesNotGenerateCommission' => true,
                ],
                'actor_staff_id' => $actorStaff?->id,
                'occurred_at' => now(),
            ]);

            $locked->save();

            return ['ledgerEntryId' => $entry->id, 'created' => true];
        });
    }

    public function applyAbsentPenalty(
        MemberCard $memberCard,
        Site $site,
        string $commandKey,
        int $appointmentId,
        ?Staff $actorStaff = null,
    ): array {
        $fingerprint = $this->fingerprint([
            'operation' => 'absent_penalty',
            'cardId' => $memberCard->id,
            'siteId' => $site->id,
            'appointmentId' => $appointmentId,
            'actorStaffId' => $actorStaff?->id,
        ]);

        return DB::transaction(function () use ($memberCard, $site, $commandKey, $appointmentId, $actorStaff, $fingerprint) {
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertReplay($existing, $memberCard, $site, $fingerprint);

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
            $existing = EntitlementLedgerEntry::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->assertReplay($existing, $locked, $site, $fingerprint);

                return [
                    'ledgerEntryId' => $existing->id,
                    'created' => false,
                    'cardFrozen' => $locked->status === MemberCardStatus::Frozen,
                ];
            }

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
                'metadata' => [
                    'appointmentId' => $appointmentId,
                    'cardFrozen' => $cardFrozen,
                    'commandFingerprint' => $fingerprint,
                ],
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

    private function assertReplay(
        EntitlementLedgerEntry $entry,
        MemberCard $card,
        Site $site,
        string $fingerprint,
    ): void {
        abort_unless(
            $entry->member_card_id === $card->id
            && $entry->site_id === $site->id
            && hash_equals((string) ($entry->metadata['commandFingerprint'] ?? ''), $fingerprint),
            409,
            'IDEMPOTENCY_KEY_REUSED',
        );
    }

    private function fingerprint(array $payload): string
    {
        $sort = function (&$value) use (&$sort) {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as &$item) {
                $sort($item);
            }
            if (! array_is_list($value)) {
                ksort($value);
            }
        };
        $sort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
