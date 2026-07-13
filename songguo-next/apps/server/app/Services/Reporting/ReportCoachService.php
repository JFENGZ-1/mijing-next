<?php

namespace App\Services\Reporting;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportCoachService
{
    /** @var list<AppointmentStatus> */
    private const DETAIL_APPOINTMENT_STATUSES = [
        AppointmentStatus::Confirmed,
        AppointmentStatus::Completed,
        AppointmentStatus::Cancelled,
        AppointmentStatus::Absent,
    ];

    /** @var list<AppointmentStatus> */
    private const QUALIFYING_APPOINTMENT_STATUSES = [
        AppointmentStatus::Confirmed,
        AppointmentStatus::Completed,
        AppointmentStatus::Absent,
    ];

    /**
     * Per-coach monthly appointment lines (maps legacy getOnestaffInMonthDetail / findPrivateAppontmentofStaffuserid).
     *
     * @return array<string, mixed>
     */
    public function appointmentDetail(
        Staff $viewer,
        Site $site,
        Staff $coach,
        int $year,
        int $month,
        string $sessionKind,
        int $page,
        int $perPage,
    ): array {
        $canReadMemberNames = $viewer->hasPermission('crm.member.read', $site->id);
        [$start, $end] = $this->periodRange($year, $month);

        $baseQuery = Appointment::query()
            ->where('appointments.tenant_id', $viewer->tenant_id)
            ->where('appointments.site_id', $site->id)
            ->whereIn('appointments.status', self::DETAIL_APPOINTMENT_STATUSES)
            ->join('schedule_sessions as coach_report_sessions', 'appointments.session_id', '=', 'coach_report_sessions.id')
            ->where('coach_report_sessions.coach_staff_id', $coach->id)
            ->where('coach_report_sessions.status', '!=', ScheduleSessionStatus::Cancelled)
            ->whereBetween('coach_report_sessions.starts_at', [$start, $end]);

        if ($sessionKind !== 'all') {
            $kind = $sessionKind === 'private' ? ScheduleSessionKind::Private : ScheduleSessionKind::Group;
            $baseQuery->where('coach_report_sessions.session_kind', $kind);
        }

        $totalsQuery = (clone $baseQuery);
        $signedInCount = (clone $totalsQuery)->where('appointments.status', AppointmentStatus::Completed)->count();
        $cancelledCount = (clone $totalsQuery)->where('appointments.status', AppointmentStatus::Cancelled)->count();
        $absentCount = (clone $totalsQuery)->where('appointments.status', AppointmentStatus::Absent)->count();

        $paginator = (clone $baseQuery)
            ->with([
                'member.crmProfile',
                'member.account',
                'session.course',
            ])
            ->orderBy('coach_report_sessions.starts_at')
            ->orderBy('appointments.id')
            ->select('appointments.*')
            ->paginate($perPage, ['appointments.*'], 'page', $page);

        return [
            'year' => $year,
            'month' => $month,
            'sessionKind' => $sessionKind,
            'coach' => $this->coachSummary($coach),
            'totals' => [
                'appointmentCount' => $paginator->total(),
                'signedInCount' => $signedInCount,
                'cancelledCount' => $cancelledCount,
                'absentCount' => $absentCount,
            ],
            'items' => collect($paginator->items())
                ->map(fn (Appointment $appointment) => $this->appointmentLine($appointment, $canReadMemberNames))
                ->values()
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * Coach monthly ranking by delivered sessions (maps legacy staffByMonth / rankstaff).
     *
     * @return array<string, mixed>
     */
    public function monthlyRank(
        Staff $viewer,
        Site $site,
        int $year,
        int $month,
        string $sortBy,
    ): array {
        [$start, $end] = $this->periodRange($year, $month);

        $sessions = ScheduleSession::query()
            ->where('tenant_id', $viewer->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', ScheduleSessionStatus::Cancelled)
            ->whereBetween('starts_at', [$start, $end])
            ->whereNotNull('coach_staff_id')
            ->with(['appointments' => fn ($query) => $query->whereIn('status', self::QUALIFYING_APPOINTMENT_STATUSES)])
            ->get();

        $coachIds = $sessions->pluck('coach_staff_id')->unique()->filter()->values();
        $coaches = Staff::query()
            ->where('tenant_id', $viewer->tenant_id)
            ->whereIn('id', $coachIds)
            ->with('account')
            ->get()
            ->keyBy('id');

        $rows = $coachIds
            ->map(function (int $coachId) use ($sessions, $coaches) {
                $coachSessions = $sessions->where('coach_staff_id', $coachId);
                $groupSessionCount = $this->countHeldGroupSessions($coachSessions);
                $privateSessionCount = $this->countDeliveredPrivateSessions($coachSessions);
                $coach = $coaches->get($coachId);

                return [
                    'staffId' => $coachId,
                    'staffName' => $coach?->name,
                    'avatarUrl' => $coach?->account?->avatar_url,
                    'groupSessionCount' => $groupSessionCount,
                    'privateSessionCount' => $privateSessionCount,
                    'completedSessionCount' => $groupSessionCount + $privateSessionCount,
                ];
            })
            ->filter(fn (array $row) => $row['completedSessionCount'] > 0)
            ->sort(function (array $left, array $right) use ($sortBy) {
                $metric = match ($sortBy) {
                    'group' => ['groupSessionCount', 'privateSessionCount'],
                    'private' => ['privateSessionCount', 'groupSessionCount'],
                    default => ['completedSessionCount', 'groupSessionCount'],
                };

                return [$right[$metric[0]], $right[$metric[1]], $left['staffId']]
                    <=> [$left[$metric[0]], $left[$metric[1]], $right['staffId']];
            })
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->all();

        return [
            'year' => $year,
            'month' => $month,
            'sortBy' => $sortBy,
            'totals' => [
                'coachCount' => count($rows),
                'groupSessionCount' => collect($rows)->sum('groupSessionCount'),
                'privateSessionCount' => collect($rows)->sum('privateSessionCount'),
            ],
            'items' => $rows,
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentLine(Appointment $appointment, bool $canReadMemberNames): array
    {
        $session = $appointment->relationLoaded('session') ? $appointment->session : null;
        $member = $appointment->relationLoaded('member') ? $appointment->member : null;
        $rawName = $member?->crmProfile?->name ?? $member?->account?->display_name;

        return [
            'appointmentId' => $appointment->id,
            'sessionId' => $appointment->session_id,
            'sessionKind' => $session?->session_kind?->value,
            'startsAt' => $session?->starts_at?->toIso8601String(),
            'endsAt' => $session?->ends_at?->toIso8601String(),
            'courseId' => $session?->course_id,
            'courseName' => $session?->course?->name,
            'memberId' => $appointment->member_id,
            'memberName' => $canReadMemberNames ? $rawName : $this->maskName($rawName),
            'memberNo' => $member?->member_no,
            'status' => $appointment->status->value,
            'signInState' => $this->signInState($appointment->status),
            'bookedAt' => $appointment->booked_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{staffId: int, staffName: ?string, avatarUrl: ?string}
     */
    private function coachSummary(Staff $coach): array
    {
        return [
            'staffId' => $coach->id,
            'staffName' => $coach->name,
            'avatarUrl' => $coach->relationLoaded('account') ? $coach->account?->avatar_url : null,
        ];
    }

    private function signInState(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::Confirmed => 'booked',
            AppointmentStatus::Completed => 'signed_in',
            AppointmentStatus::Cancelled => 'cancelled',
            AppointmentStatus::Absent => 'absent',
            AppointmentStatus::Waitlisted => 'waitlisted',
        };
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     */
    private function countHeldGroupSessions(Collection $sessions): int
    {
        return $sessions
            ->filter(fn (ScheduleSession $session) => $session->session_kind === ScheduleSessionKind::Group)
            ->filter(fn (ScheduleSession $session) => $this->isGroupSessionHeld($session))
            ->count();
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     */
    private function countDeliveredPrivateSessions(Collection $sessions): int
    {
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

    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
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
