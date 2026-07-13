<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\OrderAmountCorrectionType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\OrderAmountCorrection;
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

class StaffReportDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_dashboard_summary_returns_aggregates_from_seed_data(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.dashboard.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Report Member', now()->subMonth());
        $newMember = $this->createMemberAtSite($staff->tenant_id, $site, 'New Member', now());

        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => 'stored_value',
            'card_no' => 'MC-RPT-001',
            'status' => 'active',
            'product_snapshot' => ['name' => '年卡'],
            'issued_at' => now(),
        ]);

        $todayOrder = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => 'ORD-RPT-TODAY',
            'amount' => 1500,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
        ]);
        $todayOrder->forceFill(['created_at' => now(), 'updated_at' => now()])->save();

        $monthOrder = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $newMember->id,
            'order_no' => 'ORD-RPT-MONTH',
            'amount' => 2000,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
        ]);
        $monthOrder->forceFill([
            'created_at' => now()->startOfMonth()->addDays(2),
            'updated_at' => now()->startOfMonth()->addDays(2),
        ])->save();

        OrderAmountCorrection::create([
            'tenant_id' => $site->tenant_id,
            'order_id' => $todayOrder->id,
            'entry_type' => OrderAmountCorrectionType::Correction,
            'corrected_amount' => 1800,
            'reason' => '前台改价',
            'command_key' => (string) Str::uuid(),
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        $oldOrder = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-RPT-OLD',
            'amount' => 500,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
        ]);
        $oldOrder->forceFill([
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ])->save();

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
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(11, 0),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);

        $monthSession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->startOfMonth()->addDays(3)->setTime(15, 0),
            'ends_at' => now()->startOfMonth()->addDays(3)->setTime(16, 0),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $staff->id,
        ]);

        Appointment::create([
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
            'session_id' => $monthSession->id,
            'member_id' => $newMember->id,
            'status' => AppointmentStatus::Completed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/dashboard-summary")
            ->assertOk()
            ->assertJsonPath('data.kpis.todayRevenue', '1800.00')
            ->assertJsonPath('data.kpis.monthRevenue', '3800.00')
            ->assertJsonPath('data.kpis.todayCardSalesCount', 1)
            ->assertJsonPath('data.kpis.monthCardSalesCount', 2)
            ->assertJsonPath('data.kpis.todayAppointmentCount', 1)
            ->assertJsonPath('data.kpis.monthAppointmentCount', 2)
            ->assertJsonPath('data.kpis.totalMemberCount', 2)
            ->assertJsonPath('data.kpis.monthNewMemberCount', 1);

        $trend = $response->json('data.profitTrend');
        $this->assertCount(12, $trend);
        $this->assertSame('本', collect($trend)->last()['label']);
        $this->assertSame('3800.00', collect($trend)->last()['revenue']);
    }

    public function test_report_dashboard_summary_requires_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/dashboard-summary")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_report_dashboard_summary_is_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.dashboard.read']);
        $this->createMemberAtSite($staff->tenant_id, $site, 'Scoped Member', now());

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-report']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/reports/dashboard-summary")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/dashboard-summary")
            ->assertOk()
            ->assertJsonPath('data.kpis.totalMemberCount', 1);
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
        $account = Account::create(['display_name' => 'Report Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Report Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Report', 'code' => 'report', 'status' => 'active']);
        foreach ($permissions as $code) {
            $module = match (true) {
                str_starts_with($code, 'report.') => 'reporting',
                str_starts_with($code, 'crm.') => 'crm',
                default => 'identity',
            };
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
