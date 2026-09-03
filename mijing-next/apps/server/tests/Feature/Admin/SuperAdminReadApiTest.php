<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\Site;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminReadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = SuperAdmin::query()->create([
            'username' => 'root.admin',
            'name' => '平台管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin, ['api', 'client:admin', 'admin:platform']);
    }

    public function test_dashboard_and_tenant_list_return_cross_tenant_real_aggregates(): void
    {
        [$tenant, $site, $member] = $this->createMemberFixture();

        MemberCardOrder::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ADMIN-PAID-001',
            'amount' => '2680.00',
            'status' => 'paid',
        ]);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.totals.tenants', 1)
            ->assertJsonPath('data.totals.sites', 1)
            ->assertJsonPath('data.totals.members', 1)
            ->assertJsonPath('data.totals.paidRevenue', '2680.00');

        $this->getJson('/api/v1/admin/tenants?query=觅境')
            ->assertOk()
            ->assertJsonPath('data.items.0.code', 'mijing')
            ->assertJsonPath('data.items.0.siteCount', 1)
            ->assertJsonPath('data.items.0.memberCount', 1);
    }

    public function test_member_list_returns_tenant_context_and_masks_mobile(): void
    {
        [$tenant] = $this->createMemberFixture();

        $this->getJson("/api/v1/admin/members?tenantId={$tenant->id}&query=晴")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberNo', 'M-10001')
            ->assertJsonPath('data.items.0.name', '周雨晴')
            ->assertJsonPath('data.items.0.mobile', '138****2064')
            ->assertJsonPath('data.items.0.tenant.code', 'mijing');
    }

    private function createMemberFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => '觅境运动',
            'code' => 'mijing',
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '滨江旗舰店',
            'code' => 'binjiang',
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
        $account = Account::query()->create([
            'display_name' => '周雨晴',
            'mobile' => '13800002064',
            'status' => 'active',
        ]);
        $member = Member::query()->create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'M-10001',
            'status' => 'active',
            'source' => 'admin-test',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::query()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '周雨晴',
            'mobile_last4' => '2064',
        ]);

        return [$tenant, $site, $member];
    }
}
