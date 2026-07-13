<?php

namespace App\Services\Payroll;

use App\Enums\PayrollReportType;
use App\Models\PayrollReportSnapshot;
use App\Models\Site;
use App\Models\Staff;

class PayrollReportService
{
    public function __construct(
        private readonly PayrollReportEngine $engine,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function coachReports(Staff $actor, Site $site, int $year, int $month): array
    {
        $snapshots = $this->latestSnapshots($actor, $site, $year, $month, PayrollReportType::Coach);
        if ($snapshots->isNotEmpty()) {
            $items = $snapshots
                ->map(fn (PayrollReportSnapshot $snapshot) => $this->coachSummaryFromPayload($snapshot->payload))
                ->values()
                ->all();

            return $this->wrapList($year, $month, $items, 'snapshot');
        }

        $items = $this->engine->computeCoachReportSummaries($actor, $site, $year, $month);

        return $this->wrapList($year, $month, $items, 'computed');
    }

    /**
     * @return array<string, mixed>
     */
    public function coachReportDetail(
        Staff $actor,
        Site $site,
        Staff $coach,
        int $year,
        int $month,
    ): array {
        $this->assertStaffAtSite($actor, $site, $coach);

        $snapshot = $this->latestSnapshot($actor, $site, $year, $month, PayrollReportType::Coach, $coach->id);
        if ($snapshot !== null) {
            return array_merge($snapshot->payload, ['source' => 'snapshot']);
        }

        return array_merge(
            $this->engine->computeCoachReport($actor, $site, $coach, $year, $month),
            ['source' => 'computed'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function salesReports(Staff $actor, Site $site, int $year, int $month): array
    {
        $snapshots = $this->latestSnapshots($actor, $site, $year, $month, PayrollReportType::Sales);
        if ($snapshots->isNotEmpty()) {
            $items = $snapshots
                ->map(fn (PayrollReportSnapshot $snapshot) => $this->salesSummaryFromPayload($snapshot->payload))
                ->values()
                ->all();

            return $this->wrapList($year, $month, $items, 'snapshot');
        }

        $items = $this->engine->computeSalesReportSummaries($actor, $site, $year, $month);

        return $this->wrapList($year, $month, $items, 'computed');
    }

    /**
     * @return array<string, mixed>
     */
    public function salesReportDetail(
        Staff $actor,
        Site $site,
        Staff $salesStaff,
        int $year,
        int $month,
    ): array {
        $this->assertStaffAtSite($actor, $site, $salesStaff);

        $snapshot = $this->latestSnapshot($actor, $site, $year, $month, PayrollReportType::Sales, $salesStaff->id);
        if ($snapshot !== null) {
            return array_merge($snapshot->payload, ['source' => 'snapshot']);
        }

        return array_merge(
            $this->engine->computeSalesReport($actor, $site, $salesStaff, $year, $month),
            ['source' => 'computed'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function courseCommissionReport(
        Staff $actor,
        Site $site,
        Staff $coach,
        int $year,
        int $month,
    ): array {
        $this->assertStaffAtSite($actor, $site, $coach);

        $snapshot = $this->latestSnapshot(
            $actor,
            $site,
            $year,
            $month,
            PayrollReportType::CoachCourseCommission,
            $coach->id,
        );

        if ($snapshot !== null) {
            return array_merge($snapshot->payload, ['source' => 'snapshot']);
        }

        return array_merge(
            $this->engine->computeCourseCommissionReport($actor, $site, $coach, $year, $month),
            ['source' => 'computed'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function wrapList(int $year, int $month, array $items, string $source): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'source' => $source,
            'totals' => [
                'staffCount' => count($items),
                'totalPayCents' => collect($items)->sum(fn (array $row) => $row['totalPayCents'] ?? $row['commissionCents'] ?? 0),
            ],
            'items' => $items,
            'asOf' => now()->toIso8601String(),
        ];
    }

    private function latestSnapshot(
        Staff $actor,
        Site $site,
        int $year,
        int $month,
        PayrollReportType $type,
        int $staffId,
    ): ?PayrollReportSnapshot {
        return PayrollReportSnapshot::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('report_type', $type)
            ->where('staff_id', $staffId)
            ->orderByDesc('computed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PayrollReportSnapshot>
     */
    private function latestSnapshots(
        Staff $actor,
        Site $site,
        int $year,
        int $month,
        PayrollReportType $type,
    ) {
        $snapshots = PayrollReportSnapshot::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('report_type', $type)
            ->orderByDesc('computed_at')
            ->orderByDesc('id')
            ->get();

        return $snapshots
            ->groupBy('staff_id')
            ->map(fn ($group) => $group->first())
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function coachSummaryFromPayload(array $payload): array
    {
        if (isset($payload['totals']['totalPayCents'])) {
            return [
                'staffId' => $payload['staffId'],
                'staffName' => $payload['staffName'],
                'employeeNo' => $payload['employeeNo'] ?? null,
                'mode' => $payload['coachConfig']['mode'] ?? null,
                'groupSessionCount' => $payload['totals']['groupSessionCount'] ?? 0,
                'privateSessionCount' => $payload['totals']['privateSessionCount'] ?? 0,
                'deliveredSessionCount' => $payload['totals']['deliveredSessionCount'] ?? 0,
                'totalPayCents' => $payload['totals']['totalPayCents'] ?? 0,
                'matrixVersion' => $payload['matrixVersion'] ?? 0,
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function salesSummaryFromPayload(array $payload): array
    {
        if (isset($payload['totals']['commissionCents'])) {
            return [
                'staffId' => $payload['staffId'],
                'staffName' => $payload['staffName'],
                'employeeNo' => $payload['employeeNo'] ?? null,
                'cardSalesCount' => $payload['totals']['cardSalesCount'] ?? 0,
                'revenueCents' => $payload['totals']['revenueCents'] ?? 0,
                'commissionCents' => $payload['totals']['commissionCents'] ?? 0,
                'newSaleCommissionCents' => $payload['totals']['newSaleCommissionCents'] ?? 0,
                'renewalCommissionCents' => $payload['totals']['renewalCommissionCents'] ?? 0,
            ];
        }

        return $payload;
    }

    private function assertStaffAtSite(Staff $actor, Site $site, Staff $target): void
    {
        abort_unless($target->tenant_id === $actor->tenant_id, 404);

        abort_unless(
            $target->sites()
                ->whereKey($site->id)
                ->where('site_staff.tenant_id', $actor->tenant_id)
                ->exists(),
            404,
        );
    }
}
