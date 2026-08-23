<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_login_read_profile_and_logout(): void
    {
        SuperAdmin::query()->create([
            'username' => 'root.admin',
            'name' => '平台管理员',
            'email' => 'root@example.com',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/v1/admin/auth/login', [
            'login' => 'ROOT.ADMIN',
            'password' => 'Correct-Horse-2026',
            'deviceName' => 'feature-test',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('data.admin.username', 'root.admin')
            ->assertJsonPath('data.admin.role', 'platform_super_admin');

        $token = $login->json('data.accessToken');
        $this->withToken($token)
            ->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.name', '平台管理员');

        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.loggedOut', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/me')->assertUnauthorized();
    }

    public function test_invalid_password_and_disabled_admin_are_rejected(): void
    {
        $admin = SuperAdmin::query()->create([
            'username' => 'disabled.admin',
            'name' => '停用管理员',
            'password' => Hash::make('Correct-Horse-2026'),
            'status' => 'disabled',
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'login' => $admin->username,
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('code', 'SUPER_ADMIN_CREDENTIALS_INVALID');

        $this->postJson('/api/v1/admin/auth/login', [
            'login' => $admin->username,
            'password' => 'Correct-Horse-2026',
        ])->assertForbidden()->assertJsonPath('code', 'SUPER_ADMIN_DISABLED');
    }

    public function test_regular_account_cannot_access_super_admin_routes(): void
    {
        $account = Account::query()->create(['display_name' => '普通员工', 'status' => 'active']);
        Sanctum::actingAs($account, ['api', 'client:staff']);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'SUPER_ADMIN_ACCESS_DENIED');
    }
}
