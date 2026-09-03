<?php

namespace App\Services\Schedule;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Booking\AppointmentWriteService;
use App\Services\Booking\BookingPolicyService;
use App\Services\Booking\PrivateCoachAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleSessionWriteService
{
    public function __construct(
        private readonly AppointmentWriteService $appointments,
        private readonly PrivateCoachAvailabilityService $availability,
        private readonly BookingPolicyService $bookingPolicies,
    ) {}

    public function create(Staff $staff, Site $site, array $payload): ScheduleSession
    {
        return DB::transaction(function () use ($staff, $site, $payload) {
            $course = $this->assertCourse($staff->tenant_id, $site->id, $payload['courseId']);
            $this->assertRoom($staff->tenant_id, $site->id, $payload['roomId'] ?? null);
            $this->assertCoach($staff->tenant_id, $payload['coachStaffId']);
            $this->assertTimeRange($payload['startsAt'], $payload['endsAt']);
            $this->assertRoomConflict(
                $staff->tenant_id,
                $site->id,
                $payload['roomId'] ?? null,
                $payload['startsAt'],
                $payload['endsAt'],
            );

            $sessionKind = ScheduleSessionKind::from($payload['sessionKind']);

            return ScheduleSession::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'course_id' => $course->id,
                'room_id' => $payload['roomId'] ?? null,
                'coach_staff_id' => $payload['coachStaffId'],
                'starts_at' => $payload['startsAt'],
                'ends_at' => $payload['endsAt'],
                'capacity' => $payload['capacity'],
                'booked_count' => 0,
                'status' => ScheduleSessionStatus::Scheduled,
                'session_kind' => $sessionKind,
                'version' => 1,
                'created_by_staff_id' => $staff->id,
            ])->load(['course', 'room', 'coach']);
        });
    }

    public function update(ScheduleSession $session, array $payload): ScheduleSession
    {
        return DB::transaction(function () use ($session, $payload) {
            $hasConsumption = ConsumptionEvent::query()
                ->where('tenant_id', $session->tenant_id)
                ->where('session_id', $session->id)
                ->exists();
            if ($hasConsumption) {
                $changesFinancialContext = (array_key_exists('courseId', $payload) && (int) $payload['courseId'] !== (int) $session->course_id)
                    || (array_key_exists('coachStaffId', $payload) && (int) $payload['coachStaffId'] !== (int) $session->coach_staff_id)
                    || (array_key_exists('sessionKind', $payload) && (string) $payload['sessionKind'] !== $session->session_kind->value)
                    || (array_key_exists('startsAt', $payload) && ! $session->starts_at->equalTo(\Carbon\Carbon::parse($payload['startsAt'])))
                    || (array_key_exists('endsAt', $payload) && ! $session->ends_at->equalTo(\Carbon\Carbon::parse($payload['endsAt'])));
                abort_if($changesFinancialContext, 409, 'SCHEDULE_SESSION_FINANCIAL_CONTEXT_LOCKED');
            }
            // 对标原版：换课/换老师/修改时间在已有预约时仍允许（前端会做预检确认）。
            // 仅拦截会破坏既有预约结构的变更：容量小于已约人数、更改课程类型。
            if (array_key_exists('capacity', $payload) && (int) $payload['capacity'] < $session->booked_count) {
                abort(409, 'SCHEDULE_SESSION_UPDATE_BLOCKED');
            }
            if (array_key_exists('sessionKind', $payload)
                && $payload['sessionKind'] !== $session->session_kind->value
                && $session->booked_count > 0) {
                abort(409, 'SCHEDULE_SESSION_UPDATE_BLOCKED');
            }

            $startsAt = $payload['startsAt'] ?? $session->starts_at->toIso8601String();
            $endsAt = $payload['endsAt'] ?? $session->ends_at->toIso8601String();
            $roomId = array_key_exists('roomId', $payload) ? $payload['roomId'] : $session->room_id;

            if (array_key_exists('courseId', $payload)) {
                $this->assertCourse($session->tenant_id, $session->site_id, $payload['courseId']);
            }
            if (array_key_exists('roomId', $payload)) {
                $this->assertRoom($session->tenant_id, $session->site_id, $payload['roomId']);
            }
            if (array_key_exists('coachStaffId', $payload)) {
                $this->assertCoach($session->tenant_id, $payload['coachStaffId']);
            }
            $this->assertTimeRange($startsAt, $endsAt);
            $this->assertRoomConflict(
                $session->tenant_id,
                $session->site_id,
                $roomId,
                $startsAt,
                $endsAt,
                $session->id,
            );

            $coachStaffId = array_key_exists('coachStaffId', $payload)
                ? (int) $payload['coachStaffId']
                : (int) $session->coach_staff_id;
            $timeOrCoachChanged = array_key_exists('startsAt', $payload)
                || array_key_exists('endsAt', $payload)
                || array_key_exists('coachStaffId', $payload);
            if ($session->session_kind === ScheduleSessionKind::Private && $timeOrCoachChanged) {
                $this->assertPrivateCoachScheduleSlot(
                    $session->tenant_id,
                    $session->site_id,
                    $coachStaffId,
                    \Carbon\Carbon::parse($startsAt),
                    \Carbon\Carbon::parse($endsAt),
                    $session->id,
                    (bool) ($payload['acknowledgeGroupOverlap'] ?? false),
                );
            }

            $attributes = [];
            if (array_key_exists('courseId', $payload)) {
                $attributes['course_id'] = $payload['courseId'];
            }
            if (array_key_exists('roomId', $payload)) {
                $attributes['room_id'] = $payload['roomId'];
            }
            if (array_key_exists('coachStaffId', $payload)) {
                $attributes['coach_staff_id'] = $payload['coachStaffId'];
            }
            if (array_key_exists('startsAt', $payload)) {
                $attributes['starts_at'] = $payload['startsAt'];
            }
            if (array_key_exists('endsAt', $payload)) {
                $attributes['ends_at'] = $payload['endsAt'];
            }
            if (array_key_exists('capacity', $payload)) {
                $attributes['capacity'] = $payload['capacity'];
            }
            if (array_key_exists('sessionKind', $payload)) {
                $attributes['session_kind'] = ScheduleSessionKind::from($payload['sessionKind']);
            }
            if (array_key_exists('displayColor', $payload)) {
                $attributes['display_color'] = $payload['displayColor'];
            }

            $updated = ScheduleSession::query()
                ->whereKey($session->id)
                ->where('tenant_id', $session->tenant_id)
                ->where('version', $payload['version'])
                ->update([
                    ...$attributes,
                    'version' => DB::raw('version + 1'),
                ]);

            abort_if($updated !== 1, 409, 'SCHEDULE_SESSION_VERSION_CONFLICT');

            return $session->fresh()->load(['course', 'room', 'coach']);
        });
    }

    public function suspend(ScheduleSession $session, ?Staff $actor = null, bool $cascadeCancelAppointments = false): ScheduleSession
    {
        if ($session->status === ScheduleSessionStatus::Suspended) {
            return $session;
        }

        abort_if(
            in_array($session->status, [ScheduleSessionStatus::Cancelled, ScheduleSessionStatus::Completed], true),
            409,
            'SCHEDULE_SESSION_STATUS_CONFLICT',
        );

        // 对标原版：停课时强制取消已有会员预约（走正常退费链路）。
        if ($cascadeCancelAppointments && $actor !== null) {
            $this->cancelActiveAppointments($session, $actor);
        }

        $session->update(['status' => ScheduleSessionStatus::Suspended]);

        return $session->fresh()->load(['course', 'room', 'coach']);
    }

    public function cancel(ScheduleSession $session): ScheduleSession
    {
        if ($session->status === ScheduleSessionStatus::Cancelled) {
            return $session;
        }

        abort_if(
            $session->status === ScheduleSessionStatus::Completed,
            409,
            'SCHEDULE_SESSION_STATUS_CONFLICT',
        );

        // 对标原版删除拦截：已有会员预约（含候补）时禁止取消排课，需先手动取消约课。
        $hasActiveAppointments = Appointment::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('session_id', $session->id)
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Waitlisted->value])
            ->exists();
        abort_if($hasActiveAppointments, 409, 'SCHEDULE_SESSION_CANCEL_HAS_APPOINTMENTS');

        $session->update(['status' => ScheduleSessionStatus::Cancelled]);

        return $session->fresh()->load(['course', 'room', 'coach']);
    }

    private function cancelActiveAppointments(ScheduleSession $session, Staff $actor): void
    {
        // 先候补后正式，避免取消正式时触发候补递补。
        $active = Appointment::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('session_id', $session->id)
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Waitlisted->value])
            ->orderByRaw('case when status = ? then 0 else 1 end', [AppointmentStatus::Waitlisted->value])
            ->get();

        foreach ($active as $appointment) {
            $this->appointments->cancelForStaff($actor, $appointment, (string) Str::uuid());
        }
    }

    public function unsuspend(ScheduleSession $session): ScheduleSession
    {
        if ($session->status === ScheduleSessionStatus::Scheduled) {
            return $session->load(['course', 'room', 'coach']);
        }

        abort_unless($session->status === ScheduleSessionStatus::Suspended, 409, 'SCHEDULE_SESSION_STATUS_CONFLICT');

        $session->update(['status' => ScheduleSessionStatus::Scheduled]);

        return $session->fresh()->load(['course', 'room', 'coach']);
    }

    private function assertCourse(int $tenantId, int $siteId, int $courseId): Course
    {
        $course = Course::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->whereKey($courseId)
            ->where('catalog_status', CourseCatalogStatus::Active)
            ->first();

        abort_unless($course, 422, 'SCHEDULE_SESSION_COURSE_INVALID');

        return $course;
    }

    private function assertRoom(int $tenantId, int $siteId, ?int $roomId): void
    {
        if ($roomId === null) {
            return;
        }

        $exists = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->whereKey($roomId)
            ->where('catalog_status', CourseCatalogStatus::Active)
            ->exists();

        abort_unless($exists, 422, 'SCHEDULE_SESSION_ROOM_INVALID');
    }

    private function assertCoach(int $tenantId, int $coachStaffId): void
    {
        $exists = Staff::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($coachStaffId)
            ->where('status', 'active')
            ->exists();

        abort_unless($exists, 422, 'SCHEDULE_SESSION_COACH_INVALID');
    }

    private function assertTimeRange(string $startsAt, string $endsAt): void
    {
        abort_if(strtotime($endsAt) <= strtotime($startsAt), 422, 'SCHEDULE_SESSION_TIME_INVALID');
    }

    private function assertRoomConflict(
        int $tenantId,
        int $siteId,
        ?int $roomId,
        string $startsAt,
        string $endsAt,
        ?int $excludeSessionId = null,
    ): void {
        if ($roomId === null) {
            return;
        }

        $query = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('room_id', $roomId)
            ->whereIn('status', [
                ScheduleSessionStatus::Scheduled->value,
                ScheduleSessionStatus::Suspended->value,
            ])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeSessionId !== null) {
            $query->whereKeyNot($excludeSessionId);
        }

        abort_if($query->exists(), 409, 'SCHEDULE_SESSION_ROOM_CONFLICT');
    }

    private function assertPrivateCoachScheduleSlot(
        int $tenantId,
        int $siteId,
        int $coachStaffId,
        \Carbon\Carbon $startsAt,
        \Carbon\Carbon $endsAt,
        int $excludeSessionId,
        bool $allowGroupOverlapWarn,
    ): void {
        $overlaps = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('coach_staff_id', $coachStaffId)
            ->whereIn('status', [
                ScheduleSessionStatus::Scheduled->value,
                ScheduleSessionStatus::Suspended->value,
            ])
            ->whereKeyNot($excludeSessionId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->with(['appointments' => fn ($query) => $query->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
                AppointmentStatus::Waitlisted,
            ])])
            ->get();

        $policy = $this->bookingPolicies->policyForTenantSite($tenantId, $siteId);
        $this->availability->assertBookableSlot(
            $startsAt,
            $endsAt,
            $overlaps,
            $policy['private'],
            $allowGroupOverlapWarn,
        );
    }
}
