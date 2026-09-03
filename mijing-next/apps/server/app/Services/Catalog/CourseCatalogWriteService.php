<?php

namespace App\Services\Catalog;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Room;
use App\Models\Site;
use App\Models\Staff;
use App\Support\DomainActor;
use Illuminate\Support\Facades\DB;

class CourseCatalogWriteService
{
    public function __construct(private CatalogCommandReceiptService $commands) {}

    public function create(Staff $staff, Site $site, array $payload): Course
    {
        return $this->createForActor(DomainActor::staff($staff), $site, $payload);
    }

    public function createForActor(DomainActor $actor, Site $site, array $payload): Course
    {
        return DB::transaction(function () use ($actor, $site, $payload) {
            Site::query()->where('tenant_id', $site->tenant_id)->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $commandKey = $payload['commandKey'] ?? null;
            $payloadHash = $this->commands->payloadHash($payload);
            if ($commandKey !== null) {
                $receipt = $this->commands->replay($site, 'course', 'create', $commandKey, $payloadHash);
                if ($receipt) {
                    return Course::query()
                        ->where('tenant_id', $site->tenant_id)
                        ->where('site_id', $site->id)
                        ->findOrFail($receipt->resource_id)
                        ->load(['defaultRoom', 'coach']);
                }
            }
            $this->assertDefaultRoom($site->tenant_id, $site->id, $payload['defaultRoomId'] ?? null);
            $this->assertCoach($site->tenant_id, $site->id, $payload['coachStaffId'] ?? null);

            $course = Course::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'created_by_staff_id' => $actor->staffId(),
                'catalog_status' => CourseCatalogStatus::Active,
                'version' => 1,
                ...$this->courseAttributes($payload),
            ]);
            if ($commandKey !== null) {
                $this->commands->record(
                    $actor, $site, 'course', $course->id, 'create', $commandKey,
                    $payloadHash, $course->version, $payload['reason'] ?? null,
                );
            }

            return $course->load(['defaultRoom', 'coach']);
        });
    }

    public function update(Course $course, array $payload): Course
    {
        $site = Site::query()->where('tenant_id', $course->tenant_id)->findOrFail($course->site_id);

        return $this->updateForActor(DomainActor::system(), $site, $course, $payload);
    }

    public function updateForActor(DomainActor $actor, Site $site, Course $course, array $payload): Course
    {
        abort_unless($course->tenant_id === $site->tenant_id && $course->site_id === $site->id, 404);

        return DB::transaction(function () use ($actor, $site, $course, $payload) {
            $locked = Course::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($course->id)
                ->lockForUpdate()
                ->firstOrFail();
            $commandKey = $payload['commandKey'] ?? null;
            $payloadHash = $this->commands->payloadHash($payload);
            if ($commandKey !== null) {
                $receipt = $this->commands->replay($site, 'course', 'update', $commandKey, $payloadHash);
                if ($receipt) {
                    abort_unless((int) $receipt->resource_id === $locked->id, 409, 'IDEMPOTENCY_KEY_REUSED');

                    return $locked->load(['defaultRoom', 'coach']);
                }
            }
            if (array_key_exists('defaultRoomId', $payload)) {
                $this->assertDefaultRoom($locked->tenant_id, $locked->site_id, $payload['defaultRoomId']);
            }
            if (array_key_exists('coachStaffId', $payload)) {
                $this->assertCoach($locked->tenant_id, $locked->site_id, $payload['coachStaffId']);
            }

            $updated = Course::query()
                ->whereKey($locked->id)
                ->where('tenant_id', $locked->tenant_id)
                ->where('version', $payload['version'])
                ->update([
                    ...$this->courseAttributes($payload, $locked),
                    'version' => DB::raw('version + 1'),
                ]);

            abort_if($updated !== 1, 409, 'COURSE_CATALOG_VERSION_CONFLICT');

            $fresh = $locked->fresh()->load(['defaultRoom', 'coach']);
            if ($commandKey !== null) {
                $this->commands->record(
                    $actor, $site, 'course', $fresh->id, 'update', $commandKey,
                    $payloadHash, $fresh->version, $payload['reason'] ?? null,
                );
            }

            return $fresh;
        });
    }

    public function archive(Course $course): Course
    {
        $site = Site::query()->where('tenant_id', $course->tenant_id)->findOrFail($course->site_id);

        return $this->archiveForActor(DomainActor::system(), $site, $course, $course->version);
    }

    public function archiveForActor(
        DomainActor $actor,
        Site $site,
        Course $course,
        int $expectedVersion,
        ?string $commandKey = null,
        ?string $reason = null,
    ): Course {
        return $this->changeStatus(
            $actor, $site, $course, CourseCatalogStatus::Archived,
            $expectedVersion, $commandKey, $reason,
        );
    }

    public function restore(Course $course): Course
    {
        $site = Site::query()->where('tenant_id', $course->tenant_id)->findOrFail($course->site_id);

        return $this->restoreForActor(DomainActor::system(), $site, $course, $course->version);
    }

    public function restoreForActor(
        DomainActor $actor,
        Site $site,
        Course $course,
        int $expectedVersion,
        ?string $commandKey = null,
        ?string $reason = null,
    ): Course {
        return $this->changeStatus(
            $actor, $site, $course, CourseCatalogStatus::Active,
            $expectedVersion, $commandKey, $reason,
        );
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
        if (array_key_exists('faceStyle', $payload)) {
            $attributes['face_style'] = $payload['faceStyle'];
        }
        if (array_key_exists('displayColor', $payload)) {
            $attributes['display_color'] = $payload['displayColor'];
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

    private function assertCoach(int $tenantId, int $siteId, ?int $coachStaffId): void
    {
        if ($coachStaffId === null) {
            return;
        }

        $exists = Staff::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($coachStaffId)
            ->where('status', 'active')
            ->whereHas('sites', fn ($query) => $query->whereKey($siteId))
            ->exists();

        abort_unless($exists, 422, 'COURSE_COACH_INVALID');
    }

    private function changeStatus(
        DomainActor $actor,
        Site $site,
        Course $course,
        CourseCatalogStatus $status,
        int $expectedVersion,
        ?string $commandKey,
        ?string $reason,
    ): Course {
        abort_unless($course->tenant_id === $site->tenant_id && $course->site_id === $site->id, 404);

        return DB::transaction(function () use ($actor, $site, $course, $status, $expectedVersion, $commandKey, $reason) {
            $locked = Course::query()->where('tenant_id', $site->tenant_id)->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $action = $status === CourseCatalogStatus::Archived ? 'archive' : 'restore';
            $payloadHash = $this->commands->payloadHash(['expectedVersion' => $expectedVersion, 'reason' => $reason]);
            if ($commandKey !== null) {
                $receipt = $this->commands->replay($site, 'course', $action, $commandKey, $payloadHash);
                if ($receipt) {
                    abort_unless((int) $receipt->resource_id === $locked->id, 409, 'IDEMPOTENCY_KEY_REUSED');

                    return $locked->load(['defaultRoom', 'coach']);
                }
            }
            if ($locked->catalog_status === $status) {
                abort_if($commandKey !== null, 409, 'CATALOG_STATUS_ALREADY_CHANGED');

                return $locked->load(['defaultRoom', 'coach']);
            }
            $updated = Course::query()
                ->whereKey($locked->id)
                ->where('version', $expectedVersion)
                ->update([
                    'catalog_status' => $status,
                    'archived_at' => $status === CourseCatalogStatus::Archived ? now() : null,
                    'version' => DB::raw('version + 1'),
                ]);
            abort_unless($updated === 1, 409, 'COURSE_CATALOG_VERSION_CONFLICT');
            $fresh = $locked->fresh()->load(['defaultRoom', 'coach']);
            if ($commandKey !== null) {
                $this->commands->record(
                    $actor, $site, 'course', $fresh->id, $action, $commandKey,
                    $payloadHash, $fresh->version, $reason,
                );
            }

            return $fresh;
        });
    }
}
