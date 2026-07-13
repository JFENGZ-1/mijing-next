<?php

namespace Tests\Feature;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberMemberCardLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_own_card_ledger_with_masked_summary(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $card = $this->createCard($tenant, $site, $member, 'MC-OWN', 500);
        EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 500,
            'reason' => 'Staff issue',
            'occurred_at' => now()->subDay(),
        ]);
        EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::BalanceAdjust,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 50,
            'reason' => '赠送',
            'occurred_at' => now(),
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/member-cards/{$card->id}/ledger-entries")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.summary', '余额增加')
            ->assertJsonStructure(['data' => ['items' => [['id', 'entryType', 'summary', 'occurredAt']]]]);

        $first = $this->getJson("/api/v1/member/member-cards/{$card->id}/ledger-entries")->json('data.items.0');
        $this->assertArrayNotHasKey('actorStaffId', $first);
        $this->assertArrayNotHasKey('commandKey', $first);
        $this->assertArrayNotHasKey('reason', $first);
    }

    public function test_member_cannot_read_other_members_card_ledger(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $own = $this->createCard($tenant, $site, $member, 'MC-OWN', 100);

        $otherMember = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $otherCard = $this->createCard($tenant, $site, $otherMember, 'MC-OTHER', 200);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/member-cards/{$otherCard->id}/ledger-entries")->assertNotFound();
        $this->getJson("/api/v1/member/member-cards/{$own->id}/ledger-entries")->assertOk();
    }

    public function test_member_ledger_isolated_by_tenant(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $own = $this->createCard($tenant, $site, $member, 'MC-OWN', 100);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-ledger']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'MEM-OT',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        $otherCard = $this->createCard($otherTenant, $otherSite, $otherMember, 'MC-OT', 300);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/member-cards/{$otherCard->id}/ledger-entries")->assertNotFound();
        $this->getJson("/api/v1/member/member-cards/{$own->id}/ledger-entries")->assertOk();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member, 3: Site}
     */
    private function seedMember(): array
    {
        $tenant = Tenant::create(['name' => 'Wallet Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-'.fake()->unique()->numerify('####'),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return [$account, $tenant, $member, $site];
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }

    private function createCard(Tenant $tenant, Site $site, Member $member, string $cardNo, float $balance): MemberCard
    {
        return MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => $cardNo,
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '测试卡'],
            'cached_balance' => $balance,
            'issued_at' => now(),
        ]);
    }
}
