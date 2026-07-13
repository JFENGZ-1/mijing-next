<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
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

class MemberOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_own_orders(): void
    {
        [$account, $tenant, $site, $member] = $this->seedMember();
        $product = $this->createProduct($site, '自助储值卡');
        $order = MemberCardOrder::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-TEST-1',
            'amount' => 500,
            'status' => MemberCardOrderStatus::Paid,
            'command_key' => (string) Str::uuid(),
            'metadata' => ['channel' => 'demo_auto_paid', 'cardProductId' => $product->id],
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/orders?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $order->id)
            ->assertJsonPath('data.items.0.productName', '自助储值卡')
            ->assertJsonPath('data.items.0.siteName', $site->name);
    }

    public function test_member_can_read_order_detail_with_card_summary(): void
    {
        [$account, $tenant, $site, $member] = $this->seedMember();
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-ORDER-1',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '详情储值卡'],
            'cached_balance' => 600,
            'issued_at' => now(),
        ]);
        $order = MemberCardOrder::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => 'ORD-TEST-2',
            'amount' => 500,
            'status' => MemberCardOrderStatus::Paid,
            'command_key' => (string) Str::uuid(),
            'metadata' => ['channel' => 'demo_auto_paid'],
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/orders/{$order->id}?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.memberCard.id', $card->id)
            ->assertJsonPath('data.memberCard.name', '详情储值卡')
            ->assertJsonPath('data.memberCard.cachedBalance', '600.00');
    }

    public function test_member_cannot_read_other_members_order(): void
    {
        [$account, $tenant, $site] = $this->seedMember();
        $otherAccount = Account::create(['status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $otherAccount->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        $order = MemberCardOrder::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $otherMember->id,
            'order_no' => 'ORD-OTHER',
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
            'command_key' => (string) Str::uuid(),
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/orders/{$order->id}?tenantId={$tenant->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Site, 3: Member}
     */
    private function seedMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => '订单会员', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-ORDER',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => '订单会员',
            'gender' => 'male',
            'version' => 1,
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '订单会员',
            'version' => 1,
        ]);
        $document = LegalDocument::create([
            'scope_key' => 'global',
            'type' => 'privacy',
            'version' => '1.0',
            'title' => '隐私政策',
            'content' => 'privacy',
            'content_hash' => hash('sha256', 'privacy'),
            'status' => 'published',
            'is_required' => true,
            'published_at' => now(),
        ]);
        LegalConsent::create([
            'account_id' => $account->id,
            'legal_document_id' => $document->id,
            'action' => 'accepted',
            'source' => 'test',
            'occurred_at' => now(),
        ]);

        return [$account, $tenant, $site, $member];
    }

    private function createProduct(Site $site, string $name): CardProduct
    {
        return CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::StoredValue,
            'name' => $name,
            'price' => 500,
            'face_value' => 600,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'activation_mode' => 'immediate',
            'sort_order' => 1,
            'version' => 1,
        ]);
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
