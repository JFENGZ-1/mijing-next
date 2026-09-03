<?php

namespace Tests\Feature\Admin;

use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = SuperAdmin::query()->create([
            'username' => 'demo.admin',
            'name' => '演示数据管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin, ['api', 'client:admin', 'admin:platform']);
    }

    public function test_demo_data_generation_requires_demo_mode(): void
    {
        config()->set('wechat.staff_demo.auto_provision', false);

        $this->postJson('/api/v1/admin/demo-data/generate')->assertStatus(409);
    }

    public function test_admin_can_idempotently_generate_a_complete_demo_workspace(): void
    {
        config()->set('wechat.staff_demo', [
            'auto_provision' => true,
            'tenant_code' => 'mijing',
            'site_code' => 'main',
        ]);

        $first = $this->postJson('/api/v1/admin/demo-data/generate')
            ->assertOk()
            ->assertJsonPath('data.tenant.code', 'mijing')
            ->assertJsonPath('data.site.code', 'main')
            ->assertJsonPath('data.counts.members', 1)
            ->assertJsonPath('data.counts.cardProducts', 3)
            ->assertJsonPath('data.counts.courses', 2);

        $firstCounts = $first->json('data.counts');
        $this->assertGreaterThanOrEqual(1, $firstCounts['staff']);
        $this->assertGreaterThanOrEqual(1, $firstCounts['memberCards']);
        $this->assertGreaterThanOrEqual(2, $firstCounts['orders']);
        $this->assertGreaterThanOrEqual(1, $firstCounts['scheduleSessions']);

        $second = $this->postJson('/api/v1/admin/demo-data/generate')->assertOk();
        $this->assertSame($firstCounts, $second->json('data.counts'));
        $this->assertSame(1, Member::query()->where('member_no', 'DEMO-MEMBER-001')->count());
        $this->assertSame(1, Staff::query()->where('employee_no', 'DEMO-OWNER')->count());

        $role = Role::query()->where('code', 'demo-operator')->firstOrFail();
        $this->assertSame(Permission::query()->count(), $role->permissions()->count());
        $this->assertDatabaseHas('super_admin_audit_logs', [
            'method' => 'POST',
            'path' => '/api/v1/admin/demo-data/generate',
            'status_code' => 200,
        ]);
    }

    public function test_console_command_generates_demo_data_for_deployment(): void
    {
        config()->set('wechat.staff_demo', [
            'auto_provision' => true,
            'tenant_code' => 'mijing',
            'site_code' => 'main',
        ]);

        $this->assertSame(0, Artisan::call('demo-data:generate'));
        $this->assertStringContainsString('Demo data ready: tenant=mijing, site=main', Artisan::output());
        $this->assertDatabaseHas('roles', ['code' => 'demo-operator', 'status' => 'active']);
        $this->assertGreaterThan(50, Permission::query()->count());
    }
}
