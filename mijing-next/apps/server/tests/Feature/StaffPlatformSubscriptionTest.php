<?php

namespace Tests\Feature;

use App\Enums\MemberCardOrderStatus;
use App\Enums\TenantSubscriptionStatus;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\PlatformServiceAgreement;
use App\Models\PlatformSubscriptionPlan;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffPlatformSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_returns_active_plan_tiers(): void
    {
        [, $site] = $this->actAsStaff(['platform.subscription.read']);
        $this->seedPlatformCatalog();

        $response = $this->getJson('/api/v1/staff/platform/subscription/pricing');

        $response->assertOk()
            ->assertJsonPath('data.list.0.code', 'saas-1y')
            ->assertJsonPath('data.list.0.yearName', '1年')
            ->assertJsonPath('data.list.0.realPrice', '980')
            ->assertJsonPath('data.list.0.originalPrice', '1280')
            ->assertJsonPath('data.list.1.yearName', '2年');
    }

    public function test_agreement_returns_current_platform_html(): void
    {
        [, $site] = $this->actAsStaff(['platform.subscription.read']);
        $this->seedPlatformCatalog();

        $response = $this->getJson('/api/v1/staff/platform/subscription/agreement');

        $response->assertOk()
            ->assertJsonPath('data.version', '2026-01')
            ->assertJsonPath('data.title', '觅境软件服务协议')
            ->assertJsonPath('data.html', '<p>平台服务协议正文</p>')
            ->assertJsonPath('data.support.customServicer', true)
            ->assertJsonPath('data.support.servicerNickName', '觅境客服');
    }

    public function test_site_status_returns_tenant_license_and_software_expire_overlay(): void
    {
        [, $site] = $this->actAsStaff(['platform.subscription.read']);
        $this->seedPlatformCatalog();

        Tenant::query()->whereKey($site->tenant_id)->update([
            'subscription_plan' => 'saas-1y',
            'subscription_expires_at' => now()->addDays(15)->startOfDay(),
            'subscription_status' => TenantSubscriptionStatus::Active->value,
        ]);

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/platform/subscription/status");

        $response->assertOk()
            ->assertJsonPath('data.siteId', $site->id)
            ->assertJsonPath('data.subscription.planCode', 'saas-1y')
            ->assertJsonPath('data.subscription.planLabel', '1年')
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.daysRemaining', 15)
            ->assertJsonPath('data.subscription.softwareExpire.daynum', 15)
            ->assertJsonPath('data.support.customServicer', true)
            ->assertJsonPath('data.support.protocolUrl', 'https://support.example.com/protocol');
    }

    public function test_endpoints_require_staff_session(): void
    {
        [, $site] = $this->makeStaff(['platform.subscription.read']);

        $this->getJson('/api/v1/staff/platform/subscription/pricing')->assertUnauthorized();
        $this->getJson('/api/v1/staff/platform/subscription/agreement')->assertUnauthorized();
        $this->getJson("/api/v1/staff/sites/{$site->id}/platform/subscription/status")->assertUnauthorized();
    }

    public function test_endpoints_require_platform_subscription_read_permission(): void
    {
        [, $site] = $this->actAsStaff([]);
        $this->seedPlatformCatalog();

        $this->getJson('/api/v1/staff/platform/subscription/pricing')->assertForbidden();
        $this->getJson('/api/v1/staff/platform/subscription/agreement')->assertForbidden();
        $this->getJson("/api/v1/staff/sites/{$site->id}/platform/subscription/status")->assertForbidden();
    }

    public function test_responses_do_not_leak_member_card_orders(): void
    {
        [, $site, $member] = $this->actAsStaff(['platform.subscription.read', 'order.read']);
        $this->seedPlatformCatalog();

        $order = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'MCO-SECRET-001',
            'amount' => '2999.00',
            'status' => MemberCardOrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $pricing = $this->getJson('/api/v1/staff/platform/subscription/pricing')->assertOk();
        $agreement = $this->getJson('/api/v1/staff/platform/subscription/agreement')->assertOk();
        $status = $this->getJson("/api/v1/staff/sites/{$site->id}/platform/subscription/status")->assertOk();

        foreach ([$pricing, $agreement, $status] as $response) {
            $payload = json_encode($response->json());
            $this->assertStringNotContainsString('MCO-SECRET-001', $payload);
            $this->assertStringNotContainsString('2999.00', $payload);
            $this->assertStringNotContainsString('member_card_order', $payload);
            $this->assertStringNotContainsString('orderNo', $payload);
        }
    }

    public function test_site_status_is_scoped_to_staff_tenant(): void
    {
        [, $site] = $this->actAsStaff(['platform.subscription.read']);
        $otherSite = Site::create([
            'tenant_id' => Tenant::create(['name' => 'Other', 'code' => 'other-tenant'])->id,
            'name' => 'Other Site',
            'code' => 'other-site',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/platform/subscription/status")
            ->assertNotFound();
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site, $member] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site, $member];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Platform Admin', 'status' => 'active']);
        $site = Site::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'code' => 'main',
            'status' => 'active',
        ]);

        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Platform Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Platform',
            'code' => 'platform-'.fake()->unique()->slug(1),
            'status' => 'active',
        ]);

        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'platform']);
            $role->permissions()->attach($permission->id);
        }

        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => 'Leak Test Member',
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$staff, $site, $member];
    }

    private function seedPlatformCatalog(): void
    {
        PlatformSubscriptionPlan::query()->delete();
        PlatformServiceAgreement::query()->delete();

        PlatformSubscriptionPlan::create([
            'code' => 'saas-1y',
            'label' => '1年',
            'duration_days' => 365,
            'price_cents' => 98000,
            'original_price_cents' => 128000,
            'sort_order' => 1,
            'status' => 'active',
        ]);
        PlatformSubscriptionPlan::create([
            'code' => 'saas-2y',
            'label' => '2年',
            'duration_days' => 730,
            'price_cents' => 168000,
            'original_price_cents' => 228000,
            'sort_order' => 2,
            'status' => 'active',
        ]);

        PlatformServiceAgreement::create([
            'version' => '2026-01',
            'title' => '觅境软件服务协议',
            'html' => '<p>平台服务协议正文</p>',
            'effective_at' => now()->subMonth(),
            'status' => 'current',
            'support_contact_enabled' => true,
            'support_contact_name' => '觅境客服',
            'support_protocol_url' => 'https://support.example.com/protocol',
        ]);
    }
}
