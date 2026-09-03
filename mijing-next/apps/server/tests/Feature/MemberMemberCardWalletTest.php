<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberMemberCardWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_wallet_lists_own_active_pending_and_frozen_cards(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $active = $this->createCard($tenant, $site, $member, 'MC-ACTIVE', MemberCardStatus::Active, CardType::StoredValue, ['cached_balance' => 500]);
        $pending = $this->createCard($tenant, $site, $member, 'MC-PENDING', MemberCardStatus::PendingActivation, CardType::Count, ['cached_remaining_count' => 3]);
        $frozen = $this->createCard($tenant, $site, $member, 'MC-FROZEN', MemberCardStatus::Frozen, CardType::Period, [
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
        ]);

        $this->actAsMember($account);

        $response = $this->getJson("/api/v1/member/member-cards?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$active->id, $pending->id, $frozen->id], $ids);
        $this->assertStringStartsWith('****', $response->json('data.0.cardNoMasked'));
        $this->assertArrayNotHasKey('cardNo', $response->json('data.0'));
        $this->assertArrayNotHasKey('issuedByStaffId', $response->json('data.0'));
    }

    public function test_member_wallet_excludes_archived_hidden_and_terminal_status_cards(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $visible = $this->createCard($tenant, $site, $member, 'MC-VISIBLE', MemberCardStatus::Active, CardType::StoredValue);
        $this->createCard($tenant, $site, $member, 'MC-ARCHIVED', MemberCardStatus::Active, CardType::StoredValue, ['archived_at' => now()]);
        $this->createCard($tenant, $site, $member, 'MC-HIDDEN', MemberCardStatus::Active, CardType::StoredValue, [
            'member_visibility' => MemberCardVisibility::Hidden,
        ]);
        $this->createCard($tenant, $site, $member, 'MC-EXPIRED', MemberCardStatus::Expired, CardType::Period);
        $this->createCard($tenant, $site, $member, 'MC-VOIDED', MemberCardStatus::Voided, CardType::StoredValue);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/member-cards?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_member_sees_only_own_cards_within_tenant(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $own = $this->createCard($tenant, $site, $member, 'MC-OWN', MemberCardStatus::Active, CardType::StoredValue);

        $otherMember = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $this->createCard($tenant, $site, $otherMember, 'MC-OTHER', MemberCardStatus::Active, CardType::StoredValue);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-wallet']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherTenantMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-CROSS',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        $this->createCard($otherTenant, $otherSite, $otherTenantMember, 'MC-9001', MemberCardStatus::Active, CardType::StoredValue);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/member-cards?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->getJson("/api/v1/member/member-cards?tenantId={$otherTenant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cardNoMasked', '****9001');
    }

    public function test_member_wallet_requires_tenant_id(): void
    {
        [$account] = $this->seedMember();
        $this->actAsMember($account);

        $this->getJson('/api/v1/member/member-cards')
            ->assertStatus(422)
            ->assertJsonPath('code', 'TENANT_ID_REQUIRED');
    }

    public function test_member_can_fetch_single_wallet_card(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $card = $this->createCard($tenant, $site, $member, 'MC-SHOW', MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 880,
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/member-cards/{$card->id}?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $card->id)
            ->assertJsonPath('data.balance', '880.00');
    }

    public function test_member_cannot_fetch_other_members_card(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $otherMember = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-OTHER-SHOW',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $otherCard = $this->createCard($tenant, $site, $otherMember, 'MC-OTHER-SHOW', MemberCardStatus::Active, CardType::StoredValue);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/member-cards/{$otherCard->id}?tenantId={$tenant->id}")
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Account, 1: Tenant, 2: Member, 3: Site}
     */
    private function seedMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Wallet Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-WALLET',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return [$account, $tenant, $member, $site];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCard(
        Tenant $tenant,
        Site $site,
        Member $member,
        string $cardNo,
        MemberCardStatus $status,
        CardType $cardType,
        array $overrides = [],
    ): MemberCard {
        $product = CardProduct::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => $cardType,
            'name' => '测试卡',
            'price' => 100,
            'face_value' => $cardType === CardType::StoredValue ? 100 : null,
            'initial_count' => $cardType === CardType::Count ? 5 : null,
            'validity_days' => $cardType === CardType::Period ? 30 : null,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
        ]);

        return MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => $cardType,
            'card_no' => $cardNo,
            'status' => $status,
            'product_snapshot' => [
                'name' => $product->name,
                'cardType' => $cardType->value,
            ],
            'issued_at' => now(),
            ...$overrides,
        ]);
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
