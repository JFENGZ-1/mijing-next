<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_contact_returns_seeded_defaults(): void
    {
        [, $site] = $this->actAsStaff(['tenant.settings.support.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/support/contact")
            ->assertOk()
            ->assertJsonPath('data.phone', '400-000-0000')
            ->assertJsonStructure(['data' => ['phone', 'wechatId', 'hours', 'faqLinks', 'supportHint', 'siteName']]);
    }

    public function test_video_help_returns_demo_catalog_when_unconfigured(): void
    {
        [, $site] = $this->actAsStaff(['tenant.settings.support.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/support/video-help")
            ->assertOk()
            ->assertJsonCount(3, 'data.videos')
            ->assertJsonPath('data.videos.0.isPlaceholder', true);
    }

    public function test_support_endpoints_require_permission(): void
    {
        [, $site] = $this->actAsStaff(['tenant.settings.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/support/contact")->assertForbidden();
        $this->getJson("/api/v1/staff/sites/{$site->id}/support/video-help")->assertForbidden();
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
        $account = Account::create(['display_name' => 'Support Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active', 'address' => '地址']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Support Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'code' => 'support', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'tenant-config']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
