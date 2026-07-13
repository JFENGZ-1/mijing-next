<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffReportCourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_summary_aggregates_match_seed_sessions(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.course.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Course Member');
        $groupCourse = $this->createCourse($site, $staff, CourseType::Group, '团课瑜伽');
        $privateCourse = $this->createCourse($site, $staff, CourseType::Private, '私教塑形');

        $heldGroup = $this->createSession(
            $site,
            $groupCourse,
            $staff,
            now()->startOfMonth()->addDays(3)->setTime(10, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Completed,
        );
        $this->createAppointment($site, $heldGroup, $member, AppointmentStatus::Completed);
        $this->createAppointment($site, $heldGroup, $this->createMemberAtSite($staff->tenant_id, $site, 'Guest'), AppointmentStatus::Completed);

        $scheduledGroup = $this->createSession(
            $site,
            $groupCourse,
            $staff,
            now()->startOfMonth()->addDays(5)->setTime(18, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Scheduled,
        );
        $this->createAppointment($site, $scheduledGroup, $member, AppointmentStatus::Confirmed);

        $privateSession = $this->createSession(
            $site,
            $privateCourse,
            $staff,
            now()->startOfMonth()->addDays(7)->setTime(14, 0),
            ScheduleSessionKind::Private,
            ScheduleSessionStatus::Scheduled,
        );
        $this->createAppointment($site, $privateSession, $member, AppointmentStatus::Completed);

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/summary")
            ->assertOk();

        $currentYear = collect($response->json('data.years'))->firstWhere('year', now()->year);
        $this->assertNotNull($currentYear);
        $this->assertTrue($currentYear['isCurrentYear']);
        $this->assertSame(2, $currentYear['groupScheduledCount']);
        $this->assertSame(1, $currentYear['groupHeldCount']);
        $this->assertSame(2, $currentYear['groupSignInCount']);
        $this->assertSame(1, $currentYear['privateSessionCount']);

        $currentMonth = collect($currentYear['months'])->firstWhere('month', now()->month);
        $this->assertSame(2, $currentMonth['groupScheduledCount']);
        $this->assertSame(1, $currentMonth['groupHeldCount']);
        $this->assertSame(2, $currentMonth['groupSignInCount']);
        $this->assertSame(1, $currentMonth['privateSessionCount']);
    }

    public function test_course_calendar_returns_twelve_month_rollups_for_year(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.course.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Calendar Member');
        $groupCourse = $this->createCourse($site, $staff, CourseType::Group, '团课燃脂');

        $marchSession = $this->createSession(
            $site,
            $groupCourse,
            $staff,
            now()->setMonth(3)->startOfMonth()->addDays(2)->setTime(9, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Completed,
        );
        $this->createAppointment($site, $marchSession, $member, AppointmentStatus::Completed);

        $aprilSession = $this->createSession(
            $site,
            $groupCourse,
            $staff,
            now()->setMonth(4)->startOfMonth()->addDays(1)->setTime(9, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Scheduled,
        );
        $this->createAppointment($site, $aprilSession, $member, AppointmentStatus::Confirmed);

        $year = now()->year;
        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/calendar?year={$year}")
            ->assertOk()
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.totals.groupScheduledCount', 2)
            ->assertJsonPath('data.totals.groupHeldCount', 1)
            ->assertJsonPath('data.totals.groupSignInCount', 1);

        $months = $response->json('data.months');
        $this->assertCount(now()->month, $months);
        $this->assertSame(1, collect($months)->firstWhere('month', 3)['groupHeldCount']);
        $this->assertSame(1, collect($months)->firstWhere('month', 4)['groupScheduledCount']);
        $this->assertSame(0, collect($months)->firstWhere('month', 1)['groupScheduledCount']);
    }

    public function test_course_daily_returns_day_breakdown_and_course_kind_filter(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.course.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Daily Member');
        $groupCourse = $this->createCourse($site, $staff, CourseType::Group, '团课拉伸');
        $privateCourse = $this->createCourse($site, $staff, CourseType::Private, '私教康复');

        $dayOne = now()->startOfMonth()->addDays(1);
        $dayTwo = now()->startOfMonth()->addDays(2);

        $groupSession = $this->createSession(
            $site,
            $groupCourse,
            $staff,
            $dayOne->copy()->setTime(10, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Completed,
        );
        $this->createAppointment($site, $groupSession, $member, AppointmentStatus::Completed);

        $privateSession = $this->createSession(
            $site,
            $privateCourse,
            $staff,
            $dayTwo->copy()->setTime(15, 0),
            ScheduleSessionKind::Private,
            ScheduleSessionStatus::Scheduled,
        );
        $this->createAppointment($site, $privateSession, $member, AppointmentStatus::Confirmed);

        $year = now()->year;
        $month = now()->month;

        $allResponse = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/daily?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.courseKind', 'all')
            ->assertJsonPath('data.totals.groupHeldCount', 1)
            ->assertJsonPath('data.totals.privateSessionCount', 1);

        $days = $allResponse->json('data.days');
        $this->assertCount(2, $days);
        $this->assertSame(1, collect($days)->firstWhere('day', (int) $dayOne->day)['groupSignInCount']);
        $this->assertSame(1, collect($days)->firstWhere('day', (int) $dayTwo->day)['privateSessionCount']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/daily?year={$year}&month={$month}&courseKind=group")
            ->assertOk()
            ->assertJsonPath('data.courseKind', 'group')
            ->assertJsonPath('data.totals.groupHeldCount', 1)
            ->assertJsonPath('data.totals.privateSessionCount', 0)
            ->assertJsonCount(1, 'data.days');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/daily?year={$year}&month={$month}&courseKind=private")
            ->assertOk()
            ->assertJsonPath('data.courseKind', 'private')
            ->assertJsonPath('data.totals.groupScheduledCount', 0)
            ->assertJsonPath('data.totals.privateSessionCount', 1)
            ->assertJsonCount(1, 'data.days');
    }

    public function test_course_endpoints_require_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/summary")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/calendar?year=2026")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/daily?year=2026&month=1")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_course_endpoints_are_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.course.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Scoped Member');
        $groupCourse = $this->createCourse($site, $staff, CourseType::Group, '团课核心');
        $session = $this->createSession(
            $site,
            $groupCourse,
            $staff,
            now()->startOfMonth()->setTime(11, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Completed,
        );
        $this->createAppointment($site, $session, $member, AppointmentStatus::Completed);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-course']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);
        $otherStaff = Staff::create([
            'tenant_id' => $otherTenant->id,
            'account_id' => Account::create(['display_name' => 'Other Coach', 'status' => 'active'])->id,
            'employee_no' => 'EMP-OTHER',
            'name' => 'Other Coach',
            'status' => 'active',
        ]);
        $otherCourse = Course::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'course_type' => CourseType::Group,
            'name' => 'Other Group',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
            'created_by_staff_id' => $otherStaff->id,
        ]);
        ScheduleSession::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'course_id' => $otherCourse->id,
            'coach_staff_id' => $otherStaff->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'capacity' => 10,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Completed,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/reports/courses/summary")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/courses/summary")
            ->assertOk()
            ->assertJsonPath('data.years.0.groupScheduledCount', 1)
            ->assertJsonPath('data.years.0.groupSignInCount', 1);
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Course Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Course Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Course', 'code' => 'course', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'reporting']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createMemberAtSite(int $tenantId, Site $site, string $name): Member
    {
        $member = Member::create([
            'tenant_id' => $tenantId,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'name' => $name,
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member;
    }

    private function createCourse(Site $site, Staff $staff, CourseType $type, string $name): Course
    {
        return Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => $type,
            'name' => $name,
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
            'created_by_staff_id' => $staff->id,
        ]);
    }

    private function createSession(
        Site $site,
        Course $course,
        Staff $coach,
        $startsAt,
        ScheduleSessionKind $kind,
        ScheduleSessionStatus $status,
    ): ScheduleSession {
        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => $kind === ScheduleSessionKind::Private ? 1 : 12,
            'booked_count' => 1,
            'status' => $status,
            'session_kind' => $kind,
            'version' => 1,
        ]);
    }

    private function createAppointment(
        Site $site,
        ScheduleSession $session,
        Member $member,
        AppointmentStatus $status,
    ): Appointment {
        return Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => $status,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
    }
}
