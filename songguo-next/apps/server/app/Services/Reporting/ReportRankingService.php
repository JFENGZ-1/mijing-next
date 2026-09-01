<?php

namespace App\Services\Reporting;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardOrderStatus;
use App\Enums\PointLedgerDirection;
use App\Models\Appointment;
use App\Models\CardProduct;
use App\Models\ConsumptionEvent;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\PointLedgerEntry;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Orders\MemberCardOrderService;
use App\Support\Finance\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportRankingService
{
    public function __construct(
        private readonly MemberCardOrderService $orders,
    ) {}

    /**
     * Top members by paid order spend in a calendar month (maps legacy userOrderRank mode 3).
     *
     * @return array<string, mixed>
     */
    public function orderRank(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        int $page,
        int $perPage,
    ): array {
        [$start, $end] = $this->monthRange($year, $month);

        $orders = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->wherePaidAtBetween($start, $end)
            ->with(['member.crmProfile', 'member.account', 'amountCorrections'])
            ->get();

        $aggregates = $orders
            ->groupBy('member_id')
            ->map(function (Collection $memberOrders, int $memberId) {
                /** @var MemberCardOrder $first */
                $first = $memberOrders->first();

                return [
                    'member' => $first->member,
                    'orderCount' => $memberOrders->count(),
                    'totalSpend' => $this->decimalString(
                        $memberOrders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                    ),
                ];
            })
            ->sort(function (array $left, array $right) {
                $spendCompare = (float) $right['totalSpend'] <=> (float) $left['totalSpend'];
                if ($spendCompare !== 0) {
                    return $spendCompare;
                }

                return $left['member']->id <=> $right['member']->id;
            })
            ->values();

        $paginated = $this->paginateRanked(
            $aggregates,
            $page,
            $perPage,
            fn (array $row, int $rank) => array_merge(
                $this->memberFields($row['member'], $staff, $site),
                [
                    'rank' => $rank,
                    'orderCount' => $row['orderCount'],
                    'totalSpend' => $row['totalSpend'],
                ],
            ),
        );

        return [
            'year' => $year,
            'month' => $month,
            'totals' => [
                'memberCount' => $aggregates->count(),
                'orderCount' => $orders->count(),
                'totalSpend' => $this->decimalString(
                    $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                ),
            ],
            ...$paginated,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Top members by completed appointments in a calendar month (maps legacy userCourseRank).
     *
     * @return array<string, mixed>
     */
    public function courseAttendanceRank(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        int $page,
        int $perPage,
    ): array {
        [$start, $end] = $this->monthRange($year, $month);

        $counts = Appointment::query()
            ->join('schedule_sessions', 'appointments.session_id', '=', 'schedule_sessions.id')
            ->where('appointments.tenant_id', $staff->tenant_id)
            ->where('appointments.site_id', $site->id)
            ->where('appointments.status', AppointmentStatus::Completed)
            ->whereBetween('schedule_sessions.starts_at', [$start, $end])
            ->selectRaw('appointments.member_id, COUNT(*) as completed_count')
            ->groupBy('appointments.member_id')
            ->orderByDesc('completed_count')
            ->orderBy('appointments.member_id')
            ->get();

        $members = Member::query()
            ->with(['crmProfile', 'account'])
            ->where('tenant_id', $staff->tenant_id)
            ->whereIn('id', $counts->pluck('member_id'))
            ->get()
            ->keyBy('id');

        $aggregates = $counts
            ->map(fn ($row) => [
                'member' => $members->get($row->member_id),
                'completedAppointments' => (int) $row->completed_count,
            ])
            ->filter(fn (array $row) => $row['member'] !== null)
            ->values();

        $paginated = $this->paginateRanked(
            $aggregates,
            $page,
            $perPage,
            fn (array $row, int $rank) => array_merge(
                $this->memberFields($row['member'], $staff, $site),
                [
                    'rank' => $rank,
                    'completedAppointments' => $row['completedAppointments'],
                ],
            ),
        );

        return [
            'year' => $year,
            'month' => $month,
            'totals' => [
                'memberCount' => $aggregates->count(),
                'completedAppointments' => (int) $aggregates->sum('completedAppointments'),
            ],
            ...$paginated,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Top members by point ledger credits in a calendar month (maps legacy findUserPointList).
     *
     * @return array<string, mixed>
     */
    public function pointsRank(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        int $page,
        int $perPage,
    ): array {
        abort_unless($site->tenant?->points_enabled, 404);

        [$start, $end] = $this->monthRange($year, $month);

        $counts = PointLedgerEntry::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('direction', PointLedgerDirection::Credit)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('member_id', $this->siteMemberIds($staff, $site))
            ->selectRaw('member_id, SUM(amount_delta) as credit_points')
            ->groupBy('member_id')
            ->orderByDesc('credit_points')
            ->orderBy('member_id')
            ->get();

        $members = Member::query()
            ->with(['crmProfile', 'account'])
            ->where('tenant_id', $staff->tenant_id)
            ->whereIn('id', $counts->pluck('member_id'))
            ->get()
            ->keyBy('id');

        $aggregates = $counts
            ->map(fn ($row) => [
                'member' => $members->get($row->member_id),
                'creditPoints' => (int) $row->credit_points,
            ])
            ->filter(fn (array $row) => $row['member'] !== null)
            ->values();

        $paginated = $this->paginateRanked(
            $aggregates,
            $page,
            $perPage,
            fn (array $row, int $rank) => array_merge(
                $this->memberFields($row['member'], $staff, $site),
                [
                    'rank' => $rank,
                    'creditPoints' => $row['creditPoints'],
                ],
            ),
        );

        return [
            'year' => $year,
            'month' => $month,
            'totals' => [
                'memberCount' => $aggregates->count(),
                'creditPoints' => (int) $aggregates->sum('creditPoints'),
            ],
            ...$paginated,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Sales staff by attributed paid card orders (maps legacy salerList).
     *
     * @return array<string, mixed>
     */
    public function salesStaffRank(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        int $page,
        int $perPage,
    ): array {
        [$start, $end] = $this->monthRange($year, $month);

        $orders = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->wherePaidAtBetween($start, $end)
            ->whereNotNull('created_by_staff_id')
            ->with(['amountCorrections'])
            ->get();

        $staffIds = $orders->pluck('created_by_staff_id')->unique()->filter()->values();
        $salesStaff = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereIn('id', $staffIds)
            ->get()
            ->keyBy('id');

        $aggregates = $orders
            ->groupBy('created_by_staff_id')
            ->map(function (Collection $staffOrders, int $staffId) use ($salesStaff) {
                $salesperson = $salesStaff->get($staffId);
                if (! $salesperson) {
                    return null;
                }

                return [
                    'staff' => $salesperson,
                    'cardSalesCount' => $staffOrders->count(),
                    'revenue' => $this->decimalString(
                        $staffOrders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                    ),
                    'memberCount' => $staffOrders->pluck('member_id')->unique()->count(),
                ];
            })
            ->filter()
            ->sort(function (array $left, array $right) {
                $revenueCompare = (float) $right['revenue'] <=> (float) $left['revenue'];
                if ($revenueCompare !== 0) {
                    return $revenueCompare;
                }

                $countCompare = $right['cardSalesCount'] <=> $left['cardSalesCount'];
                if ($countCompare !== 0) {
                    return $countCompare;
                }

                return $left['staff']->id <=> $right['staff']->id;
            })
            ->values();

        $paginated = $this->paginateRanked(
            $aggregates,
            $page,
            $perPage,
            fn (array $row, int $rank) => [
                'rank' => $rank,
                'staffId' => $row['staff']->id,
                'staffName' => $row['staff']->name,
                'cardSalesCount' => $row['cardSalesCount'],
                'revenue' => $row['revenue'],
                'memberCount' => $row['memberCount'],
            ],
        );

        return [
            'year' => $year,
            'month' => $month,
            'totals' => [
                'staffCount' => $aggregates->count(),
                'cardSalesCount' => $orders->count(),
                'revenue' => $this->decimalString(
                    $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                ),
            ],
            ...$paginated,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Drill-down for one salesperson's attributed orders (maps legacy findUserDetailOfOneSaler).
     *
     * @return array<string, mixed>
     */
    public function salesStaffDetail(
        Staff $actor,
        Site $site,
        Staff $salesStaff,
        int $year,
        int $month,
        int $page,
        int $perPage,
    ): array {
        abort_unless($salesStaff->tenant_id === $actor->tenant_id, 404);
        abort_unless(
            $salesStaff->sites()->whereKey($site->id)->where('site_staff.tenant_id', $actor->tenant_id)->exists(),
            404,
        );

        [$start, $end] = $this->monthRange($year, $month);

        $orders = MemberCardOrder::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->where('created_by_staff_id', $salesStaff->id)
            ->wherePaidAtBetween($start, $end)
            ->with(['member.crmProfile', 'member.account', 'amountCorrections'])
            ->get();

        $aggregates = $orders
            ->groupBy('member_id')
            ->map(function (Collection $memberOrders) {
                /** @var MemberCardOrder $first */
                $first = $memberOrders->first();
                $lastOrder = $memberOrders
                    ->sortByDesc(fn (MemberCardOrder $order) => $order->reportingPaidAt())
                    ->first();

                return [
                    'member' => $first->member,
                    'cardSalesCount' => $memberOrders->count(),
                    'revenue' => $this->decimalString(
                        $memberOrders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                    ),
                    'lastOrderAt' => $lastOrder?->reportingPaidAt()?->toIso8601String(),
                ];
            })
            ->sort(function (array $left, array $right) {
                $revenueCompare = (float) $right['revenue'] <=> (float) $left['revenue'];
                if ($revenueCompare !== 0) {
                    return $revenueCompare;
                }

                return $left['member']->id <=> $right['member']->id;
            })
            ->values();

        $paginated = $this->paginateRanked(
            $aggregates,
            $page,
            $perPage,
            fn (array $row, int $rank) => array_merge(
                $this->memberFields($row['member'], $actor, $site),
                [
                    'rank' => $rank,
                    'cardSalesCount' => $row['cardSalesCount'],
                    'revenue' => $row['revenue'],
                    'lastOrderAt' => $row['lastOrderAt'],
                ],
            ),
        );

        return [
            'year' => $year,
            'month' => $month,
            'staffId' => $salesStaff->id,
            'staffName' => $salesStaff->name,
            'totals' => [
                'memberCount' => $aggregates->count(),
                'cardSalesCount' => $orders->count(),
                'revenue' => $this->decimalString(
                    $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                ),
            ],
            ...$paginated,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Top members by entitlement consumption value in a calendar month.
     *
     * @return array<string, mixed>
     */
    public function memberCardConsumptionRank(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        int $page,
        int $perPage,
    ): array {
        [$start, $end] = $this->monthRange($year, $month);

        $events = ConsumptionEvent::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', 'reversed')
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->with(['member.crmProfile', 'member.account'])
            ->get();

        $aggregates = $events
            ->groupBy('member_id')
            ->map(function (Collection $memberEvents) {
                /** @var ConsumptionEvent $first */
                $first = $memberEvents->first();
                $knownValueCents = (int) $memberEvents->sum(
                    fn (ConsumptionEvent $event) => (int) ($event->consumed_value_cents ?? 0),
                );
                $unvaluedCount = $memberEvents->whereNull('consumed_value_cents')->count();

                return [
                    'member' => $first->member,
                    'consumptionCount' => $memberEvents->count(),
                    'consumptionValueCents' => $knownValueCents,
                    'consumptionAmount' => Money::centsToDecimal($knownValueCents),
                    'unvaluedCount' => $unvaluedCount,
                    'hasUnvalued' => $unvaluedCount > 0,
                ];
            })
            ->sort(fn (array $left, array $right) => [
                $right['consumptionValueCents'], $right['consumptionCount'],
            ] <=> [
                $left['consumptionValueCents'], $left['consumptionCount'],
            ])
            ->values();

        $paginated = $this->paginateRanked(
            $aggregates,
            $page,
            $perPage,
            fn (array $row, int $rank) => array_merge(
                $this->memberFields($row['member'], $staff, $site),
                [
                    'rank' => $rank,
                    'consumptionCount' => $row['consumptionCount'],
                    'consumptionAmount' => $row['consumptionAmount'],
                    'unvaluedCount' => $row['unvaluedCount'],
                    'hasUnvalued' => $row['hasUnvalued'],
                ],
            ),
        );

        return [
            'year' => $year,
            'month' => $month,
            'totals' => [
                'memberCount' => $aggregates->count(),
                'consumptionCount' => $events->count(),
                'consumptionAmount' => Money::centsToDecimal((int) $events->sum(
                    fn (ConsumptionEvent $event) => (int) ($event->consumed_value_cents ?? 0),
                )),
                'unvaluedCount' => $events->whereNull('consumed_value_cents')->count(),
                'hasUnvalued' => $events->whereNull('consumed_value_cents')->isNotEmpty(),
            ],
            ...$paginated,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Top card products by paid sales in a calendar month.
     *
     * @return array<string, mixed>
     */
    public function cardProductSalesRank(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        int $page,
        int $perPage,
    ): array {
        [$start, $end] = $this->monthRange($year, $month);

        $orders = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->wherePaidAtBetween($start, $end)
            ->with(['memberCard', 'amountCorrections'])
            ->get();

        $aggregates = $orders
            ->groupBy(fn (MemberCardOrder $order) => $order->memberCard?->card_product_id ?? (int) ($order->metadata['cardProductId'] ?? 0))
            ->map(function (Collection $productOrders, int $cardProductId) {
                $firstCard = $productOrders->first()?->memberCard;
                $product = $cardProductId > 0
                    ? CardProduct::query()->find($cardProductId)
                    : null;

                return [
                    'cardProductId' => $cardProductId > 0 ? $cardProductId : null,
                    'cardProductName' => $product?->name ?? $firstCard?->product_snapshot['name'] ?? '未知卡种',
                    'salesCount' => $productOrders->count(),
                    'revenue' => $this->decimalString(
                        $productOrders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                    ),
                ];
            })
            ->sort(fn (array $left, array $right) => (float) $right['revenue'] <=> (float) $left['revenue'])
            ->values();

        $paginated = $this->paginateRanked(
            $aggregates,
            $page,
            $perPage,
            fn (array $row, int $rank) => [
                'rank' => $rank,
                'cardProductId' => $row['cardProductId'],
                'cardProductName' => $row['cardProductName'],
                'salesCount' => $row['salesCount'],
                'revenue' => $row['revenue'],
            ],
        );

        return [
            'year' => $year,
            'month' => $month,
            'totals' => [
                'cardProductCount' => $aggregates->count(),
                'salesCount' => $orders->count(),
                'revenue' => $this->decimalString(
                    $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                ),
            ],
            ...$paginated,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Cross-site card product analytics for the tenant.
     *
     * @return array<string, mixed>
     */
    public function cardProductAnalytics(Staff $staff, Site $site): array
    {
        $siteIds = Site::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->pluck('id');

        $products = CardProduct::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereIn('site_id', $siteIds)
            ->whereNull('archived_at')
            ->with('site:id,name')
            ->get();

        $issuedCounts = MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereIn('site_id', $siteIds)
            ->whereNull('archived_at')
            ->selectRaw('card_product_id, site_id, count(*) as issued_count')
            ->groupBy('card_product_id', 'site_id')
            ->get()
            ->groupBy('card_product_id');

        $items = $products->map(function (CardProduct $product) use ($issuedCounts) {
            $counts = $issuedCounts->get($product->id, collect());
            $siteIssued = $counts->sum('issued_count');

            return [
                'cardProductId' => $product->id,
                'cardProductName' => $product->name,
                'siteId' => $product->site_id,
                'siteName' => $product->site?->name,
                'cardType' => $product->card_type->value,
                'issuedCount' => (int) $siteIssued,
                'linkedSiteCount' => count($product->scope_config['linkedSiteIds'] ?? []),
            ];
        })->sortByDesc('issuedCount')->values();

        return [
            'siteId' => $site->id,
            'totals' => [
                'cardProductCount' => $items->count(),
                'issuedCount' => (int) $items->sum('issuedCount'),
            ],
            'items' => $items->all(),
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * @return list<int>
     */
    private function siteMemberIds(Staff $staff, Site $site): array
    {
        return Member::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereHas('sites', fn ($query) => $query
                ->whereKey($site->id)
                ->where('member_sites.tenant_id', $staff->tenant_id)
                ->where('member_sites.status', 'active'))
            ->pluck('id')
            ->all();
    }

    /**
     * @return array{memberId: int, memberNo: string, memberName: ?string, memberAvatarUrl: ?string}
     */
    private function memberFields(Member $member, Staff $staff, Site $site): array
    {
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);
        $rawName = $member->crmProfile?->name ?? $member->account?->display_name;

        return [
            'memberId' => $member->id,
            'memberNo' => $member->member_no,
            'memberName' => $canReadMemberNames ? $rawName : $this->maskName($rawName),
            'memberAvatarUrl' => $canReadMemberNames ? $member->account?->avatar_url : null,
        ];
    }

    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $ranked
     * @param  callable(T, int): array<string, mixed>  $presenter
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    private function paginateRanked(Collection $ranked, int $page, int $perPage, callable $presenter): array
    {
        $total = $ranked->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $offset = ($page - 1) * $perPage;

        $items = $ranked
            ->slice($offset, $perPage)
            ->values()
            ->map(fn ($row, int $index) => $presenter($row, $offset + $index + 1))
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
        ];
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
