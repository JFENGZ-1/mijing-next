<?php

namespace App\Services\Schedule;

use App\Enums\ScheduleSessionStatus;
use App\Models\Course;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Carbon\Carbon;

class ScheduleRecurringTemplateService
{
    /**
     * @return array<string, mixed>
     */
    public function forCourse(Staff $staff, Site $site, int $courseId): array
    {
        $course = Course::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($courseId)
            ->firstOrFail();

        $sessions = ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('course_id', $course->id)
            ->whereIn('status', [
                ScheduleSessionStatus::Scheduled->value,
                ScheduleSessionStatus::Suspended->value,
            ])
            ->where('starts_at', '>=', now()->subWeeks(4))
            ->orderBy('starts_at')
            ->get();

        $weekdaySlots = $sessions
            ->groupBy(fn (ScheduleSession $session) => $session->starts_at->dayOfWeek)
            ->map(function ($group, $weekday) {
                $first = $group->first();

                return [
                    'weekday' => (int) $weekday,
                    'startsAtTime' => $first->starts_at->format('H:i'),
                    'endsAtTime' => $first->ends_at->format('H:i'),
                    'roomId' => $first->room_id,
                    'coachStaffId' => $first->coach_staff_id,
                    'capacity' => $first->capacity,
                    'sessionKind' => $first->session_kind->value,
                    'occurrenceCount' => $group->count(),
                ];
            })
            ->values()
            ->all();

        return [
            'courseId' => $course->id,
            'courseName' => $course->name,
            'courseType' => $course->course_type->value,
            'durationMinutes' => $course->duration_minutes,
            'weekdaySlots' => $weekdaySlots,
            'sampleSessionCount' => $sessions->count(),
            'anchorDate' => Carbon::now()->toDateString(),
        ];
    }
}
