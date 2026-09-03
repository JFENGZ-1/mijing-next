<?php

namespace App\Services\Members;

use App\Enums\AppointmentStatus;
use App\Enums\CourseType;
use App\Models\Appointment;
use App\Models\Member;
use App\Services\Booking\StaffMemberBookingHistoryPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MemberStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function year(Member $member, int $year): array
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = $this->monthBucket($member, $year, $month);
        }

        $yearBucket = $this->aggregateBuckets($months);
        $yearBucket['year'] = $year;
        $yearBucket['totalCount'] = $this->totalCompletedCount($member);

        return [
            'tenantId' => $member->tenant_id,
            ...$yearBucket,
            'months' => $months,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function month(Member $member, int $year, int $month): array
    {
        return [
            'tenantId' => $member->tenant_id,
            ...$this->monthBucket($member, $year, $month),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function monthAppointments(
        Member $member,
        int $year,
        int $month,
        string $courseKind,
        int $page,
        int $perPage,
    ): array {
        $paginator = $this->monthAppointmentsQuery($member, $year, $month, $courseKind)
            ->paginate($perPage, ['appointments.*'], 'page', $page);

        return [
            'tenantId' => $member->tenant_id,
            'year' => $year,
            'month' => $month,
            'courseKind' => $courseKind,
            'items' => $this->monthAppointmentItems($paginator),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
                'hasNext' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * @return Builder<Appointment>
     */
    private function monthAppointmentsQuery(Member $member, int $year, int $month, string $courseKind): Builder
    {
        return Appointment::query()
            ->with(['session.course', 'session.room', 'session.coach'])
            ->join('schedule_sessions', 'appointments.session_id', '=', 'schedule_sessions.id')
            ->join('courses', 'schedule_sessions.course_id', '=', 'courses.id')
            ->where('appointments.tenant_id', $member->tenant_id)
            ->where('appointments.member_id', $member->id)
            ->whereYear('schedule_sessions.starts_at', $year)
            ->whereMonth('schedule_sessions.starts_at', $month)
            ->when($courseKind !== 'all', function (Builder $query) use ($courseKind) {
                $query->where('courses.course_type', $courseKind);
            })
            ->select('appointments.*')
            ->orderByDesc('schedule_sessions.starts_at')
            ->orderByDesc('appointments.id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function monthAppointmentItems(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (Appointment $appointment) => StaffMemberBookingHistoryPresenter::toArray($appointment))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function monthBucket(Member $member, int $year, int $month): array
    {
        $rows = $this->statusRows($member, $year, $month);
        $bucket = $this->emptyBucket();
        $bucket['year'] = $year;
        $bucket['month'] = $month;

        foreach ($rows as $row) {
            $courseType = $row->course_type instanceof CourseType
                ? $row->course_type
                : CourseType::tryFrom((string) $row->course_type);
            $status = $row->status instanceof AppointmentStatus
                ? $row->status
                : AppointmentStatus::tryFrom((string) $row->status);
            $count = (int) $row->count;

            if (! $courseType || ! $status) {
                continue;
            }

            $this->applyCount($bucket, $courseType, $status, $count);
        }

        return $bucket;
    }

    /**
     * @return Collection<int, object{course_type: string, status: string, count: int}>
     */
    private function statusRows(Member $member, int $year, ?int $month = null): Collection
    {
        return Appointment::query()
            ->join('schedule_sessions', 'appointments.session_id', '=', 'schedule_sessions.id')
            ->join('courses', 'schedule_sessions.course_id', '=', 'courses.id')
            ->where('appointments.tenant_id', $member->tenant_id)
            ->where('appointments.member_id', $member->id)
            ->whereYear('schedule_sessions.starts_at', $year)
            ->when($month !== null, fn ($query) => $query->whereMonth('schedule_sessions.starts_at', $month))
            ->selectRaw('courses.course_type, appointments.status, count(*) as count')
            ->groupBy('courses.course_type', 'appointments.status')
            ->get();
    }

    private function totalCompletedCount(Member $member): int
    {
        return Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('status', AppointmentStatus::Completed)
            ->count();
    }

    /**
     * @param  list<array<string, mixed>>  $buckets
     * @return array<string, mixed>
     */
    private function aggregateBuckets(array $buckets): array
    {
        $aggregate = $this->emptyBucket();

        foreach ($buckets as $bucket) {
            foreach (['teamTimes', 'teamAbsent', 'privateTimes', 'privateAbsent', 'confirmedCount', 'cancelledCount'] as $key) {
                $aggregate[$key] += (int) ($bucket[$key] ?? 0);
            }
        }

        return $aggregate;
    }

    /**
     * @return array<string, int>
     */
    private function emptyBucket(): array
    {
        return [
            'teamTimes' => 0,
            'teamAbsent' => 0,
            'privateTimes' => 0,
            'privateAbsent' => 0,
            'confirmedCount' => 0,
            'cancelledCount' => 0,
        ];
    }

    private function applyCount(array &$bucket, CourseType $courseType, AppointmentStatus $status, int $count): void
    {
        match ($status) {
            AppointmentStatus::Completed => $courseType === CourseType::Group
                ? $bucket['teamTimes'] += $count
                : $bucket['privateTimes'] += $count,
            AppointmentStatus::Absent => $courseType === CourseType::Group
                ? $bucket['teamAbsent'] += $count
                : $bucket['privateAbsent'] += $count,
            AppointmentStatus::Confirmed => $bucket['confirmedCount'] += $count,
            AppointmentStatus::Cancelled => $bucket['cancelledCount'] += $count,
            default => null,
        };
    }
}
