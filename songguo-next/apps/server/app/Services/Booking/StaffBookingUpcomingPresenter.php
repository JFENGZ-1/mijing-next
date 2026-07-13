<?php

namespace App\Services\Booking;

use App\Models\ScheduleSession;

class StaffBookingUpcomingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ScheduleSession $session): array
    {
        $session->loadMissing(['course', 'room']);

        return [
            'sessionId' => $session->id,
            'courseId' => $session->course_id,
            'courseName' => $session->course?->name,
            'courseType' => $session->course?->course_type->value,
            'sessionKind' => $session->session_kind->value,
            'startsAt' => $session->starts_at?->toIso8601String(),
            'endsAt' => $session->ends_at?->toIso8601String(),
            'roomName' => $session->room?->name,
            'capacity' => $session->capacity,
            'bookedCount' => $session->booked_count,
            'status' => $session->status->value,
        ];
    }
}
