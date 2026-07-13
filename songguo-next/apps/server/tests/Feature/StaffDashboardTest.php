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

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_expected_kpis(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Alice Buyer', now());
        $newMember = $this->createMemberAtSite($staff->tenant_id, $site, 'Bob New', now());

        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => 'stored_value',
            'card_no' => 'MC-DASH-001',
            'status' => 'active',
            'product_snapshot' => ['name' => '储值卡'],
            'issued_at' => now(),
        ]);

        MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => 'ORD-DASH-001',
            'amount' => 1200,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $newMember->id,
            'order_no' => 'ORD-DASH-002',
            'amount' => 800,
            'status' => MemberCardOrderStatus::PendingPayment,
            'created_by_staff_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $privateCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Private,
            'name' => '私教',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $groupSession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $groupCourse->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(11, 0),
            'capacity' => 10,
            'booked_count' => 2,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);
        $privateSession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $privateCourse->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->setTime(14, 0),
            'ends_at' => now()->setTime(15, 0),
            'capacity' => 1,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Private,
            'created_by_staff_id' => $staff->id,
        ]);

        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $groupSession->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $groupSession->id,
            'member_id' => $newMember->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $privateSession->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/summary")
            ->assertOk()
            ->assertJsonPath('data.greeting.headline', $staff->name)
            ->assertJsonPath('data.kpis.todayRevenue', '1200.00')
            ->assertJsonPath('data.kpis.groupAppointmentCount', 2)
            ->assertJsonPath('data.kpis.privateAppointmentCount', 1)
            ->assertJsonPath('data.kpis.saleCardCount', 1)
            ->assertJsonPath('data.kpis.newMemberCount', 2);
    }

    public function test_crm_read_permission_also_grants_dashboard_summary(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/summary")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'greeting' => ['headline', 'hint'],
                    'kpis' => [
                        'todayRevenue',
                        'groupAppointmentCount',
                        'privateAppointmentCount',
                        'saleCardCount',
                        'newMemberCount',
                    ],
                    'asOf',
                ],
            ]);
    }

    public function test_dashboard_summary_requires_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/summary")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_dashboard_summary_is_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read']);
        $this->createMemberAtSite($staff->tenant_id, $site, 'Local Member', now());

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-dash']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/dashboard/summary")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/summary")
            ->assertOk()
            ->assertJsonPath('data.kpis.newMemberCount', 1);
    }

    public function test_sales_feed_returns_today_paid_orders(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Feed Member', now());
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => 'stored_value',
            'card_no' => 'MC-FEED-001',
            'status' => 'active',
            'product_snapshot' => ['name' => '年卡'],
            'issued_at' => now(),
        ]);

        $order = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => 'ORD-FEED-001',
            'amount' => 3000,
            'status' => MemberCardOrderStatus::Paid,
            'metadata' => ['remark' => '前台收款'],
            'created_by_staff_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldOrder = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-FEED-OLD',
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
        ]);
        $oldOrder->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/sales-feed")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $order->id)
            ->assertJsonPath('data.items.0.memberName', 'Feed Member')
            ->assertJsonPath('data.items.0.cardName', '年卡')
            ->assertJsonPath('data.items.0.amount', '3000.00')
            ->assertJsonPath('data.items.0.isNewMember', true)
            ->assertJsonPath('data.items.0.remark', '前台收款')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_order_read_permission_also_grants_sales_feed(): void
    {
        [, $site] = $this->actAsStaff(['order.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/sales-feed")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => ['page', 'perPage', 'total', 'lastPage'],
                ],
            ]);
    }

    public function test_sales_feed_requires_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/sales-feed")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_sales_feed_is_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Scoped Member', now()->subDay());
        MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-SCOPED',
            'amount' => 500,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-feed']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/dashboard/sales-feed")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/sales-feed")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_appointment_feed_returns_today_confirmed_appointments_ordered_by_session_start(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read', 'crm.member.read']);
        $memberA = $this->createMemberAtSite($staff->tenant_id, $site, 'Alice Appoint', now()->subDay());
        $memberB = $this->createMemberAtSite($staff->tenant_id, $site, 'Bob Appoint', now()->subDay());

        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '晨间瑜伽',
            'duration_minutes' => 60,
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

        $laterSession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $privateCourse->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->setTime(16, 0),
            'ends_at' => now()->setTime(17, 0),
            'capacity' => 1,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Private,
            'created_by_staff_id' => $staff->id,
        ]);
        $earlierSession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $groupCourse->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->setTime(9, 30),
            'ends_at' => now()->setTime(10, 30),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);

        $earlierAppointment = Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $earlierSession->id,
            'member_id' => $memberA->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
        $laterAppointment = Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $laterSession->id,
            'member_id' => $memberB->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/appointment-feed")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $earlierAppointment->id)
            ->assertJsonPath('data.items.0.memberName', 'Alice Appoint')
            ->assertJsonPath('data.items.0.courseName', '晨间瑜伽')
            ->assertJsonPath('data.items.0.coachName', $staff->name)
            ->assertJsonPath('data.items.0.status', 'confirmed')
            ->assertJsonPath('data.items.1.id', $laterAppointment->id)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_appointment_feed_excludes_cancelled_waitlisted_and_non_today_sessions(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read', 'crm.member.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Feed Member', now()->subDay());

        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $todaySession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->setTime(11, 0),
            'ends_at' => now()->setTime(12, 0),
            'capacity' => 10,
            'booked_count' => 3,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);
        $yesterdaySession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->subDay()->setTime(11, 0),
            'ends_at' => now()->subDay()->setTime(12, 0),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);

        $confirmed = Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $todaySession->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $todaySession->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Cancelled,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $todaySession->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Waitlisted,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $yesterdaySession->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now()->subDay(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/appointment-feed")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $confirmed->id)
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_appointment_feed_masks_member_name_without_crm_read(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Hidden Member', now()->subDay());

        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->setTime(13, 0),
            'ends_at' => now()->setTime(14, 0),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/appointment-feed")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', 'H************');
    }

    public function test_appointment_feed_requires_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/appointment-feed")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_appointment_feed_is_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.dashboard.read', 'crm.member.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Scoped Member', now()->subDay());

        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->setTime(15, 0),
            'ends_at' => now()->setTime(16, 0),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-appoint']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/dashboard/appointment-feed")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/dashboard/appointment-feed")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
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
        $account = Account::create(['display_name' => 'Dashboard Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Dashboard Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Dashboard', 'code' => 'dashboard', 'status' => 'active']);
        foreach ($permissions as $code) {
            $module = str_starts_with($code, 'crm.') ? 'crm' : (str_starts_with($code, 'order.') ? 'order' : 'identity');
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => $module]);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createMemberAtSite(int $tenantId, Site $site, string $name, $joinedAt): Member
    {
        $member = Member::create([
            'tenant_id' => $tenantId,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => $joinedAt,
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
}
