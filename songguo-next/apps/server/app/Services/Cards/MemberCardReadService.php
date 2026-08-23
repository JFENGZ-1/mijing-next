<?php

namespace App\Services\Cards;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
            ->with('cardProduct:id,scope_config')
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
            ->with('cardProduct:id,scope_config')
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
    /**
     * 卡面图案：卡种实时配置优先（改图案所有已发卡同步），回退发卡快照。
     */
    public function faceStyleFor(MemberCard $card): int
    {
        $live = $card->cardProduct?->scope_config['faceStyle'] ?? null;
        if (is_numeric($live)) {
            return (int) $live;
        }
        $snapshot = $card->product_snapshot['scopeConfig']['faceStyle'] ?? null;

        return is_numeric($snapshot) ? (int) $snapshot : 0;
    }

    public function faceGradientFor(MemberCard $card): ?string
    {
        return app(CardFaceLibraryService::class)->gradientFor($this->faceStyleFor($card));
    }

    public function memberWalletSummary(MemberCard $card): array
    {
        $snapshot = $card->product_snapshot;

        return [
            'id' => $card->id,
            'siteId' => $card->site_id,
            'cardType' => $card->card_type->value,
            'status' => $card->status->value,
            'cardNoMasked' => $this->maskCardNo($card->card_no),
            'faceStyle' => $this->faceStyleFor($card),
            'faceGradient' => $this->faceGradientFor($card),
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
            'faceStyle' => $this->faceStyleFor($card),
            'faceGradient' => $this->faceGradientFor($card),
            'name' => $snapshot['name'] ?? null,
            'cachedBalance' => $this->nullableDecimal($card->cached_balance),
            'cachedRemainingCount' => $card->cached_remaining_count,
            'validFrom' => $card->valid_from?->toDateString(),
            'validUntil' => $card->valid_until?->toDateString(),
            'issuedAt' => $card->issued_at?->toIso8601String(),
            'staffRemark' => $snapshot['staffRemark'] ?? null,
            'openingType' => $snapshot['openingType'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffDetail(MemberCard $card): array
    {
        $snapshot = $this->staffSnapshot($card);
        $metrics = $this->staffDetailMetrics($card, $snapshot);
        $lifecycle = $this->staffLifecycleSummary($card);

        return [
            ...$this->staffListSummary($card),
            'memberId' => $card->member_id,
            'cardProductId' => $card->card_product_id,
            'snapshot' => $snapshot,
            'freezeState' => $card->freeze_state,
            'issuedByStaffId' => $card->issued_by_staff_id,
            'issuedByStaffName' => $this->issuedByStaffName($card),
            'archivedAt' => $card->archived_at?->toIso8601String(),
            'paidAmount' => $metrics['paidAmount'],
            'unitConvert' => $metrics['unitConvert'],
            'consumedAmount' => $metrics['consumedAmount'],
            'residualValue' => $metrics['residualValue'],
            'initialTotal' => $metrics['initialTotal'],
            'holidaySummary' => $lifecycle['holiday'],
            'freezeSummary' => $lifecycle['freeze'],
        ];
    }

    /**
     * @return array{paidAmount: string|null, unitConvert: string|null, consumedAmount: string|null, residualValue: string|null, initialTotal: string|null}
     */
    public function staffValueMetrics(MemberCard $card): array
    {
        return $this->staffDetailMetrics($card, $this->staffSnapshot($card));
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
            'price' => isset($snapshot['price']) ? $this->nullableDecimal($snapshot['price']) : null,
            'faceValue' => isset($snapshot['faceValue']) ? $this->nullableDecimal($snapshot['faceValue']) : null,
            'initialCount' => $snapshot['initialCount'] ?? null,
            'validityDays' => $snapshot['validityDays'] ?? null,
            'validityMode' => $snapshot['validityMode'] ?? null,
            'activationMode' => $snapshot['activationMode'] ?? null,
            'productVersion' => $snapshot['productVersion'] ?? null,
            'openingType' => $snapshot['openingType'] ?? null,
            'staffRemark' => $snapshot['staffRemark'] ?? null,
            'scopeConfig' => $snapshot['scopeConfig'] ?? null,
            'bookingRules' => $snapshot['bookingRules'] ?? null,
            'courseScopes' => $snapshot['courseScopes'] ?? [],
        ];
    }

    private function issuedByStaffName(MemberCard $card): ?string
    {
        if ($card->issued_by_staff_id === null) {
            return null;
        }

        $name = Staff::query()
            ->where('tenant_id', $card->tenant_id)
            ->whereKey($card->issued_by_staff_id)
            ->value('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{paidAmount: string|null, unitConvert: string|null, consumedAmount: string|null, residualValue: string|null, initialTotal: string|null}
     */
    private function staffDetailMetrics(MemberCard $card, array $snapshot): array
    {
        $paidAmount = $this->resolvePaidAmount($card, $snapshot);
        $initialUnits = $this->resolveInitialUnits($card, $snapshot);
        $unitConvert = null;
        if ($paidAmount !== null && $initialUnits !== null && $initialUnits > 0) {
            $unitConvert = $this->nullableDecimal($paidAmount / $initialUnits);
        }

        $consumedAmount = $this->resolveConsumedAmount($card, $paidAmount, $unitConvert, $initialUnits);
        $residualValue = null;
        if ($paidAmount !== null && $consumedAmount !== null) {
            $residualValue = $this->nullableDecimal(max(0, $paidAmount - $consumedAmount));
        } elseif ($unitConvert !== null) {
            $remainingUnits = $this->resolveRemainingUnits($card);
            if ($remainingUnits !== null) {
                $residualValue = $this->nullableDecimal($unitConvert * $remainingUnits);
            }
        }

        $initialTotal = match ($card->card_type) {
            CardType::StoredValue => isset($snapshot['faceValue']) ? $this->nullableDecimal($snapshot['faceValue']) : null,
            CardType::Count => isset($snapshot['initialCount']) ? (string) (int) $snapshot['initialCount'] : null,
            CardType::Period => isset($snapshot['validityDays']) ? (string) (int) $snapshot['validityDays'] : null,
            default => null,
        };

        return [
            'paidAmount' => $paidAmount !== null ? $this->nullableDecimal($paidAmount) : null,
            'unitConvert' => $unitConvert,
            'consumedAmount' => $consumedAmount !== null ? $this->nullableDecimal($consumedAmount) : null,
            'residualValue' => $residualValue,
            'initialTotal' => $initialTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolvePaidAmount(MemberCard $card, array $snapshot): ?float
    {
        $orderSum = (float) MemberCardOrder::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('site_id', $card->site_id)
            ->where('member_card_id', $card->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->sum('amount');

        if ($orderSum > 0) {
            return $orderSum;
        }

        if (isset($snapshot['price']) && is_numeric($snapshot['price'])) {
            return (float) $snapshot['price'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolveInitialUnits(MemberCard $card, array $snapshot): ?float
    {
        return match ($card->card_type) {
            CardType::StoredValue => isset($snapshot['faceValue']) && is_numeric($snapshot['faceValue'])
                ? (float) $snapshot['faceValue']
                : null,
            CardType::Count => isset($snapshot['initialCount']) && is_numeric($snapshot['initialCount'])
                ? (float) $snapshot['initialCount']
                : null,
            CardType::Period => isset($snapshot['validityDays']) && is_numeric($snapshot['validityDays'])
                ? (float) $snapshot['validityDays']
                : null,
            default => null,
        };
    }

    private function resolveRemainingUnits(MemberCard $card): ?float
    {
        return match ($card->card_type) {
            CardType::StoredValue => $card->cached_balance !== null ? (float) $card->cached_balance : null,
            CardType::Count => $card->cached_remaining_count !== null ? (float) $card->cached_remaining_count : null,
            CardType::Period => $card->valid_until !== null
                ? (float) max(0, (int) now()->startOfDay()->diffInDays($card->valid_until->copy()->startOfDay(), false))
                : null,
            default => null,
        };
    }

    private function resolveConsumedAmount(
        MemberCard $card,
        ?float $paidAmount,
        ?string $unitConvert,
        ?float $initialUnits,
    ): ?float {
        $debitSum = (float) EntitlementLedgerEntry::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('direction', EntitlementLedgerDirection::Debit)
            ->whereNotNull('amount_delta')
            ->sum('amount_delta');

        if (abs($debitSum) > 0.00001) {
            return abs($debitSum);
        }

        $remainingUnits = $this->resolveRemainingUnits($card);
        if ($paidAmount !== null && $initialUnits !== null && $remainingUnits !== null && $initialUnits > 0) {
            $usedRatio = max(0, min(1, ($initialUnits - $remainingUnits) / $initialUnits));

            return $paidAmount * $usedRatio;
        }

        if ($unitConvert !== null && $initialUnits !== null && $remainingUnits !== null) {
            return (float) $unitConvert * max(0, $initialUnits - $remainingUnits);
        }

        return null;
    }

    /**
     * @return array{holiday: array{count: int, days: int}, freeze: array{count: int, days: int}}
     */
    private function staffLifecycleSummary(MemberCard $card): array
    {
        $entries = EntitlementLedgerEntry::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->whereIn('entry_type', [
                EntitlementLedgerEntryType::HolidayApply,
                EntitlementLedgerEntryType::HolidayCancel,
                EntitlementLedgerEntryType::Freeze,
                EntitlementLedgerEntryType::FreezeLift,
            ])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['entry_type', 'valid_from_after', 'valid_until_after', 'metadata', 'occurred_at']);

        $holidayCount = 0;
        $holidayDays = 0;
        $openHolidayStart = null;
        $freezeCount = 0;
        $freezeDays = 0;
        $openFreezeAt = null;

        foreach ($entries as $entry) {
            $type = $entry->entry_type;
            if ($type === EntitlementLedgerEntryType::HolidayApply) {
                $holidayCount++;
                $openHolidayStart = $entry->valid_from_after?->toDateString()
                    ?? ($entry->occurred_at?->toDateString());
                $plannedEnd = $entry->valid_until_after?->toDateString()
                    ?? (is_array($entry->metadata) ? ($entry->metadata['plannedEndAt'] ?? null) : null);
                if ($openHolidayStart && $plannedEnd) {
                    $holidayDays += $this->inclusiveDaySpan($openHolidayStart, (string) $plannedEnd);
                    $openHolidayStart = null;
                }
            } elseif ($type === EntitlementLedgerEntryType::HolidayCancel && $openHolidayStart) {
                $endedAt = is_array($entry->metadata)
                    ? ($entry->metadata['holidayEndedAt'] ?? $entry->occurred_at?->toDateString())
                    : $entry->occurred_at?->toDateString();
                if ($endedAt) {
                    $holidayDays += $this->inclusiveDaySpan($openHolidayStart, (string) $endedAt);
                }
                $openHolidayStart = null;
            } elseif ($type === EntitlementLedgerEntryType::Freeze) {
                $freezeCount++;
                $openFreezeAt = $entry->occurred_at?->toDateString();
            } elseif ($type === EntitlementLedgerEntryType::FreezeLift && $openFreezeAt) {
                $endedAt = $entry->occurred_at?->toDateString();
                if ($endedAt) {
                    $freezeDays += $this->inclusiveDaySpan($openFreezeAt, $endedAt);
                }
                $openFreezeAt = null;
            }
        }

        if ($openHolidayStart) {
            $holidayDays += $this->inclusiveDaySpan($openHolidayStart, now()->toDateString());
        }
        if ($openFreezeAt) {
            $freezeDays += $this->inclusiveDaySpan($openFreezeAt, now()->toDateString());
        }

        return [
            'holiday' => ['count' => $holidayCount, 'days' => $holidayDays],
            'freeze' => ['count' => $freezeCount, 'days' => $freezeDays],
        ];
    }

    private function inclusiveDaySpan(string $from, string $to): int
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    private function memberLedgerSummary(EntitlementLedgerEntry $entry): string
    {
        $isCount = in_array($entry->entry_type->value, ['count_adjust', 'count_deduct'], true)
            || ($entry->count_delta !== null && $entry->amount_delta === null);

        return match ($entry->entry_type->value) {
            'issue' => '开卡',
            'purchase' => '购卡',
            'balance_adjust' => $entry->direction->value === 'credit' ? '余额增加' : '余额扣减',
            'count_adjust' => $entry->direction->value === 'credit' ? '次数增加' : '次数扣减',
            'correction' => $isCount
                ? ($entry->direction->value === 'credit' ? '次数调整' : '次数扣减')
                : ($entry->direction->value === 'credit' ? '余额调整' : '余额扣减'),
            'reversal' => '冲正',
            'recharge' => '充值',
            'count_deduct' => '消费扣次',
            'period_use' => '入场使用',
            'validity_change' => '有效期调整',
            'freeze' => '卡片冻结',
            'freeze_lift' => '解除冻结',
            'holiday_apply' => '请假',
            'holiday_cancel' => '销假',
            'penalty' => '违约扣费',
            'expire' => '到期',
            'void' => '作废',
            'archive' => '归档',
            'archive_restore' => '恢复归档',
            'visibility_change' => str_contains((string) $entry->reason, 'hid') ? '隐藏卡片' : '恢复显示',
            default => $this->translateLedgerReason($entry),
        };
    }

    /**
     * 兜底：不透出英文 reason，翻译已知短语，未知归为"其他变动"。
     */
    private function translateLedgerReason(EntitlementLedgerEntry $entry): string
    {
        $reason = (string) ($entry->reason ?? '');

        return match (true) {
            str_contains($reason, 'purchase') => '购卡',
            str_contains($reason, 'activation') => '激活',
            str_contains($reason, 'hid card') => '隐藏卡片',
            str_contains($reason, 'restored card') => '恢复显示',
            default => '其他变动',
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
