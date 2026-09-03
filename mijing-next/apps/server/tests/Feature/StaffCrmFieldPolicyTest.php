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
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCrmFieldPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_field_policy_returns_typed_schema(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/member-field-policy")
            ->assertOk()
            ->assertJsonPath('data.fields.0.key', 'mobile')
            ->assertJsonPath('data.fields.1.key', 'name')
            ->assertJsonStructure([
                'data' => [
                    'fields' => [[
                        'key', 'label', 'legacyIndex', 'isRequired', 'isVisible', 'staffEditable',
                    ]],
                ],
            ]);
    }

    public function test_create_rejects_missing_tenant_required_field(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.create']);
        $staff->load('tenant');
        $staff->tenant->update([
            'crm_field_policy' => ['fields' => ['mobile' => ['isRequired' => true]]],
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '缺手机号潜客',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'CRM_FIELD_REQUIRED');
    }

    public function test_update_rejects_staff_editing_disallowed_field(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.create', 'crm.member.update']);
        $tenant = Tenant::findOrFail($site->tenant_id);
        $tenant->update([
            'crm_field_policy' => ['fields' => ['gender' => ['staffEditable' => false]]],
        ]);

        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '性别锁定',
        ])->assertCreated();

        $this->patchJson("/api/v1/staff/sites/{$site->id}/members/{$created->json('data.id')}", [
            'version' => $created->json('data.version'),
            'gender' => 'male',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'CRM_FIELD_NOT_EDITABLE');
    }

    public function test_member_filter_presets_return_legacy_mappings(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/member-filter-presets")
            ->assertOk()
            ->assertJsonPath('data.sumModePresets.0.id', 'all')
            ->assertJsonPath('data.flagPresets.1.query.sumMode', 'valid')
            ->assertJsonPath('data.runOffPresets.0.runOff', 1)
            ->assertJsonPath('data.runOffPresets.0.query.runOff', 1);
    }

    public function test_member_list_run_off_filter_returns_churned_members(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $churned = $this->createMemberAtSite($site->tenant_id, $site, 'Churned Member', 'active');
        $this->createMemberCard($site, $churned, MemberCardStatus::Expired, CardType::StoredValue, [
            'cached_balance' => 0,
            'valid_until' => now()->subMonths(4)->toDateString(),
            'issued_at' => now()->subYear(),
        ]);

        $recent = $this->createMemberAtSite($site->tenant_id, $site, 'Recent Invalid', 'active');
        $this->createMemberCard($site, $recent, MemberCardStatus::Expired, CardType::StoredValue, [
            'cached_balance' => 0,
            'valid_until' => now()->subMonth()->toDateString(),
            'issued_at' => now()->subMonths(2),
        ]);

        $valid = $this->createMemberAtSite($site->tenant_id, $site, 'Still Valid', 'active');
        $this->createMemberCard($site, $valid, MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 50,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members?runOff=1")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $churned->id);
    }

    public function test_field_policy_and_filter_presets_require_crm_read_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/member-field-policy")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/member-filter-presets")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_field_policy_update_persists_and_enforces_validation(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.create', 'tenant.crm.field-config.write']);
        $staff->load('tenant');

        $this->putJson("/api/v1/staff/sites/{$site->id}/crm/member-field-policy", [
            'fields' => [
                ['key' => 'gender', 'isRequired' => true, 'isVisible' => true],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.fields.2.key', 'gender')
            ->assertJsonPath('data.fields.2.isRequired', true);

        $staff->tenant->refresh();
        $this->assertTrue($staff->tenant->crm_field_policy['fields']['gender']['isRequired']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '缺性别潜客',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'CRM_FIELD_REQUIRED');
    }

    public function test_field_policy_update_requires_write_permission(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $this->putJson("/api/v1/staff/sites/{$site->id}/crm/member-field-policy", [
            'fields' => [
                ['key' => 'gender', 'isRequired' => true],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_field_policy_update_rejects_unlocking_name_requirement(): void
    {
        [, $site] = $this->actAsStaff(['tenant.crm.field-config.write']);

        $this->putJson("/api/v1/staff/sites/{$site->id}/crm/member-field-policy", [
            'fields' => [
                ['key' => 'name', 'isRequired' => false],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('code', 'CRM_FIELD_POLICY_LOCKED');
    }

    public function test_create_requires_crm_create_permission(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => 'No Write'])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
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

    private function createMemberAtSite(int $tenantId, Site $site, string $name, string $status): Member
    {
        $member = Member::create([
            'tenant_id' => $tenantId,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => $status,
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
