<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ScheduleSession;
use App\Models\Site;
use Illuminate\Support\Collection;

class StaffBookingDailyBoardService
{
    public function __construct(private BookingDayBoardQueryService $query) {}

    /**
     * @return array{date: string, items: list<array<string, mixed>>}
     */
    public function board(Site $site, string $date): array
    {
        $sessions = $this->query->staffDailyBoardSessions($site, $date);

        // 批量取各节课的预约会员（前排头像墙）与候补数，避免 N+1
        $appointmentsBySession = Appointment::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->whereIn('session_id', $sessions->pluck('id'))
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
            ->with('member.crmProfile:member_id,name')
            ->orderBy('id')
            ->get(['id', 'session_id', 'member_id', 'status'])
            ->groupBy('session_id');

        $items = $sessions
            ->map(fn (ScheduleSession $session) => $this->boardItem(
                $session,
                $appointmentsBySession->get($session->id) ?? collect(),
            ))
            ->values()
            ->all();

        return [
            'date' => $date,
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return array<string, mixed>
     */
    private function boardItem(ScheduleSession $session, Collection $appointments): array
    {
        $confirmed = $appointments->filter(fn (Appointment $item) => $item->status === AppointmentStatus::Confirmed);
        $waitlisted = $appointments->filter(fn (Appointment $item) => $item->status === AppointmentStatus::Waitlisted);

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
            // 课程卡背景图案（平台图案库）
            'courseFaceStyle' => $session->course?->face_style,
            'courseFaceGradient' => app(\App\Services\Cards\CardFaceLibraryService::class)
                ->gradientFor($session->course?->face_style),
            // 预约会员头像墙（对标原版 userlist，前 7 位）
            'attendees' => $confirmed
                ->take(7)
                ->map(fn (Appointment $item) => [
                    'memberId' => $item->member_id,
                    'name' => $item->member?->crmProfile?->name,
                ])
                ->values()
                ->all(),
            'waitlistCount' => $waitlisted->count(),
        ];
    }
}
