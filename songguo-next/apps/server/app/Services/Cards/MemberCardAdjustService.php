<?php

namespace App\Services\Cards;

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
use Illuminate\Support\Facades\DB;

class MemberCardAdjustService
{
    public function __construct(private MemberCardValueLotService $valueLots) {}

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function adjustBalance(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        return $this->adjust(
            $staff,
            $site,
            $memberCard,
            $payload,
            CardType::StoredValue,
            EntitlementLedgerEntryType::BalanceAdjust,
            'amount',
            'cached_balance',
            'MEMBER_CARD_BALANCE_ADJUST_INVALID',
            'INSUFFICIENT_BALANCE',
        );
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function adjustCount(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        return $this->adjust(
            $staff,
            $site,
            $memberCard,
            $payload,
            CardType::Count,
            EntitlementLedgerEntryType::CountAdjust,
            'count',
            'cached_remaining_count',
            'MEMBER_CARD_COUNT_ADJUST_INVALID',
            'INSUFFICIENT_COUNT',
        );
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    private function adjust(
        Staff $staff,
        Site $site,
        MemberCard $memberCard,
        array $payload,
        CardType $expectedType,
        EntitlementLedgerEntryType $entryType,
        string $amountField,
        string $cacheField,
        string $invalidCode,
        string $insufficientCode,
    ): array {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        $commandKey = $payload['commandKey'];
        $payloadHash = $this->payloadHash($site, $memberCard, $expectedType, $amountField, $payload);

        return DB::transaction(function () use (
            $staff,
            $site,
            $memberCard,
            $payload,
            $expectedType,
            $entryType,
            $amountField,
            $cacheField,
            $invalidCode,
            $insufficientCode,
            $commandKey,
            $payloadHash,
        ) {
            $locked = MemberCard::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Recheck after the subject lock. Concurrent calls with the same key
            // cannot both pass this point and create ledger facts.
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                abort_unless(
                    (int) $existingEntry->site_id === (int) $site->id
                    && (int) $existingEntry->member_card_id === (int) $locked->id
                    && ($existingEntry->metadata['adjustmentPayloadHash'] ?? null) === $payloadHash,
                    409,
                    'IDEMPOTENCY_KEY_REUSED',
                );

                return [
                    'memberCard' => $locked->fresh(),
                    'ledgerEntryIds' => [$existingEntry->id],
                    'created' => false,
                ];
            }

            abort_unless($locked->card_type === $expectedType, 409, $invalidCode);
            abort_if(
                in_array($locked->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)
                    || $locked->archived_at !== null,
                409,
                'MEMBER_CARD_ARCHIVED_MUTATION_BLOCKED',
            );
            abort_unless(
                in_array($locked->status, [MemberCardStatus::Active, MemberCardStatus::Frozen], true),
                409,
                $invalidCode,
            );

            $direction = EntitlementLedgerDirection::from($payload['direction']);
            abort_if(
                $locked->status === MemberCardStatus::Frozen
                    && $direction === EntitlementLedgerDirection::Debit,
                409,
                'FROZEN_CARD_DEBIT_BLOCKED',
            );
            $delta = $amountField === 'amount'
                ? $this->decimalString($payload['amount'])
                : (int) $payload['count'];

            $ledgerEntryIds = [];

            if (! empty($payload['correctsEntryId'])) {
                $original = EntitlementLedgerEntry::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('member_card_id', $locked->id)
                    ->whereKey($payload['correctsEntryId'])
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if(
                    ! in_array($original->entry_type, [
                        EntitlementLedgerEntryType::BalanceAdjust,
                        EntitlementLedgerEntryType::CountAdjust,
                        EntitlementLedgerEntryType::Correction,
                    ], true)
                    || empty($original->metadata['adjustmentPayloadHash'])
                    || DB::table('entitlement_reservations')->where('ledger_entry_id', $original->id)->exists()
                    || DB::table('consumption_events')->where('ledger_entry_id', $original->id)->exists(),
                    409,
                    'LEDGER_ENTRY_NOT_CORRECTABLE',
                );

                $alreadyReversed = EntitlementLedgerEntry::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('reversal_of_id', $original->id)
                    ->where('entry_type', EntitlementLedgerEntryType::Reversal)
                    ->exists();

                abort_if($alreadyReversed, 409, 'LEDGER_ENTRY_ALREADY_REVERSED');

                $reversalDirection = $this->oppositeDirection($original->direction);
                $this->assertCorrectionNetSufficient(
                    $locked,
                    $cacheField,
                    $reversalDirection,
                    $original->amount_delta,
                    $original->count_delta,
                    $direction,
                    $amountField === 'amount' ? $delta : null,
                    $amountField === 'count' ? $delta : null,
                    $insufficientCode,
                    (bool) ($payload['allowNegative'] ?? false),
                );
                $reversalLotMetadata = $this->prepareCountLotMovement(
                    $locked,
                    $cacheField,
                    $reversalDirection,
                    (int) ($original->count_delta ?? 0),
                    $original->metadata['countLotAllocations'] ?? null,
                    $original,
                );
                $reversal = EntitlementLedgerEntry::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'member_card_id' => $locked->id,
                    'member_id' => $locked->member_id,
                    'entry_type' => EntitlementLedgerEntryType::Reversal,
                    'direction' => $reversalDirection,
                    'amount_delta' => $original->amount_delta,
                    'count_delta' => $original->count_delta,
                    'reversal_of_id' => $original->id,
                    'reason' => 'Reversal: '.$payload['reason'],
                    'metadata' => $reversalLotMetadata,
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
                $this->finishCountLotCredit(
                    $locked,
                    $reversal,
                    $reversalLotMetadata,
                    'count-adjustment:reversal:'.$reversal->id,
                );
                $ledgerEntryIds[] = $reversal->id;

                $this->applyDelta($locked, $cacheField, $reversalDirection, $original->amount_delta, $original->count_delta);

                $correctionLotMetadata = $this->prepareCountLotMovement(
                    $locked,
                    $cacheField,
                    $direction,
                    $amountField === 'count' ? (int) $delta : 0,
                );
                $correction = EntitlementLedgerEntry::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'member_card_id' => $locked->id,
                    'member_id' => $locked->member_id,
                    'entry_type' => EntitlementLedgerEntryType::Correction,
                    'direction' => $direction,
                    'amount_delta' => $amountField === 'amount' ? $delta : null,
                    'count_delta' => $amountField === 'count' ? $delta : null,
                    'reversal_of_id' => $original->id,
                    'command_key' => $commandKey,
                    'reason' => $payload['reason'],
                    'metadata' => [
                        'adjustmentPayloadHash' => $payloadHash,
                        ...$correctionLotMetadata,
                    ],
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
                $this->finishCountLotCredit(
                    $locked,
                    $correction,
                    $correctionLotMetadata,
                    'count-adjustment:'.$commandKey,
                );
                $ledgerEntryIds[] = $correction->id;

                $this->applyDelta(
                    $locked,
                    $cacheField,
                    $direction,
                    $amountField === 'amount' ? $delta : null,
                    $amountField === 'count' ? $delta : null,
                );
            } else {
                $this->assertSufficient(
                    $locked,
                    $cacheField,
                    $direction,
                    $delta,
                    $insufficientCode,
                    (bool) ($payload['allowNegative'] ?? false),
                );

                $lotMetadata = $this->prepareCountLotMovement(
                    $locked,
                    $cacheField,
                    $direction,
                    $amountField === 'count' ? (int) $delta : 0,
                );
                $entry = EntitlementLedgerEntry::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'member_card_id' => $locked->id,
                    'member_id' => $locked->member_id,
                    'entry_type' => $entryType,
                    'direction' => $direction,
                    'amount_delta' => $amountField === 'amount' ? $delta : null,
                    'count_delta' => $amountField === 'count' ? $delta : null,
                    'command_key' => $commandKey,
                    'reason' => $payload['reason'],
                    'metadata' => [
                        'adjustmentPayloadHash' => $payloadHash,
                        ...$lotMetadata,
                    ],
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
                $this->finishCountLotCredit(
                    $locked,
                    $entry,
                    $lotMetadata,
                    'count-adjustment:'.$commandKey,
                );
                $ledgerEntryIds[] = $entry->id;

                $this->applyDelta(
                    $locked,
                    $cacheField,
                    $direction,
                    $amountField === 'amount' ? $delta : null,
                    $amountField === 'count' ? $delta : null,
                );
            }

            $locked->save();

            return [
                'memberCard' => $locked->fresh(),
                'ledgerEntryIds' => $ledgerEntryIds,
                'created' => true,
            ];
        });
    }

    private function oppositeDirection(EntitlementLedgerDirection $direction): EntitlementLedgerDirection
    {
        return match ($direction) {
            EntitlementLedgerDirection::Credit => EntitlementLedgerDirection::Debit,
            EntitlementLedgerDirection::Debit => EntitlementLedgerDirection::Credit,
            EntitlementLedgerDirection::Neutral => EntitlementLedgerDirection::Neutral,
        };
    }

    /** @return array<string,mixed> */
    private function prepareCountLotMovement(
        MemberCard $card,
        string $cacheField,
        EntitlementLedgerDirection $direction,
        int $count,
        ?array $restoreAllocations = null,
        ?EntitlementLedgerEntry $correctedEntry = null,
    ): array {
        if ($cacheField !== 'cached_remaining_count' || $count < 1) {
            return [];
        }
        if ($direction === EntitlementLedgerDirection::Debit) {
            $allocation = $correctedEntry !== null
                && $correctedEntry->direction === EntitlementLedgerDirection::Credit
                ? $this->valueLots->consumeManualCountCredit($card, $correctedEntry, $count)
                : $this->valueLots->allocateCountValue($card, $count);

            return [
                'countLotMovement' => 'debit',
                'countLotAllocations' => $allocation['allocations'],
                'knownValueCents' => $allocation['knownValueCents'] ?? $allocation['valueCents'],
                'unknownCount' => $allocation['unknownCount'] ?? 0,
            ];
        }
        if ($direction === EntitlementLedgerDirection::Credit && is_array($restoreAllocations)) {
            $this->valueLots->restoreCountAllocations($card, $restoreAllocations);

            return [
                'countLotMovement' => 'credit',
                'countLotAllocations' => $restoreAllocations,
                'restoredExistingLots' => true,
            ];
        }

        return $direction === EntitlementLedgerDirection::Credit
            ? ['countLotMovement' => 'credit', 'creditedCount' => $count]
            : [];
    }

    /** @param array<string,mixed> $movement */
    private function finishCountLotCredit(
        MemberCard $card,
        EntitlementLedgerEntry $entry,
        array $movement,
        string $commandKey,
    ): void {
        if (($movement['countLotMovement'] ?? null) !== 'credit'
            || ($movement['restoredExistingLots'] ?? false)
            || (int) ($movement['creditedCount'] ?? 0) < 1) {
            return;
        }
        $this->valueLots->recordManualCountCredit(
            $card,
            $entry,
            (int) $movement['creditedCount'],
            $commandKey,
        );
    }

    private function payloadHash(
        Site $site,
        MemberCard $card,
        CardType $expectedType,
        string $amountField,
        array $payload,
    ): string {
        return hash('sha256', json_encode([
            'siteId' => (int) $site->id,
            'memberCardId' => (int) $card->id,
            'cardType' => $expectedType->value,
            'direction' => (string) ($payload['direction'] ?? ''),
            'amount' => $amountField === 'amount' ? $this->decimalString($payload['amount'] ?? 0) : null,
            'count' => $amountField === 'count' ? (int) ($payload['count'] ?? 0) : null,
            'correctsEntryId' => isset($payload['correctsEntryId']) ? (int) $payload['correctsEntryId'] : null,
            'reason' => (string) ($payload['reason'] ?? ''),
            'allowNegative' => (bool) ($payload['allowNegative'] ?? false),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function applyDelta(
        MemberCard $card,
        string $cacheField,
        EntitlementLedgerDirection $direction,
        mixed $amountDelta,
        mixed $countDelta,
    ): void {
        if ($cacheField === 'cached_balance' && $amountDelta !== null) {
            $current = Money::decimalToCents($card->cached_balance ?? '0');
            $delta = Money::decimalToCents($amountDelta);
            $card->cached_balance = Money::centsToDecimal(
                $direction === EntitlementLedgerDirection::Debit ? $current - $delta : $current + $delta,
            );

            return;
        }

        if ($cacheField === 'cached_remaining_count' && $countDelta !== null) {
            $current = (int) ($card->cached_remaining_count ?? 0);
            $card->cached_remaining_count = $direction === EntitlementLedgerDirection::Debit
                ? $current - (int) $countDelta
                : $current + (int) $countDelta;
        }
    }

    private function assertSufficient(
        MemberCard $card,
        string $cacheField,
        EntitlementLedgerDirection $direction,
        mixed $delta,
        string $insufficientCode,
        bool $allowNegative,
    ): void {
        if ($direction !== EntitlementLedgerDirection::Debit || $allowNegative) {
            return;
        }

        if ($cacheField === 'cached_balance') {
            $current = Money::decimalToCents($card->cached_balance ?? '0');
            abort_if($current < Money::decimalToCents($delta), 409, $insufficientCode);

            return;
        }

        $current = (int) ($card->cached_remaining_count ?? 0);
        abort_if($current < (int) $delta, 409, $insufficientCode);
    }

    private function assertCorrectionNetSufficient(
        MemberCard $card,
        string $cacheField,
        EntitlementLedgerDirection $reversalDirection,
        mixed $reversalAmount,
        mixed $reversalCount,
        EntitlementLedgerDirection $correctionDirection,
        mixed $correctionAmount,
        mixed $correctionCount,
        string $insufficientCode,
        bool $allowNegative,
    ): void {
        if ($allowNegative) {
            return;
        }
        if ($cacheField === 'cached_balance') {
            $current = Money::decimalToCents($card->cached_balance ?? '0');
            $afterReversal = $this->signedDelta(
                $current,
                $reversalDirection,
                Money::decimalToCents($reversalAmount ?? '0'),
            );
            $final = $this->signedDelta(
                $afterReversal,
                $correctionDirection,
                Money::decimalToCents($correctionAmount ?? '0'),
            );
            abort_if($final < 0, 409, $insufficientCode);

            return;
        }

        $current = (int) ($card->cached_remaining_count ?? 0);
        $afterReversal = $this->signedDelta($current, $reversalDirection, (int) ($reversalCount ?? 0));
        $final = $this->signedDelta($afterReversal, $correctionDirection, (int) ($correctionCount ?? 0));
        abort_if($final < 0, 409, $insufficientCode);
    }

    private function signedDelta(int $current, EntitlementLedgerDirection $direction, int $delta): int
    {
        return $direction === EntitlementLedgerDirection::Debit ? $current - $delta : $current + $delta;
    }

    private function decimalString(mixed $value): string
    {
        return Money::centsToDecimal(Money::decimalToCents($value));
    }
}
