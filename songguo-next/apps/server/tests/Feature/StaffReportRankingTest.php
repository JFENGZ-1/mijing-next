<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\PointLedgerDirection;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\PointLedgerEntry;
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

class StaffReportRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_ranking_returns_members_ordered_by_spend(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.rankings.read']);
        $topMember = $this->createMemberAtSite($staff->tenant_id, $site, 'Top Spender');
        $secondMember = $this->createMemberAtSite($staff->tenant_id, $site, 'Second Spender');

        $this->createPaidOrder($site, $topMember, $staff, 2000);
        $this->createPaidOrder($site, $topMember, $staff, 500);
        $this->createPaidOrder($site, $secondMember, $staff, 1200);

        $year = now()->year;
        $month = now()->month;

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/orders?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.memberCount', 2)
            ->assertJsonPath('data.totals.orderCount', 3)
            ->assertJsonPath('data.totals.totalSpend', '3700.00');

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]['rank']);
        $this->assertSame($topMember->id, $items[0]['memberId']);
        $this->assertSame('2500.00', $items[0]['totalSpend']);
        $this->assertSame(2, $items[0]['orderCount']);
        $this->assertSame(2, $items[1]['rank']);
        $this->assertSame($secondMember->id, $items[1]['memberId']);
        $this->assertSame('1200.00', $items[1]['totalSpend']);
    }

    public function test_course_attendance_ranking_returns_members_ordered_by_completed_appointments(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.rankings.read']);
        $leader = $this->createMemberAtSite($staff->tenant_id, $site, 'Attendance Leader');
        $runnerUp = $this->createMemberAtSite($staff->tenant_id, $site, 'Attendance Runner Up');
        $course = $this->createCourse($site, $staff);

        $sessionOne = $this->createSession($site, $course, $staff, now()->startOfMonth()->addDays(2)->setTime(10, 0));
        $sessionTwo = $this->createSession($site, $course, $staff, now()->startOfMonth()->addDays(4)->setTime(11, 0));
        $sessionThree = $this->createSession($site, $course, $staff, now()->startOfMonth()->addDays(6)->setTime(12, 0));

        $this->createAppointment($site, $sessionOne, $leader, AppointmentStatus::Completed);
        $this->createAppointment($site, $sessionTwo, $leader, AppointmentStatus::Completed);
        $this->createAppointment($site, $sessionThree, $runnerUp, AppointmentStatus::Completed);

        $year = now()->year;
        $month = now()->month;

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/course-attendance?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.memberCount', 2)
            ->assertJsonPath('data.totals.completedAppointments', 3);

        $items = $response->json('data.items');
        $this->assertSame($leader->id, $items[0]['memberId']);
        $this->assertSame(2, $items[0]['completedAppointments']);
        $this->assertSame($runnerUp->id, $items[1]['memberId']);
        $this->assertSame(1, $items[1]['completedAppointments']);
    }

    public function test_points_ranking_returns_members_ordered_by_credits_when_enabled(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.rankings.read'], pointsEnabled: true);
        $leader = $this->createMemberAtSite($staff->tenant_id, $site, 'Point Leader');
        $runnerUp = $this->createMemberAtSite($staff->tenant_id, $site, 'Point Runner Up');

        $this->createPointCredit($staff->tenant_id, $leader->id, 120, now()->startOfMonth()->addDay());
        $this->createPointCredit($staff->tenant_id, $leader->id, 30, now()->startOfMonth()->addDays(2));
        $this->createPointCredit($staff->tenant_id, $runnerUp->id, 80, now()->startOfMonth()->addDays(3));

        $year = now()->year;
        $month = now()->month;

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/points?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.memberCount', 2)
            ->assertJsonPath('data.totals.creditPoints', 230);

        $items = $response->json('data.items');
        $this->assertSame($leader->id, $items[0]['memberId']);
        $this->assertSame(150, $items[0]['creditPoints']);
        $this->assertSame($runnerUp->id, $items[1]['memberId']);
        $this->assertSame(80, $items[1]['creditPoints']);
    }

    public function test_points_ranking_returns_not_found_when_points_disabled(): void
    {
        [, $site] = $this->actAsStaff(['report.rankings.read'], pointsEnabled: false);

        $year = now()->year;
        $month = now()->month;

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/points?year={$year}&month={$month}")
            ->assertNotFound();
    }

    public function test_sales_staff_ranking_and_detail_return_attributed_orders(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.rankings.read']);
        $salesOne = $this->createSiteStaff($site, 'Sales One');
        $salesTwo = $this->createSiteStaff($site, 'Sales Two');
        $memberA = $this->createMemberAtSite($staff->tenant_id, $site, 'Member A');
        $memberB = $this->createMemberAtSite($staff->tenant_id, $site, 'Member B');

        $this->createPaidOrder($site, $memberA, $salesOne, 1000);
        $this->createPaidOrder($site, $memberB, $salesOne, 800);
        $this->createPaidOrder($site, $memberA, $salesTwo, 600);

        $year = now()->year;
        $month = now()->month;

        $rankResponse = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/sales-staff?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.staffCount', 2)
            ->assertJsonPath('data.totals.cardSalesCount', 3)
            ->assertJsonPath('data.totals.revenue', '2400.00');

        $rankItems = $rankResponse->json('data.items');
        $this->assertSame($salesOne->id, $rankItems[0]['staffId']);
        $this->assertSame('1800.00', $rankItems[0]['revenue']);
        $this->assertSame(2, $rankItems[0]['cardSalesCount']);
        $this->assertSame(2, $rankItems[0]['memberCount']);
        $this->assertSame($salesTwo->id, $rankItems[1]['staffId']);
        $this->assertSame('600.00', $rankItems[1]['revenue']);

        $detailResponse = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/sales-staff/{$salesOne->id}?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.staffId', $salesOne->id)
            ->assertJsonPath('data.staffName', 'Sales One')
            ->assertJsonPath('data.totals.memberCount', 2)
            ->assertJsonPath('data.totals.cardSalesCount', 2)
            ->assertJsonPath('data.totals.revenue', '1800.00');

        $detailItems = $detailResponse->json('data.items');
        $this->assertSame($memberA->id, $detailItems[0]['memberId']);
        $this->assertSame('1000.00', $detailItems[0]['revenue']);
        $this->assertSame($memberB->id, $detailItems[1]['memberId']);
        $this->assertSame('800.00', $detailItems[1]['revenue']);
    }

    public function test_ranking_endpoints_mask_member_pii_without_crm_read(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.rankings.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Hidden Rank Member');
        $this->createPaidOrder($site, $member, $staff, 300);

        $year = now()->year;
        $month = now()->month;

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/orders?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', 'H*****************')
            ->assertJsonPath('data.items.0.memberAvatarUrl', null);
    }

    public function test_ranking_endpoints_require_permission(): void
    {
        [, $site] = $this->actAsStaff([]);
        $year = now()->year;
        $month = now()->month;
        $query = "year={$year}&month={$month}";

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/orders?{$query}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/course-attendance?{$query}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/points?{$query}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/sales-staff?{$query}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_ranking_endpoints_are_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.rankings.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Scoped Rank Member');
        $this->createPaidOrder($site, $member, $staff, 900);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-rank']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);
        $otherStaff = Staff::create([
            'tenant_id' => $otherTenant->id,
            'account_id' => Account::create(['display_name' => 'Other Sales', 'status' => 'active'])->id,
            'employee_no' => 'EMP-OTHER-RANK',
            'name' => 'Other Sales',
            'status' => 'active',
        ]);
        $otherStaff->sites()->attach($otherSite->id, ['tenant_id' => $otherTenant->id, 'is_primary' => true]);

        $year = now()->year;
        $month = now()->month;
        $query = "year={$year}&month={$month}";

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/reports/rankings/orders?{$query}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/rankings/orders?{$query}")
            ->assertOk()
            ->assertJsonPath('data.totals.orderCount', 1)
            ->assertJsonPath('data.totals.totalSpend', '900.00');
    }

    private function actAsStaff(array $permissions, bool $pointsEnabled = false): array
    {
        [$staff, $site] = $this->makeStaff($permissions, $pointsEnabled);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions, bool $pointsEnabled): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant',
            'code' => fake()->unique()->slug(1),
            'points_enabled' => $pointsEnabled,
        ]);
        $account = Account::create(['display_name' => 'Ranking Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Ranking Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Ranking', 'code' => 'ranking', 'status' => 'active']);
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

    private function createPaidOrder(Site $site, Member $member, Staff $salesStaff, float $amount): MemberCardOrder
    {
        return MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-'.strtoupper(Str::random(8)),
            'amount' => $amount,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $salesStaff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCourse(Site $site, Staff $staff): Course
    {
        return Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => 'Ranking Group',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
            'created_by_staff_id' => $staff->id,
        ]);
    }

    private function createSession(Site $site, Course $course, Staff $coach, $startsAt): ScheduleSession
    {
        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => 12,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Completed,
            'session_kind' => ScheduleSessionKind::Group,
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

    private function createPointCredit(int $tenantId, int $memberId, int $amount, $createdAt): PointLedgerEntry
    {
        return PointLedgerEntry::create([
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'amount_delta' => $amount,
            'direction' => PointLedgerDirection::Credit,
            'reason' => 'Ranking seed credit',
            'command_key' => (string) Str::uuid(),
            'created_at' => $createdAt,
        ]);
    }
}
