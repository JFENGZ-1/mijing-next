<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCardProductEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_and_update_card_product_template(): void
    {
        [, $site] = $this->actAsStaff(['card-product.editor.write', 'card-product.catalog.read']);

        $create = $this->postJson("/api/v1/staff/sites/{$site->id}/card-products", [
            'cardType' => 'count',
            'name' => '瑜伽 10 次卡',
            'price' => 880,
            'initialCount' => 10,
            'validityDays' => 180,
            'validityMode' => 'from_activation',
            'activationMode' => 'on_first_use',
            'sortOrder' => 5,
            'courseScopes' => [
                [
                    'scopeKind' => 'single',
                    'scopeKey' => 'course-yoga-101',
                    'displayName' => '瑜伽基础课',
                    'sortOrder' => 0,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', '瑜伽 10 次卡')
            ->assertJsonPath('data.cardType', 'count')
            ->assertJsonPath('data.initialCount', 10)
            ->assertJsonPath('data.catalogStatus', 'active')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.courseScopes.0.scopeKey', 'course-yoga-101');

        $productId = $create->json('data.id');

        $this->putJson("/api/v1/staff/sites/{$site->id}/card-products/{$productId}", [
            'version' => 1,
            'name' => '瑜伽 12 次卡',
            'price' => 980,
            'initialCount' => 12,
            'validityDays' => 180,
            'saleStatus' => 'stopped',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', '瑜伽 12 次卡')
            ->assertJsonPath('data.initialCount', 12)
            ->assertJsonPath('data.saleStatus', 'stopped')
            ->assertJsonPath('data.version', 2);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.name', '瑜伽 12 次卡');
    }

    public function test_create_validates_card_type_specific_fields(): void
    {
        [, $site] = $this->actAsStaff(['card-product.editor.write']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products", [
            'cardType' => 'stored_value',
            'name' => '储值卡',
            'price' => 1000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products", [
            'cardType' => 'count',
            'name' => '计次卡',
            'price' => 500,
        ])->assertUnprocessable();

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products", [
            'cardType' => 'period',
            'name' => '期限卡',
            'price' => 300,
        ])->assertUnprocessable();
    }

    public function test_archive_excludes_from_catalog_and_restore_brings_back(): void
    {
        [, $site] = $this->actAsStaff([
            'card-product.editor.write',
            'card-product.archive',
            'card-product.catalog.read',
        ]);

        $product = $this->createProduct($site, '待归档卡', CardProductCatalogStatus::Active);

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'archived')
            ->assertJsonPath('data.archivedAt', fn ($value) => $value !== null);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'active')
            ->assertJsonPath('data.archivedAt', null);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $product->id);
    }

    public function test_physical_delete_is_forbidden_even_without_issued_cards(): void
    {
        [, $site] = $this->actAsStaff(['card-product.editor.write']);
        $product = $this->createProduct($site, '不可删除卡', CardProductCatalogStatus::Active);

        $this->deleteJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'CARD_PRODUCT_DELETE_FORBIDDEN');

        $this->assertDatabaseHas('card_products', ['id' => $product->id, 'catalog_status' => 'active']);
    }

    public function test_editor_changes_do_not_alter_issued_member_card_snapshot(): void
    {
        [, $site] = $this->actAsStaff(['card-product.editor.write']);
        $product = $this->createProduct($site, '原始卡名', CardProductCatalogStatus::Active, faceValue: 1000);
        $memberCard = $this->createIssuedMemberCard($site, $product, ['name' => '原始卡名', 'faceValue' => '1000.00']);

        $this->putJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}", [
            'version' => 1,
            'name' => '更新后的卡名',
            'price' => 1200,
            'faceValue' => 1200,
        ])->assertOk();

        $this->assertSame(
            ['name' => '原始卡名', 'faceValue' => '1000.00'],
            $memberCard->fresh()->product_snapshot,
        );
    }

    public function test_staff_without_write_permission_is_denied_for_editor_actions(): void
    {
        [, $site] = $this->actAsStaff(['card-product.catalog.read']);
        $product = $this->createProduct($site, '只读卡', CardProductCatalogStatus::Active);

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products", [
            'cardType' => 'stored_value',
            'name' => '新卡',
            'price' => 100,
            'faceValue' => 100,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->putJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}", [
            'version' => 1,
            'name' => '改名',
            'price' => 100,
            'faceValue' => 100,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_without_archive_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff(['card-product.editor.write']);
        $product = $this->createProduct($site, '归档权限卡', CardProductCatalogStatus::Active);

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}/archive")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_write_apis_are_isolated_by_tenant_and_site(): void
    {
        [$staff, $site] = $this->actAsStaff(['card-product.editor.write', 'card-product.archive']);
        $product = $this->createProduct($site, '本馆卡', CardProductCatalogStatus::Active);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherProduct = CardProduct::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'card_type' => CardType::StoredValue,
            'name' => '外馆卡',
            'price' => 500,
            'face_value' => 500,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
        ]);

        $secondSite = Site::create(['tenant_id' => $staff->tenant_id, 'name' => 'Branch', 'code' => 'branch', 'status' => 'active']);
        $branchProduct = $this->createProduct($secondSite, '分店卡', CardProductCatalogStatus::Active);

        $this->putJson("/api/v1/staff/sites/{$site->id}/card-products/{$otherProduct->id}", [
            'version' => 1,
            'name' => '越权',
            'price' => 1,
            'faceValue' => 1,
        ])->assertNotFound();

        $this->putJson("/api/v1/staff/sites/{$site->id}/card-products/{$branchProduct->id}", [
            'version' => 1,
            'name' => '越权',
            'price' => 1,
            'faceValue' => 1,
        ])->assertNotFound();

        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}/archive")->assertOk();
        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products/{$otherProduct->id}/archive")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/card-products")->assertNotFound();
    }

    private function createProduct(
        Site $site,
        string $name,
        CardProductCatalogStatus $catalogStatus,
        ?int $faceValue = null,
    ): CardProduct {
        return CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::StoredValue,
            'name' => $name,
            'price' => $faceValue ?? 1000,
            'face_value' => $faceValue ?? 1000,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => $catalogStatus,
            'sort_order' => 0,
            'version' => 1,
        ]);
    }

    private function createIssuedMemberCard(Site $site, CardProduct $product, array $snapshot): MemberCard
    {
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-TEST-001',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'name' => '测试会员',
        ]);

        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-TEST-001',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => $snapshot,
            'cached_balance' => 1000,
            'issued_at' => now(),
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
