<?php

namespace App\Services\Payroll;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardOrderStatus;
use App\Enums\PayrollCoachMode;
use App\Enums\PayrollReportType;
use App\Enums\PayrollSalesMode;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\MemberCardOrder;
use App\Models\PayrollCoachConfig;
use App\Models\PayrollCoachRule;
use App\Models\PayrollSalesConfig;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Orders\MemberCardOrderService;
use Illuminate\Support\Carbon;
class PayrollReportEngine
{
    /** @var list<AppointmentStatus> */
    private const QUALIFYING_APPOINTMENT_STATUSES = [
        AppointmentStatus::Confirmed,
        AppointmentStatus::Completed,
        AppointmentStatus::Absent,
    ];

    public function __construct(
        private readonly MemberCardOrderService $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function computeCoachReport(Staff $actor, Site $site, Staff $coach, int $year, int $month): array
    {
        $config = PayrollCoachConfig::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        $mode = $config?->enabled ? $config->mode : null;
        $rule = PayrollCoachRule::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('staff_id', $coach->id)
            ->first();

        $matrix = $this->ruleMatrix($rule?->matrix ?? []);
        [$start, $end] = $this->periodRange($year, $month);

        $sessions = ScheduleSession::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('coach_staff_id', $coach->id)
            ->where('status', '!=', ScheduleSessionStatus::Cancelled)
            ->whereBetween('starts_at', [$start, $end])
            ->with([
                'course',
                'appointments' => fn ($query) => $query->whereIn('status', self::QUALIFYING_APPOINTMENT_STATUSES),
            ])
            ->get();

        $courseLines = [];
        $groupSessionCount = 0;
        $privateSessionCount = 0;
        $totalPayCents = 0;

        foreach ($sessions as $session) {
            $courseId = $session->course_id;
            if (! $courseId) {
                continue;
            }

            $isGroup = $session->session_kind === ScheduleSessionKind::Group;
            $delivered = $isGroup ? $this->isGroupSessionHeld($session) : $this->isPrivateSessionDelivered($session);
            if (! $delivered) {
                continue;
            }

            if ($isGroup) {
                $groupSessionCount++;
            } else {
                $privateSessionCount++;
            }

            $ruleRow = $this->findRuleRow($matrix, $courseId, $isGroup);
            $payCents = $this->coachPayForSession($mode, $session, $ruleRow);
            $totalPayCents += $payCents;

            $lineKey = ($isGroup ? 'group' : 'private').':'.$courseId;
            if (! isset($courseLines[$lineKey])) {
                $courseLines[$lineKey] = [
                    'courseId' => $courseId,
                    'courseName' => $session->course?->name,
                    'sessionKind' => $isGroup ? 'group' : 'private',
                    'deliveredCount' => 0,
                    'headcount' => 0,
                    'payCents' => 0,
                ];
            }

            $courseLines[$lineKey]['deliveredCount']++;
            $courseLines[$lineKey]['headcount'] += $session->appointments
                ->where('status', AppointmentStatus::Completed)
                ->count();
            $courseLines[$lineKey]['payCents'] += $payCents;
        }

        return [
            'staffId' => $coach->id,
            'staffName' => $coach->name,
            'employeeNo' => $coach->employee_no,
            'year' => $year,
            'month' => $month,
            'coachConfig' => [
                'enabled' => (bool) ($config?->enabled ?? false),
                'mode' => $mode?->value,
            ],
            'matrixVersion' => $rule?->matrix_version ?? 0,
            'totals' => [
                'groupSessionCount' => $groupSessionCount,
                'privateSessionCount' => $privateSessionCount,
                'deliveredSessionCount' => $groupSessionCount + $privateSessionCount,
                'totalPayCents' => $totalPayCents,
            ],
            'courseLines' => array_values($courseLines),
            'computedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function computeCoachReportSummaries(Staff $actor, Site $site, int $year, int $month): array
    {
        $coachIds = ScheduleSession::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', ScheduleSessionStatus::Cancelled)
            ->whereBetween('starts_at', $this->periodRange($year, $month))
            ->whereNotNull('coach_staff_id')
            ->distinct()
            ->pluck('coach_staff_id');

        $coaches = Staff::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('id', $coachIds)
            ->orderBy('name')
            ->get();

        return $coaches
            ->map(function (Staff $coach) use ($actor, $site, $year, $month) {
                $report = $this->computeCoachReport($actor, $site, $coach, $year, $month);

                return [
                    'staffId' => $coach->id,
                    'staffName' => $coach->name,
                    'employeeNo' => $coach->employee_no,
                    'mode' => $report['coachConfig']['mode'],
                    'groupSessionCount' => $report['totals']['groupSessionCount'],
                    'privateSessionCount' => $report['totals']['privateSessionCount'],
                    'deliveredSessionCount' => $report['totals']['deliveredSessionCount'],
                    'totalPayCents' => $report['totals']['totalPayCents'],
                    'matrixVersion' => $report['matrixVersion'],
                ];
            })
            ->filter(fn (array $row) => $row['deliveredSessionCount'] > 0 || $row['totalPayCents'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function computeSalesReport(Staff $actor, Site $site, Staff $salesStaff, int $year, int $month): array
    {
        $config = PayrollSalesConfig::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        [$start, $end] = $this->periodRange($year, $month);

        $orders = MemberCardOrder::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('created_by_staff_id', $salesStaff->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->whereBetween('created_at', [$start, $end])
            ->with('amountCorrections')
            ->orderBy('created_at')
            ->get();

        $newSaleRevenueCents = 0;
        $renewalRevenueCents = 0;
        $newSaleCommissionCents = 0;
        $renewalCommissionCents = 0;
        $orderLines = [];

        foreach ($orders as $order) {
            $amountCents = $this->amountToCents((float) $this->orders->effectiveAmount($order));
            $category = $this->saleCategory($order);
            $commissionCents = $this->salesCommissionCents($config, $category, $amountCents);

            if ($category === 'renewal') {
                $renewalRevenueCents += $amountCents;
                $renewalCommissionCents += $commissionCents;
            } else {
                $newSaleRevenueCents += $amountCents;
                $newSaleCommissionCents += $commissionCents;
            }

            $orderLines[] = [
                'orderId' => $order->id,
                'orderNo' => $order->order_no,
                'memberId' => $order->member_id,
                'saleCategory' => $category,
                'amountCents' => $amountCents,
                'commissionCents' => $commissionCents,
                'createdAt' => $order->created_at?->toIso8601String(),
            ];
        }

        return [
            'staffId' => $salesStaff->id,
            'staffName' => $salesStaff->name,
            'employeeNo' => $salesStaff->employee_no,
            'year' => $year,
            'month' => $month,
            'salesConfig' => [
                'enabled' => (bool) ($config?->enabled ?? false),
                'mode' => $config?->mode?->value,
            ],
            'totals' => [
                'cardSalesCount' => $orders->count(),
                'revenueCents' => $newSaleRevenueCents + $renewalRevenueCents,
                'newSaleRevenueCents' => $newSaleRevenueCents,
                'renewalRevenueCents' => $renewalRevenueCents,
                'commissionCents' => $newSaleCommissionCents + $renewalCommissionCents,
                'newSaleCommissionCents' => $newSaleCommissionCents,
                'renewalCommissionCents' => $renewalCommissionCents,
            ],
            'orderLines' => $orderLines,
            'computedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function computeSalesReportSummaries(Staff $actor, Site $site, int $year, int $month): array
    {
        [$start, $end] = $this->periodRange($year, $month);

        $staffIds = MemberCardOrder::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('created_by_staff_id')
            ->distinct()
            ->pluck('created_by_staff_id');

        $salesStaff = Staff::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('id', $staffIds)
            ->orderBy('name')
            ->get();

        return $salesStaff
            ->map(function (Staff $person) use ($actor, $site, $year, $month) {
                $report = $this->computeSalesReport($actor, $site, $person, $year, $month);

                return [
                    'staffId' => $person->id,
                    'staffName' => $person->name,
                    'employeeNo' => $person->employee_no,
                    'cardSalesCount' => $report['totals']['cardSalesCount'],
                    'revenueCents' => $report['totals']['revenueCents'],
                    'commissionCents' => $report['totals']['commissionCents'],
                    'newSaleCommissionCents' => $report['totals']['newSaleCommissionCents'],
                    'renewalCommissionCents' => $report['totals']['renewalCommissionCents'],
                ];
            })
            ->filter(fn (array $row) => $row['cardSalesCount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function computeCourseCommissionReport(
        Staff $actor,
        Site $site,
        Staff $coach,
        int $year,
        int $month,
    ): array {
        $report = $this->computeCoachReport($actor, $site, $coach, $year, $month);

        return [
            'staffId' => $coach->id,
            'staffName' => $coach->name,
            'year' => $year,
            'month' => $month,
            'mode' => $report['coachConfig']['mode'],
            'items' => collect($report['courseLines'])
                ->map(fn (array $line) => [
                    'courseId' => $line['courseId'],
                    'courseName' => $line['courseName'],
                    'sessionKind' => $line['sessionKind'],
                    'deliveredCount' => $line['deliveredCount'],
                    'headcount' => $line['headcount'],
                    'commissionCents' => $line['payCents'],
                ])
                ->values()
                ->all(),
            'totals' => [
                'courseCount' => count($report['courseLines']),
                'deliveredSessionCount' => $report['totals']['deliveredSessionCount'],
                'commissionCents' => $report['totals']['totalPayCents'],
            ],
            'computedAt' => $report['computedAt'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $ruleRow
     */
    private function coachPayForSession(?PayrollCoachMode $mode, ScheduleSession $session, ?array $ruleRow): int
    {
        if ($ruleRow === null) {
            return 0;
        }

        $unitPriceCents = (int) ($ruleRow['unitPriceCents'] ?? 0);
        $additionalPriceCents = (int) ($ruleRow['additionalPriceCents'] ?? 0);

        return match ($mode) {
            PayrollCoachMode::Headcount => $this->headcountPay($session, $unitPriceCents, $additionalPriceCents),
            PayrollCoachMode::Amount => $this->amountModePay($session, $ruleRow),
            default => $unitPriceCents,
        };
    }

    private function headcountPay(ScheduleSession $session, int $unitPriceCents, int $additionalPriceCents): int
    {
        $completedCount = $session->appointments
            ->where('status', AppointmentStatus::Completed)
            ->count();

        if ($completedCount <= 0) {
            return $unitPriceCents;
        }

        $pay = $completedCount * $unitPriceCents;
        if ($session->session_kind === ScheduleSessionKind::Private && $additionalPriceCents > 0 && $completedCount > 1) {
            $pay += ($completedCount - 1) * $additionalPriceCents;
        }

        return $pay;
    }

    /**
     * @param  array<string, mixed>  $ruleRow
     */
    private function amountModePay(ScheduleSession $session, array $ruleRow): int
    {
        $ratePercent = (int) ($ruleRow['supplementalRatePercent'] ?? 0);
        if ($ratePercent <= 0) {
            return (int) ($ruleRow['unitPriceCents'] ?? 0);
        }

        $baseCents = (int) ($ruleRow['unitPriceCents'] ?? 0);
        $headcount = max(1, $session->appointments
            ->where('status', AppointmentStatus::Completed)
            ->count());

        return (int) round($baseCents * $headcount * $ratePercent / 100);
    }

    /**
     * @return array{groupCourses: array<int, array<string, mixed>>, privateCourses: array<int, array<string, mixed>>}
     */
    private function ruleMatrix(array $matrix): array
    {
        $group = collect($matrix['groupCourses'] ?? [])->keyBy('courseId')->all();
        $private = collect($matrix['privateCourses'] ?? [])->keyBy('courseId')->all();

        return [
            'groupCourses' => $group,
            'privateCourses' => $private,
        ];
    }

    /**
     * @param  array{groupCourses: array<int, array<string, mixed>>, privateCourses: array<int, array<string, mixed>>}  $matrix
     * @return array<string, mixed>|null
     */
    private function findRuleRow(array $matrix, int $courseId, bool $isGroup): ?array
    {
        $bucket = $isGroup ? $matrix['groupCourses'] : $matrix['privateCourses'];

        return $bucket[$courseId] ?? null;
    }

    private function saleCategory(MemberCardOrder $order): string
    {
        $metadata = $order->metadata ?? [];
        $category = $metadata['saleCategory'] ?? $metadata['saleType'] ?? 'new';

        return in_array($category, ['renewal', 'renew'], true) ? 'renewal' : 'new';
    }

    private function salesCommissionCents(?PayrollSalesConfig $config, string $category, int $amountCents): int
    {
        if (! $config?->enabled || $config->mode === null || $amountCents <= 0) {
            return 0;
        }

        $settings = $config->settings ?? [];

        if ($config->mode === PayrollSalesMode::FlatRate) {
            $rate = $category === 'renewal'
                ? (int) ($settings['renewalRatePercent'] ?? 0)
                : (int) ($settings['newSaleRatePercent'] ?? 0);

            return (int) round($amountCents * $rate / 100);
        }

        $tiers = $category === 'renewal'
            ? ($settings['renewalTiers'] ?? [])
            : ($settings['newSaleTiers'] ?? []);

        foreach ($tiers as $tier) {
            $from = (int) ($tier['fromAmountCents'] ?? 0);
            $to = array_key_exists('toAmountCents', $tier) && $tier['toAmountCents'] !== null
                ? (int) $tier['toAmountCents']
                : null;
            $rate = (int) ($tier['ratePercent'] ?? 0);

            if ($amountCents >= $from && ($to === null || $amountCents <= $to)) {
                return (int) round($amountCents * $rate / 100);
            }
        }

        return 0;
    }

    private function isGroupSessionHeld(ScheduleSession $session): bool
    {
        if ($session->status === ScheduleSessionStatus::Completed) {
            return true;
        }

        return $session->appointments
            ->where('status', AppointmentStatus::Completed)
            ->isNotEmpty();
    }

    private function isPrivateSessionDelivered(ScheduleSession $session): bool
    {
        if ($session->status === ScheduleSessionStatus::Completed) {
            return true;
        }

        return $session->appointments->isNotEmpty();
    }

    private function amountToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }
}
