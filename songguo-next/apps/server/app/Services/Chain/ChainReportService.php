<?php

namespace App\Services\Chain;

use App\Models\Member;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportCourseService;
use App\Services\Reporting\ReportFinanceProfitService;
use Illuminate\Support\Collection;

class ChainReportService
{
    public function __construct(
        private readonly ReportFinanceProfitService $finance,
        private readonly ReportCourseService $courses,
        private readonly StaffMemberAccessService $members,
    ) {}

    /**
     * @param  Collection<int, Site>  $sites
     * @return array<string, mixed>
     */
    public function financeSummary(Staff $staff, Collection $sites): array
    {
        $summaries = $sites->map(fn (Site $site) => $this->finance->summary($staff, $site));

        return [
            'siteIds' => $sites->pluck('id')->values()->all(),
            'years' => $this->mergeFinanceYears($summaries),
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return array<string, mixed>
     */
    public function courseSummary(Staff $staff, Collection $sites): array
    {
        $summaries = $sites->map(fn (Site $site) => $this->courses->summary($staff, $site));

        return [
            'siteIds' => $sites->pluck('id')->values()->all(),
            'years' => $this->mergeCourseYears($summaries),
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Cross-site member counts deduped by member id within the tenant.
     *
     * @param  Collection<int, Site>  $sites
     * @return array<string, mixed>
     */
    public function membersSummary(Staff $staff, Collection $sites): array
    {
        $siteIds = $sites->pluck('id')->all();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $baseQuery = Member::query()
            ->where('members.tenant_id', $staff->tenant_id)
            ->whereNull('members.archived_at')
            ->whereHas('sites', fn ($query) => $query
                ->whereIn('sites.id', $siteIds)
                ->where('member_sites.tenant_id', $staff->tenant_id)
                ->where('member_sites.status', 'active'));

        return [
            'siteIds' => $siteIds,
            'totalMemberCount' => (clone $baseQuery)->distinct()->count('members.id'),
            'monthNewMemberCount' => (clone $baseQuery)
                ->whereBetween('joined_at', [$monthStart, $monthEnd])
                ->distinct()
                ->count('members.id'),
            'bySite' => $sites
                ->map(fn (Site $site) => [
                    'siteId' => $site->id,
                    'memberCount' => $this->members->query($staff, $site)
                        ->whereNull('members.archived_at')
                        ->count(),
                ])
                ->values()
                ->all(),
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $summaries
     * @return list<array<string, mixed>>
     */
    private function mergeFinanceYears(Collection $summaries): array
    {
        $yearMap = [];

        foreach ($summaries as $summary) {
            foreach ($summary['years'] as $yearBlock) {
                $year = $yearBlock['year'];
                if (! isset($yearMap[$year])) {
                    $yearMap[$year] = [
                        'year' => $year,
                        'isCurrentYear' => $yearBlock['isCurrentYear'],
                        'newMemberCount' => 0,
                        'cardSalesCount' => 0,
                        'revenue' => 0.0,
                        'months' => [],
                    ];
                }

                $yearMap[$year]['newMemberCount'] += $yearBlock['newMemberCount'];
                $yearMap[$year]['cardSalesCount'] += $yearBlock['cardSalesCount'];
                $yearMap[$year]['revenue'] += (float) $yearBlock['revenue'];

                foreach ($yearBlock['months'] as $monthRow) {
                    $month = $monthRow['month'];
                    if (! isset($yearMap[$year]['months'][$month])) {
                        $yearMap[$year]['months'][$month] = [
                            'year' => $year,
                            'month' => $month,
                            'newMemberCount' => 0,
                            'cardSalesCount' => 0,
                            'revenue' => 0.0,
                        ];
                    }

                    $yearMap[$year]['months'][$month]['newMemberCount'] += $monthRow['newMemberCount'];
                    $yearMap[$year]['months'][$month]['cardSalesCount'] += $monthRow['cardSalesCount'];
                    $yearMap[$year]['months'][$month]['revenue'] += (float) $monthRow['revenue'];
                }
            }
        }

        return collect($yearMap)
            ->sortKeysDesc()
            ->map(function (array $yearBlock) {
                $yearBlock['revenue'] = $this->decimalString($yearBlock['revenue']);
                $yearBlock['months'] = collect($yearBlock['months'])
                    ->sortKeys()
                    ->map(function (array $monthRow) {
                        $monthRow['revenue'] = $this->decimalString($monthRow['revenue']);

                        return $monthRow;
                    })
                    ->values()
                    ->all();

                return $yearBlock;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $summaries
     * @return list<array<string, mixed>>
     */
    private function mergeCourseYears(Collection $summaries): array
    {
        $yearMap = [];

        foreach ($summaries as $summary) {
            foreach ($summary['years'] as $yearBlock) {
                $year = $yearBlock['year'];
                if (! isset($yearMap[$year])) {
                    $yearMap[$year] = [
                        'year' => $year,
                        'isCurrentYear' => $yearBlock['isCurrentYear'],
                        'groupScheduledCount' => 0,
                        'groupHeldCount' => 0,
                        'groupSignInCount' => 0,
                        'privateSessionCount' => 0,
                        'months' => [],
                    ];
                }

                $yearMap[$year]['groupScheduledCount'] += $yearBlock['groupScheduledCount'];
                $yearMap[$year]['groupHeldCount'] += $yearBlock['groupHeldCount'];
                $yearMap[$year]['groupSignInCount'] += $yearBlock['groupSignInCount'];
                $yearMap[$year]['privateSessionCount'] += $yearBlock['privateSessionCount'];

                foreach ($yearBlock['months'] as $monthRow) {
                    $month = $monthRow['month'];
                    if (! isset($yearMap[$year]['months'][$month])) {
                        $yearMap[$year]['months'][$month] = [
                            'year' => $year,
                            'month' => $month,
                            'groupScheduledCount' => 0,
                            'groupHeldCount' => 0,
                            'groupSignInCount' => 0,
                            'privateSessionCount' => 0,
                        ];
                    }

                    $yearMap[$year]['months'][$month]['groupScheduledCount'] += $monthRow['groupScheduledCount'];
                    $yearMap[$year]['months'][$month]['groupHeldCount'] += $monthRow['groupHeldCount'];
                    $yearMap[$year]['months'][$month]['groupSignInCount'] += $monthRow['groupSignInCount'];
                    $yearMap[$year]['months'][$month]['privateSessionCount'] += $monthRow['privateSessionCount'];
                }
            }
        }

        return collect($yearMap)
            ->sortKeysDesc()
            ->map(function (array $yearBlock) {
                $yearBlock['months'] = collect($yearBlock['months'])
                    ->sortKeys()
                    ->values()
                    ->all();

                return $yearBlock;
            })
            ->values()
            ->all();
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
