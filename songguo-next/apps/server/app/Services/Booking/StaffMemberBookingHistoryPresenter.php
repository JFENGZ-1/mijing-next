<?php

namespace App\Services\Booking;

use App\Models\Appointment;

class StaffMemberBookingHistoryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Appointment $appointment): array
    {
        $session = $appointment->relationLoaded('session') ? $appointment->session : null;

        return [
            'id' => $appointment->id,
            'siteId' => $appointment->site_id,
            'sessionId' => $appointment->session_id,
            'status' => $appointment->status->value,
            'bookedAt' => $appointment->booked_at?->toIso8601String(),
            'cancelledAt' => $appointment->cancelled_at?->toIso8601String(),
            'absentMarkedAt' => $appointment->absent_marked_at?->toIso8601String(),
            'staffNotes' => $appointment->staff_notes,
            'courseName' => $session?->course?->name,
            'courseType' => $session?->course?->course_type->value,
            'sessionKind' => $session?->session_kind->value,
            'startsAt' => $session?->starts_at?->toIso8601String(),
            'endsAt' => $session?->ends_at?->toIso8601String(),
            'roomName' => $session?->room?->name,
            'coachName' => $session?->coach?->name,
        ];
    }
}
