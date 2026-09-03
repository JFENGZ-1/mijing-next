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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberCardReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiring_reminder_returns_cards_within_threshold(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read']);
        $this->createCard($site, $member, [
            'card_type' => CardType::Period,
            'status' => MemberCardStatus::Active,
            'valid_until' => now()->addDays(5),
            'card_no' => 'MC-EXP-1',
            'issued_at' => now(),
        ]);
        $this->createCard($site, $member, [
            'card_type' => CardType::Period,
            'status' => MemberCardStatus::Active,
            'valid_until' => now()->addDays(2),
            'card_no' => 'MC-EXP-URGENT',
            'issued_at' => now()->subMonth(),
        ]);
        $this->createCard($site, $member, [
            'card_type' => CardType::Period,
            'status' => MemberCardStatus::Active,
            'valid_until' => now()->addDays(90),
            'card_no' => 'MC-EXP-2',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/expiring")
            ->assertOk()
            ->assertJsonPath('data.config.expiringWithinDays', 30)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.cardNo', 'MC-EXP-URGENT')
            ->assertJsonPath('data.items.1.cardNo', 'MC-EXP-1');
    }

    public function test_zero_balance_reminder_returns_exhausted_value_and_count_cards_only(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read']);
        $this->createCard($site, $member, [
            'card_type' => CardType::StoredValue,
            'cached_balance' => 0,
            'card_no' => 'MC-ZERO',
        ]);
        $this->createCard($site, $member, [
            'card_type' => CardType::StoredValue,
            'cached_balance' => 100,
            'card_no' => 'MC-NONZERO',
        ]);
        $this->createCard($site, $member, [
            'card_type' => CardType::Count,
            'cached_balance' => null,
            'cached_remaining_count' => 0,
            'card_no' => 'MC-COUNT-ZERO',
        ]);
        $this->createCard($site, $member, [
            'card_type' => CardType::Count,
            'cached_balance' => null,
            'cached_remaining_count' => 2,
            'card_no' => 'MC-COUNT-AVAILABLE',
        ]);
        $this->createCard($site, $member, [
            'card_type' => CardType::Period,
            'cached_balance' => 0,
            'cached_remaining_count' => 0,
            'card_no' => 'MC-PERIOD-NOT-BALANCE-BASED',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/zero-balance")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonFragment(['cardNo' => 'MC-ZERO'])
            ->assertJsonFragment(['cardNo' => 'MC-COUNT-ZERO'])
            ->assertJsonMissing(['cardNo' => 'MC-NONZERO'])
            ->assertJsonMissing(['cardNo' => 'MC-COUNT-AVAILABLE'])
            ->assertJsonMissing(['cardNo' => 'MC-PERIOD-NOT-BALANCE-BASED']);
    }

    public function test_pending_open_reminder_returns_pending_activation_cards(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read']);
        $this->createCard($site, $member, [
            'status' => MemberCardStatus::PendingActivation,
            'card_no' => 'MC-PENDING',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/pending-open")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', MemberCardStatus::PendingActivation->value);
    }

    public function test_penalized_reminder_returns_frozen_cards(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read']);
        $this->createCard($site, $member, [
            'status' => MemberCardStatus::Frozen,
            'card_no' => 'MC-FROZEN',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/penalized")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', MemberCardStatus::Frozen->value);
    }

    public function test_penalized_reminder_includes_penalty_ledger_cards(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read']);
        $card = $this->createCard($site, $member, [
            'status' => MemberCardStatus::Active,
            'card_no' => 'MC-PENALTY',
        ]);

        EntitlementLedgerEntry::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Penalty,
            'direction' => EntitlementLedgerDirection::Neutral,
            'reason' => 'Penalty',
            'occurred_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/penalized")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.cardNo', 'MC-PENALTY');
    }

    public function test_reminder_config_can_be_read_and_updated(): void
    {
        [, $site] = $this->actAsStaff(['member-card.reminder.config', 'member-card.reminder.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminder-config")
            ->assertOk()
            ->assertJsonPath('data.expiringWithinDays', 30);

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-card-reminder-config", [
            'expiringWithinDays' => 14,
        ])
            ->assertOk()
            ->assertJsonPath('data.expiringWithinDays', 14);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/expiring?withinDays=14")
            ->assertOk()
            ->assertJsonPath('data.config.expiringWithinDays', 14);
    }

    public function test_staff_without_reminder_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff(['member-card.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/expiring")
            ->assertForbidden();
    }

    public function test_reminder_read_masks_member_name_without_crm_permission(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read']);
        $this->createCard($site, $member, [
            'status' => MemberCardStatus::PendingActivation,
            'card_no' => 'MC-PII-MASKED',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/pending-open")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '测***');
    }

    public function test_reminder_read_returns_full_member_name_with_crm_permission(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read', 'crm.member.read']);
        $this->createCard($site, $member, [
            'status' => MemberCardStatus::PendingActivation,
            'card_no' => 'MC-PII-FULL',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/pending-open")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '测试会员');
    }

    public function test_reminder_queries_are_tenant_isolated(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.reminder.read']);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => fake()->unique()->slug(1)]);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        $this->createCard($otherSite, $otherMember, [
            'status' => MemberCardStatus::PendingActivation,
            'card_no' => 'MC-OTHER',
        ]);
        $this->createCard($site, $member, [
            'status' => MemberCardStatus::PendingActivation,
            'card_no' => 'MC-LOCAL',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-card-reminders/pending-open")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.cardNo', 'MC-LOCAL');
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member}
     */
    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $member = $this->createMember($site);

        return [$staff, $site, $member];
    }

    private function createCard(Site $site, Member $member, array $overrides = []): MemberCard
    {
        return MemberCard::create(array_merge([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-'.fake()->unique()->numerify('#####'),
            'status' => MemberCardStatus::Active,
            'member_visibility' => MemberCardVisibility::Visible,
            'product_snapshot' => ['name' => '测试卡'],
            'cached_balance' => 100,
            'issued_at' => now(),
        ], $overrides));
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
        $account = Account::create(['display_name' => 'Reminders', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Reminders',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Reminders', 'code' => 'reminders', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'card']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
