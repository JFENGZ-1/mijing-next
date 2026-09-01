<?php

namespace App\Services\Reporting;

use App\Enums\MemberCardOrderStatus;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Orders\MemberCardOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportFinanceProfitService
{
    public function __construct(
        private readonly StaffMemberAccessService $members,
        private readonly MemberCardOrderService $orders,
    ) {}

    /**
     * Year/month rollup (maps legacy profitList / businessReportForm).
     *
     * @return array<string, mixed>
     */
    public function summary(Staff $staff, Site $site): array
    {
        $orders = $this->paidOrders($staff, $site);
        $years = $this->yearsWithActivity($orders);

        return [
            'years' => collect($years)
                ->map(fn (int $year) => $this->yearBlock($staff, $site, $year, $orders))
                ->values()
                ->all(),
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Twelve-month calendar for one year (maps legacy profitMonthList year scope).
     *
     * @return array<string, mixed>
     */
    public function calendar(Staff $staff, Site $site, int $year): array
    {
        $orders = $this->paidOrders($staff, $site, $year);
        $now = now();
        $maxMonth = $year === (int) $now->year ? (int) $now->month : 12;
        $months = [];

        for ($month = 1; $month <= $maxMonth; $month++) {
            $months[] = $this->periodMetrics($staff, $site, $year, $month, null, $orders);
        }

        return [
            'year' => $year,
            'totals' => $this->rollupTotals($months),
            'months' => $months,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Daily breakdown for one month (maps legacy profitMonthList dayList).
     *
     * @return array<string, mixed>
     */
    public function daily(Staff $staff, Site $site, int $year, int $month): array
    {
        $orders = $this->paidOrders($staff, $site, $year, $month);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = (int) $end->day;
        $days = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $metrics = $this->periodMetrics($staff, $site, $year, $month, $day, $orders);
            if ($metrics['cardSalesCount'] > 0 || $metrics['newMemberCount'] > 0) {
                $days[] = $metrics;
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'totals' => $this->rollupTotals($days),
            'days' => $days,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, MemberCardOrder>
     */
    private function paidOrders(Staff $staff, Site $site, ?int $year = null, ?int $month = null): Collection
    {
        $query = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->with('amountCorrections')
            ->orderByPaidAt();

        if ($year !== null && $month !== null) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $query->wherePaidAtBetween($start, $start->copy()->endOfMonth());
        } elseif ($year !== null) {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $query->wherePaidAtBetween($start, $start->copy()->endOfYear());
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, MemberCardOrder>  $orders
     * @return list<int>
     */
    private function yearsWithActivity(Collection $orders): array
    {
        $years = $orders
            ->map(fn (MemberCardOrder $order) => (int) $order->reportingPaidAt()?->year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $currentYear = (int) now()->year;
        if (! in_array($currentYear, $years, true)) {
            array_unshift($years, $currentYear);
        }

        return $years;
    }

    /**
     * @param  Collection<int, MemberCardOrder>  $orders
     * @return array<string, mixed>
     */
    private function yearBlock(Staff $staff, Site $site, int $year, Collection $orders): array
    {
        $yearOrders = $orders->filter(fn (MemberCardOrder $order) => (int) $order->reportingPaidAt()?->year === $year);
        $now = now();
        $maxMonth = $year === (int) $now->year ? (int) $now->month : 12;
        $months = [];

        for ($month = 1; $month <= $maxMonth; $month++) {
            $metrics = $this->periodMetrics($staff, $site, $year, $month, null, $yearOrders);
            if ($metrics['cardSalesCount'] > 0 || $metrics['newMemberCount'] > 0) {
                $months[] = $metrics;
            }
        }

        return [
            'year' => $year,
            'isCurrentYear' => $year === (int) $now->year,
            'newMemberCount' => $this->countNewMembers($staff, $site, $this->yearRange($year)),
            'cardSalesCount' => $yearOrders->count(),
            'revenue' => $this->sumRevenue($yearOrders),
            'months' => $months,
        ];
    }

    /**
     * @param  Collection<int, MemberCardOrder>  $orders
     * @return array<string, mixed>
     */
    private function periodMetrics(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        ?int $day,
        Collection $orders,
    ): array {
        [$start, $end] = $this->periodRange($year, $month, $day);

        $periodOrders = $orders->filter(
            fn (MemberCardOrder $order) => $order->reportingPaidAt()?->betweenIncluded($start, $end) ?? false,
        );

        $metrics = [
            'year' => $year,
            'month' => $month,
            'newMemberCount' => $this->countNewMembers($staff, $site, [$start, $end]),
            'cardSalesCount' => $periodOrders->count(),
            'revenue' => $this->sumRevenue($periodOrders),
        ];

        if ($day !== null) {
            $metrics['day'] = $day;
        }

        return $metrics;
    }

    /**
     * @param  list<array<string, mixed>>  $periods
     * @return array{newMemberCount: int, cardSalesCount: int, revenue: string}
     */
    private function rollupTotals(array $periods): array
    {
        return [
            'newMemberCount' => collect($periods)->sum('newMemberCount'),
            'cardSalesCount' => collect($periods)->sum('cardSalesCount'),
            'revenue' => $this->decimalString(collect($periods)->sum(fn (array $row) => (float) $row['revenue'])),
        ];
    }

    /**
     * @param  Collection<int, MemberCardOrder>  $orders
     */
    private function sumRevenue(Collection $orders): string
    {
        $total = $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order));

        return $this->decimalString($total);
    }

    /**
     * Legacy "新增会员": joined in period and holds at least one card at the site.
     *
     * @param  array{0: Carbon, 1: Carbon}  $range
     */
    private function countNewMembers(Staff $staff, Site $site, array $range): int
    {
        [$start, $end] = $range;

        return $this->members->query($staff, $site)
            ->whereNull('members.archived_at')
            ->whereBetween('joined_at', [$start, $end])
            ->whereExists(function ($query) use ($staff, $site) {
                $query->from('member_cards')
                    ->whereColumn('member_cards.member_id', 'members.id')
                    ->where('member_cards.tenant_id', $staff->tenant_id)
                    ->where('member_cards.site_id', $site->id);
            })
            ->count();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function yearRange(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();

        return [$start, $start->copy()->endOfYear()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(int $year, int $month, ?int $day): array
    {
        if ($day !== null) {
            $start = Carbon::create($year, $month, $day)->startOfDay();

            return [$start, $start->copy()->endOfDay()];
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
