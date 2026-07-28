<?php

namespace App\Services\Booking;

use App\Enums\ScheduleSessionKind;
use App\Models\CoachPrivateProfile;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Services\Catalog\CoachPrivateProfileService;
use Illuminate\Support\Facades\DB;

class MemberPrivateCoachBookingService
{
    public function __construct(
        private readonly CoachPrivateProfileService $profiles,
        private readonly BookingPolicyService $policies,
        private readonly BookingPayableCardService $payableCards,
        private readonly AppointmentWriteService $appointments,
    ) {}

    public function profile(Site $site, int $coachStaffId): array
    {
        $profile = $this->findProfile($site, $coachStaffId);

        return $this->profiles->memberPresentation($profile);
    }

    /**
     * @return array<string, mixed>
     */
    public function timeSlots(Site $site, int $coachStaffId, string $date, int $courseId = 0): array
    {
        $profile = $this->findProfile($site, $coachStaffId);
        $policy = $this->policies->policyForTenantSite($site->tenant_id, $site->id);
        $this->policies->assertMemberCatalogDateAllowed($site, $date, $policy);
        abort_unless(
            $this->policies->memberSessionBookableOnDate($site, $date, ScheduleSessionKind::Private->value, $policy),
            422,
            'BOOKING_DATE_OUT_OF_ADVANCE_WINDOW',
        );

        $result = $this->profiles->timeSlots($site, $profile, $date, $courseId);
        $result['slots'] = $this->applyMemberSlotRules($site, $policy, $result['slots'], (bool) $result['grayOutBookedSlots']);
        $result['limits'] = [
            'privateLastBookableDate' => now($this->siteTimezone($site))->startOfDay()
                ->addDays((int) $policy['private']['advanceBookingDays'])
                ->toDateString(),
        ];

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payableCards(
        Site $site,
        Member $member,
        int $coachStaffId,
        string $date,
        string $start,
        int $courseId = 0,
    ): array {
        $session = $this->previewSession($site, $coachStaffId, $date, $start, $courseId);

        return $this->payableCards->payableCardsForSession($member, $session);
    }

    /**
     * @return array{appointment: array<string, mixed>, sessionId: int}
     */
    public function book(
        Site $site,
        Member $member,
        int $accountId,
        int $coachStaffId,
        int $memberCardId,
        string $date,
        string $start,
        string $commandKey,
        int $courseId = 0,
        ?string $remark = null,
    ): array {
        $profile = $this->findProfile($site, $coachStaffId);
        $policy = $this->policies->policyForTenantSite($site->tenant_id, $site->id);
        $this->policies->assertMemberCatalogDateAllowed($site, $date, $policy);
        abort_unless(
            $this->policies->memberSessionBookableOnDate($site, $date, ScheduleSessionKind::Private->value, $policy),
            422,
            'BOOKING_DATE_OUT_OF_ADVANCE_WINDOW',
        );

        $payload = [
            'date' => $date,
            'start' => $start,
            'courseId' => $courseId > 0 ? $courseId : null,
            'remark' => $remark,
            'acknowledgeGroupOverlap' => false,
        ];

        return DB::transaction(function () use ($site, $member, $accountId, $profile, $payload, $memberCardId, $commandKey) {
            $session = $this->profiles->resolveBookableSession(null, $site, $profile, $payload);
            $card = $this->payableCards->resolvePayableCard($member, $session, $memberCardId);
            $result = $this->appointments->createForMember($member, $session, $card, $commandKey, $accountId);

            return [
                'appointment' => AppointmentPresenter::toArray($result['appointment']),
                'sessionId' => $session->id,
            ];
        });
    }

    private function findProfile(Site $site, int $coachStaffId): CoachPrivateProfile
    {
        $profile = CoachPrivateProfile::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('coach_staff_id', $coachStaffId)
            ->first();

        abort_unless($profile !== null, 404, 'NOT_FOUND');

        return $profile;
    }

    private function previewSession(Site $site, int $coachStaffId, string $date, string $start, int $courseId): ScheduleSession
    {
        $profile = $this->findProfile($site, $coachStaffId);
        $course = $this->resolveCourse($profile, $courseId);
        $startsAt = \Carbon\Carbon::parse($date.' '.$start.':00');
        $endsAt = $startsAt->copy()->addMinutes(max(1, (int) $course->duration_minutes));

        $session = new ScheduleSession([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $profile->coach_staff_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => 1,
            'booked_count' => 0,
            'session_kind' => ScheduleSessionKind::Private,
        ]);
        $session->setRelation('course', $course);

        return $session;
    }

    private function resolveCourse(CoachPrivateProfile $profile, int $courseId): Course
    {
        if ($courseId > 0) {
            $course = Course::query()
                ->where('tenant_id', $profile->tenant_id)
                ->where('site_id', $profile->site_id)
                ->whereKey($courseId)
                ->first();
            abort_unless($course !== null, 422, 'COACH_PRIVATE_SUBJECT_REQUIRED');

            return $course;
        }

        $course = Course::query()->whereKey($profile->uniform_course_id)->first();
        abort_unless($course !== null, 422, 'COACH_PRIVATE_SUBJECT_REQUIRED');

        return $course;
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     * @return list<array<string, mixed>>
     */
    private function applyMemberSlotRules(Site $site, array $policy, array $slots, bool $grayOutBookedSlots): array
    {
        $timezone = $this->siteTimezone($site);
        $now = now($timezone);
        $minLead = (int) ($policy['private']['minimumLeadMinutes'] ?? 0);
        $filtered = [];

        foreach ($slots as $slot) {
            if (! empty($slot['groupOverlapWarn'])) {
                $slot['available'] = false;
            }
            unset($slot['groupOverlapWarn']);
            if (! ($slot['available'] ?? false)) {
                if ($grayOutBookedSlots) {
                    $filtered[] = $slot;
                }
                continue;
            }

            $starts = \Carbon\Carbon::parse($slot['startsAt'])->timezone($timezone);
            if ($now->gte($starts->copy()->subMinutes($minLead))) {
                $slot['available'] = false;
                if ($grayOutBookedSlots) {
                    $filtered[] = $slot;
                }
                continue;
            }

            $filtered[] = $slot;
        }

        return $filtered;
    }

    private function siteTimezone(Site $site): string
    {
        return $site->timezone ?: (string) config('app.timezone');
    }
}
