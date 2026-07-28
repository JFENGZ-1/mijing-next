<?php

namespace App\Services\Reporting;

use App\Enums\EntitlementLedgerEntryType;
use App\Models\EntitlementLedgerEntry;
use App\Models\Site;
use App\Models\Staff;

/**
 * 变更记录（对标原版 siteModifyLog：发卡/请假/停卡/删卡）。
 * 数据源为 entitlement_ledger_entries，不需要额外审计写入。
 */
class ReportChangeLogService
{
    /** @var array<string, list<EntitlementLedgerEntryType>> */
    private const CATEGORY_TYPES = [
        'issue' => [EntitlementLedgerEntryType::Issue, EntitlementLedgerEntryType::Purchase, EntitlementLedgerEntryType::Recharge],
        'holiday' => [EntitlementLedgerEntryType::HolidayApply, EntitlementLedgerEntryType::HolidayCancel],
        'freeze' => [EntitlementLedgerEntryType::Freeze, EntitlementLedgerEntryType::FreezeLift],
        'archive' => [EntitlementLedgerEntryType::Archive, EntitlementLedgerEntryType::Void, EntitlementLedgerEntryType::ArchiveRestore],
        'adjust' => [EntitlementLedgerEntryType::BalanceAdjust, EntitlementLedgerEntryType::CountAdjust, EntitlementLedgerEntryType::ValidityChange],
    ];

    public function categories(): array
    {
        return [
            ['key' => 'all', 'label' => '全部'],
            ['key' => 'issue', 'label' => '发卡'],
            ['key' => 'holiday', 'label' => '请假'],
            ['key' => 'freeze', 'label' => '停卡'],
            ['key' => 'archive', 'label' => '删卡'],
            ['key' => 'adjust', 'label' => '调整'],
        ];
    }

    public function list(
        Staff $staff,
        Site $site,
        string $category,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $actorStaffId,
        int $page,
        int $perPage,
    ): array {
        $query = EntitlementLedgerEntry::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereNotNull('actor_staff_id')
            ->with([
                'memberCard:id,card_no,card_type,card_product_id',
                'memberCard.cardProduct:id,name',
                'member:id',
                'member.crmProfile:member_id,name',
                'actorStaff:id,name',
            ]);

        if ($category !== 'all' && isset(self::CATEGORY_TYPES[$category])) {
            $query->whereIn('entry_type', self::CATEGORY_TYPES[$category]);
        } else {
            $query->whereIn('entry_type', array_merge(...array_values(self::CATEGORY_TYPES)));
        }

        if ($dateFrom) {
            $query->where('occurred_at', '>=', $dateFrom.' 00:00:00');
        }
        if ($dateTo) {
            $query->where('occurred_at', '<=', $dateTo.' 23:59:59');
        }
        if ($actorStaffId) {
            $query->where('actor_staff_id', $actorStaffId);
        }

        $paginator = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())->map(fn (EntitlementLedgerEntry $entry) => [
                'id' => $entry->id,
                'entryType' => $entry->entry_type->value,
                'entryLabel' => $this->entryLabel($entry->entry_type),
                'category' => $this->categoryOf($entry->entry_type),
                'memberId' => $entry->member_id,
                'memberName' => $entry->member?->crmProfile?->name,
                'memberCardId' => $entry->member_card_id,
                'cardNo' => $entry->memberCard?->card_no,
                'cardName' => $entry->memberCard?->cardProduct?->name,
                'amountDelta' => $entry->amount_delta,
                'countDelta' => $entry->count_delta,
                'reason' => $entry->reason,
                'actorStaffId' => $entry->actor_staff_id,
                'actorStaffName' => $entry->actorStaff?->name,
                'occurredAt' => $entry->occurred_at?->toIso8601String(),
            ])->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'categories' => $this->categories(),
        ];
    }

    private function categoryOf(EntitlementLedgerEntryType $type): string
    {
        foreach (self::CATEGORY_TYPES as $category => $types) {
            if (in_array($type, $types, true)) {
                return $category;
            }
        }

        return 'other';
    }

    private function entryLabel(EntitlementLedgerEntryType $type): string
    {
        return match ($type) {
            EntitlementLedgerEntryType::Issue => '发卡',
            EntitlementLedgerEntryType::Purchase => '购卡',
            EntitlementLedgerEntryType::Recharge => '充值',
            EntitlementLedgerEntryType::HolidayApply => '请假',
            EntitlementLedgerEntryType::HolidayCancel => '销假',
            EntitlementLedgerEntryType::Freeze => '停卡',
            EntitlementLedgerEntryType::FreezeLift => '启用',
            EntitlementLedgerEntryType::Archive => '删卡',
            EntitlementLedgerEntryType::Void => '作废',
            EntitlementLedgerEntryType::ArchiveRestore => '恢复',
            EntitlementLedgerEntryType::BalanceAdjust => '余额调整',
            EntitlementLedgerEntryType::CountAdjust => '次数调整',
            EntitlementLedgerEntryType::ValidityChange => '延期',
            default => $type->value,
        };
    }
}
