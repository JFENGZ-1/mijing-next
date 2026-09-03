<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberMemberCardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_hide_and_restore_card_visibility(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $card = $this->createCard($tenant, $site, $member, 'MC-HIDE', MemberCardStatus::Active, CardType::StoredValue);
        $this->actAsMember($account);

        $hideKey = (string) Str::uuid();
        $this->postJson("/api/v1/member/member-cards/{$card->id}/hide", [
            'commandKey' => $hideKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.memberVisibility', MemberCardVisibility::Hidden->value);

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::VisibilityChange->value,
            'command_key' => $hideKey,
        ]);

        $this->getJson("/api/v1/member/member-cards?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $restoreKey = (string) Str::uuid();
        $this->postJson("/api/v1/member/member-cards/{$card->id}/restore-visibility", [
            'commandKey' => $restoreKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.memberVisibility', MemberCardVisibility::Visible->value);

        $this->getJson("/api/v1/member/member-cards?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $card->id);
    }

    public function test_hidden_card_remains_visible_to_staff_crm(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $card = $this->createCard($tenant, $site, $member, 'MC-STAFF-VIEW', MemberCardStatus::Active, CardType::StoredValue);
        $this->actAsMember($account);

        $this->postJson("/api/v1/member/member-cards/{$card->id}/hide", [
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        [$staff] = $this->makeStaffForTenant($tenant, $site, ['member-card.read']);
        \Illuminate\Support\Facades\DB::table('member_sites')->insert([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'home',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/member-cards")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $card->id)
            ->assertJsonPath('data.0.memberVisibility', MemberCardVisibility::Hidden->value);
    }

    public function test_member_cannot_hide_another_members_card(): void
    {
        [$account, $tenant, , $site] = $this->seedMember();
        $otherMember = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $otherCard = $this->createCard($tenant, $site, $otherMember, 'MC-OTHER', MemberCardStatus::Active, CardType::StoredValue);

        $this->actAsMember($account);

        $this->postJson("/api/v1/member/member-cards/{$otherCard->id}/hide", [
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_hide_does_not_remove_ledger_history(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $card = $this->createCard($tenant, $site, $member, 'MC-LEDGER', MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 500,
        ]);
        $this->actAsMember($account);

        $this->postJson("/api/v1/member/member-cards/{$card->id}/hide", [
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $this->getJson("/api/v1/member/member-cards/{$card->id}/ledger-entries")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', fn ($total) => $total >= 1);

        $this->assertSame('500.00', number_format((float) MemberCard::findOrFail($card->id)->cached_balance, 2, '.', ''));
    }

    public function test_visibility_commands_are_idempotent_when_command_key_reused(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $card = $this->createCard($tenant, $site, $member, 'MC-IDEM', MemberCardStatus::Active, CardType::StoredValue);
        $this->actAsMember($account);
        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/member/member-cards/{$card->id}/hide", [
            'commandKey' => $commandKey,
        ])->assertCreated();

        $this->postJson("/api/v1/member/member-cards/{$card->id}/hide", [
            'commandKey' => $commandKey,
        ])->assertOk();

        $this->assertSame(
            1,
            \App\Models\EntitlementLedgerEntry::query()
                ->where('member_card_id', $card->id)
                ->where('entry_type', EntitlementLedgerEntryType::VisibilityChange->value)
                ->where('command_key', $commandKey)
                ->count(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Account, 1: Tenant, 2: Member, 3: Site}
     */
    private function seedMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Visibility Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-VIS',
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
            'member_visibility' => MemberCardVisibility::Visible,
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

    /**
     * @return array{0: \App\Models\Staff}
     */
    private function makeStaffForTenant(Tenant $tenant, Site $site, array $permissions): array
    {
        $account = Account::create(['display_name' => 'Staff', 'status' => 'active']);
        $staff = \App\Models\Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = \App\Models\Role::create(['tenant_id' => $tenant->id, 'name' => 'Cards', 'code' => 'cards', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = \App\Models\Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'card']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff];
    }
}
