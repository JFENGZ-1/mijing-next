<?php

namespace App\Services\Reporting;

use App\Enums\EntitlementLedgerDirection;
use App\Enums\MemberCardStatus;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;

/**
 * 会员卡分析 + 资产负债表（对标原版 UserCardAnalyze / 资产负债表）。
 */
class ReportCardAnalyzeService
{
    public function summary(Staff $staff, Site $site): array
    {
        $today = now()->toDateString();

        $base = fn () => MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id);

        // 在册 = 未删卡（未归档/未作废）
        $registered = fn () => $base()->whereNotIn('status', [MemberCardStatus::Archived, MemberCardStatus::Voided]);

        $totalCount = $registered()->count();

        // 有效卡：已激活、在有效期内（或不限期）、未被停卡
        $validCount = $registered()
            ->where('status', MemberCardStatus::Active)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today))
            ->count();

        $expiredCount = $registered()
            ->where(fn ($query) => $query
                ->where('status', MemberCardStatus::Expired)
                ->orWhere(fn ($nested) => $nested
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<', $today)))
            ->count();

        $zeroBalanceCount = $registered()
            ->where(fn ($query) => $query
                ->where(fn ($money) => $money->whereNotNull('cached_balance')->where('cached_balance', '<=', 0))
                ->orWhere(fn ($count) => $count->whereNotNull('cached_remaining_count')->where('cached_remaining_count', '<=', 0)))
            ->count();

        $expiredWithBalanceCount = $registered()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', $today)
            ->where(fn ($query) => $query
                ->where(fn ($money) => $money->whereNotNull('cached_balance')->where('cached_balance', '>', 0))
                ->orWhere(fn ($count) => $count->whereNotNull('cached_remaining_count')->where('cached_remaining_count', '>', 0)))
            ->count();

        $pendingOpenCount = $registered()->where('status', MemberCardStatus::PendingActivation)->count();

        // 请假中：freeze_state 含 holiday
        $holidayCount = $registered()
            ->whereNotNull('freeze_state')
            ->where('freeze_state', 'like', '%"holiday"%')
            ->count();

        // 停卡中：frozen 且非请假
        $frozenCount = $registered()
            ->where('status', MemberCardStatus::Frozen)
            ->where(fn ($query) => $query
                ->whereNull('freeze_state')
                ->orWhere('freeze_state', 'not like', '%"holiday"%'))
            ->count();

        return [
            'cards' => [
                ['key' => 'total', 'label' => '全部会员卡', 'hint' => '总发卡张数', 'count' => $totalCount],
                ['key' => 'valid', 'label' => '有效的会员卡', 'hint' => '有余额且在有效期内', 'count' => $validCount],
                ['key' => 'invalid', 'label' => '无效的会员卡', 'hint' => '已无余额或已过期', 'count' => max(0, $totalCount - $validCount)],
                ['key' => 'expired', 'label' => '已过期的会员卡', 'hint' => '超过有效期', 'count' => $expiredCount],
                ['key' => 'zero-balance', 'label' => '余额为0的会员卡', 'hint' => '储值/次数已用尽', 'count' => $zeroBalanceCount],
                ['key' => 'expired-with-balance', 'label' => '已过期但有余额', 'hint' => '可考虑延期挽回', 'count' => $expiredWithBalanceCount],
                ['key' => 'pending-open', 'label' => '未开卡的会员卡', 'hint' => '待激活', 'count' => $pendingOpenCount],
                ['key' => 'holiday', 'label' => '请假中的会员卡', 'hint' => '假期冻结', 'count' => $holidayCount],
                ['key' => 'frozen', 'label' => '停卡中的会员卡', 'hint' => '人工停卡', 'count' => $frozenCount],
            ],
            'balanceSheet' => $this->balanceSheet($staff, $site),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * 资产负债表：总收入 / 已耗卡金额 / 剩余价值。
     */
    private function balanceSheet(Staff $staff, Site $site): array
    {
        $totalRevenue = (float) MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'paid')
            ->sum('amount');

        $consumedValue = (float) EntitlementLedgerEntry::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('direction', EntitlementLedgerDirection::Debit)
            ->whereNotNull('amount_delta')
            ->sum('amount_delta');

        $remainingValue = (float) MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereNotIn('status', [MemberCardStatus::Archived, MemberCardStatus::Voided])
            ->whereNotNull('cached_balance')
            ->sum('cached_balance');

        return [
            'totalRevenue' => number_format($totalRevenue, 2, '.', ''),
            'consumedValue' => number_format(abs($consumedValue), 2, '.', ''),
            'remainingValue' => number_format($remainingValue, 2, '.', ''),
            'notes' => [
                '总收入：实际收款的购卡订单金额合计',
                '已耗卡金额：会员消耗的储值金额合计',
                '剩余价值：在册卡未消耗的储值余额合计',
            ],
        ];
    }
}
