<?php

namespace Tests\Feature;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\WechatIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_lists_directory_at_site(): void
    {
        [$staff, $site, $role] = $this->actAsStaff(['staff.directory.read']);
        $member = $this->createDirectoryMember($site, $role, 'Coach A', 'active');
        $departed = $this->createDirectoryMember($site, $role, 'Coach B', 'departed');

        $this->getJson("/api/v1/staff/sites/{$site->id}/staff-directory")
            ->assertOk()
            ->assertJsonPath('data.activeCount', 2)
            ->assertJsonPath('data.departedCount', 1)
            ->assertJsonFragment(['id' => $member->id, 'displayName' => 'Coach A'])
            ->assertJsonFragment(['id' => $departed->id, 'displayName' => 'Coach B', 'status' => 'departed']);
    }

    public function test_staff_reads_directory_member_detail(): void
    {
        [$staff, $site, $role] = $this->actAsStaff(['staff.directory.read']);
        $member = $this->createDirectoryMember($site, $role, 'Detail Coach', 'active', ['coach', 'sales']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/staff-directory/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.displayName', 'Detail Coach')
            ->assertJsonPath('data.role.id', $role->id)
            ->assertJsonPath('data.capabilities.0', 'coach')
            ->assertJsonPath('data.capabilities.1', 'sales')
            ->assertJsonPath('data.hasFutureBookings', false);
    }

    public function test_staff_creates_and_updates_directory_member(): void
    {
        [$staff, $site, $role] = $this->actAsStaff(['staff.directory.read', 'staff.directory.write']);

        $create = $this->postJson("/api/v1/staff/sites/{$site->id}/staff-directory", [
            'displayName' => '新教练',
            'mobile' => '13800138001',
            'gender' => 'female',
            'capabilities' => ['coach'],
            'roleId' => $role->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.displayName', '新教练')
            ->assertJsonPath('data.mobile', '13800138001')
            ->assertJsonPath('data.status', 'active');

        $memberId = $create->json('data.id');

        $this->patchJson("/api/v1/staff/sites/{$site->id}/staff-directory/{$memberId}", [
            'displayName' => '资深教练',
            'capabilities' => ['coach', 'sales'],
            'version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.displayName', '资深教练')
            ->assertJsonPath('data.capabilities.1', 'sales')
            ->assertJsonPath('data.version', 2);
    }

    public function test_soft_departure_marks_staff_as_departed(): void
    {
        [$staff, $site, $role] = $this->actAsStaff([
            'staff.directory.read',
            'staff.departure.soft',
        ]);
        $member = $this->createDirectoryMember($site, $role, 'Leaving Coach', 'active');

        $this->postJson("/api/v1/staff/sites/{$site->id}/staff-directory/{$member->id}/departure")
            ->assertOk()
            ->assertJsonPath('data.status', 'departed')
            ->assertJsonPath('data.leftOn', now()->toDateString());

        $member->refresh();
        $this->assertSame('departed', $member->status);
    }

    public function test_soft_departure_blocked_when_future_bookings_exist(): void
    {
        [$staff, $site, $role] = $this->actAsStaff([
            'staff.directory.read',
            'staff.departure.soft',
        ]);
        $member = $this->createDirectoryMember($site, $role, 'Busy Coach', 'active');
        $this->seedFutureCoachSession($site, $member);

        $this->postJson("/api/v1/staff/sites/{$site->id}/staff-directory/{$member->id}/departure")
            ->assertStatus(409)
            ->assertJsonPath('code', 'STAFF_DEPARTURE_BLOCKED');
    }

    public function test_transfer_ownership_requires_wechat_binding(): void
    {
        [$staff, $site, $role] = $this->actAsStaff([
            'staff.directory.read',
            'staff.directory.transfer-ownership',
        ]);
        $member = $this->createDirectoryMember($site, $role, 'Target Coach', 'active');
        $site->update(['owner_staff_id' => $staff->id]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/staff-directory/{$member->id}/transfer-ownership")
            ->assertStatus(422)
            ->assertJsonPath('code', 'STAFF_TRANSFER_NOT_INVITED');

        WechatIdentity::create([
            'account_id' => $member->account_id,
            'appid' => 'wx-staff-test',
            'openid' => 'openid-target',
            'unionid' => 'union-target',
            'session_key_ciphertext' => 'encrypted',
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/staff-directory/{$member->id}/transfer-ownership")
            ->assertOk()
            ->assertJsonPath('data.ownerStaffId', $member->id);

        $site->refresh();
        $this->assertSame($member->id, $site->owner_staff_id);
    }

    public function test_roles_and_permission_catalog_endpoints(): void
    {
        [$staff, $site, $role] = $this->actAsStaff(['staff.directory.read', 'staff.directory.write']);

        $this->getJson('/api/v1/staff/roles')
            ->assertOk()
            ->assertJsonFragment(['id' => $role->id, 'name' => $role->name]);

        $this->getJson('/api/v1/staff/permission-catalog')
            ->assertOk()
            ->assertJsonStructure(['data' => ['modules']]);
    }

    public function test_directory_requires_permission_and_is_site_scoped(): void
    {
        [$staff, $site] = $this->actAsStaff([]);
        $otherSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/staff-directory")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/staff-directory")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    public function test_other_tenant_directory_member_is_not_visible(): void
    {
        [$staff, $site] = $this->actAsStaff(['staff.directory.read']);
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'code' => 'other-tenant']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign',
            'code' => 'foreign',
            'status' => 'active',
        ]);
        $foreignRole = Role::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Role',
            'code' => 'foreign-role',
            'status' => 'active',
        ]);
        $foreignMember = $this->createDirectoryMember($otherSite, $foreignRole, 'Foreign Coach', 'active');

        $this->getJson("/api/v1/staff/sites/{$site->id}/staff-directory/{$foreignMember->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site, $role] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site, $role];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: Role}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Directory Admin', 'status' => 'active']);
        $site = Site::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Studio',
            'code' => 'main',
            'status' => 'active',
            'owner_staff_id' => null,
        ]);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Directory Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $site->update(['owner_staff_id' => $staff->id]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Manager',
            'code' => 'manager',
            'status' => 'active',
        ]);

        if ($permissions !== []) {
            $permissionRole = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Directory Access',
                'code' => 'directory-access',
                'status' => 'active',
            ]);
            foreach ($permissions as $code) {
                $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'identity']);
                $permissionRole->permissions()->attach($permission->id);
            }
            $staff->roles()->attach($permissionRole->id, ['tenant_id' => $tenant->id, 'site_id' => null]);
        }

        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => $site->id]);

        return [$staff, $site, $role];
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function createDirectoryMember(Site $site, Role $role, string $name, string $status, array $capabilities = ['coach']): Staff
    {
        $account = Account::create(['display_name' => $name, 'status' => 'active']);
        $member = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => $name,
            'status' => $status,
        ]);
        $member->sites()->attach($site->id, [
            'tenant_id' => $site->tenant_id,
            'is_primary' => true,
            'capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR),
        ]);
        $member->roles()->attach($role->id, [
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
        ]);

        return $member;
    }

    private function seedFutureCoachSession(Site $site, Staff $coach): void
    {
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => 'Future Class',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => 'group',
            'version' => 1,
        ]);
    }
}
