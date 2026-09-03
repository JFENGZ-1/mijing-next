<?php

namespace App\Services\Reporting;

use App\Enums\CardType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Models\ConsumptionEvent;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Orders\MemberCardOrderService;
use App\Support\Finance\Money;

/**
 * 会员卡分析 + 资产负债表（对标原版 UserCardAnalyze / 资产负债表）。
 */
class ReportCardAnalyzeService
{
    public function __construct(
        private readonly MemberCardOrderService $orders,
    ) {}

    public function summary(Staff $staff, Site $site): array
    {
        $today = now()->toDateString();

        $base = fn () => MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id);

        // 在册 = 未删卡（未归档/未作废）
        $registered = fn () => $base()->whereNotIn('status', [MemberCardStatus::Archived, MemberCardStatus::Voided]);

        $totalCount = $registered()->count();

        // 有效卡：已激活、在有效期内，且储值/次卡仍有可用权益。
        $validCount = $registered()
            ->where('status', MemberCardStatus::Active)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today))
            ->where(function ($query) {
                $query->where(function ($storedValue) {
                    $storedValue->where('card_type', CardType::StoredValue)
                        ->where('cached_balance', '>', 0);
                })->orWhere(function ($count) {
                    $count->where('card_type', CardType::Count)
                        ->where('cached_remaining_count', '>', 0);
                })->orWhere('card_type', CardType::Period);
            })
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

        $invalidCount = $registered()
            ->where(function ($query) use ($today) {
                $query->where('status', MemberCardStatus::Expired)
                    ->orWhere('status', MemberCardStatus::Exhausted)
                    ->orWhere(function ($expired) use ($today) {
                        $expired->whereNotNull('valid_until')
                            ->whereDate('valid_until', '<', $today);
                    })
                    ->orWhere(function ($storedValue) {
                        $storedValue->where('card_type', CardType::StoredValue)
                            ->whereNotNull('cached_balance')
                            ->where('cached_balance', '<=', 0);
                    })
                    ->orWhere(function ($count) {
                        $count->where('card_type', CardType::Count)
                            ->whereNotNull('cached_remaining_count')
                            ->where('cached_remaining_count', '<=', 0);
                    });
            })
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
                ['key' => 'invalid', 'label' => '无效的会员卡', 'hint' => '已无余额或已过期', 'count' => $invalidCount],
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
        $paidOrders = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->with('amountCorrections')
            ->get();
        $totalRevenueCents = (int) $paidOrders->sum(
            fn (MemberCardOrder $order) => Money::decimalToCents($this->orders->effectiveAmount($order)),
        );

        $effectiveConsumptions = ConsumptionEvent::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', 'reversed');

        $knownConsumedValueCents = (int) (clone $effectiveConsumptions)
            ->whereNotNull('consumed_value_cents')
            ->sum('consumed_value_cents');
        $unvaluedCount = (clone $effectiveConsumptions)
            ->whereNull('consumed_value_cents')
            ->count();
        $remainingValueCents = max($totalRevenueCents - $knownConsumedValueCents, 0);
        $excessConsumedValueCents = max($knownConsumedValueCents - $totalRevenueCents, 0);
        $hasUnvalued = $unvaluedCount > 0;

        $notes = [
            '总实收：已支付订单的有效金额合计，待支付、关闭和作废订单不计入。',
            '已知耗卡价值：储值卡、次卡和期限卡未冲正耗卡事件中可核价金额的合计。',
            '估算剩余价值 = max（总实收 - 已知耗卡价值，0）。',
            '耗卡冲正会从有效耗卡中排除；订单历史有效更正会反映在总实收中。',
        ];
        if ($hasUnvalued) {
            $notes[] = "另有 {$unvaluedCount} 笔耗卡尚未核价，未从估算剩余价值扣除，因此结果可能偏高。";
        }
        if ($excessConsumedValueCents > 0) {
            $notes[] = '已知耗卡价值超过总实收，可能来自赠卡、历史收款缺失或待核对数据；剩余价值已按 0 展示。';
        }

        return [
            'totalRevenue' => Money::centsToDecimal($totalRevenueCents),
            'consumedValue' => Money::centsToDecimal($knownConsumedValueCents),
            'remainingValue' => Money::centsToDecimal($remainingValueCents),
            'excessConsumedValue' => Money::centsToDecimal($excessConsumedValueCents),
            'unvaluedCount' => $unvaluedCount,
            'hasUnvalued' => $hasUnvalued,
            'remainingValueIsEstimate' => true,
            'notes' => $notes,
        ];
    }
}
