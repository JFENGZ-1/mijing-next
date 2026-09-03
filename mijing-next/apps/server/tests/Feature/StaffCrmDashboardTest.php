<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\MemberNote;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCrmDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_expected_counts(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);

        $this->createMemberAtSite($staff->tenant_id, $site, 'Alice Active', 'active', appAccess: 'allowed');
        $validMember = $this->createMemberAtSite($staff->tenant_id, $site, 'Bob Balance', 'active');
        $this->createMemberCard($site, $validMember, MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 100,
            'valid_until' => now()->addMonth()->toDateString(),
            'issued_at' => now()->subMonths(2),
        ]);

        $invalidMember = $this->createMemberAtSite($staff->tenant_id, $site, 'Carl Expired', 'active');
        $this->createMemberCard($site, $invalidMember, MemberCardStatus::Expired, CardType::StoredValue, [
            'cached_balance' => 0,
            'valid_until' => now()->subDay()->toDateString(),
            'issued_at' => now()->subMonths(2),
        ]);

        $this->createMemberAtSite($staff->tenant_id, $site, 'Diana Visitor', 'lead');
        $this->createMemberAtSite($staff->tenant_id, $site, 'Eric Blocked', 'active', appAccess: 'blocked');

        $monthMember = $this->createMemberAtSite($staff->tenant_id, $site, 'Frank Month', 'active');
        $this->createMemberCard($site, $monthMember, MemberCardStatus::Active, CardType::Count, [
            'cached_remaining_count' => 3,
            'issued_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/dashboard-summary")
            ->assertOk()
            ->assertJsonPath('data.totalCount', 6)
            ->assertJsonPath('data.monthCount', 1)
            ->assertJsonPath('data.validUserCount', 2)
            ->assertJsonPath('data.invalidUserCount', 1)
            ->assertJsonPath('data.nocardUserCount', 3)
            ->assertJsonPath('data.nologinUserCount', 1)
            ->assertJsonStructure([
                'data' => [
                    'pinyinIndex' => [['initial', 'count', 'pingyinChar', 'ncount']],
                ],
            ]);
    }

    public function test_member_list_filters_by_search_term(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createMemberAtSite($site->tenant_id, $site, 'Searchable Alpha', 'lead');
        $this->createMemberAtSite($site->tenant_id, $site, 'Other Beta', 'lead');

        $this->getJson("/api/v1/staff/sites/{$site->id}/members?q=Searchable")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Searchable Alpha');
    }

    public function test_member_list_excludes_visitors_when_requested(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $visitor = $this->createMemberAtSite($site->tenant_id, $site, 'No Card Visitor', 'lead');
        $cardHolder = $this->createMemberAtSite($site->tenant_id, $site, 'Card Holder', 'active');
        $this->createMemberCard($site, $cardHolder, MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 50,
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members?includeVisitors=false")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $cardHolder->id);

        $this->assertNotEquals($visitor->id, $this->getJson("/api/v1/staff/sites/{$site->id}/members?includeVisitors=false")->json('data.items.0.id'));
    }

    public function test_member_list_sum_mode_valid_filters_to_valid_members(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $valid = $this->createMemberAtSite($site->tenant_id, $site, 'Valid Member', 'active');
        $this->createMemberCard($site, $valid, MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 80,
        ]);
        $this->createMemberAtSite($site->tenant_id, $site, 'Lead Only', 'lead');

        $this->getJson("/api/v1/staff/sites/{$site->id}/members?sumMode=valid")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $valid->id);
    }

    public function test_member_list_includes_list_summary_fields(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $member = $this->createMemberAtSite($site->tenant_id, $site, 'Summary Alice', 'active');
        $this->createMemberCard($site, $member, MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 88.5,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members")
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Summary Alice')
            ->assertJsonPath('data.items.0.cardCount', 1)
            ->assertJsonPath('data.items.0.cardType', CardType::StoredValue->value)
            ->assertJsonPath('data.items.0.balanceAmount', 88.5)
            ->assertJsonPath('data.items.0.balanceUnit', '元')
            ->assertJsonPath('data.items.0.lastAppointDate', null)
            ->assertJsonPath('data.items.0.holidayDate', null)
            ->assertJsonPath('data.items.0.hintMsg', null)
            ->assertJsonStructure([
                'data' => [
                    'items' => [['pinyinInitial', 'lastAppointDate', 'avatarUrl']],
                ],
            ]);
    }

    public function test_member_list_includes_active_holiday_and_authorized_note_hint(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read', 'crm.member.note.read']);
        $member = $this->createMemberAtSite($site->tenant_id, $site, 'Holiday Alice', 'active');
        $holidayDate = now()->addDays(7)->toDateString();
        $this->createMemberCard($site, $member, MemberCardStatus::Active, CardType::Period, [
            'valid_until' => now()->addMonths(3)->toDateString(),
            'freeze_state' => [
                'holiday' => [
                    'startedAt' => now()->subDay()->toDateString(),
                    'plannedEndAt' => $holidayDate,
                    'startedByStaffId' => $staff->id,
                ],
            ],
        ]);
        MemberNote::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'author_staff_id' => $staff->id,
            'body' => '需跟进续卡',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members")
            ->assertOk()
            ->assertJsonPath('data.items.0.holidayDate', $holidayDate)
            ->assertJsonPath('data.items.0.hintMsg', '需跟进续卡');
    }

    public function test_member_list_last_appoint_date_is_null_without_appointments(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createMemberAtSite($site->tenant_id, $site, 'No Appoint Bob', 'lead');

        $this->getJson("/api/v1/staff/sites/{$site->id}/members")
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'No Appoint Bob')
            ->assertJsonPath('data.items.0.lastAppointDate', null);
    }

    public function test_dashboard_summary_requires_crm_read_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/dashboard-summary")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_dashboard_summary_is_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createMemberAtSite($staff->tenant_id, $site, 'Local Member', 'lead');

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-crm']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $this->createMemberAtSite($otherTenant->id, $otherSite, 'Remote Member', 'lead');

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/crm/dashboard-summary")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/dashboard-summary")
            ->assertOk()
            ->assertJsonPath('data.totalCount', 1);
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'CRM Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'CRM Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'CRM', 'code' => 'crm', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'crm']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createMemberAtSite(
        int $tenantId,
        Site $site,
        string $name,
        string $status,
        string $appAccess = 'allowed',
    ): Member {
        $member = Member::create([
            'tenant_id' => $tenantId,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => $status,
            'app_access_status' => $appAccess,
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'name' => $name,
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMemberCard(
        Site $site,
        Member $member,
        MemberCardStatus $status,
        CardType $cardType,
        array $overrides = [],
    ): MemberCard {
        $product = CardProduct::create([
            'tenant_id' => $site->tenant_id,
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
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => $cardType,
            'card_no' => 'MC-'.fake()->unique()->numerify('####'),
            'status' => $status,
            'product_snapshot' => ['name' => $product->name, 'cardType' => $cardType->value],
            'issued_at' => now(),
            ...$overrides,
        ]);
    }
}
