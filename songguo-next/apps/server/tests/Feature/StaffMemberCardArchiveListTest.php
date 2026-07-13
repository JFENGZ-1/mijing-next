<?php

namespace Tests\Feature;

use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\Account;
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

class StaffMemberCardArchiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_archived_cards(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.archive']);
        $archived = $this->createCard($site, $member, [
            'status' => MemberCardStatus::Archived,
            'archived_at' => now(),
            'card_no' => 'MC-ARCHIVED',
        ]);
        $this->createCard($site, $member, [
            'status' => MemberCardStatus::Active,
            'card_no' => 'MC-ACTIVE',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/archived")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $archived->id)
            ->assertJsonPath('data.items.0.status', MemberCardStatus::Archived->value);
    }

    public function test_member_can_list_hidden_cards(): void
    {
        $member = $this->actAsMember();
        $hidden = $this->createCardForMember($member, [
            'member_visibility' => MemberCardVisibility::Hidden,
            'card_no' => 'MC-HIDDEN',
        ]);
        $this->createCardForMember($member, [
            'member_visibility' => MemberCardVisibility::Visible,
            'card_no' => 'MC-VISIBLE',
        ]);

        $this->getJson('/api/v1/member/member-cards/hidden?tenantId='.$member->tenant_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hidden->id);
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

    private function actAsMember(): Member
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-'.fake()->unique()->numerify('####'),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'active',
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '会员',
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'home',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($account, ['api', 'client:member', "tenant:{$tenant->id}"]);

        return $member;
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

    private function createCardForMember(Member $member, array $overrides = []): MemberCard
    {
        $siteId = $member->home_site_id;

        return MemberCard::create(array_merge([
            'tenant_id' => $member->tenant_id,
            'site_id' => $siteId,
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
        $account = Account::create(['display_name' => 'Archive', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Archive',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Archive', 'code' => 'archive', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'card']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
