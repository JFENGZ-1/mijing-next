<?php

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBusinessScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_selector_requires_super_admin_authentication(): void
    {
        $tenant = Tenant::query()->create([
            'name' => '觅境运动',
            'code' => 'mijing',
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);

        $this->getJson("/api/v1/admin/tenants/{$tenant->id}/sites")
            ->assertUnauthorized();
    }

    public function test_site_selector_is_explicitly_scoped_to_route_tenant(): void
    {
        $admin = SuperAdmin::query()->create([
            'username' => 'scope.admin',
            'name' => '作用域管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin, ['api', 'client:admin', 'admin:platform']);

        $tenantA = $this->tenant('觅境一区', 'mijing-a');
        $tenantB = $this->tenant('觅境二区', 'mijing-b');
        $siteA = $this->site($tenantA, '滨江店', 'binjiang');
        $this->site($tenantB, '朝阳店', 'chaoyang');

        $this->getJson("/api/v1/admin/tenants/{$tenantA->id}/sites?query=滨江")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $siteA->id)
            ->assertJsonPath('data.items.0.tenantId', $tenantA->id)
            ->assertJsonMissing(['朝阳店']);
    }

    private function tenant(string $name, string $code): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'code' => $code,
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
    }

    private function site(Tenant $tenant, string $name, string $code): Site
    {
        return Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'code' => $code,
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
    }
}
