<?php

namespace App\Services\Booking;

use App\Models\Appointment;

class MemberAppointmentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Appointment $appointment): array
    {
        $data = [
            'id' => $appointment->id,
            'siteId' => $appointment->site_id,
            'sessionId' => $appointment->session_id,
            'status' => $appointment->status->value,
            'bookedAt' => $appointment->booked_at?->toIso8601String(),
            'cancelledAt' => $appointment->cancelled_at?->toIso8601String(),
            'absentMarkedAt' => $appointment->absent_marked_at?->toIso8601String(),
        ];

        if ($appointment->relationLoaded('session') && $appointment->session) {
            $session = $appointment->session;
            $data['courseName'] = $session->course?->name;
            $data['startsAt'] = $session->starts_at?->toIso8601String();
            $data['endsAt'] = $session->ends_at?->toIso8601String();
            $data['coachName'] = $session->coach?->name;
        }

        return $data;
    }
}
