<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCardProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_permission_can_list_active_catalog_products(): void
    {
        [, $site] = $this->actAsStaff(['card-product.catalog.read']);
        $active = $this->createProduct($site, '储值卡 1000', CardProductCatalogStatus::Active, 10);
        $this->createProduct($site, '旧模板', CardProductCatalogStatus::Archived, 20, archived: true);

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/card-products")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $active->id)
            ->assertJsonPath('data.items.0.name', '储值卡 1000')
            ->assertJsonPath('data.items.0.cardType', 'stored_value')
            ->assertJsonPath('data.items.0.price', '1000.00');

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products/{$active->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $active->id)
            ->assertJsonPath('data.courseScopes', []);
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createProduct($site, '储值卡 1000', CardProductCatalogStatus::Active);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_catalog_is_isolated_by_tenant_and_site(): void
    {
        [$staff, $site] = $this->actAsStaff(['card-product.catalog.read']);
        $visible = $this->createProduct($site, '本馆卡', CardProductCatalogStatus::Active);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherProduct = CardProduct::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'card_type' => CardType::StoredValue,
            'name' => '外馆卡',
            'price' => 500,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
        ]);

        $secondSite = Site::create(['tenant_id' => $staff->tenant_id, 'name' => 'Branch', 'code' => 'branch', 'status' => 'active']);
        $branchProduct = $this->createProduct($secondSite, '分店卡', CardProductCatalogStatus::Active);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $visible->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products/{$otherProduct->id}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products/{$branchProduct->id}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/card-products")->assertNotFound();
    }

    public function test_archived_products_are_excluded_from_catalog_list(): void
    {
        [, $site] = $this->actAsStaff(['card-product.catalog.read']);
        $this->createProduct($site, '在售卡', CardProductCatalogStatus::Active, 1);
        $archived = $this->createProduct($site, '已归档卡', CardProductCatalogStatus::Archived, 2, archived: true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.name', '在售卡');

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products/{$archived->id}")
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'archived');
    }

    public function test_staff_can_list_archived_products_for_recycle_bin(): void
    {
        [, $site] = $this->actAsStaff(['card-product.catalog.read']);
        $this->createProduct($site, '在售卡', CardProductCatalogStatus::Active, 1);
        $archived = $this->createProduct($site, '已归档卡', CardProductCatalogStatus::Archived, 2, archived: true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products?catalogStatus=archived")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $archived->id)
            ->assertJsonPath('data.items.0.catalogStatus', 'archived');
    }

    private function createProduct(
        Site $site,
        string $name,
        CardProductCatalogStatus $catalogStatus,
        int $sortOrder = 0,
        bool $archived = false,
    ): CardProduct {
        return CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::StoredValue,
            'name' => $name,
            'price' => 1000,
            'face_value' => 1000,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => $catalogStatus,
            'sort_order' => $sortOrder,
            'archived_at' => $archived ? now() : null,
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
        $account = Account::create(['display_name' => 'Card Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Card Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Cards', 'code' => 'cards', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'card']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
