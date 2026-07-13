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

class StaffSiteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_reads_site_profile(): void
    {
        [$staff, $site] = $this->actAsStaff(['site.profile.read']);
        $site->update([
            'phone' => '13800138000',
            'address' => '南山区科技园',
            'description' => '瑜伽馆',
            'region' => [
                'provinceCode' => '440000',
                'provinceName' => '广东省',
                'cityCode' => '440300',
                'cityName' => '深圳市',
                'countyCode' => '440305',
                'countyName' => '南山区',
            ],
            'business_hours' => [
                ['weekDays' => '1234567', 'timeValue' => '09:00~21:00'],
            ],
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.id', $site->id)
            ->assertJsonPath('data.name', $site->name)
            ->assertJsonPath('data.phone', '13800138000')
            ->assertJsonPath('data.address', '南山区科技园')
            ->assertJsonPath('data.description', '瑜伽馆')
            ->assertJsonPath('data.region.cityName', '深圳市')
            ->assertJsonPath('data.businessHours.0.timeValue', '09:00~21:00')
            ->assertJsonPath('data.version', 1);
    }

    public function test_staff_updates_allowed_profile_fields(): void
    {
        [$staff, $site] = $this->actAsStaff(['site.profile.read', 'site.profile.write']);

        $this->patchJson("/api/v1/staff/sites/{$site->id}/profile", [
            'name' => '松果瑜伽南山店',
            'phone' => '13900139000',
            'address' => '科技园南路 1 号',
            'description' => '精品小班课',
            'region' => [
                'provinceCode' => '440000',
                'provinceName' => '广东省',
                'cityCode' => '440300',
                'cityName' => '深圳市',
                'countyCode' => '440305',
                'countyName' => '南山区',
            ],
            'businessHours' => [
                ['weekDays' => '12345', 'timeValue' => '08:00~20:00'],
                ['weekDays' => '67', 'timeValue' => '10:00~18:00'],
            ],
            'version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', '松果瑜伽南山店')
            ->assertJsonPath('data.phone', '13900139000')
            ->assertJsonPath('data.businessHours.1.weekDays', '67')
            ->assertJsonPath('data.version', 2);

        $site->refresh();
        $this->assertSame('松果瑜伽南山店', $site->name);
        $this->assertSame('13900139000', $site->phone);
        $this->assertCount(2, $site->business_hours);
    }

    public function test_staff_without_read_permission_is_denied(): void
    {
        [$staff, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/profile")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_without_write_permission_cannot_update_profile(): void
    {
        [$staff, $site] = $this->actAsStaff(['site.profile.read']);

        $this->patchJson("/api/v1/staff/sites/{$site->id}/profile", [
            'name' => 'Forbidden Rename',
            'version' => 1,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_cannot_read_another_tenants_site_profile(): void
    {
        [$staff, $site] = $this->actAsStaff(['site.profile.read']);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Site',
            'code' => 'other',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/profile")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    public function test_staff_cannot_update_site_outside_assignment(): void
    {
        [$staff, $site] = $this->actAsStaff(['site.profile.read', 'site.profile.write']);
        $otherSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Unassigned',
            'code' => 'unassigned',
            'status' => 'active',
        ]);

        $this->patchJson("/api/v1/staff/sites/{$otherSite->id}/profile", [
            'name' => 'Should Fail',
            'version' => 1,
        ])
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    public function test_staff_reads_region_constants(): void
    {
        $this->actAsStaff(['site.profile.read']);

        $this->getJson('/api/v1/staff/constants/regions')
            ->assertOk()
            ->assertJsonPath('data.province.0.citiesProvinceName', '北京市')
            ->assertJsonPath('data.city.0.citiesCityName', '北京市')
            ->assertJsonPath('data.county.0.citiesCountyName', '东城区');
    }

    public function test_member_token_cannot_read_site_profile(): void
    {
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson('/api/v1/staff/sites/1/profile')->assertForbidden();
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
        $account = Account::create(['display_name' => 'Site Admin', 'status' => 'active']);
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
            'name' => 'Site Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        if ($permissions !== []) {
            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Site Profile',
                'code' => 'site-profile',
                'status' => 'active',
            ]);
            foreach ($permissions as $code) {
                $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'organization']);
                $role->permissions()->attach($permission->id);
            }
            $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);
        }

        return [$staff, $site];
    }
}
