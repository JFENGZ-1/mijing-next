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
use Illuminate\Support\Facades\DB;

class MemberCardAdjustService
{
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
        ) {
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                return [
                    'memberCard' => MemberCard::query()
                        ->where('tenant_id', $staff->tenant_id)
                        ->whereKey($existingEntry->member_card_id)
                        ->firstOrFail(),
                    'ledgerEntryIds' => [$existingEntry->id],
                    'created' => false,
                ];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

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
                    $original->entry_type === EntitlementLedgerEntryType::Reversal,
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
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
                $ledgerEntryIds[] = $reversal->id;

                $this->applyDelta($locked, $cacheField, $reversalDirection, $original->amount_delta, $original->count_delta);

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
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
                $ledgerEntryIds[] = $correction->id;

                $this->assertSufficient(
                    $locked,
                    $cacheField,
                    $direction,
                    $delta,
                    $insufficientCode,
                    (bool) ($payload['allowNegative'] ?? false),
                );
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
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
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

    private function applyDelta(
        MemberCard $card,
        string $cacheField,
        EntitlementLedgerDirection $direction,
        mixed $amountDelta,
        mixed $countDelta,
    ): void {
        if ($cacheField === 'cached_balance' && $amountDelta !== null) {
            $current = (float) ($card->cached_balance ?? 0);
            $delta = (float) $amountDelta;
            $card->cached_balance = $direction === EntitlementLedgerDirection::Debit
                ? $this->decimalString($current - $delta)
                : $this->decimalString($current + $delta);

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
            $current = (float) ($card->cached_balance ?? 0);
            abort_if($current < (float) $delta, 409, $insufficientCode);

            return;
        }

        $current = (int) ($card->cached_remaining_count ?? 0);
        abort_if($current < (int) $delta, 409, $insufficientCode);
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
