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

class SiteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_manage_permission_cannot_create_site(): void
    {
        [$account, , $staff] = $this->makeStaff(withManagePermission: false);
        $this->actAsStaff($account, $staff);

        $this->postJson('/api/v1/sites', ['name' => '二店', 'code' => 'second'])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_cannot_read_another_tenants_site(): void
    {
        [$account, , $staff] = $this->makeStaff(withManagePermission: true);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Site', 'code' => 'other']);
        $this->actAsStaff($account, $staff);

        $this->getJson("/api/v1/sites/{$otherSite->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    public function test_site_update_requires_current_version(): void
    {
        [$account, $site, $staff] = $this->makeStaff(withManagePermission: true);
        $this->actAsStaff($account, $staff);

        $this->patchJson("/api/v1/sites/{$site->id}", ['name' => 'Changed', 'version' => 999])
            ->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT');
    }

    public function test_me_serializes_role_permissions(): void
    {
        [$account, , $staff] = $this->makeStaff(withManagePermission: true);
        $this->actAsStaff($account, $staff);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.staffProfiles.0.permissions.0', 'organization.site.manage')
            ->assertJsonPath('data.staffProfiles.0.sites.0.permissions.0', 'organization.site.manage');
    }

    public function test_me_calculates_permissions_for_each_site_context(): void
    {
        [$account, $firstSite, $staff] = $this->makeStaff(withManagePermission: false);
        $secondSite = Site::create([
            'tenant_id' => $staff->tenant_id, 'name' => 'Second', 'code' => 'second', 'status' => 'active',
        ]);
        $staff->sites()->attach($secondSite->id, ['tenant_id' => $staff->tenant_id, 'is_primary' => false]);
        $permission = Permission::create(['name' => 'CRM read', 'code' => 'crm.member.read', 'module' => 'crm']);
        $role = Role::create([
            'tenant_id' => $staff->tenant_id, 'name' => 'First site CRM', 'code' => 'first-crm', 'status' => 'active',
        ]);
        $role->permissions()->attach($permission);
        $staff->roles()->attach($role->id, [
            'tenant_id' => $staff->tenant_id, 'site_id' => $firstSite->id,
        ]);
        $this->actAsStaff($account, $staff);

        $response = $this->getJson('/api/v1/me')->assertOk();
        $sites = collect($response->json('data.staffProfiles.0.sites'))->keyBy('id');
        $this->assertContains('crm.member.read', $sites[$firstSite->id]['permissions']);
        $this->assertNotContains('crm.member.read', $sites[$secondSite->id]['permissions']);
    }

    public function test_member_session_cannot_call_staff_site_api_even_for_same_account(): void
    {
        [$account] = $this->makeStaff(withManagePermission: true);
        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson('/api/v1/sites')
            ->assertForbidden()
            ->assertJsonPath('code', 'TOKEN_AUDIENCE_INVALID');
    }

    public function test_member_session_does_not_receive_staff_context_from_me(): void
    {
        [$account] = $this->makeStaff(withManagePermission: true);
        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonCount(0, 'data.staffProfiles');
    }

    public function test_disabled_account_loses_existing_staff_access(): void
    {
        [$account, , $staff] = $this->makeStaff(withManagePermission: true);
        $this->actAsStaff($account, $staff);
        $account->update(['status' => 'disabled']);

        $this->getJson('/api/v1/sites')
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_disabled_tenant_loses_existing_staff_access(): void
    {
        [$account, , $staff] = $this->makeStaff(withManagePermission: true);
        $this->actAsStaff($account, $staff);
        $staff->tenant->update(['status' => 'disabled']);

        $this->getJson('/api/v1/sites')
            ->assertForbidden()
            ->assertJsonPath('code', 'STAFF_ACCESS_DENIED');
    }

    private function makeStaff(bool $withManagePermission): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role', 'code' => 'role', 'status' => 'active']);
        if ($withManagePermission) {
            $permission = Permission::create([
                'name' => 'Manage sites',
                'code' => 'organization.site.manage',
                'module' => 'organization',
            ]);
            $role->permissions()->attach($permission);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$account, $site, $staff];
    }

    private function actAsStaff(Account $account, Staff $staff): void
    {
        Sanctum::actingAs($account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
    }
}
