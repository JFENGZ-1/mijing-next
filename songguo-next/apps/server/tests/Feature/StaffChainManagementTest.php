<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffChainManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_sites_include_brand_and_branch_metadata(): void
    {
        [$staff, $siteA] = $this->actAsStaff(['tenant.settings.chain.read']);
        $tenant = Tenant::query()->findOrFail($staff->tenant_id);
        $tenant->update(['chain_brand_name' => '松果瑜伽']);
        Site::create(['tenant_id' => $tenant->id, 'name' => '分店B', 'code' => 'branch-b', 'status' => 'active', 'address' => '地址B']);

        $this->getJson('/api/v1/staff/chain/sites')
            ->assertOk()
            ->assertJsonPath('data.brand.name', '松果瑜伽')
            ->assertJsonPath('data.brand.chainActivated', true)
            ->assertJsonCount(2, 'data.sites');
    }

    public function test_chain_brand_can_be_updated_by_site_manager(): void
    {
        [, $site] = $this->actAsStaff(['tenant.settings.chain.read', 'organization.site.manage']);

        $this->putJson('/api/v1/staff/chain/brand', [
            'name' => '连锁品牌',
            'logoUrl' => 'https://cdn.example.com/logo.png',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', '连锁品牌')
            ->assertJsonPath('data.logoUrl', 'https://cdn.example.com/logo.png');
    }

    public function test_chain_store_courses_lists_linked_products(): void
    {
        [$staff, $siteA, $siteB] = $this->actAsStaffWithTwoSites(['tenant.settings.chain.read', 'card-product.editor.write']);
        $course = Course::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $siteA->id,
            'name' => '阿斯汤加',
            'course_type' => CourseType::Group,
            'catalog_status' => CourseCatalogStatus::Active,
            'duration_minutes' => 60,
        ]);
        $product = CardProduct::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $siteA->id,
            'name' => '通用次卡',
            'card_type' => CardType::Count,
            'price' => 1000,
            'initial_count' => 10,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'scope_config' => ['linkedSiteIds' => [$siteA->id, $siteB->id]],
        ]);
        CardProductCourseScope::create([
            'tenant_id' => $staff->tenant_id,
            'card_product_id' => $product->id,
            'scope_kind' => 'single',
            'scope_key' => (string) $course->id,
            'price_override' => 80,
        ]);

        $this->getJson('/api/v1/staff/chain/store-courses')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.cardProductId', $product->id)
            ->assertJsonPath('data.items.0.courseScopeCount', 1);
    }

    public function test_chain_staff_lists_tenant_wide_assignments(): void
    {
        [$staff, $siteA, $siteB] = $this->actAsStaffWithTwoSites(['tenant.settings.chain.read', 'staff.directory.read']);
        $hq = $this->createTenantWideStaff($staff->tenant_id, $siteA, 'HQ Admin');

        $this->getJson('/api/v1/staff/chain/staff')
            ->assertOk()
            ->assertJsonFragment(['id' => $hq->id, 'displayName' => 'HQ Admin', 'hasTenantWideRole' => true]);
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

  /**
     * @return array{0: Staff, 1: Site, 2: Site}
     */
    private function actAsStaffWithTwoSites(array $permissions): array
    {
        [$staff, $siteA] = $this->actAsStaff($permissions);
        $siteB = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => '分店B',
            'code' => 'branch-b',
            'status' => 'active',
            'address' => '地址B',
        ]);
        $staff->sites()->attach($siteB->id, ['tenant_id' => $staff->tenant_id, 'is_primary' => false]);

        return [$staff, $siteA, $siteB];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Chain Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => '总店', 'code' => 'hq', 'status' => 'active', 'address' => '地址A']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Chain Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Chain', 'code' => 'chain', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'org']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createTenantWideStaff(int $tenantId, Site $site, string $name): Staff
    {
        $account = Account::create(['display_name' => $name, 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenantId,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => $name,
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenantId, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenantId, 'name' => 'HQ', 'code' => 'hq', 'status' => 'active']);
        $staff->roles()->attach($role->id, ['tenant_id' => $tenantId, 'site_id' => null]);

        return $staff;
    }
}
