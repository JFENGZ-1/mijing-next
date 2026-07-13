<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberCardAdjustTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_credit_and_debit_balance_with_cached_balance_update(): void
    {
        [$staff, $site, $member, $card] = $this->actAsStaff(['member-card.balance.adjust']);
        $this->assertSame('1000.00', $card->cached_balance);

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 200,
            'reason' => '赠送余额',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.cachedBalance', '1200.00')
            ->assertJsonCount(1, 'data.ledgerEntryIds');

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'debit',
            'amount' => 50,
            'reason' => '手动扣减',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.cachedBalance', '1150.00');

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::BalanceAdjust->value,
            'direction' => EntitlementLedgerDirection::Credit->value,
            'amount_delta' => '200.00',
            'actor_staff_id' => $staff->id,
        ]);
        $this->assertSame('1150.00', MemberCard::findOrFail($card->id)->cached_balance);
    }

    public function test_staff_can_adjust_count_card_remaining_count(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.count.adjust'], CardType::Count, [
            'cached_remaining_count' => 10,
        ]);

        $this->postJson($this->countPath($site, $card), [
            'direction' => 'debit',
            'count' => 3,
            'reason' => '补扣次数',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.cachedRemainingCount', 7);

        $this->postJson($this->countPath($site, $card), [
            'direction' => 'credit',
            'count' => 2,
            'reason' => '补偿次数',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.cachedRemainingCount', 9);
    }

    public function test_balance_correction_appends_reversal_and_correction_without_mutating_original(): void
    {
        [$staff, $site, , $card] = $this->actAsStaff(['member-card.balance.adjust']);

        $original = $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 100,
            'reason' => '首次调整',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $originalEntryId = $original->json('data.ledgerEntryIds.0');
        $originalReason = EntitlementLedgerEntry::findOrFail($originalEntryId)->reason;

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 150,
            'reason' => '更正为150',
            'commandKey' => (string) Str::uuid(),
            'correctsEntryId' => $originalEntryId,
        ])
            ->assertCreated()
            ->assertJsonPath('data.cachedBalance', '1150.00')
            ->assertJsonCount(2, 'data.ledgerEntryIds');

        $this->assertSame($originalReason, EntitlementLedgerEntry::findOrFail($originalEntryId)->reason);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Reversal->value,
            'reversal_of_id' => $originalEntryId,
            'direction' => EntitlementLedgerDirection::Debit->value,
            'amount_delta' => '100.00',
        ]);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Correction->value,
            'reversal_of_id' => $originalEntryId,
            'direction' => EntitlementLedgerDirection::Credit->value,
            'amount_delta' => '150.00',
            'actor_staff_id' => $staff->id,
        ]);
    }

    public function test_adjust_commands_are_idempotent_on_command_key(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.balance.adjust']);
        $commandKey = (string) Str::uuid();

        $first = $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 80,
            'reason' => '活动赠送',
            'commandKey' => $commandKey,
        ])->assertCreated();

        $second = $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 80,
            'reason' => '活动赠送',
            'commandKey' => $commandKey,
        ])->assertOk();

        $this->assertSame($first->json('data.ledgerEntryIds'), $second->json('data.ledgerEntryIds'));
        $this->assertSame('1080.00', MemberCard::findOrFail($card->id)->cached_balance);
        $this->assertSame(1, EntitlementLedgerEntry::query()->where('command_key', $commandKey)->count());
    }

    public function test_staff_without_adjust_permission_is_denied(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.read']);

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 10,
            'reason' => '无权限',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_debit_blocked_when_insufficient_balance(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.balance.adjust']);

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'debit',
            'amount' => 2000,
            'reason' => '超额扣减',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSUFFICIENT_BALANCE');

        $this->assertSame('1000.00', MemberCard::findOrFail($card->id)->cached_balance);
    }

    public function test_adjust_enforces_tenant_and_site_isolation(): void
    {
        [$staff, $site, , $card] = $this->actAsStaff(['member-card.balance.adjust']);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-adjust']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherCard = MemberCard::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'member_id' => Member::create([
                'tenant_id' => $otherTenant->id,
                'member_no' => 'MEM-OTHER',
                'registration_site_id' => $otherSite->id,
                'home_site_id' => $otherSite->id,
                'status' => 'active',
            ])->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-OTHER',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '外馆卡'],
            'cached_balance' => 100,
            'issued_at' => now(),
        ]);

        $this->postJson($this->balancePath($site, $otherCard), [
            'direction' => 'credit',
            'amount' => 10,
            'reason' => '跨租户',
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_staff_can_list_paginated_ledger_entries(): void
    {
        [$staff, $site, , $card] = $this->actAsStaff(['member-card.read', 'member-card.balance.adjust']);

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 10,
            'reason' => 'A',
            'commandKey' => (string) Str::uuid(),
        ]);
        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 20,
            'reason' => 'B',
            'commandKey' => (string) Str::uuid(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/ledger-entries?perPage=1")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.perPage', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.actorStaffId', $staff->id)
            ->assertJsonStructure(['data' => ['items' => [['commandKey', 'reason', 'entryType']]]]);
    }

    private function balancePath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/balance-adjustments";
    }

    private function countPath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/count-adjustments";
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard}
     */
    private function actAsStaff(
        array $permissions,
        CardType $cardType = CardType::StoredValue,
        array $cardOverrides = [],
    ): array {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $member = $this->createMember($site);
        $card = $this->createCard($site, $member, $cardType, $cardOverrides);

        return [$staff, $site, $member, $card];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCard(Site $site, Member $member, CardType $cardType, array $overrides = []): MemberCard
    {
        $defaults = [
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => $cardType,
            'card_no' => 'MC-'.fake()->unique()->numerify('#####'),
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '测试卡'],
            'issued_at' => now(),
        ];

        if ($cardType === CardType::StoredValue) {
            $defaults['cached_balance'] = 1000;
        }
        if ($cardType === CardType::Count) {
            $defaults['cached_remaining_count'] = 10;
        }

        return MemberCard::create([...$defaults, ...$overrides]);
    }

    private function createMember(Site $site): Member
    {
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-'.fake()->unique()->numerify('####'),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'name' => '测试会员',
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'home',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member;
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Card Adjuster', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Card Adjuster',
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
