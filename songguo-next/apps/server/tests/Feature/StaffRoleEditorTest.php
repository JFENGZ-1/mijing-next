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

class StaffRoleEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_creates_and_updates_custom_role_with_permissions(): void
    {
        [$staff, $site, $permissions] = $this->actAsStaff(['staff.directory.read', 'staff.directory.write']);

        $create = $this->postJson('/api/v1/staff/roles', [
            'name' => '前台顾问',
            'permissionIds' => [$permissions[0]->id, $permissions[1]->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', '前台顾问')
            ->assertJsonPath('data.isSystem', false)
            ->assertJsonPath('data.permissionCount', 2);

        $roleId = $create->json('data.id');

        $this->getJson("/api/v1/staff/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $roleId)
            ->assertJsonPath('data.permissions.0.code', $permissions[0]->code);

        $this->postJson('/api/v1/staff/roles', [
            'id' => $roleId,
            'name' => '资深顾问',
            'permissionIds' => [$permissions[0]->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', '资深顾问')
            ->assertJsonPath('data.permissionCount', 1);
    }

    public function test_system_role_cannot_be_updated(): void
    {
        [$staff, $site, $permissions, $systemRole] = $this->actAsStaffWithSystemRole(['staff.directory.write']);

        $this->postJson('/api/v1/staff/roles', [
            'id' => $systemRole->id,
            'name' => '篡改系统角色',
            'permissionIds' => [$permissions[0]->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'STAFF_ROLE_SYSTEM_IMMUTABLE');
    }

    public function test_role_editor_requires_write_permission(): void
    {
        [$staff, $site, $permissions] = $this->actAsStaff(['staff.directory.read']);

        $this->postJson('/api/v1/staff/roles', [
            'name' => '无权角色',
            'permissionIds' => [$permissions[0]->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson('/api/v1/staff/roles/999')
            ->assertNotFound();
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: list<Permission>}
     */
    private function actAsStaff(array $permissions): array
    {
        [$staff, $site, $permissionModels, $systemRole] = $this->makeStaff($permissions);

        return [$staff, $site, $permissionModels];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: list<Permission>, 3: Role}
     */
    private function actAsStaffWithSystemRole(array $permissions): array
    {
        return $this->makeStaff($permissions);
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: list<Permission>, 3: Role}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Role Admin', 'status' => 'active']);
        $site = Site::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Studio',
            'code' => 'main',
            'status' => 'active',
        ]);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Role Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        $permissionModels = [];
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Directory Access',
            'code' => 'directory-access',
            'status' => 'active',
        ]);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'identity']);
            $role->permissions()->attach($permission->id);
            $permissionModels[] = $permission;
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        $systemRole = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Manager',
            'code' => 'manager',
            'is_system' => true,
            'status' => 'active',
        ]);

        Sanctum::actingAs($account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$tenant->id}"]);

        return [$staff, $site, $permissionModels, $systemRole];
    }
}
