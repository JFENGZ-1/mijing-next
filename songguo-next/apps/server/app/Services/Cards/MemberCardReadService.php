<?php

namespace App\Services\Cards;

use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MemberCardReadService
{
    /** @var list<MemberCardStatus> */
    private const MEMBER_WALLET_STATUSES = [
        MemberCardStatus::Active,
        MemberCardStatus::PendingActivation,
        MemberCardStatus::Frozen,
    ];

    public function memberWalletQuery(Member $member): Builder
    {
        return MemberCard::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->whereIn('status', self::MEMBER_WALLET_STATUSES)
            ->where('member_visibility', MemberCardVisibility::Visible)
            ->whereNull('archived_at')
            ->orderByDesc('issued_at');
    }

    public function staffMemberCardsQuery(Staff $staff, Site $site, Member $member): Builder
    {
        return MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_id', $member->id)
            ->where('status', '!=', MemberCardStatus::Voided)
            ->whereNull('archived_at')
            ->orderByDesc('issued_at');
    }

    public function staffCard(Staff $staff, Site $site, int $memberCardId): MemberCard
    {
        return MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($memberCardId)
            ->where('status', '!=', MemberCardStatus::Voided)
            ->firstOrFail();
    }

    public function staffLifecycleCard(Staff $staff, Site $site, int $memberCardId): MemberCard
    {
        return MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($memberCardId)
            ->firstOrFail();
    }

    public function staffArchivedCardsQuery(Staff $staff, Site $site): Builder
    {
        return MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardStatus::Archived)
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at');
    }

    public function memberHiddenCardsQuery(Member $member): Builder
    {
        return MemberCard::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('member_visibility', MemberCardVisibility::Hidden)
            ->whereIn('status', self::MEMBER_WALLET_STATUSES)
            ->whereNull('archived_at')
            ->orderByDesc('issued_at');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function memberWalletSummaries(Collection $cards): array
    {
        return $cards->map(fn (MemberCard $card) => $this->memberWalletSummary($card))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function memberWalletSummary(MemberCard $card): array
    {
        $snapshot = $card->product_snapshot;

        return [
            'id' => $card->id,
            'siteId' => $card->site_id,
            'cardType' => $card->card_type->value,
            'status' => $card->status->value,
            'cardNoMasked' => $this->maskCardNo($card->card_no),
            'name' => $snapshot['name'] ?? null,
            'balance' => $card->card_type === CardType::StoredValue
                ? $this->nullableDecimal($card->cached_balance)
                : null,
            'remainingCount' => $card->card_type === CardType::Count
                ? $card->cached_remaining_count
                : null,
            'validFrom' => $card->valid_from?->toDateString(),
            'validUntil' => $card->valid_until?->toDateString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staffListSummaries(Collection $cards): array
    {
        return $cards->map(fn (MemberCard $card) => $this->staffListSummary($card))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function staffListSummary(MemberCard $card): array
    {
        $snapshot = $card->product_snapshot;

        return [
            'id' => $card->id,
            'cardNo' => $card->card_no,
            'cardType' => $card->card_type->value,
            'status' => $card->status->value,
            'memberVisibility' => $card->member_visibility->value,
            'name' => $snapshot['name'] ?? null,
            'cachedBalance' => $this->nullableDecimal($card->cached_balance),
            'cachedRemainingCount' => $card->cached_remaining_count,
            'validFrom' => $card->valid_from?->toDateString(),
            'validUntil' => $card->valid_until?->toDateString(),
            'issuedAt' => $card->issued_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffDetail(MemberCard $card): array
    {
        return [
            ...$this->staffListSummary($card),
            'memberId' => $card->member_id,
            'cardProductId' => $card->card_product_id,
            'snapshot' => $this->staffSnapshot($card),
            'freezeState' => $card->freeze_state,
            'issuedByStaffId' => $card->issued_by_staff_id,
            'archivedAt' => $card->archived_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ledgerQuery(Staff $staff, Site $site, MemberCard $card): Builder
    {
        return EntitlementLedgerEntry::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_card_id', $card->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function memberLedgerQuery(Member $member, MemberCard $card): Builder
    {
        return EntitlementLedgerEntry::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('member_id', $member->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function staffLedgerEntry(EntitlementLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entryType' => $entry->entry_type->value,
            'direction' => $entry->direction->value,
            'amountDelta' => $this->nullableDecimal($entry->amount_delta),
            'countDelta' => $entry->count_delta,
            'validFromAfter' => $entry->valid_from_after?->toDateString(),
            'validUntilAfter' => $entry->valid_until_after?->toDateString(),
            'countGroupKey' => $entry->count_group_key,
            'reversalOfId' => $entry->reversal_of_id,
            'reason' => $entry->reason,
            'commandKey' => $entry->command_key,
            'actorStaffId' => $entry->actor_staff_id,
            'actorAccountId' => $entry->actor_account_id,
            'occurredAt' => $entry->occurred_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function memberLedgerEntry(EntitlementLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entryType' => $entry->entry_type->value,
            'direction' => $entry->direction->value,
            'amountDelta' => $this->nullableDecimal($entry->amount_delta),
            'countDelta' => $entry->count_delta,
            'summary' => $this->memberLedgerSummary($entry),
            'occurredAt' => $entry->occurred_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staffLedgerEntries(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (EntitlementLedgerEntry $entry) => $this->staffLedgerEntry($entry))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function memberLedgerEntries(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (EntitlementLedgerEntry $entry) => $this->memberLedgerEntry($entry))
            ->values()
            ->all();
    }

    public function benefits(MemberCard $card): array
    {
        $snapshot = $card->product_snapshot;

        return [
            'memberCardId' => $card->id,
            'cardType' => $card->card_type->value,
            'name' => $snapshot['name'] ?? null,
            'courseScopes' => $snapshot['courseScopes'] ?? [],
            'scopeConfig' => $snapshot['scopeConfig'] ?? null,
            'bookingRules' => $snapshot['bookingRules'] ?? null,
            'entitlements' => [
                'cachedBalance' => $this->nullableDecimal($card->cached_balance),
                'cachedRemainingCount' => $card->cached_remaining_count,
                'validFrom' => $card->valid_from?->toDateString(),
                'validUntil' => $card->valid_until?->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffSnapshot(MemberCard $card): array
    {
        $snapshot = $card->product_snapshot;

        return [
            'name' => $snapshot['name'] ?? null,
            'cardType' => $snapshot['cardType'] ?? $card->card_type->value,
            'faceValue' => $snapshot['faceValue'] ?? null,
            'initialCount' => $snapshot['initialCount'] ?? null,
            'validityDays' => $snapshot['validityDays'] ?? null,
            'validityMode' => $snapshot['validityMode'] ?? null,
            'activationMode' => $snapshot['activationMode'] ?? null,
            'productVersion' => $snapshot['productVersion'] ?? null,
            'scopeConfig' => $snapshot['scopeConfig'] ?? null,
            'bookingRules' => $snapshot['bookingRules'] ?? null,
            'courseScopes' => $snapshot['courseScopes'] ?? [],
        ];
    }

    private function memberLedgerSummary(EntitlementLedgerEntry $entry): string
    {
        $isCount = in_array($entry->entry_type->value, ['count_adjust', 'count_deduct'], true)
            || ($entry->count_delta !== null && $entry->amount_delta === null);

        return match ($entry->entry_type->value) {
            'issue' => '开卡',
            'balance_adjust' => $entry->direction->value === 'credit' ? '余额增加' : '余额扣减',
            'count_adjust' => $entry->direction->value === 'credit' ? '次数增加' : '次数扣减',
            'correction' => $isCount
                ? ($entry->direction->value === 'credit' ? '次数调整' : '次数扣减')
                : ($entry->direction->value === 'credit' ? '余额调整' : '余额扣减'),
            'reversal' => '冲正',
            'recharge' => '充值',
            'count_deduct' => '消费扣次',
            default => $entry->reason ?? $entry->entry_type->value,
        };
    }

    private function maskCardNo(string $cardNo): string
    {
        $suffix = strlen($cardNo) > 4 ? substr($cardNo, -4) : $cardNo;

        return '****'.$suffix;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}
