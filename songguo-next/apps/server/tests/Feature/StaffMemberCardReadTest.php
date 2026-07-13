<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\CardProduct;
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

class StaffMemberCardReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_permission_lists_member_cards_at_site(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.read']);
        $card = $this->createCard($site, $member, 'MC-STAFF-1', MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 800,
        ]);
        $this->createCard($site, $member, 'MC-HIDDEN', MemberCardStatus::Active, CardType::Count, [
            'member_visibility' => MemberCardVisibility::Hidden,
            'cached_remaining_count' => 2,
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/member-cards")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.cardNo', 'MC-STAFF-1')
            ->assertJsonPath('data.0.cachedBalance', '800.00');
    }

    public function test_staff_can_read_card_detail_and_benefits(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.read']);
        $card = $this->createCard($site, $member, 'MC-DETAIL', MemberCardStatus::Active, CardType::Count, [
            'cached_remaining_count' => 4,
            'product_snapshot' => [
                'name' => '计次权益卡',
                'cardType' => 'count',
                'initialCount' => 10,
                'courseScopes' => [
                    ['scopeKind' => 'course', 'scopeKey' => 'yoga-101', 'displayName' => '瑜伽基础'],
                ],
                'bookingRules' => ['advanceHours' => 2],
            ],
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $card->id)
            ->assertJsonPath('data.snapshot.name', '计次权益卡')
            ->assertJsonPath('data.cachedRemainingCount', 4);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/benefits")
            ->assertOk()
            ->assertJsonPath('data.memberCardId', $card->id)
            ->assertJsonPath('data.courseScopes.0.displayName', '瑜伽基础')
            ->assertJsonPath('data.entitlements.cachedRemainingCount', 4)
            ->assertJsonPath('data.bookingRules.advanceHours', 2);
    }

    public function test_crm_member_card_read_permission_alias_works(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['crm.member.card.read']);
        $card = $this->createCard($site, $member, 'MC-ALIAS', MemberCardStatus::Active, CardType::StoredValue);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/member-cards")
            ->assertOk()
            ->assertJsonPath('data.0.id', $card->id);
    }

    public function test_staff_without_read_permission_is_denied(): void
    {
        [, $site, $member] = $this->actAsStaff(['crm.member.read']);
        $card = $this->createCard($site, $member, 'MC-DENIED', MemberCardStatus::Active, CardType::StoredValue);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/member-cards")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}")
            ->assertForbidden();
    }

    public function test_staff_read_enforces_site_and_tenant_isolation(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.read']);
        $card = $this->createCard($site, $member, 'MC-ISO', MemberCardStatus::Active, CardType::StoredValue);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-read']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        $otherCard = $this->createCard($otherSite, $otherMember, 'MC-OTHER', MemberCardStatus::Active, CardType::StoredValue);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$otherMember->id}/member-cards")
            ->assertNotFound();

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$otherCard->id}")
            ->assertNotFound();

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/member-cards/{$card->id}")
            ->assertNotFound();
    }

    public function test_staff_list_excludes_archived_and_voided_cards(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.read']);
        $active = $this->createCard($site, $member, 'MC-LIST-ACTIVE', MemberCardStatus::Active, CardType::StoredValue);
        $this->createCard($site, $member, 'MC-LIST-ARCHIVED', MemberCardStatus::Active, CardType::StoredValue, [
            'archived_at' => now(),
        ]);
        $this->createCard($site, $member, 'MC-LIST-VOIDED', MemberCardStatus::Voided, CardType::StoredValue);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/member-cards")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCard(
        Site $site,
        Member $member,
        string $cardNo,
        MemberCardStatus $status,
        CardType $cardType,
        array $overrides = [],
    ): MemberCard {
        $product = CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => $cardType,
            'name' => '员工查看卡',
            'price' => 100,
            'face_value' => $cardType === CardType::StoredValue ? 100 : null,
            'initial_count' => $cardType === CardType::Count ? 5 : null,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
        ]);

        $snapshot = $overrides['product_snapshot'] ?? [
            'name' => $product->name,
            'cardType' => $cardType->value,
        ];
        unset($overrides['product_snapshot']);

        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => $cardType,
            'card_no' => $cardNo,
            'status' => $status,
            'product_snapshot' => $snapshot,
            'issued_at' => now(),
            ...$overrides,
        ]);
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
        $account = \App\Models\Account::create(['display_name' => 'Card Reader', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Card Reader',
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
