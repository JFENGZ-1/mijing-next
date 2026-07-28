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

class MemberBookingPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_picker_returns_pinyin_grouped_members(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);

        $alice = $this->createMemberAtSite($staff->tenant_id, $site, 'Alice', 'active');
        $this->createMemberCard($site, $alice, MemberCardStatus::Active, CardType::StoredValue, [
            'cached_balance' => 88,
        ]);
        $this->createMemberAtSite($staff->tenant_id, $site, '张三', 'active');
        $blocked = $this->createMemberAtSite($staff->tenant_id, $site, 'Bob', 'active', appAccess: 'blocked');

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/members/booking-picker")
            ->assertOk()
            ->assertJsonPath('data.totalCount', 3)
            ->assertJsonStructure([
                'data' => [
                    'pinyinIndex' => [['initial', 'count', 'pingyinChar', 'ncount']],
                    'groups' => [[
                        'initial',
                        'count',
                        'items' => [[
                            'id', 'memberNo', 'name', 'initial', 'avatarUrl',
                            'mobileMasked', 'joinedAt', 'status', 'appAccessStatus',
                            'balanceAmount', 'balanceUnit',
                        ]],
                    ]],
                ],
            ]);

        $groups = collect($response->json('data.groups'));
        $this->assertSame(['A', 'B', 'Z'], $groups->pluck('initial')->all());

        $aliceItem = $groups->firstWhere('initial', 'A')['items'][0];
        $this->assertSame(88.0, (float) $aliceItem['balanceAmount']);
        $this->assertSame('元', $aliceItem['balanceUnit']);

        $bobItem = $groups->firstWhere('initial', 'B')['items'][0];
        $this->assertSame('blocked', $bobItem['appAccessStatus']);
        $this->assertNull($bobItem['balanceAmount']);
    }

    public function test_booking_picker_filters_by_initials(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createMemberAtSite($staff->tenant_id, $site, 'Alice', 'active');
        $this->createMemberAtSite($staff->tenant_id, $site, '张三', 'active');

        // initials 仅控制批次：分布与总数始终为全量
        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/members/booking-picker?initials=Z")
            ->assertOk()
            ->assertJsonPath('data.totalCount', 2)
            ->assertJsonCount(2, 'data.groups')
            ->assertJsonPath('data.pinyinIndex.0.initial', 'A')
            ->assertJsonPath('data.pinyinIndex.1.initial', 'Z')
            ->assertJsonPath('data.pinyinIndex.1.count', 1);

        $groups = collect($response->json('data.groups'));
        $this->assertSame([], $groups->firstWhere('initial', 'A')['items']);
        $this->assertSame('张三', $groups->firstWhere('initial', 'Z')['items'][0]['name']);
    }

    public function test_booking_picker_filters_by_keyword(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createMemberAtSite($staff->tenant_id, $site, 'Searchable Alpha', 'active');
        $this->createMemberAtSite($staff->tenant_id, $site, 'Other Beta', 'active');

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/booking-picker?q=Searchable")
            ->assertOk()
            ->assertJsonPath('data.totalCount', 1)
            ->assertJsonPath('data.groups.0.items.0.name', 'Searchable Alpha');
    }

    public function test_booking_picker_requires_crm_read_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/booking-picker")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_booking_picker_scope_all_includes_members_from_other_visible_sites(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createMemberAtSite($staff->tenant_id, $site, 'Local Member', 'active');

        // 同租户第二站点，员工可见（attach 到 staff sites）
        $secondSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => '分店',
            'code' => 'branch',
            'status' => 'active',
        ]);
        $staff->sites()->attach($secondSite->id, ['tenant_id' => $staff->tenant_id, 'is_primary' => false]);
        $this->createMemberAtSite($staff->tenant_id, $secondSite, 'Branch Member', 'active');

        // 默认仅本店
        $this->getJson("/api/v1/staff/sites/{$site->id}/members/booking-picker")
            ->assertOk()
            ->assertJsonPath('data.totalCount', 1)
            ->assertJsonPath('data.groups.0.items.0.name', 'Local Member')
            ->assertJsonPath('data.groups.0.items.0.otherSiteName', null);

        // 全部店：两站会员均可见，分店会员带 otherSiteName
        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/members/booking-picker?scope=all")
            ->assertOk()
            ->assertJsonPath('data.totalCount', 2);

        $items = collect($response->json('data.groups'))->flatMap->items;
        $branch = $items->firstWhere('name', 'Branch Member');
        $this->assertSame('分店', $branch['otherSiteName']);
        $this->assertNull($items->firstWhere('name', 'Local Member')['otherSiteName']);
    }

    public function test_booking_picker_is_scoped_to_tenant_and_site(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createMemberAtSite($staff->tenant_id, $site, 'Local Member', 'active');

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-picker']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $this->createMemberAtSite($otherTenant->id, $otherSite, 'Remote Member', 'active');

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/members/booking-picker")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/booking-picker")
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
        $account = Account::create(['display_name' => 'Picker Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Picker Admin',
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

        return MemberCard::create(array_merge([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => $cardType,
            'card_no' => 'C'.fake()->unique()->numerify('######'),
            'status' => $status,
            'product_snapshot' => ['name' => $product->name, 'cardType' => $cardType->value],
            'issued_at' => now(),
        ], $overrides));
    }
}
