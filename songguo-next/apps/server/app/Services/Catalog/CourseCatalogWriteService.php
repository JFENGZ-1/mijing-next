<?php

namespace App\Services\Catalog;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Room;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourseCatalogWriteService
{
    public function create(Staff $staff, Site $site, array $payload): Course
    {
        return DB::transaction(function () use ($staff, $site, $payload) {
            $this->assertDefaultRoom($staff->tenant_id, $site->id, $payload['defaultRoomId'] ?? null);
            $this->assertCoach($staff->tenant_id, $payload['coachStaffId'] ?? null);

            return Course::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'created_by_staff_id' => $staff->id,
                'catalog_status' => CourseCatalogStatus::Active,
                'version' => 1,
                ...$this->courseAttributes($payload),
            ])->load(['defaultRoom', 'coach']);
        });
    }

    public function update(Course $course, array $payload): Course
    {
        return DB::transaction(function () use ($course, $payload) {
            if (array_key_exists('defaultRoomId', $payload)) {
                $this->assertDefaultRoom($course->tenant_id, $course->site_id, $payload['defaultRoomId']);
            }
            if (array_key_exists('coachStaffId', $payload)) {
                $this->assertCoach($course->tenant_id, $payload['coachStaffId']);
            }

            $updated = Course::query()
                ->whereKey($course->id)
                ->where('tenant_id', $course->tenant_id)
                ->where('version', $payload['version'])
                ->update([
                    ...$this->courseAttributes($payload, $course),
                    'version' => DB::raw('version + 1'),
                ]);

            abort_if($updated !== 1, 409, 'COURSE_CATALOG_VERSION_CONFLICT');

            return $course->fresh()->load(['defaultRoom', 'coach']);
        });
    }

    public function archive(Course $course): Course
    {
        $this->assertArchiveAllowed($course);

        if ($course->catalog_status === CourseCatalogStatus::Archived) {
            return $course;
        }

        $course->update([
            'catalog_status' => CourseCatalogStatus::Archived,
            'archived_at' => now(),
        ]);

        return $course->fresh()->load(['defaultRoom', 'coach']);
    }

    public function restore(Course $course): Course
    {
        if ($course->catalog_status === CourseCatalogStatus::Active) {
            return $course;
        }

        $course->update([
            'catalog_status' => CourseCatalogStatus::Active,
            'archived_at' => null,
        ]);

        return $course->fresh()->load(['defaultRoom', 'coach']);
    }

    public function assertPhysicalDeleteForbidden(Course $course): void
    {
        abort(409, 'COURSE_CATALOG_DELETE_FORBIDDEN');
    }

    private function courseAttributes(array $payload, ?Course $existing = null): array
    {
        $attributes = [];

        if (isset($payload['courseType']) || $existing === null) {
            $attributes['course_type'] = CourseType::from($payload['courseType'] ?? $existing?->course_type->value);
        }
        if (array_key_exists('name', $payload)) {
            $attributes['name'] = $payload['name'];
        }
        if (array_key_exists('description', $payload)) {
            $attributes['description'] = $payload['description'];
        }
        if (array_key_exists('durationMinutes', $payload)) {
            $attributes['duration_minutes'] = $payload['durationMinutes'];
        }
        if (array_key_exists('difficulty', $payload)) {
            $attributes['difficulty'] = $payload['difficulty'];
        }
        if (array_key_exists('minCapacity', $payload)) {
            $attributes['min_capacity'] = $payload['minCapacity'];
        }
        if (array_key_exists('maxCapacity', $payload)) {
            $attributes['max_capacity'] = $payload['maxCapacity'];
        }
        if (array_key_exists('defaultRoomId', $payload)) {
            $attributes['default_room_id'] = $payload['defaultRoomId'];
        }
        if (array_key_exists('coachStaffId', $payload)) {
            $attributes['coach_staff_id'] = $payload['coachStaffId'];
        }
        if (array_key_exists('tags', $payload)) {
            $attributes['tags'] = $payload['tags'];
        }
        if (array_key_exists('sortOrder', $payload)) {
            $attributes['sort_order'] = $payload['sortOrder'];
        }

        return $attributes;
    }

    private function assertDefaultRoom(int $tenantId, int $siteId, ?int $roomId): void
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

        abort_unless($exists, 422, 'COURSE_DEFAULT_ROOM_INVALID');
    }

    private function assertCoach(int $tenantId, ?int $coachStaffId): void
    {
        if ($coachStaffId === null) {
            return;
        }

        $exists = Staff::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($coachStaffId)
            ->where('status', 'active')
            ->exists();

        abort_unless($exists, 422, 'COURSE_COACH_INVALID');
    }

    private function assertArchiveAllowed(Course $course): void
    {
        if (! Schema::hasTable('schedule_sessions')) {
            return;
        }

        $hasSessions = DB::table('schedule_sessions')
            ->where('tenant_id', $course->tenant_id)
            ->where('course_id', $course->id)
            ->exists();

        abort_if($hasSessions, 409, 'COURSE_CATALOG_ARCHIVE_BLOCKED');
    }
}
