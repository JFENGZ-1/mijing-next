<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberCardPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_saleable_card_products_for_site(): void
    {
        [$account, $tenant, $site] = $this->seedPurchasableMember();
        $onSale = $this->createProduct($site, CardType::StoredValue, [
            'name' => '在售储值卡',
            'price' => 888,
            'face_value' => 1000,
        ]);
        $this->createProduct($site, CardType::Count, [
            'name' => '停售次卡',
            'sale_status' => CardProductSaleStatus::Stopped,
        ]);
        $this->createProduct($site, CardType::Period, [
            'name' => '归档期限卡',
            'catalog_status' => CardProductCatalogStatus::Archived,
        ]);

        $this->actAsMember($account);

        $this->getJson($this->catalogPath($tenant, $site))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $onSale->id)
            ->assertJsonPath('data.items.0.name', '在售储值卡')
            ->assertJsonPath('data.items.0.price', '888.00')
            ->assertJsonPath('data.items.0.faceValue', '1000.00');
    }

    public function test_member_purchase_submits_demo_paid_order_and_issues_card(): void
    {
        [$account, $tenant, $site, $member] = $this->seedPurchasableMember();
        $product = $this->createProduct($site, CardType::StoredValue, [
            'name' => '自助储值卡',
            'price' => 500,
            'face_value' => 600,
        ]);
        $commandKey = (string) Str::uuid();

        $this->actAsMember($account);

        $response = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.order.status', MemberCardOrderStatus::Paid->value)
            ->assertJsonPath('data.order.originalAmount', '500.00')
            ->assertJsonPath('data.memberCard.cardType', 'stored_value')
            ->assertJsonPath('data.memberCard.status', MemberCardStatus::Active->value)
            ->assertJsonPath('data.memberCard.cachedBalance', '600.00');

        $orderId = $response->json('data.order.id');
        $cardId = $response->json('data.memberCard.id');

        $this->assertDatabaseHas('member_card_orders', [
            'id' => $orderId,
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'member_card_id' => $cardId,
            'status' => MemberCardOrderStatus::Paid->value,
            'command_key' => $commandKey,
        ]);

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'tenant_id' => $tenant->id,
            'member_card_id' => $cardId,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Purchase->value,
            'amount_delta' => '600.00',
            'command_key' => $commandKey,
            'actor_account_id' => $account->id,
        ]);
    }

    public function test_member_purchase_is_idempotent_by_command_key(): void
    {
        [$account, $tenant, $site, $member] = $this->seedPurchasableMember();
        $product = $this->createProduct($site, CardType::Count, [
            'initial_count' => 10,
            'activation_mode' => 'on_first_use',
        ]);
        $commandKey = (string) Str::uuid();

        $this->actAsMember($account);

        $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])->assertCreated();

        $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.memberCard.status', MemberCardStatus::PendingActivation->value);

        $this->assertSame(1, MemberCardOrder::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, MemberCard::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->count());
    }

    public function test_sale_category_distinguishes_same_product_renewal_from_cross_product_purchase(): void
    {
        [$account, $tenant, $site] = $this->seedPurchasableMember();
        $firstProduct = $this->createProduct($site, CardType::StoredValue);
        $otherProduct = $this->createProduct($site, CardType::Count);
        $firstCommand = (string) Str::uuid();
        $this->actAsMember($account);

        $first = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $firstProduct->id,
            'commandKey' => $firstCommand,
        ])->assertCreated();
        $this->assertSame('new', MemberCardOrder::findOrFail($first->json('data.order.id'))->metadata['saleCategory']);

        $replay = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $firstProduct->id,
            'commandKey' => $firstCommand,
        ])->assertOk();
        $this->assertSame($first->json('data.order.id'), $replay->json('data.order.id'));
        $this->assertSame('new', MemberCardOrder::findOrFail($replay->json('data.order.id'))->metadata['saleCategory']);

        $renewal = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $firstProduct->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertSame('renewal', MemberCardOrder::findOrFail($renewal->json('data.order.id'))->metadata['saleCategory']);

        $crossProduct = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $otherProduct->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertSame('new', MemberCardOrder::findOrFail($crossProduct->json('data.order.id'))->metadata['saleCategory']);
    }

    public function test_incomplete_profile_blocks_member_purchase(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'purchase-blocked']);
        $account = Account::create(['status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-BLOCKED',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $product = $this->createProduct($site, CardType::StoredValue);
        $this->publishPrivacyDocument();

        $this->actAsMember($account);

        $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $product->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertStatus(422);
    }

    public function test_member_cannot_purchase_products_from_other_tenant_site(): void
    {
        [$account, $tenant, $site] = $this->seedPurchasableMember();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-purchase']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherProduct = $this->createProduct($otherSite, CardType::StoredValue);

        $this->actAsMember($account);

        $this->postJson($this->purchasePath($tenant, $otherSite), [
            'cardProductId' => $otherProduct->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Account, 1: Tenant, 2: Site, 3: Member}
     */
    private function seedPurchasableMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => '购卡会员', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-PURCHASE',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => '购卡会员',
            'mobile_last4' => '8000',
            'mobile_verified_at' => now(),
            'version' => 1,
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '购卡会员',
        ]);
        $document = $this->publishPrivacyDocument();
        LegalConsent::create([
            'account_id' => $account->id,
            'legal_document_id' => $document->id,
            'action' => 'accepted',
            'source' => 'member-miniapp',
            'occurred_at' => now(),
        ]);

        return [$account, $tenant, $site, $member];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProduct(Site $site, CardType $cardType, array $overrides = []): CardProduct
    {
        return CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => $cardType,
            'name' => '测试卡',
            'price' => 100,
            'face_value' => $cardType === CardType::StoredValue ? 100 : null,
            'initial_count' => $cardType === CardType::Count ? 5 : null,
            'validity_days' => $cardType === CardType::Period ? 30 : null,
            'activation_mode' => 'immediate',
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            ...$overrides,
        ]);
    }

    private function catalogPath(Tenant $tenant, Site $site): string
    {
        return "/api/v1/member/card-products?tenantId={$tenant->id}&siteId={$site->id}";
    }

    private function purchasePath(Tenant $tenant, Site $site): string
    {
        return "/api/v1/member/card-purchases?tenantId={$tenant->id}&siteId={$site->id}";
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }

    private function publishPrivacyDocument(): LegalDocument
    {
        return LegalDocument::create([
            'scope_key' => 'global',
            'type' => 'privacy',
            'version' => 'test-v1',
            'title' => 'Privacy',
            'content' => 'Test privacy content',
            'content_hash' => hash('sha256', 'Test privacy content'),
            'status' => 'published',
            'is_required' => true,
            'published_at' => now(),
        ]);
    }
}
