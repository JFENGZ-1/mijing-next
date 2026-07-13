<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevWechatBindingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loads_in_testing_environment(): void
    {
        [$staff] = $this->seedAdministrator();

        $this->get('/dev/wechat-bindings')
            ->assertOk()
            ->assertSee('微信 OpenID 绑定')
            ->assertSee($staff->employee_no)
            ->assertSee('staff-appid')
            ->assertDontSee('devtools-openid-secret');
    }

    public function test_bind_staff_openid_creates_wechat_identity_row(): void
    {
        [$staff] = $this->seedAdministrator();
        config()->set('wechat.apps.staff', ['appid' => 'staff-appid', 'secret' => 'staff-secret']);

        $this->from('/dev/wechat-bindings')
            ->post('/dev/wechat-bindings/staff', [
                'employee_no' => $staff->employee_no,
                'openid' => 'devtools-openid-secret',
            ])
            ->assertRedirect('/dev/wechat-bindings')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('wechat_identities', [
            'appid' => 'staff-appid',
            'openid' => 'devtools-openid-secret',
            'account_id' => $staff->account_id,
        ]);

        $flash = session('success');
        $this->assertIsString($flash);
        $this->assertStringContainsString('cret', $flash);
        $this->assertStringNotContainsString('devtools-openid-secret', $flash);
    }

    public function test_page_is_hidden_in_production_without_token(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.dev_tools_token', 'secret-token');

        $this->get('/dev/wechat-bindings')->assertNotFound();
    }

    public function test_page_loads_in_production_with_valid_token(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.dev_tools_token', 'secret-token');
        $this->seedAdministrator();

        $this->get('/dev/wechat-bindings?token=secret-token')
            ->assertOk()
            ->assertSee('微信 OpenID 绑定');
    }

    public function test_unbind_removes_wechat_identity_row(): void
    {
        [$staff] = $this->seedAdministrator();
        config()->set('wechat.apps.staff', ['appid' => 'staff-appid', 'secret' => 'staff-secret']);

        $this->post('/dev/wechat-bindings/staff', [
            'employee_no' => $staff->employee_no,
            'openid' => 'devtools-openid-secret',
        ]);

        $identityId = (int) $this->app['db']->table('wechat_identities')
            ->where('openid', 'devtools-openid-secret')
            ->value('id');

        $this->from('/dev/wechat-bindings')
            ->post('/dev/wechat-bindings/unbind', ['identity_id' => $identityId])
            ->assertRedirect('/dev/wechat-bindings')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('wechat_identities', [
            'id' => $identityId,
        ]);
    }

    /**
     * @return array{0: Staff}
     */
    private function seedAdministrator(): array
    {
        config()->set('wechat.apps.staff', ['appid' => 'staff-appid', 'secret' => 'staff-secret']);
        config()->set('wechat.apps.member', ['appid' => 'member-appid', 'secret' => 'member-secret']);

        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'songguo', 'status' => 'active']);
        $account = Account::create(['display_name' => '系统管理员', 'status' => 'active']);
        $site = Site::create([
            'tenant_id' => $tenant->id,
            'name' => '示范场馆',
            'code' => 'main',
            'status' => 'active',
        ]);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => 'ADMIN001',
            'name' => '系统管理员',
            'status' => 'active',
        ]);
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => '租户所有者',
            'code' => 'owner',
            'status' => 'active',
        ]);
        $permission = Permission::create([
            'name' => 'Manage sites',
            'code' => 'organization.site.manage',
            'module' => 'organization',
        ]);
        $role->permissions()->attach($permission);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff];
    }
}
