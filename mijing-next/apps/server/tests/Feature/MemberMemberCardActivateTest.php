<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberMemberCardActivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_activate_pending_count_card(): void
    {
        [$account, $member, $card] = $this->seedPendingCard(CardType::Count, 'on_first_use');

        $this->actAsMember($account);

        $this->postJson("/api/v1/member/member-cards/{$card->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.id', $card->id);

        $this->assertSame(MemberCardStatus::Active, $card->fresh()->status);
    }

    public function test_member_activate_sets_period_validity_window(): void
    {
        [$account, , $card] = $this->seedPendingCard(CardType::Period, 'on_first_use', ['validity_days' => 45]);

        $this->actAsMember($account);

        $this->postJson("/api/v1/member/member-cards/{$card->id}/activate", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('data.validFrom', now()->toDateString())
            // validityDays includes the activation day, so a 45-day card ends at +44.
            ->assertJsonPath('data.validUntil', now()->addDays(44)->toDateString());

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::ValidityChange->value,
        ]);
    }

    public function test_activate_is_idempotent_when_already_active(): void
    {
        [$account, , $card] = $this->seedPendingCard(CardType::Count, 'on_first_use');
        $card->update(['status' => MemberCardStatus::Active]);

        $this->actAsMember($account);

        $this->postJson("/api/v1/member/member-cards/{$card->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_member_cannot_activate_another_members_card(): void
    {
        [$account, , $card] = $this->seedPendingCard(CardType::Count, 'on_first_use');
        $otherAccount = Account::create(['display_name' => 'Other', 'status' => 'active']);

        $this->actAsMember($otherAccount);

        $this->postJson("/api/v1/member/member-cards/{$card->id}/activate")->assertNotFound();
    }

    public function test_blocked_member_cannot_activate_card(): void
    {
        [$account, $member, $card] = $this->seedPendingCard(CardType::Count, 'on_first_use');
        $member->update(['app_access_status' => 'blocked']);

        $this->actAsMember($account);

        $this->postJson("/api/v1/member/member-cards/{$card->id}/activate")
            ->assertForbidden()
            ->assertJsonPath('code', 'MEMBER_APP_ACCESS_BLOCKED');
    }

    /**
     * @param  array<string, mixed>  $productOverrides
     * @return array{0: Account, 1: Member, 2: MemberCard}
     */
    private function seedPendingCard(CardType $cardType, string $activationMode, array $productOverrides = []): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-ACT-1',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        $productDefaults = [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => $cardType,
            'name' => '待激活卡',
            'price' => 100,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'activation_mode' => $activationMode,
        ];
        if ($cardType === CardType::Count) {
            $productDefaults['initial_count'] = 5;
        }
        if ($cardType === CardType::Period) {
            $productDefaults['validity_days'] = 30;
        }

        $product = CardProduct::create([...$productDefaults, ...$productOverrides]);

        $snapshot = [
            'cardProductId' => $product->id,
            'cardType' => $cardType->value,
            'name' => $product->name,
            'activationMode' => $activationMode,
            'initialCount' => $product->initial_count,
            'validityDays' => $product->validity_days,
        ];

        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => $cardType,
            'card_no' => 'MC-PENDING-1',
            'status' => MemberCardStatus::PendingActivation,
            'product_snapshot' => $snapshot,
            'cached_remaining_count' => $cardType === CardType::Count ? 5 : null,
            'issued_at' => now(),
        ]);

        EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => 'credit',
            'count_delta' => $cardType === CardType::Count ? 5 : null,
            'occurred_at' => now(),
        ]);

        return [$account, $member, $card];
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
