<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffWechatBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_is_denied_without_bound_identity(): void
    {
        [$staff] = $this->seedAdministrator();
        $this->fakeStaffWechat('devtools-openid');

        $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'staff',
            'code' => 'staff-login-code',
            'deviceName' => 'staff-miniapp',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'STAFF_ACCESS_DENIED');

        $this->assertDatabaseMissing('wechat_identities', [
            'appid' => 'staff-appid',
            'openid' => 'devtools-openid',
            'account_id' => $staff->account_id,
        ]);
    }

    public function test_staff_login_succeeds_after_bind_openid_command(): void
    {
        [$staff] = $this->seedAdministrator();
        $this->fakeStaffWechat('devtools-openid');

        Artisan::call('staff:bind-openid', [
            'openid' => 'devtools-openid',
            '--employee-no' => $staff->employee_no,
        ]);

        $login = $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'staff',
            'code' => 'staff-login-code',
            'deviceName' => 'staff-miniapp',
        ])->assertOk();

        $token = $login->json('data.accessToken');
        $this->assertSame($staff->tenant_id, $login->json('data.staff.tenantId'));

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.staffProfiles.0.tenantId', $staff->tenant_id);
    }

    public function test_staff_login_succeeds_after_bind_wechat_code_command(): void
    {
        [$staff] = $this->seedAdministrator();
        $this->fakeStaffWechat('devtools-openid');

        Artisan::call('staff:bind-wechat-code', [
            'code' => 'bind-code',
            '--employee-no' => $staff->employee_no,
        ]);

        $this->assertDatabaseHas('wechat_identities', [
            'appid' => 'staff-appid',
            'openid' => 'devtools-openid',
            'account_id' => $staff->account_id,
        ]);

        $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'staff',
            'code' => 'staff-login-code',
            'deviceName' => 'staff-miniapp',
        ])
            ->assertOk()
            ->assertJsonPath('data.staff.tenantId', $staff->tenant_id);
    }

    public function test_bind_openid_command_is_blocked_outside_local_environments(): void
    {
        config()->set('app.env', 'production');

        $exitCode = Artisan::call('staff:bind-openid', ['openid' => 'devtools-openid']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'only available in local or testing environments',
            Artisan::output(),
        );
    }

    /**
     * @return array{0: Staff}
     */
    private function seedAdministrator(): array
    {
        config()->set('wechat.apps.staff', ['appid' => 'staff-appid', 'secret' => 'staff-secret']);

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

    private function fakeStaffWechat(string $openid): void
    {
        Http::fake([
            'https://api.weixin.qq.com/sns/jscode2session*' => Http::response([
                'openid' => $openid,
                'session_key' => 'session-key',
            ], 200),
        ]);
    }
}
