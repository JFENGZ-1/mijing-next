<?php

namespace Tests\Feature;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffScheduleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_group_and_private_sessions(): void
    {
        [$staff, $site, $room, $groupCourse, $privateCourse] = $this->actAsScheduleStaff();
        $coach = $this->createCoach($site);

        $group = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions", [
            'courseId' => $groupCourse->id,
            'roomId' => $room->id,
            'coachStaffId' => $coach->id,
            'startsAt' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'endsAt' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'capacity' => 12,
            'sessionKind' => 'group',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sessionKind', 'group')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.bookedCount', 0)
            ->assertJsonPath('data.version', 1);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions", [
            'courseId' => $privateCourse->id,
            'coachStaffId' => $coach->id,
            'startsAt' => now()->addDays(2)->setTime(14, 0)->toIso8601String(),
            'endsAt' => now()->addDays(2)->setTime(15, 0)->toIso8601String(),
            'capacity' => 1,
            'sessionKind' => 'private',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sessionKind', 'private')
            ->assertJsonPath('data.roomId', null);

        $this->assertDatabaseHas('schedule_sessions', [
            'id' => $group->json('data.id'),
            'session_kind' => ScheduleSessionKind::Group->value,
            'status' => ScheduleSessionStatus::Scheduled->value,
        ]);
    }

    public function test_staff_can_list_sessions_by_date_range(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsScheduleStaff();
        $coach = $this->createCoach($site);

        $inRange = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(9, 0), now()->addDay()->setTime(10, 0));
        $this->createSession($site, $groupCourse, $coach, $room, now()->addDays(10)->setTime(9, 0), now()->addDays(10)->setTime(10, 0));

        $from = now()->startOfDay()->toIso8601String();
        $to = now()->addDays(7)->startOfDay()->toIso8601String();
        $query = http_build_query(['from' => $from, 'to' => $to]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $inRange->id);
    }

    public function test_staff_can_suspend_and_cancel_sessions(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsScheduleStaff();
        $coach = $this->createCoach($site);
        $session = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_staff_can_update_session_with_optimistic_version(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsScheduleStaff();
        $coach = $this->createCoach($site);
        $session = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        $this->putJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}", [
            'version' => 1,
            'capacity' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('data.capacity', 15)
            ->assertJsonPath('data.version', 2);
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site, $room, $groupCourse] = $this->actAsScheduleStaff(['crm.member.read']);
        $coach = $this->createCoach($site);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions?".http_build_query([
            'from' => now()->toIso8601String(),
            'to' => now()->addDay()->toIso8601String(),
        ]))
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions", [
            'courseId' => $groupCourse->id,
            'roomId' => $room->id,
            'coachStaffId' => $coach->id,
            'startsAt' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'endsAt' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'capacity' => 12,
            'sessionKind' => 'group',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_sessions_are_isolated_by_tenant_and_site(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsScheduleStaff();
        $coach = $this->createCoach($site);
        $visible = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherCourse = Course::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'course_type' => CourseType::Group,
            'name' => '外馆课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $otherCoach = Staff::create([
            'tenant_id' => $otherTenant->id,
            'account_id' => Account::create(['display_name' => 'Other Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '外馆教练',
            'status' => 'active',
        ]);
        $otherSession = ScheduleSession::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'course_id' => $otherCourse->id,
            'coach_staff_id' => $otherCoach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 10,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
        ]);

        $from = now()->startOfDay()->toIso8601String();
        $to = now()->addDays(7)->startOfDay()->toIso8601String();
        $query = http_build_query(['from' => $from, 'to' => $to]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $visible->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$otherSession->id}")->assertNotFound();
    }

    public function test_cross_site_course_and_room_are_rejected(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsScheduleStaff();
        $coach = $this->createCoach($site);

        $branchSite = Site::create(['tenant_id' => $staff->tenant_id, 'name' => 'Branch', 'code' => 'branch', 'status' => 'active']);
        $branchRoom = Room::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $branchSite->id,
            'name' => '分店教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $branchCourse = Course::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $branchSite->id,
            'course_type' => CourseType::Group,
            'name' => '分店课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $payload = [
            'coachStaffId' => $coach->id,
            'startsAt' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'endsAt' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'capacity' => 12,
            'sessionKind' => 'group',
        ];

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions", [
            ...$payload,
            'courseId' => $branchCourse->id,
            'roomId' => $room->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SCHEDULE_SESSION_COURSE_INVALID');

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions", [
            ...$payload,
            'courseId' => $groupCourse->id,
            'roomId' => $branchRoom->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SCHEDULE_SESSION_ROOM_INVALID');
    }

    public function test_room_overlap_is_rejected(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsScheduleStaff();
        $coach = $this->createCoach($site);

        $startsAt = now()->addDay()->setTime(10, 0);
        $endsAt = now()->addDay()->setTime(11, 0);
        $this->createSession($site, $groupCourse, $coach, $room, $startsAt, $endsAt);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions", [
            'courseId' => $groupCourse->id,
            'roomId' => $room->id,
            'coachStaffId' => $coach->id,
            'startsAt' => $startsAt->copy()->addMinutes(30)->toIso8601String(),
            'endsAt' => $endsAt->copy()->addMinutes(30)->toIso8601String(),
            'capacity' => 12,
            'sessionKind' => 'group',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'SCHEDULE_SESSION_ROOM_CONFLICT');
    }

    private function actAsScheduleStaff(array $permissions = ['schedule.session.read', 'schedule.session.write']): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $room = $this->createRoom($site, 'A教室');
        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'max_capacity' => 12,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $privateCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Private,
            'name' => '私教课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        return [$staff, $site, $room, $groupCourse, $privateCourse];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Schedule Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Schedule Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Schedule', 'code' => 'schedule', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'schedule']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createRoom(Site $site, string $name): Room
    {
        return Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => $name,
            'catalog_status' => CourseCatalogStatus::Active,
            'sort_order' => 0,
            'version' => 1,
        ]);
    }

    private function createCoach(Site $site): Staff
    {
        return Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
    }

    private function createSession(
        Site $site,
        Course $course,
        Staff $coach,
        ?Room $room,
        $startsAt,
        $endsAt,
    ): ScheduleSession {
        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room?->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => 12,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }
}
