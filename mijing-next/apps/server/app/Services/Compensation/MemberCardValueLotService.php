<?php

namespace App\Services\Compensation;

use App\Enums\CardType;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCardValueLot;
use App\Services\Cards\MemberCardHolidayCalendarService;
use App\Support\Finance\Money;
use Carbon\Carbon;

class MemberCardValueLotService
{
    public function __construct(
        private DeterministicAllocationCalculator $allocator,
        private MemberCardHolidayCalendarService $holidays,
    ) {}

    public function recordForOrder(MemberCardOrder $order, MemberCard $card): MemberCardValueLot
    {
        $commandKey = 'order:'.$order->id.':value-lot';
        $existing = MemberCardValueLot::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('command_key', $commandKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        $paidCents = $order->paid_amount_cents ?? Money::decimalToCents($order->amount);

        return $this->createLot($card, [
            'order_id' => $order->id,
            'source_type' => 'purchase',
            'payment_method' => $order->payment_method ?? 'online',
            'value_provenance' => 'actual',
            'paid_amount_cents' => $paidCents,
            'command_key' => $commandKey,
            'occurred_at' => $order->paid_at ?? now(),
        ]);
    }

    public function recordForIssue(MemberCard $card, array $payload): MemberCardValueLot
    {
        $commandKey = 'member-card:'.$card->id.':issue-value-lot';
        $existing = MemberCardValueLot::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('command_key', $commandKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        $paidCents = array_key_exists('actualAmount', $payload)
            ? Money::decimalToCents($payload['actualAmount'])
            : (array_key_exists('paidAmountCents', $payload)
                ? (int) $payload['paidAmountCents']
                : (array_key_exists('paidAmount', $payload) ? Money::decimalToCents($payload['paidAmount']) : null));
        abort_if($paidCents !== null && $paidCents < 0, 422, 'MEMBER_CARD_PAID_AMOUNT_INVALID');

        return $this->createLot($card, [
            'source_type' => 'staff_issue',
            'payment_method' => $payload['paymentMethod'] ?? null,
            'value_provenance' => $paidCents === null ? 'unknown' : 'actual',
            'paid_amount_cents' => $paidCents,
            'command_key' => $commandKey,
            'occurred_at' => $card->issued_at ?? now(),
        ]);
    }

    public function recordDerivedBackfill(MemberCard $card, ?int $paidAmountCents, string $provenance): MemberCardValueLot
    {
        abort_unless(in_array($provenance, ['derived', 'unknown'], true), 422);

        if ($paidAmountCents === null) {
            $provenance = 'unknown';
        }
        $attributes = [];
        if ($card->card_type === CardType::Count) {
            $initial = max(0, (int) ($card->product_snapshot['initialCount'] ?? 0));
            $current = max(0, (int) ($card->cached_remaining_count ?? 0));
            // The paid denominator is the original issued count, never the cached
            // remainder. Any excess current units are unprovable manual/gift value.
            $denominator = $initial > 0 ? $initial : $current;
            $attributes = [
                'entitlement_count' => $denominator,
                'remaining_count' => min($current, $denominator),
            ];
        }

        $lot = $this->createLot($card, [
            'source_type' => 'backfill',
            'payment_method' => null,
            'value_provenance' => $provenance,
            'paid_amount_cents' => $paidAmountCents,
            'command_key' => 'member-card:'.$card->id.':backfill-value-lot',
            'occurred_at' => $card->issued_at ?? now(),
            ...$attributes,
        ]);

        if ($card->card_type === CardType::Count) {
            $extra = max(0, (int) ($card->cached_remaining_count ?? 0) - (int) $lot->remaining_count);
            if ($extra > 0) {
                $this->createLot($card, [
                    'source_type' => 'backfill_unknown_excess',
                    'payment_method' => null,
                    'value_provenance' => 'unknown',
                    'paid_amount_cents' => null,
                    'entitlement_count' => $extra,
                    'remaining_count' => $extra,
                    'command_key' => 'member-card:'.$card->id.':backfill-unknown-excess',
                    'metadata' => ['reason' => 'cached count exceeds provable issued count'],
                    'occurred_at' => $card->issued_at ?? now(),
                ]);
            }
        }

        return $lot;
    }

    /**
     * Manual count credits have no sale consideration. Keep them in their own
     * unknown-value lot so they can never inherit value from an older paid lot.
     */
    public function recordManualCountCredit(
        MemberCard $card,
        EntitlementLedgerEntry $entry,
        int $count,
        string $commandKey,
    ): MemberCardValueLot {
        abort_unless($card->card_type === CardType::Count && $count > 0, 422, 'MEMBER_CARD_COUNT_ADJUST_INVALID');

        return $this->createLot($card, [
            'source_ledger_entry_id' => $entry->id,
            'source_type' => 'manual_adjustment',
            'payment_method' => null,
            'value_provenance' => 'unknown',
            'paid_amount_cents' => null,
            'entitlement_count' => $count,
            'remaining_count' => $count,
            'command_key' => $commandKey,
            'metadata' => ['reason' => $entry->reason, 'freeOrUnknown' => true],
            'occurred_at' => $entry->occurred_at ?? now(),
        ]);
    }

    /** @param list<array{valueLotId:int|null,count:int,valueCents:int|null}> $allocations */
    public function restoreCountAllocations(MemberCard $card, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            if (($allocation['valueLotId'] ?? null) === null) {
                continue;
            }
            $lot = MemberCardValueLot::query()
                ->where('tenant_id', $card->tenant_id)
                ->where('member_card_id', $card->id)
                ->whereKey((int) $allocation['valueLotId'])
                ->lockForUpdate()
                ->firstOrFail();
            $restored = (int) $lot->remaining_count + (int) $allocation['count'];
            abort_if($restored > (int) $lot->entitlement_count, 409, 'VALUE_LOT_RESTORE_OVERFLOW');
            $lot->update(['remaining_count' => $restored]);
        }
    }

    /**
     * Reverse a manual count credit from the exact unknown/free lot it created.
     * If that lot has already been consumed, correction is rejected rather than
     * silently burning an older paid lot.
     *
     * @return array{valueCents:?int,knownValueCents:int,unknownCount:int,provenance:string,valueLotId:int,allocations:list<array<string,int|null>>}
     */
    public function consumeManualCountCredit(MemberCard $card, EntitlementLedgerEntry $entry, int $count): array
    {
        $lot = MemberCardValueLot::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('source_ledger_entry_id', $entry->id)
            ->where('source_type', 'manual_adjustment')
            ->lockForUpdate()
            ->firstOrFail();
        abort_if($count < 1 || (int) $lot->remaining_count < $count, 409, 'MANUAL_COUNT_CREDIT_ALREADY_CONSUMED');
        $lot->update(['remaining_count' => (int) $lot->remaining_count - $count]);

        return [
            'valueCents' => null,
            'knownValueCents' => 0,
            'unknownCount' => $count,
            'provenance' => 'unknown',
            'valueLotId' => $lot->id,
            'allocations' => [['valueLotId' => $lot->id, 'count' => $count, 'valueCents' => null]],
        ];
    }

    /**
     * @return array{valueCents:?int, knownValueCents:int, unknownCount:int, provenance:string, valueLotId:?int, allocations:list<array<string,int|null>>}
     */
    public function allocateCountValue(MemberCard $card, int $count): array
    {
        abort_if($count < 1, 422, 'CONSUMPTION_COUNT_INVALID');

        $lots = MemberCardValueLot::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->whereNotNull('entitlement_count')
            ->where('remaining_count', '>', 0)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $count;
        $value = 0;
        $knownCount = 0;
        $unknownCount = 0;
        $provenance = 'actual';
        $allocations = [];

        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }
            $take = min($remaining, (int) $lot->remaining_count);
            $offset = (int) $lot->entitlement_count - (int) $lot->remaining_count;
            $lotValue = null;
            if ($lot->paid_amount_cents !== null) {
                $lotValue = $this->allocator->unitSlice(
                    (int) $lot->paid_amount_cents,
                    (int) $lot->entitlement_count,
                    $offset,
                    $take,
                );
                $value += $lotValue;
                $knownCount += $take;
                if ($lot->value_provenance !== 'actual') {
                    $provenance = $lot->value_provenance;
                }
            } else {
                $unknownCount += $take;
            }

            $lot->update(['remaining_count' => (int) $lot->remaining_count - $take]);
            $allocations[] = ['valueLotId' => $lot->id, 'count' => $take, 'valueCents' => $lotValue];
            $remaining -= $take;
        }
        if ($remaining > 0) {
            // Historical/manual credits may not yet have a lot. Consume every known FIFO unit first,
            // then snapshot the untracked remainder so known value can never be reused later.
            $allocations[] = ['valueLotId' => null, 'count' => $remaining, 'valueCents' => null];
            $unknownCount += $remaining;
        }
        $provenance = $unknownCount === 0
            ? $provenance
            : ($knownCount > 0 ? 'partial_unknown' : 'unknown');

        return [
            // A mixed debit keeps the provable paid portion. Unknown/gift units are
            // snapshotted separately and never erase or reuse known paid value.
            'valueCents' => $knownCount > 0 ? $value : null,
            'knownValueCents' => $value,
            'unknownCount' => $unknownCount,
            'provenance' => $provenance,
            'valueLotId' => collect($allocations)->first(fn ($allocation) => $allocation['valueLotId'] !== null)['valueLotId'] ?? null,
            'allocations' => $allocations,
        ];
    }

    /** @return array{valueCents:?int, knownValueCents?:int, unknownCount?:int, provenance:string} */
    public function previewCountValue(MemberCard $card, int $count): array
    {
        if ($count < 1) {
            return ['valueCents' => null, 'provenance' => 'unknown'];
        }
        $lots = MemberCardValueLot::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->whereNotNull('entitlement_count')
            ->where('remaining_count', '>', 0)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
        $remaining = $count;
        $value = 0;
        $provenance = 'actual';
        $knownCount = 0;
        $unknownCount = 0;
        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }
            if ($lot->paid_amount_cents === null) {
                $take = min($remaining, (int) $lot->remaining_count);
                $unknownCount += $take;
                $remaining -= $take;

                continue;
            }
            $take = min($remaining, (int) $lot->remaining_count);
            $offset = (int) $lot->entitlement_count - (int) $lot->remaining_count;
            $value += $this->allocator->unitSlice(
                (int) $lot->paid_amount_cents,
                (int) $lot->entitlement_count,
                $offset,
                $take,
            );
            $knownCount += $take;
            if ($lot->value_provenance !== 'actual') {
                $provenance = $lot->value_provenance;
            }
            $remaining -= $take;
        }
        if ($remaining > 0) {
            $unknownCount += $remaining;
        }

        $provenance = $unknownCount === 0
            ? $provenance
            : ($knownCount > 0 ? 'partial_unknown' : 'unknown');

        return [
            'valueCents' => $knownCount > 0 ? $value : null,
            'knownValueCents' => $value,
            'unknownCount' => $unknownCount,
            'provenance' => $provenance,
        ];
    }

    /**
     * Earliest covering lot wins when period lots overlap. This makes renewals deterministic.
     *
     * @return array{valueCents:?int, provenance:string, valueLotId:?int, dayOrdinal:?int}
     */
    public function periodDayValue(MemberCard $card, string $businessDate): array
    {
        $date = Carbon::parse($businessDate)->startOfDay();
        $lots = MemberCardValueLot::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->whereNotNull('entitlement_days')
            ->orderByRaw('valid_from IS NULL')
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get();

        foreach ($lots as $lot) {
            $from = $lot->valid_from ?? $card->valid_from;
            if ($from === null) {
                continue;
            }
            $calendarOrdinal = (int) Carbon::parse($from)->startOfDay()->diffInDays($date, false);
            $ordinal = $calendarOrdinal - $this->holidays->completedBlackoutDaysBefore(
                $card,
                Carbon::parse($from)->toDateString(),
                $date->toDateString(),
            );
            if ($ordinal < 0 || $ordinal >= (int) $lot->entitlement_days) {
                continue;
            }

            return [
                'valueCents' => $lot->paid_amount_cents === null
                    ? null
                    : $this->allocator->unitSlice(
                        (int) $lot->paid_amount_cents,
                        (int) $lot->entitlement_days,
                        $ordinal,
                        1,
                    ),
                'provenance' => $lot->paid_amount_cents === null ? 'unknown' : $lot->value_provenance,
                'valueLotId' => $lot->id,
                'dayOrdinal' => $ordinal,
            ];
        }

        return ['valueCents' => null, 'provenance' => 'unknown', 'valueLotId' => null, 'dayOrdinal' => null];
    }

    private function createLot(MemberCard $card, array $attributes): MemberCardValueLot
    {
        $snapshot = $card->product_snapshot ?? [];
        $sourceLedger = EntitlementLedgerEntry::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->orderBy('id')
            ->first();
        $days = $card->card_type === CardType::Period ? (int) ($snapshot['validityDays'] ?? 0) : null;
        $validFrom = $card->valid_from;

        return MemberCardValueLot::query()->firstOrCreate([
            'tenant_id' => $card->tenant_id,
            'command_key' => $attributes['command_key'],
        ], [
            'site_id' => $card->site_id,
            'member_id' => $card->member_id,
            'member_card_id' => $card->id,
            'source_ledger_entry_id' => $sourceLedger?->id,
            'entitlement_amount_cents' => $card->card_type === CardType::StoredValue
                ? Money::decimalToCents($snapshot['faceValue'] ?? $card->cached_balance ?? '0')
                : null,
            'entitlement_count' => $card->card_type === CardType::Count
                ? (int) ($card->cached_remaining_count ?? $snapshot['initialCount'] ?? 0)
                : null,
            'remaining_count' => $card->card_type === CardType::Count
                ? ($attributes['source_type'] === 'backfill'
                    ? (int) ($card->cached_remaining_count ?? 0)
                    : (int) ($card->cached_remaining_count ?? $snapshot['initialCount'] ?? 0))
                : null,
            'entitlement_days' => $days > 0 ? $days : null,
            'valid_from' => $validFrom,
            'valid_until' => $validFrom !== null && $days > 0
                ? $validFrom->copy()->addDays($days - 1)
                : null,
            'product_version' => isset($snapshot['productVersion']) ? (int) $snapshot['productVersion'] : null,
            ...$attributes,
        ]);
    }
}
