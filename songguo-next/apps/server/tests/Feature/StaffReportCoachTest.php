<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\CompensationRole;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionStaffAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffReportCoachTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_appointment_detail_returns_monthly_lines_from_seed_appointments(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read', 'crm.member.read']);
        $coach = $this->createSiteStaff($site, 'Lead Coach');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Detail Member');
        $groupCourse = $this->createCourse($site, $viewer, CourseType::Group, 'Coach Group');
        $privateCourse = $this->createCourse($site, $viewer, CourseType::Private, 'Coach Private');

        $groupSession = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            now()->startOfMonth()->addDays(2)->setTime(10, 0),
            ScheduleSessionKind::Group,
        );
        $privateSession = $this->createSession(
            $site,
            $privateCourse,
            $coach,
            now()->startOfMonth()->addDays(4)->setTime(14, 0),
            ScheduleSessionKind::Private,
        );

        $this->createAppointment($site, $groupSession, $member, AppointmentStatus::Completed);
        $this->createAppointment($site, $privateSession, $member, AppointmentStatus::Completed);
        $this->createAppointment(
            $site,
            $privateSession,
            $this->createMemberAtSite($viewer->tenant_id, $site, 'Cancelled Member'),
            AppointmentStatus::Cancelled,
        );

        $year = now()->year;
        $month = now()->month;

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/{$coach->id}/appointments?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.coach.staffId', $coach->id)
            ->assertJsonPath('data.totals.appointmentCount', 3)
            ->assertJsonPath('data.totals.signedInCount', 2)
            ->assertJsonPath('data.totals.cancelledCount', 1);

        $items = $response->json('data.items');
        $this->assertCount(3, $items);
        $this->assertSame('signed_in', $items[0]['signInState']);
        $this->assertSame('Detail Member', $items[0]['memberName']);
        $this->assertSame('Coach Group', $items[0]['courseName']);
        $this->assertNotNull($items[0]['startsAt']);
        $this->assertSame('completed', $items[0]['status']);
    }

    public function test_coach_monthly_rankings_return_coaches_ordered_by_completed_sessions(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read']);
        $topCoach = $this->createSiteStaff($site, 'Top Coach');
        $runnerUpCoach = $this->createSiteStaff($site, 'Runner Up Coach');
        $groupCourse = $this->createCourse($site, $viewer, CourseType::Group, 'Rank Group');
        $privateCourse = $this->createCourse($site, $viewer, CourseType::Private, 'Rank Private');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Rank Member');

        $this->seedCompletedGroupSession($site, $groupCourse, $topCoach, $member, now()->startOfMonth()->addDays(1));
        $this->seedCompletedGroupSession($site, $groupCourse, $topCoach, $member, now()->startOfMonth()->addDays(2));
        $this->seedCompletedPrivateSession($site, $privateCourse, $topCoach, $member, now()->startOfMonth()->addDays(3));
        $this->seedCompletedGroupSession($site, $groupCourse, $runnerUpCoach, $member, now()->startOfMonth()->addDays(4));

        $year = now()->year;
        $month = now()->month;

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/rankings?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.coachCount', 2)
            ->assertJsonPath('data.totals.groupSessionCount', 3)
            ->assertJsonPath('data.totals.privateSessionCount', 1);

        $items = $response->json('data.items');
        $this->assertSame(1, $items[0]['rank']);
        $this->assertSame($topCoach->id, $items[0]['staffId']);
        $this->assertSame(2, $items[0]['groupSessionCount']);
        $this->assertSame(1, $items[0]['privateSessionCount']);
        $this->assertSame(3, $items[0]['completedSessionCount']);
        $this->assertSame(2, $items[1]['rank']);
        $this->assertSame($runnerUpCoach->id, $items[1]['staffId']);
        $this->assertSame(1, $items[1]['completedSessionCount']);
    }

    public function test_coach_rankings_sort_by_group_when_requested(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read']);
        $groupLeader = $this->createSiteStaff($site, 'Group Leader');
        $privateLeader = $this->createSiteStaff($site, 'Private Leader');
        $groupCourse = $this->createCourse($site, $viewer, CourseType::Group, 'Sort Group');
        $privateCourse = $this->createCourse($site, $viewer, CourseType::Private, 'Sort Private');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Sort Member');

        $this->seedCompletedGroupSession($site, $groupCourse, $groupLeader, $member, now()->startOfMonth()->addDays(1));
        $this->seedCompletedGroupSession($site, $groupCourse, $groupLeader, $member, now()->startOfMonth()->addDays(2));
        $this->seedCompletedPrivateSession($site, $privateCourse, $privateLeader, $member, now()->startOfMonth()->addDays(3));
        $this->seedCompletedPrivateSession($site, $privateCourse, $privateLeader, $member, now()->startOfMonth()->addDays(4));
        $this->seedCompletedPrivateSession($site, $privateCourse, $privateLeader, $member, now()->startOfMonth()->addDays(5));

        $year = now()->year;
        $month = now()->month;

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/rankings?year={$year}&month={$month}&sortBy=group")
            ->assertOk()
            ->assertJsonPath('data.items.0.staffId', $groupLeader->id)
            ->assertJsonPath('data.items.1.staffId', $privateLeader->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/rankings?year={$year}&month={$month}&sortBy=private")
            ->assertOk()
            ->assertJsonPath('data.items.0.staffId', $privateLeader->id)
            ->assertJsonPath('data.items.1.staffId', $groupLeader->id);
    }

    public function test_private_ranking_does_not_count_future_confirmed_or_absent_bookings_as_delivered(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read']);
        $coach = $this->createSiteStaff($site, 'Private Delivery Coach');
        $course = $this->createCourse($site, $viewer, CourseType::Private, 'Delivery Private');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Delivery Member');

        $this->seedCompletedPrivateSession(
            $site,
            $course,
            $coach,
            $member,
            now()->startOfMonth()->addDay(),
        );

        foreach ([AppointmentStatus::Confirmed, AppointmentStatus::Absent] as $index => $status) {
            $session = $this->createSession(
                $site,
                $course,
                $coach,
                now()->addDays($index + 1),
                ScheduleSessionKind::Private,
            );
            $session->update(['status' => ScheduleSessionStatus::Scheduled]);
            $this->createAppointment($site, $session, $member, $status);
        }

        $this->getJson(
            "/api/v1/staff/sites/{$site->id}/reports/coaches/rankings?year=".now()->year.'&month='.now()->month,
        )
            ->assertOk()
            ->assertJsonPath('data.totals.privateSessionCount', 1)
            ->assertJsonPath('data.items.0.privateSessionCount', 1)
            ->assertJsonPath('data.items.0.completedSessionCount', 1);
    }

    public function test_coach_reports_include_every_delivery_staff_from_the_session_snapshot(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read']);
        $coachA = $this->createSiteStaff($site, 'Primary Coach');
        $coachB = $this->createSiteStaff($site, 'Second Coach');
        $course = $this->createCourse($site, $viewer, CourseType::Group, 'Multi A Group');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Multi A Member');
        $session = $this->createSession(
            $site,
            $course,
            $coachA,
            now()->startOfMonth()->addDays(2)->setTime(10, 0),
            ScheduleSessionKind::Group,
        );
        $this->createAppointment($site, $session, $member, AppointmentStatus::Completed);
        $role = CompensationRole::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'code' => 'delivery-coach',
            'name' => '授课教练',
            'role_type' => 'delivery',
            'status' => 'active',
            'version' => 1,
        ]);
        foreach ([[$coachA, true], [$coachB, false]] as [$coach, $primary]) {
            ScheduleSessionStaffAssignment::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'schedule_session_id' => $session->id,
                'staff_id' => $coach->id,
                'compensation_role_id' => $role->id,
                'is_primary' => $primary,
                'allocation_bps' => 5_000,
                'assignment_version' => 1,
            ]);
        }

        $year = now()->year;
        $month = now()->month;
        $ranking = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/rankings?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.coachCount', 2)
            ->assertJsonPath('data.totals.groupSessionCount', 2);
        $this->assertEqualsCanonicalizing(
            [$coachA->id, $coachB->id],
            collect($ranking->json('data.items'))->pluck('staffId')->all(),
        );

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/{$coachB->id}/appointments?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.appointmentCount', 1)
            ->assertJsonPath('data.items.0.sessionId', $session->id);
    }

    public function test_coach_report_endpoints_require_permission(): void
    {
        [$viewer, $site] = $this->actAsStaff([]);
        $coach = $this->createSiteStaff($site, 'Protected Coach');
        $year = now()->year;
        $month = now()->month;
        $query = "year={$year}&month={$month}";

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/rankings?{$query}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/{$coach->id}/appointments?{$query}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_coach_report_endpoints_mask_member_pii_without_crm_read(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read']);
        $coach = $this->createSiteStaff($site, 'Mask Coach');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Mask Member');
        $course = $this->createCourse($site, $viewer, CourseType::Private, 'Mask Private');
        $session = $this->createSession(
            $site,
            $course,
            $coach,
            now()->startOfMonth()->addDay()->setTime(9, 0),
            ScheduleSessionKind::Private,
        );
        $this->createAppointment($site, $session, $member, AppointmentStatus::Completed);

        $year = now()->year;
        $month = now()->month;

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/{$coach->id}/appointments?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', 'M**********');
    }

    public function test_coach_report_endpoints_are_scoped_to_assigned_site(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read']);
        $coach = $this->createSiteStaff($site, 'Scoped Coach');
        $otherSite = Site::create([
            'tenant_id' => $viewer->tenant_id,
            'name' => 'Other Coach Site',
            'code' => 'coach-other',
            'status' => 'active',
        ]);

        $year = now()->year;
        $month = now()->month;
        $query = "year={$year}&month={$month}";

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/reports/coaches/rankings?{$query}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/reports/coaches/{$coach->id}/appointments?{$query}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/rankings?{$query}")->assertOk();
    }

    public function test_cannot_query_coach_from_other_tenant(): void
    {
        [$viewer, $site] = $this->actAsStaff(['report.coach.read']);

        $otherTenant = Tenant::create(['name' => 'Other Coach Tenant', 'code' => 'other-coach']);
        $otherCoach = Staff::create([
            'tenant_id' => $otherTenant->id,
            'account_id' => Account::create(['display_name' => 'Other Coach', 'status' => 'active'])->id,
            'employee_no' => 'EMP-OTHER-COACH',
            'name' => 'Other Coach',
            'status' => 'active',
        ]);

        $year = now()->year;
        $month = now()->month;
        $query = "year={$year}&month={$month}";

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/coaches/{$otherCoach->id}/appointments?{$query}")
            ->assertNotFound();
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant',
            'code' => fake()->unique()->slug(1),
        ]);
        $account = Account::create(['display_name' => 'Coach Report Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Coach Report Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Coach Report', 'code' => 'coach-report', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'reporting']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createSiteStaff(Site $site, string $name): Staff
    {
        $staff = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => $name, 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => $name,
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $site->tenant_id, 'is_primary' => false]);

        return $staff;
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
            'status' => ScheduleSessionStatus::Completed,
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

    private function seedCompletedGroupSession(
        Site $site,
        Course $course,
        Staff $coach,
        Member $member,
        $startsAt,
    ): void {
        $session = $this->createSession($site, $course, $coach, $startsAt, ScheduleSessionKind::Group);
        $this->createAppointment($site, $session, $member, AppointmentStatus::Completed);
    }

    private function seedCompletedPrivateSession(
        Site $site,
        Course $course,
        Staff $coach,
        Member $member,
        $startsAt,
    ): void {
        $session = $this->createSession($site, $course, $coach, $startsAt, ScheduleSessionKind::Private);
        $this->createAppointment($site, $session, $member, AppointmentStatus::Completed);
    }
}
