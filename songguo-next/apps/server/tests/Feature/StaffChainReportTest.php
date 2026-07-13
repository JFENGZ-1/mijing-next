<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
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

class StaffChainReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_sites_lists_all_tenant_sites_with_access_flags(): void
    {
        [$staff, $siteA] = $this->actAsStaff(['org.chain.read']);
        $siteB = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch B',
            'code' => 'branch-b',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/staff/chain/sites')->assertOk();

        $sites = collect($response->json('data.sites'));
        $this->assertCount(2, $sites);
        $this->assertTrue($sites->firstWhere('id', $siteA->id)['accessible']);
        $this->assertFalse($sites->firstWhere('id', $siteB->id)['accessible']);
    }

    public function test_finance_summary_rolls_up_across_selected_sites(): void
    {
        [$staff, $siteA, $siteB] = $this->actAsStaffWithTwoSites(['org.chain.read']);
        $memberA = $this->createMemberAtSite($staff->tenant_id, $siteA, 'Site A Member', now()->startOfMonth());
        $cardA = $this->createCard($siteA, $memberA);
        $this->createPaidOrder($siteA, $memberA, $cardA, $staff, 'ORD-CHAIN-A', 1000, now()->startOfMonth()->addDay());

        $memberB = $this->createMemberAtSite($staff->tenant_id, $siteB, 'Site B Member', now()->startOfMonth());
        $cardB = $this->createCard($siteB, $memberB);
        $this->createPaidOrder($siteB, $memberB, $cardB, $staff, 'ORD-CHAIN-B', 500, now()->startOfMonth()->addDays(2));

        $response = $this->getJson("/api/v1/staff/chain/reports/finance/summary?siteIds={$siteA->id},{$siteB->id}")
            ->assertOk();

        $response->assertJsonPath('data.siteIds', [$siteA->id, $siteB->id]);
        $currentYear = collect($response->json('data.years'))->firstWhere('year', now()->year);
        $this->assertSame(2, $currentYear['cardSalesCount']);
        $this->assertSame('1500.00', $currentYear['revenue']);
        $this->assertSame(2, $currentYear['newMemberCount']);
    }

    public function test_course_summary_rolls_up_across_selected_sites(): void
    {
        [$staff, $siteA, $siteB] = $this->actAsStaffWithTwoSites(['org.chain.read']);
        $memberA = $this->createMemberAtSite($staff->tenant_id, $siteA, 'Course A');
        $memberB = $this->createMemberAtSite($staff->tenant_id, $siteB, 'Course B');
        $courseA = $this->createCourse($siteA, $staff, CourseType::Group, '团课A');
        $courseB = $this->createCourse($siteB, $staff, CourseType::Group, '团课B');

        $sessionA = $this->createSession(
            $siteA,
            $courseA,
            $staff,
            now()->startOfMonth()->addDays(2)->setTime(10, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Completed,
        );
        $this->createAppointment($siteA, $sessionA, $memberA, AppointmentStatus::Completed);

        $sessionB = $this->createSession(
            $siteB,
            $courseB,
            $staff,
            now()->startOfMonth()->addDays(3)->setTime(11, 0),
            ScheduleSessionKind::Group,
            ScheduleSessionStatus::Completed,
        );
        $this->createAppointment($siteB, $sessionB, $memberB, AppointmentStatus::Completed);

        $response = $this->getJson("/api/v1/staff/chain/reports/courses/summary?siteIds={$siteA->id},{$siteB->id}")
            ->assertOk();

        $currentYear = collect($response->json('data.years'))->firstWhere('year', now()->year);
        $this->assertSame(2, $currentYear['groupScheduledCount']);
        $this->assertSame(2, $currentYear['groupHeldCount']);
        $this->assertSame(2, $currentYear['groupSignInCount']);
    }

    public function test_members_summary_dedupes_cross_site_members_by_member_id(): void
    {
        [$staff, $siteA, $siteB] = $this->actAsStaffWithTwoSites(['org.chain.read']);
        $sharedMember = $this->createMemberAtSite($staff->tenant_id, $siteA, 'Shared Member', now()->startOfMonth());
        $this->attachMemberToSite($sharedMember, $siteB);
        $this->createCard($siteA, $sharedMember);

        $siteOnlyMember = $this->createMemberAtSite($staff->tenant_id, $siteB, 'Branch Only', now()->subMonth());
        $this->createCard($siteB, $siteOnlyMember);

        $response = $this->getJson("/api/v1/staff/chain/reports/members/summary?siteIds={$siteA->id},{$siteB->id}")
            ->assertOk();

        $response->assertJsonPath('data.totalMemberCount', 2);
        $response->assertJsonPath('data.monthNewMemberCount', 1);
        $this->assertSame(1, collect($response->json('data.bySite'))->firstWhere('siteId', $siteA->id)['memberCount']);
        $this->assertSame(2, collect($response->json('data.bySite'))->firstWhere('siteId', $siteB->id)['memberCount']);
    }

    public function test_report_defaults_to_staff_assigned_sites_when_site_ids_omitted(): void
    {
        [$staff, $siteA, $siteB] = $this->actAsStaffWithTwoSites(['org.chain.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $siteA, 'Default Scope');
        $card = $this->createCard($siteA, $member);
        $this->createPaidOrder($siteA, $member, $card, $staff, 'ORD-DEFAULT', 200, now()->startOfMonth());

        $response = $this->getJson('/api/v1/staff/chain/reports/finance/summary')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$siteA->id, $siteB->id],
            $response->json('data.siteIds'),
        );
        $currentYear = collect($response->json('data.years'))->firstWhere('year', now()->year);
        $this->assertSame(1, $currentYear['cardSalesCount']);
    }

    public function test_report_rejects_site_without_staff_access(): void
    {
        [$staff, $siteA] = $this->actAsStaff(['org.chain.read']);
        $siteB = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Other Branch',
            'code' => 'other-branch',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/chain/reports/finance/summary?siteIds={$siteB->id}")
            ->assertNotFound();

        $this->getJson("/api/v1/staff/chain/reports/finance/summary?siteIds={$siteA->id},{$siteB->id}")
            ->assertNotFound();
    }

    public function test_chain_endpoints_require_org_chain_read_permission(): void
    {
        [$staff, $site] = $this->actAsStaff([]);

        $this->getJson('/api/v1/staff/chain/sites')
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson('/api/v1/staff/chain/reports/finance/summary')
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson('/api/v1/staff/chain/reports/courses/summary')
            ->assertForbidden();

        $this->getJson('/api/v1/staff/chain/reports/members/summary')
            ->assertForbidden();
    }

    public function test_chain_endpoints_require_staff_session(): void
    {
        [, $site] = $this->makeStaff(['org.chain.read']);

        $this->getJson('/api/v1/staff/chain/sites')->assertUnauthorized();
        $this->getJson('/api/v1/staff/chain/reports/finance/summary')->assertUnauthorized();
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Site}
     */
    private function actAsStaffWithTwoSites(array $permissions): array
    {
        [$staff, $siteA] = $this->makeStaff($permissions);
        $siteB = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch Two',
            'code' => 'branch-two',
            'status' => 'active',
        ]);
        $staff->sites()->attach($siteB->id, ['tenant_id' => $staff->tenant_id, 'is_primary' => false]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $siteA, $siteB];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Chain Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Chain Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Chain Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Chain', 'code' => 'chain', 'status' => 'active']);
        foreach ($permissions as $code) {
            $module = str_starts_with($code, 'org.') ? 'org' : 'reporting';
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => $module]);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createMemberAtSite(int $tenantId, Site $site, string $name, $joinedAt = null): Member
    {
        $member = Member::create([
            'tenant_id' => $tenantId,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => $joinedAt ?? now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'name' => $name,
        ]);
        $this->attachMemberToSite($member, $site);

        return $member;
    }

    private function attachMemberToSite(Member $member, Site $site): void
    {
        DB::table('member_sites')->insert([
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCard(Site $site, Member $member): MemberCard
    {
        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => 'stored_value',
            'card_no' => 'MC-'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'product_snapshot' => ['name' => '年卡'],
            'issued_at' => now(),
        ]);
    }

    private function createPaidOrder(
        Site $site,
        Member $member,
        MemberCard $card,
        Staff $staff,
        string $orderNo,
        float $amount,
        $createdAt,
    ): MemberCardOrder {
        $order = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => $orderNo,
            'amount' => $amount,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $order;
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
