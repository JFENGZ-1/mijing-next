<?php

namespace Tests\Feature;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\Account;
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

class StaffMemberCardArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_archive_removes_card_from_member_wallet_and_staff_active_list(): void
    {
        [$staff, $site, $member, $card] = $this->actAsStaff(['member-card.archive', 'member-card.read']);
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $member->update(['account_id' => $account->id]);

        $this->postJson($this->archivePath($site, $card), [
            'reason' => '会员退卡',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberCardStatus::Archived->value)
            ->assertJsonPath('data.archivedAt', fn ($value) => $value !== null);

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Archive->value,
            'actor_staff_id' => $staff->id,
        ]);
        $this->assertSame(MemberCardStatus::Archived, MemberCard::findOrFail($card->id)->status);

        Sanctum::actingAs($account, ['api', 'client:member']);
        $this->getJson("/api/v1/member/member-cards?tenantId={$site->tenant_id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/member-cards")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}")
            ->assertOk()
            ->assertJsonPath('data.status', MemberCardStatus::Archived->value);
    }

    public function test_staff_restore_brings_archived_card_back_to_wallet(): void
    {
        [, $site, $member, $card] = $this->actAsStaff(['member-card.archive']);
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $member->update(['account_id' => $account->id]);

        $archiveKey = (string) Str::uuid();
        $this->postJson($this->archivePath($site, $card), [
            'reason' => '归档',
            'commandKey' => $archiveKey,
        ])->assertCreated();

        $restoreKey = (string) Str::uuid();
        $this->postJson($this->restorePath($site, $card), [
            'reason' => '恢复',
            'commandKey' => $restoreKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberCardStatus::Active->value)
            ->assertJsonPath('data.archivedAt', null);

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::ArchiveRestore->value,
            'command_key' => $restoreKey,
        ]);

        Sanctum::actingAs($account, ['api', 'client:member']);
        $this->getJson("/api/v1/member/member-cards?tenantId={$site->tenant_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $card->id);
    }

    public function test_archive_with_consumption_becomes_voided_and_cannot_restore(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.archive']);

        EntitlementLedgerEntry::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $card->member_id,
            'entry_type' => EntitlementLedgerEntryType::BalanceAdjust,
            'direction' => EntitlementLedgerDirection::Debit,
            'amount_delta' => 10,
            'reason' => '消费',
            'occurred_at' => now(),
        ]);

        $this->postJson($this->archivePath($site, $card), [
            'reason' => '已消费退卡',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberCardStatus::Voided->value);

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Void->value,
        ]);

        $this->postJson($this->restorePath($site, $card), [
            'reason' => '尝试恢复',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_CARD_RESTORE_INVALID');
    }

    public function test_archived_card_cannot_be_balance_adjusted(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.archive', 'member-card.balance.adjust']);

        $this->postJson($this->archivePath($site, $card), [
            'reason' => '归档',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 10,
            'reason' => '补赠',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_CARD_ARCHIVED_MUTATION_BLOCKED');
    }

    public function test_archive_commands_are_idempotent(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.archive']);
        $commandKey = (string) Str::uuid();

        $this->postJson($this->archivePath($site, $card), [
            'reason' => '归档',
            'commandKey' => $commandKey,
        ])->assertCreated();

        $this->postJson($this->archivePath($site, $card), [
            'reason' => '归档',
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', MemberCardStatus::Archived->value);

        $this->assertSame(
            1,
            EntitlementLedgerEntry::query()
                ->where('member_card_id', $card->id)
                ->where('entry_type', EntitlementLedgerEntryType::Archive->value)
                ->count(),
        );
    }

    public function test_staff_without_archive_permission_is_denied(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.read']);

        $this->postJson($this->archivePath($site, $card), [
            'reason' => '归档',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_archive_enforces_site_isolation(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.archive']);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-archive']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        $otherCard = MemberCard::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'member_id' => $otherMember->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-OTHER',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '其他卡'],
            'issued_at' => now(),
        ]);

        $this->postJson($this->archivePath($site, $otherCard), [
            'reason' => '跨租户',
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    private function archivePath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/archive";
    }

    private function restorePath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/restore";
    }

    private function balancePath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/balance-adjustments";
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard}
     */
    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $member = $this->createMember($site);
        $card = $this->createCard($site, $member);

        return [$staff, $site, $member, $card];
    }

    private function createCard(Site $site, Member $member): MemberCard
    {
        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-'.fake()->unique()->numerify('#####'),
            'status' => MemberCardStatus::Active,
            'member_visibility' => MemberCardVisibility::Visible,
            'product_snapshot' => ['name' => '测试卡'],
            'cached_balance' => 1000,
            'issued_at' => now(),
        ]);
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
        $account = Account::create(['display_name' => 'Archiver', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Archiver',
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
