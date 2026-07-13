<?php

namespace App\Services\Schedule;

use App\Enums\CourseCatalogStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Course;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class ScheduleSessionWriteService
{
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
            abort_if($session->booked_count > 0, 409, 'SCHEDULE_SESSION_UPDATE_BLOCKED');

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

    public function suspend(ScheduleSession $session): ScheduleSession
    {
        if ($session->status === ScheduleSessionStatus::Suspended) {
            return $session;
        }

        abort_if(
            in_array($session->status, [ScheduleSessionStatus::Cancelled, ScheduleSessionStatus::Completed], true),
            409,
            'SCHEDULE_SESSION_STATUS_CONFLICT',
        );

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

        $session->update(['status' => ScheduleSessionStatus::Cancelled]);

        return $session->fresh()->load(['course', 'room', 'coach']);
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
}
