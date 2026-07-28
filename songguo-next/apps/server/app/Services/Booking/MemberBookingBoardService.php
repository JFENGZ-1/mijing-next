<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Support\AvatarUrl;

class MemberBookingBoardService
{
    private const BOOKED_AVATARS_LIMIT = 7;

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
        $sessionIds = $sessions->pluck('id')->all();
        $appointmentStatuses = $this->memberAppointmentStatuses($member, $sessionIds);
        $bookedAvatars = $this->bookedAvatarsBySession($member, $sessionIds);

        $items = $sessions
            ->map(fn (ScheduleSession $session) => $this->catalogItem(
                $site,
                $date,
                $session,
                $policy,
                $appointmentStatuses[$session->id] ?? null,
                $bookedAvatars[$session->id] ?? [],
            ))
            ->values()
            ->all();

        return [
            'date' => $date,
            'items' => $items,
            'limits' => $this->catalogLimits($site, $policy),
        ];
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array{catalogLastDate: string, groupLastBookableDate: string, privateLastBookableDate: string}
     */
    private function catalogLimits(Site $site, array $policy): array
    {
        $timezone = $site->timezone ?: (string) config('app.timezone');
        $today = now($timezone)->startOfDay();
        $catalogDays = (int) ($policy['group']['calendarDisplayDays'] ?? 7);
        $groupAdvance = $this->policies->effectiveAdvanceBookingDays(
            $site,
            (int) ($policy['group']['advanceBookingDays'] ?? 0),
            ScheduleSessionKind::Group->value,
            $policy,
        );
        $privateAdvance = (int) ($policy['private']['advanceBookingDays'] ?? 0);

        return [
            'catalogLastDate' => $today->copy()->addDays($catalogDays)->toDateString(),
            'groupLastBookableDate' => $today->copy()->addDays($groupAdvance)->toDateString(),
            'privateLastBookableDate' => $today->copy()->addDays($privateAdvance)->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionDetail(Site $site, ScheduleSession $session, Member $member): array
    {
        abort_unless($session->site_id === $site->id, 404);
        abort_unless($session->status === ScheduleSessionStatus::Scheduled, 404);

        $session->loadMissing(['course', 'coach.account', 'room']);
        $policy = $this->policies->policyForTenantSite($site->tenant_id, $site->id);
        $timezone = $site->timezone ?: (string) config('app.timezone');
        $date = $session->starts_at->timezone($timezone)->toDateString();
        $this->policies->assertMemberCatalogDateAllowed($site, $date, $policy);

        $appointmentStatuses = $this->memberAppointmentStatuses($member, [$session->id]);
        $bookedAvatars = $this->bookedAvatarsBySession($member, [$session->id]);

        return $this->sessionDetailItem(
            $site,
            $date,
            $session,
            $policy,
            $appointmentStatuses[$session->id] ?? null,
            $bookedAvatars[$session->id] ?? [],
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
        array $bookedAvatars = [],
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
            'coachStaffId' => $session->coach_staff_id,
            'coachAvatarUrl' => $session->coach?->account?->avatar_url,
            'capacity' => $session->capacity,
            'bookedCount' => $showBookedCount ? $session->booked_count : null,
            'bookedAvatars' => $showBookedCount ? $bookedAvatars : [],
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
        array $bookedAvatars = [],
    ): array {
        return [
            ...$this->catalogItem($site, $date, $session, $policy, $memberAppointmentStatus, $bookedAvatars),
            'siteId' => $session->site_id,
            'roomId' => $session->room_id,
            'roomName' => $session->room?->name,
            'coachStaffId' => $session->coach_staff_id,
            'durationMinutes' => $session->course?->duration_minutes,
            'description' => $session->course?->description,
        ];
    }

    /**
     * 每个 session 前若干名已预约会员的头像（对标原版课程卡头像列表）。
     *
     * @param  list<int>  $sessionIds
     * @return array<int, list<string|null>>
     */
    private function bookedAvatarsBySession(Member $member, array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereIn('session_id', $sessionIds)
            ->where('status', AppointmentStatus::Confirmed)
            ->orderBy('id')
            ->with('member.account.memberProfile')
            ->get(['id', 'tenant_id', 'session_id', 'member_id'])
            ->groupBy('session_id')
            ->map(
                fn ($appointments) => $appointments
                    ->take(self::BOOKED_AVATARS_LIMIT)
                    ->map(fn (Appointment $appointment) => AvatarUrl::fromObjectKey(
                        $appointment->member?->account?->memberProfile?->avatar_object_key,
                    ))
                    ->values()
                    ->all(),
            )
            ->all();
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
