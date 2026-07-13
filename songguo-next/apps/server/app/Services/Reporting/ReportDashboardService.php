<?php

namespace App\Services\Reporting;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardOrderStatus;
use App\Models\Appointment;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Orders\MemberCardOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportDashboardService
{
    public function __construct(
        private readonly StaffMemberAccessService $members,
        private readonly MemberCardOrderService $orders,
    ) {}

    /**
     * Report-tab analytics (maps legacy pages/report/report mainpage).
     * Distinct from staff home dashboard: month-scoped KPIs and 12-month profit trend.
     *
     * @return array<string, mixed>
     */
    public function summary(Staff $staff, Site $site): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $paidOrdersQuery = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid);

        $todayOrders = (clone $paidOrdersQuery)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->with('amountCorrections')
            ->get();

        $monthOrders = (clone $paidOrdersQuery)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->with('amountCorrections')
            ->get();

        $appointmentBase = Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Waitlisted,
                AppointmentStatus::Completed,
            ]);

        $todayAppointmentCount = (clone $appointmentBase)
            ->whereHas('session', fn ($query) => $query->whereBetween('starts_at', [$todayStart, $todayEnd]))
            ->count();

        $monthAppointmentCount = (clone $appointmentBase)
            ->whereHas('session', fn ($query) => $query->whereBetween('starts_at', [$monthStart, $monthEnd]))
            ->count();

        $memberBase = $this->members->query($staff, $site)->whereNull('members.archived_at');

        return [
            'kpis' => [
                'todayRevenue' => $this->sumRevenue($todayOrders),
                'monthRevenue' => $this->sumRevenue($monthOrders),
                'todayCardSalesCount' => $todayOrders->count(),
                'monthCardSalesCount' => $monthOrders->count(),
                'todayAppointmentCount' => $todayAppointmentCount,
                'monthAppointmentCount' => $monthAppointmentCount,
                'totalMemberCount' => (clone $memberBase)->count(),
                'monthNewMemberCount' => (clone $memberBase)
                    ->whereBetween('joined_at', [$monthStart, $monthEnd])
                    ->count(),
            ],
            'profitTrend' => $this->profitTrend($staff, $site, $now),
            'asOf' => $now->toIso8601String(),
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
     * @return list<array{year: int, month: int, label: string, revenue: string}>
     */
    private function profitTrend(Staff $staff, Site $site, Carbon $now): array
    {
        $cursor = $now->copy()->startOfMonth()->subMonths(11);
        $items = [];

        for ($index = 0; $index < 12; $index++) {
            $monthStart = $cursor->copy();
            $monthEnd = $cursor->copy()->endOfMonth();

            $orders = MemberCardOrder::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->where('status', MemberCardOrderStatus::Paid)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->with('amountCorrections')
                ->get();

            $items[] = [
                'year' => (int) $monthStart->year,
                'month' => (int) $monthStart->month,
                'label' => $monthStart->isSameMonth($now) ? '本' : (string) $monthStart->month,
                'revenue' => $this->sumRevenue($orders),
            ];

            $cursor->addMonth();
        }

        return $items;
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
