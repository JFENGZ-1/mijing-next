<?php

namespace App\Services\Booking;

use App\Models\ScheduleSession;
use App\Models\Site;

class StaffBookingDailyBoardService
{
    public function __construct(private BookingDayBoardQueryService $query) {}

    /**
     * @return array{date: string, items: list<array<string, mixed>>}
     */
    public function board(Site $site, string $date): array
    {
        $items = $this->query->staffDailyBoardSessions($site, $date)
            ->map(fn (ScheduleSession $session) => $this->boardItem($session))
            ->values()
            ->all();

        return [
            'date' => $date,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boardItem(ScheduleSession $session): array
    {
        return [
            'id' => $session->id,
            'courseId' => $session->course_id,
            'courseName' => $session->course?->name,
            'courseType' => $session->course?->course_type->value,
            'roomId' => $session->room_id,
            'roomName' => $session->room?->name,
            'coachStaffId' => $session->coach_staff_id,
            'coachName' => $session->coach?->name,
            'startsAt' => $session->starts_at?->toIso8601String(),
            'endsAt' => $session->ends_at?->toIso8601String(),
            'capacity' => $session->capacity,
            'bookedCount' => $session->booked_count,
            'status' => $session->status->value,
            'sessionKind' => $session->session_kind->value,
        ];
    }
}
