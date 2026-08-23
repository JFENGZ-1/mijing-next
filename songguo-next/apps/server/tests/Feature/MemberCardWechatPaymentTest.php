<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\EntitlementLedgerEntry;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\Payments\WechatPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberCardWechatPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'wechat', 'payment.wechat.webhook_secret' => 'test-webhook-secret']);
        $this->app->singleton(PaymentGateway::class, fn () => new WechatPaymentGateway);
    }

    public function test_wechat_driver_creates_pending_payment_order_without_card(): void
    {
        [$account, $tenant, $site] = $this->seedPurchasableMember();
        $product = $this->createProduct($site, CardType::StoredValue, [
            'name' => '微信储值卡',
            'price' => 300,
            'face_value' => 350,
        ]);
        $commandKey = (string) Str::uuid();

        $this->actAsMember($account);

        $response = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.order.status', MemberCardOrderStatus::PendingPayment->value)
            ->assertJsonPath('data.payment.driver', 'wechat')
            ->assertJsonPath('data.payment.autoPaid', false)
            ->assertJsonMissingPath('data.memberCard');

        $orderNo = $response->json('data.order.orderNo');
        $this->assertDatabaseHas('member_card_orders', [
            'order_no' => $orderNo,
            'status' => MemberCardOrderStatus::PendingPayment->value,
            'member_card_id' => null,
        ]);
        $this->assertSame(0, MemberCard::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_member_can_resume_own_pending_wechat_payment(): void
    {
        [$account, $tenant, $site] = $this->seedPurchasableMember();
        $product = $this->createProduct($site, CardType::StoredValue, ['price' => 288]);
        $this->actAsMember($account);

        $created = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $product->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $orderId = $created->json('data.order.id');
        $prepayId = $created->json('data.payment.prepayId');
        $this->postJson("/api/v1/member/orders/{$orderId}/payment?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.order.status', MemberCardOrderStatus::PendingPayment->value)
            ->assertJsonPath('data.payment.driver', 'wechat')
            ->assertJsonPath('data.payment.prepayId', $prepayId)
            ->assertJsonStructure(['data' => ['payment' => ['paymentParams' => [
                'timeStamp', 'nonceStr', 'package', 'signType', 'paySign',
            ]]]]);
    }

    public function test_paid_order_cannot_resume_payment(): void
    {
        [$account, $tenant, $site, $member] = $this->seedPurchasableMember();
        $order = MemberCardOrder::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-ALREADY-PAID',
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
            'command_key' => (string) Str::uuid(),
        ]);
        $this->actAsMember($account);

        $this->postJson("/api/v1/member/orders/{$order->id}/payment?tenantId={$tenant->id}")
            ->assertStatus(409);
    }

    public function test_wechat_webhook_fulfills_pending_order_and_issues_card(): void
    {
        [$account, $tenant, $site, $member] = $this->seedPurchasableMember();
        $product = $this->createProduct($site, CardType::StoredValue, [
            'price' => 400,
            'face_value' => 450,
        ]);
        $commandKey = (string) Str::uuid();

        $this->actAsMember($account);
        $orderNo = $this->postJson($this->purchasePath($tenant, $site), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])->json('data.order.orderNo');

        $payload = json_encode([
            'event_type' => 'TRANSACTION.SUCCESS',
            'out_trade_no' => $orderNo,
            'transaction_id' => 'wx_txn_test_001',
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        $this->postJson('/webhooks/wechat-pay', json_decode($payload, true), [
            'X-Wechat-Signature' => $signature,
        ])
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.created', true);

        $order = MemberCardOrder::query()->where('order_no', $orderNo)->firstOrFail();
        $this->assertSame(MemberCardOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->member_card_id);

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'member_card_id' => $order->member_card_id,
            'entry_type' => EntitlementLedgerEntryType::Purchase->value,
            'command_key' => $commandKey,
        ]);
    }

    public function test_wechat_webhook_rejects_invalid_signature(): void
    {
        $this->postJson('/webhooks/wechat-pay', [
            'event_type' => 'TRANSACTION.SUCCESS',
            'out_trade_no' => 'ORD-MISSING',
        ], [
            'X-Wechat-Signature' => 'invalid',
        ])->assertUnauthorized();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Site, 3: Member}
     */
    private function seedPurchasableMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => '微信购卡会员', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-WX-PAY',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => '微信购卡会员',
            'mobile_last4' => '9000',
            'mobile_verified_at' => now(),
            'version' => 1,
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '微信购卡会员',
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
