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

class TenantSettingsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_hub_returns_gated_sections_and_items(): void
    {
        [$staff, $site] = $this->actAsStaff([
            'tenant.settings.read',
            'tenant.settings.defaults.read',
            'tenant.crm.field-config.write',
            'booking.policy.read',
            'site.profile.read',
            'site.rooms.read',
            'staff.directory.read',
            'card-product.editor.write',
            'schedule.session.read',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/settings-hub")
            ->assertOk()
            ->assertJsonPath('data.featureFlags.shopBasics', true)
            ->assertJsonPath('data.featureFlags.shopDefault', true)
            ->assertJsonStructure([
                'data' => [
                    'featureFlags' => [
                        'shopBasics', 'shopDefault', 'shopMemberConfig', 'shopManagerTool',
                        'multipleShopConfig', 'shopServiceCenter',
                    ],
                    'setupCounts' => [
                        'siteProfile', 'cardProducts', 'staffDirectory', 'courseCatalog',
                        'cardCourseLinks', 'scheduleSessions',
                    ],
                    'sections' => [[
                        'key', 'label', 'legacyFlag', 'visible', 'items' => [[
                            'key', 'label', 'route', 'capability', 'requiredPermission',
                            'enabled', 'implemented', 'setupIncomplete',
                        ]],
                    ]],
                ],
            ])
            ->assertJsonFragment([
                'key' => 'crm-field-config',
                'label' => '会员资料',
                'route' => '/subpackages/settings/crm/field-config/index',
                'enabled' => true,
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'booking-policy',
                'label' => '预约设置',
                'route' => '/subpackages/settings/booking-policy/index',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'card-course-links',
                'route' => '/subpackages/settings/card-products/course-matrix',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'room-catalog',
                'label' => '教室管理',
                'route' => '/subpackages/settings/rooms/index',
                'requiredPermission' => 'site.rooms.read',
                'implemented' => true,
                'enabled' => true,
            ])
            ->assertJsonFragment([
                'key' => 'schedule-sessions',
                'route' => '/subpackages/course/timetable/index',
                'implemented' => true,
            ]);
    }

    public function test_settings_hub_hides_defaults_without_sub_flag_permission(): void
    {
        [, $site] = $this->actAsStaff([
            'tenant.settings.read',
            'tenant.crm.field-config.write',
        ]);

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/settings-hub")
            ->assertOk()
            ->assertJsonPath('data.featureFlags.shopDefault', false);

        $sectionKeys = collect($response->json('data.sections'))->pluck('key')->all();
        $this->assertNotContains('defaults', $sectionKeys);
    }

    public function test_settings_hub_requires_tenant_settings_read_permission(): void
    {
        [, $site] = $this->actAsStaff(['site.profile.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/settings-hub")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_settings_hub_wires_chain_and_support_routes(): void
    {
        [, $site] = $this->actAsStaff([
            'tenant.settings.read',
            'tenant.settings.chain.read',
            'organization.site.read',
            'card-product.editor.write',
            'staff.directory.read',
            'tenant.settings.support.read',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/settings-hub")
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'chain-instructions',
                'route' => '/subpackages/settings/chain/instructions/index',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'chain-stores',
                'route' => '/subpackages/settings/chain/stores/index',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'customer-service',
                'route' => '/subpackages/settings/support/customer-service/index',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'video-help',
                'route' => '/subpackages/settings/support/video-help/index',
                'implemented' => true,
            ]);
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
        $account = Account::create(['display_name' => 'Settings Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Settings Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Settings', 'code' => 'settings', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'tenant-config']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
