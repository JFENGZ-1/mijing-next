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

class StaffBookingDailyBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_daily_board_returns_site_sessions_for_date(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBoardStaff(['booking.staff-daily-board.read']);
        $coach = $this->createCoach($site);
        $date = now()->addDay()->toDateString();
        $scheduled = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));
        $suspended = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(12, 0), now()->addDay()->setTime(13, 0), ScheduleSessionStatus::Suspended);

        $query = http_build_query(['date' => $date]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/booking/daily-board?{$query}")
            ->assertOk()
            ->assertJsonPath('data.date', $date)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $scheduled->id)
            ->assertJsonPath('data.items.0.roomName', 'A教室')
            ->assertJsonPath('data.items.0.coachName', '李教练')
            ->assertJsonPath('data.items.0.courseType', CourseType::Group->value)
            ->assertJsonPath('data.items.0.status', 'scheduled')
            ->assertJsonPath('data.items.1.id', $suspended->id)
            ->assertJsonPath('data.items.1.status', 'suspended');
    }

    public function test_schedule_session_read_permission_also_grants_daily_board_access(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBoardStaff(['schedule.session.read']);
        $coach = $this->createCoach($site);
        $date = now()->addDay()->toDateString();
        $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        $query = http_build_query(['date' => $date]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/booking/daily-board?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_staff_without_board_permission_is_denied(): void
    {
        [$staff, $site] = $this->actAsBoardStaff(['crm.member.read']);
        $date = now()->addDay()->toDateString();
        $query = http_build_query(['date' => $date]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/booking/daily-board?{$query}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_daily_board_is_isolated_by_tenant_and_site(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBoardStaff(['booking.staff-daily-board.read']);
        $coach = $this->createCoach($site);
        $date = now()->addDay()->toDateString();
        $visible = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-board']);
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
        $this->createSession($otherSite, $otherCourse, $otherCoach, null, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        $query = http_build_query(['date' => $date]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/booking/daily-board?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $visible->id);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/booking/daily-board?{$query}")->assertNotFound();
    }

    private function actAsBoardStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $room = Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => 'A教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        return [$staff, $site, $room, $groupCourse];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Board Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Board Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Board', 'code' => 'board', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
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
        ScheduleSessionStatus $status = ScheduleSessionStatus::Scheduled,
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
            'status' => $status,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }
}
