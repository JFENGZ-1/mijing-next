<?php

namespace Tests\Feature;

use App\Enums\MemberCardOrderStatus;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 新增接口的租户隔离验证：变更记录 / 卡分析 / 会员积分流水。
 */
class NewReportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_read_permission_is_available_for_role_configuration(): void
    {
        $this->assertDatabaseHas('permissions', [
            'code' => 'report.read',
            'module' => 'reporting',
        ]);
    }

    public function test_new_report_apis_reject_cross_tenant_site(): void
    {
        // 租户 A：目标数据
        $tenantA = Tenant::create(['name' => 'Tenant A', 'code' => 'tenant-a']);
        $siteA = Site::create(['tenant_id' => $tenantA->id, 'name' => 'A馆', 'code' => 'site-a', 'status' => 'active']);
        $memberA = Member::create([
            'tenant_id' => $tenantA->id,
            'member_no' => 'MEM-A-1',
            'registration_site_id' => $siteA->id,
            'home_site_id' => $siteA->id,
            'status' => 'active',
        ]);

        // 租户 B 的员工（拥有全部所需权限）
        $staffB = $this->makeStaff('tenant-b', ['report.read', 'crm.member.read']);
        Sanctum::actingAs($staffB->account, ['api', 'client:staff', "staff:{$staffB->id}", "tenant:{$staffB->tenant_id}"]);

        // 三个新接口访问租户 A 的 site → 一律 404（site 归属校验拦截）
        $this->getJson("/api/v1/staff/sites/{$siteA->id}/reports/change-log")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$siteA->id}/reports/card-analyze/summary")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$siteA->id}/members/{$memberA->id}/point-ledger")->assertNotFound();
    }

    public function test_point_ledger_rejects_member_of_other_site_in_same_tenant(): void
    {
        // 同租户不同门店：staff 只属于 site1，访问 site2 的会员 → 404
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'tenant-x']);
        $tenant->update(['points_enabled' => true]);
        $site1 = Site::create(['tenant_id' => $tenant->id, 'name' => '一店', 'code' => 'x1', 'status' => 'active']);
        $site2 = Site::create(['tenant_id' => $tenant->id, 'name' => '二店', 'code' => 'x2', 'status' => 'active']);
        $memberSite2 = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-X-2',
            'registration_site_id' => $site2->id,
            'home_site_id' => $site2->id,
            'status' => 'active',
        ]);

        $staff = $this->makeStaffInTenant($tenant, $site1, ['crm.member.read']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        // site1 下查 site2 的会员（会员未关联 site1）→ 404
        $this->getJson("/api/v1/staff/sites/{$site1->id}/members/{$memberSite2->id}/point-ledger")->assertNotFound();
        // 未被指派的 site2 → 404
        $this->getJson("/api/v1/staff/sites/{$site2->id}/members/{$memberSite2->id}/point-ledger")->assertNotFound();
    }

    public function test_report_query_contracts_reject_malformed_filters(): void
    {
        $staff = $this->makeStaff('report-filter-validation', [
            'report.read',
            'member-card.reminder.read',
        ]);
        $site = $staff->sites()->firstOrFail();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/change-log?category=unknown")
            ->assertUnprocessable();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/change-log?dateFrom=not-a-date")
            ->assertUnprocessable();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/card-sales/summary?month=13")
            ->assertUnprocessable();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/card-sales/detail?perPage=51")
            ->assertUnprocessable();
        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/expiring?withinDays=366")
            ->assertUnprocessable();
        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/zero-balance?page=0")
            ->assertUnprocessable();
    }

    public function test_card_sales_uses_paid_at_for_period_sorting_and_presentation_with_legacy_fallback(): void
    {
        $staff = $this->makeStaff('card-sales-paid-at', ['report.read']);
        $site = $staff->sites()->firstOrFail();
        $member = Member::create([
            'tenant_id' => $staff->tenant_id,
            'member_no' => 'MEM-PAID-AT',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $paidThisMonth = MemberCardOrder::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORDER-PAID-THIS-MONTH',
            'amount' => 300,
            'status' => MemberCardOrderStatus::Paid,
            'paid_at' => now(),
        ]);
        $paidThisMonth->forceFill(['created_at' => now()->subMonth(), 'updated_at' => now()])->save();

        $paidLastMonth = MemberCardOrder::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORDER-PAID-LAST-MONTH',
            'amount' => 900,
            'status' => MemberCardOrderStatus::Paid,
            'paid_at' => now()->subMonth(),
        ]);
        $paidLastMonth->forceFill(['created_at' => now(), 'updated_at' => now()])->save();

        $legacy = MemberCardOrder::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORDER-LEGACY-FALLBACK',
            'amount' => 200,
            'status' => MemberCardOrderStatus::Paid,
        ]);
        $legacy->forceFill(['created_at' => now()->subMinute(), 'updated_at' => now()])->save();

        $year = now()->year;
        $month = now()->month;
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/card-sales/summary?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.salesCount', 2)
            ->assertJsonPath('data.totals.revenue', '500.00');

        $detail = $this->getJson(
            "/api/v1/staff/sites/{$site->id}/reports/card-sales/detail?year={$year}&month={$month}",
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.orderNo', 'ORDER-PAID-THIS-MONTH');

        $this->assertSame(
            $paidThisMonth->paid_at->toIso8601String(),
            $detail->json('data.items.0.paidAt'),
        );
        $this->assertSame(
            $legacy->created_at->toIso8601String(),
            $detail->json('data.items.1.paidAt'),
        );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeStaff(string $tenantCode, array $permissions): Staff
    {
        $tenant = Tenant::create(['name' => $tenantCode, 'code' => $tenantCode]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => '主馆', 'code' => $tenantCode.'-main', 'status' => 'active']);

        return $this->makeStaffInTenant($tenant, $site, $permissions);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeStaffInTenant(Tenant $tenant, Site $site, array $permissions): Staff
    {
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Staff', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '员工',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => '角色', 'code' => 'role-'.fake()->unique()->numerify('###'), 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'test']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return $staff;
    }
}
