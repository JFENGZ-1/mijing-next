<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\ScheduleSession;
use App\Models\Site;

class MemberBookingBoardService
{
    public function __construct(
        private BookingPolicyService $policies,
        private BookingDayBoardQueryService $query,
    ) {}

    /**
     * @return array{date: string, items: list<array<string, mixed>>}
     */
    public function catalog(Site $site, string $date, Member $member): array
    {
        $policy = $this->policies->policyForTenantSite($site->tenant_id, $site->id);
        $this->policies->assertMemberCatalogDateAllowed($site, $date, $policy);

        $sessions = $this->query->memberCatalogSessions($site, $date);
        $appointmentStatuses = $this->memberAppointmentStatuses($member, $sessions->pluck('id')->all());

        $items = $sessions
            ->map(fn (ScheduleSession $session) => $this->catalogItem(
                $site,
                $date,
                $session,
                $policy,
                $appointmentStatuses[$session->id] ?? null,
            ))
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
    public function sessionDetail(Site $site, ScheduleSession $session, Member $member): array
    {
        abort_unless($session->site_id === $site->id, 404);
        abort_unless($session->status === ScheduleSessionStatus::Scheduled, 404);

        $session->loadMissing(['course', 'coach', 'room']);
        $policy = $this->policies->policyForTenantSite($site->tenant_id, $site->id);
        $timezone = $site->timezone ?: (string) config('app.timezone');
        $date = $session->starts_at->timezone($timezone)->toDateString();
        $this->policies->assertMemberCatalogDateAllowed($site, $date, $policy);

        $appointmentStatuses = $this->memberAppointmentStatuses($member, [$session->id]);

        return $this->sessionDetailItem(
            $site,
            $date,
            $session,
            $policy,
            $appointmentStatuses[$session->id] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function catalogItem(
        Site $site,
        string $date,
        ScheduleSession $session,
        array $policy,
        ?string $memberAppointmentStatus = null,
    ): array {
        $showBookedCount = $session->session_kind === ScheduleSessionKind::Group
            ? (bool) $policy['group']['showBookedCount']
            : true;
        $waitlistEnabled = $session->session_kind === ScheduleSessionKind::Group
            && (bool) $policy['group']['waitlistEnabled'];

        return [
            'id' => $session->id,
            'courseId' => $session->course_id,
            'courseName' => $session->course?->name,
            'startsAt' => $session->starts_at?->toIso8601String(),
            'endsAt' => $session->ends_at?->toIso8601String(),
            'coachName' => $session->coach?->name,
            'capacity' => $session->capacity,
            'bookedCount' => $showBookedCount ? $session->booked_count : null,
            'sessionKind' => $session->session_kind->value,
            'courseType' => $session->course?->course_type->value,
            'waitlistEnabled' => $waitlistEnabled,
            'bookable' => $this->policies->memberSessionBookableOnDate($site, $date, $session->session_kind->value, $policy),
            'memberAppointmentStatus' => $memberAppointmentStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function sessionDetailItem(
        Site $site,
        string $date,
        ScheduleSession $session,
        array $policy,
        ?string $memberAppointmentStatus = null,
    ): array {
        return [
            ...$this->catalogItem($site, $date, $session, $policy, $memberAppointmentStatus),
            'siteId' => $session->site_id,
            'roomId' => $session->room_id,
            'roomName' => $session->room?->name,
            'coachStaffId' => $session->coach_staff_id,
            'durationMinutes' => $session->course?->duration_minutes,
            'description' => $session->course?->description,
        ];
    }

    /**
     * @param  list<int>  $sessionIds
     * @return array<int, string>
     */
    private function memberAppointmentStatuses(Member $member, array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->whereIn('session_id', $sessionIds)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
            ->get(['session_id', 'status'])
            ->mapWithKeys(fn (Appointment $appointment) => [
                $appointment->session_id => $appointment->status->value,
            ])
            ->all();
    }
}
