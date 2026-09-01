<?php

namespace App\Services\Reporting;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportCourseService
{
    /** @var list<AppointmentStatus> */
    private const QUALIFYING_APPOINTMENT_STATUSES = [
        AppointmentStatus::Confirmed,
        AppointmentStatus::Completed,
        AppointmentStatus::Absent,
    ];

    /**
     * Year/month rollup (maps legacy sumMainCourseList / courseReportForm).
     *
     * @return array<string, mixed>
     */
    public function summary(Staff $staff, Site $site): array
    {
        $sessions = $this->sessions($staff, $site);
        $years = $this->yearsWithActivity($sessions);

        return [
            'years' => collect($years)
                ->map(fn (int $year) => $this->yearBlock($year, $sessions))
                ->values()
                ->all(),
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Twelve-month calendar for one year (maps legacy CourseMonthList year scope).
     *
     * @return array<string, mixed>
     */
    public function calendar(Staff $staff, Site $site, int $year): array
    {
        $sessions = $this->sessions($staff, $site, $year);
        $now = now();
        $maxMonth = $year === (int) $now->year ? (int) $now->month : 12;
        $months = [];

        for ($month = 1; $month <= $maxMonth; $month++) {
            $months[] = $this->periodMetrics($year, $month, null, $sessions, 'all');
        }

        return [
            'year' => $year,
            'totals' => $this->rollupTotals($months),
            'months' => $months,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Daily breakdown for one month (maps legacy CourseMonthList dayList).
     *
     * @return array<string, mixed>
     */
    public function daily(Staff $staff, Site $site, int $year, int $month, string $courseKind): array
    {
        $sessions = $this->sessions($staff, $site, $year, $month);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $daysInMonth = (int) $start->copy()->endOfMonth()->day;
        $days = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $metrics = $this->periodMetrics($year, $month, $day, $sessions, $courseKind);
            if ($this->periodHasActivity($metrics, $courseKind)) {
                $days[] = $metrics;
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'courseKind' => $courseKind,
            'totals' => $this->rollupTotals($days, $courseKind),
            'days' => $days,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, ScheduleSession>
     */
    private function sessions(Staff $staff, Site $site, ?int $year = null, ?int $month = null): Collection
    {
        $query = ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', ScheduleSessionStatus::Cancelled)
            ->with(['appointments' => fn ($query) => $query->whereIn('status', self::QUALIFYING_APPOINTMENT_STATUSES)])
            ->orderBy('starts_at');

        if ($year !== null && $month !== null) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $query->whereBetween('starts_at', [$start, $start->copy()->endOfMonth()]);
        } elseif ($year !== null) {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $query->whereBetween('starts_at', [$start, $start->copy()->endOfYear()]);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     * @return list<int>
     */
    private function yearsWithActivity(Collection $sessions): array
    {
        $years = $sessions
            ->map(fn (ScheduleSession $session) => (int) $session->starts_at?->year)
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
     * @param  Collection<int, ScheduleSession>  $sessions
     * @return array<string, mixed>
     */
    private function yearBlock(int $year, Collection $sessions): array
    {
        $yearSessions = $sessions->filter(
            fn (ScheduleSession $session) => (int) $session->starts_at?->year === $year,
        );
        $now = now();
        $maxMonth = $year === (int) $now->year ? (int) $now->month : 12;
        $months = [];

        for ($month = 1; $month <= $maxMonth; $month++) {
            $metrics = $this->periodMetrics($year, $month, null, $yearSessions, 'all');
            if ($this->periodHasActivity($metrics, 'all')) {
                $months[] = $metrics;
            }
        }

        $yearTotals = $this->rollupTotals(
            collect(range(1, $maxMonth))
                ->map(fn (int $month) => $this->periodMetrics($year, $month, null, $yearSessions, 'all'))
                ->all(),
        );

        return [
            'year' => $year,
            'isCurrentYear' => $year === (int) $now->year,
            'groupScheduledCount' => $yearTotals['groupScheduledCount'],
            'groupHeldCount' => $yearTotals['groupHeldCount'],
            'groupSignInCount' => $yearTotals['groupSignInCount'],
            'privateSessionCount' => $yearTotals['privateSessionCount'],
            'months' => $months,
        ];
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     * @return array<string, mixed>
     */
    private function periodMetrics(
        int $year,
        int $month,
        ?int $day,
        Collection $sessions,
        string $courseKind,
    ): array {
        [$start, $end] = $this->periodRange($year, $month, $day);

        $periodSessions = $sessions->filter(
            fn (ScheduleSession $session) => $session->starts_at?->betweenIncluded($start, $end) ?? false,
        );

        $metrics = [
            'year' => $year,
            'month' => $month,
            'groupScheduledCount' => $this->countGroupScheduled($periodSessions, $courseKind),
            'groupHeldCount' => $this->countGroupHeld($periodSessions, $courseKind),
            'groupSignInCount' => $this->countGroupSignIns($periodSessions, $courseKind),
            'privateSessionCount' => $this->countPrivateSessions($periodSessions, $courseKind),
        ];

        if ($day !== null) {
            $metrics['day'] = $day;
        }

        return $metrics;
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     */
    private function countGroupScheduled(Collection $sessions, string $courseKind): int
    {
        if ($courseKind === 'private') {
            return 0;
        }

        return $sessions
            ->filter(fn (ScheduleSession $session) => $session->session_kind === ScheduleSessionKind::Group)
            ->count();
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     */
    private function countGroupHeld(Collection $sessions, string $courseKind): int
    {
        if ($courseKind === 'private') {
            return 0;
        }

        return $sessions
            ->filter(fn (ScheduleSession $session) => $session->session_kind === ScheduleSessionKind::Group)
            ->filter(fn (ScheduleSession $session) => $this->isGroupSessionHeld($session))
            ->count();
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     */
    private function countGroupSignIns(Collection $sessions, string $courseKind): int
    {
        if ($courseKind === 'private') {
            return 0;
        }

        return $sessions
            ->filter(fn (ScheduleSession $session) => $session->session_kind === ScheduleSessionKind::Group)
            ->sum(fn (ScheduleSession $session) => $this->completedAppointmentCount($session));
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     */
    private function countPrivateSessions(Collection $sessions, string $courseKind): int
    {
        if ($courseKind === 'group') {
            return 0;
        }

        return $sessions
            ->filter(fn (ScheduleSession $session) => $session->session_kind === ScheduleSessionKind::Private)
            ->filter(fn (ScheduleSession $session) => $this->isPrivateSessionDelivered($session))
            ->count();
    }

    private function isGroupSessionHeld(ScheduleSession $session): bool
    {
        if ($session->status === ScheduleSessionStatus::Completed) {
            return true;
        }

        return $this->completedAppointmentCount($session) > 0;
    }

    private function isPrivateSessionDelivered(ScheduleSession $session): bool
    {
        if ($session->status === ScheduleSessionStatus::Completed) {
            return true;
        }

        return $this->completedAppointmentCount($session) > 0;
    }

    private function completedAppointmentCount(ScheduleSession $session): int
    {
        return $session->appointments
            ->where('status', AppointmentStatus::Completed)
            ->count();
    }

    /**
     * @param  list<array<string, mixed>>  $periods
     * @return array{groupScheduledCount: int, groupHeldCount: int, groupSignInCount: int, privateSessionCount: int}
     */
    private function rollupTotals(array $periods, string $courseKind = 'all'): array
    {
        return [
            'groupScheduledCount' => collect($periods)->sum('groupScheduledCount'),
            'groupHeldCount' => collect($periods)->sum('groupHeldCount'),
            'groupSignInCount' => collect($periods)->sum('groupSignInCount'),
            'privateSessionCount' => collect($periods)->sum('privateSessionCount'),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function periodHasActivity(array $metrics, string $courseKind): bool
    {
        return match ($courseKind) {
            'group' => $metrics['groupScheduledCount'] > 0
                || $metrics['groupHeldCount'] > 0
                || $metrics['groupSignInCount'] > 0,
            'private' => $metrics['privateSessionCount'] > 0,
            default => $metrics['groupScheduledCount'] > 0
                || $metrics['groupHeldCount'] > 0
                || $metrics['groupSignInCount'] > 0
                || $metrics['privateSessionCount'] > 0,
        };
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
}
