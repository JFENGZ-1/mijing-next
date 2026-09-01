<?php

namespace Tests\Feature;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerEntryType;
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

class StaffMemberCardIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_issue_stored_value_count_and_period_cards(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $storedValue = $this->createProduct($site, CardType::StoredValue, ['face_value' => 2000]);
        $count = $this->createProduct($site, CardType::Count, [
            'initial_count' => 10,
            'activation_mode' => 'on_first_use',
        ]);
        $period = $this->createProduct($site, CardType::Period, [
            'validity_days' => 30,
            'activation_mode' => 'immediate',
        ]);

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $storedValue->id,
            'commandKey' => (string) Str::uuid(),
            'openingBalance' => 1500,
        ])
            ->assertCreated()
            ->assertJsonPath('data.cardType', 'stored_value')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cachedBalance', '1500.00')
            ->assertJsonPath('data.snapshot.faceValue', '2000.00')
            ->assertJsonPath('data.snapshot.productVersion', 1);

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $count->id,
            'commandKey' => (string) Str::uuid(),
            'openingCount' => 8,
        ])
            ->assertCreated()
            ->assertJsonPath('data.cardType', 'count')
            ->assertJsonPath('data.status', 'pending_activation')
            ->assertJsonPath('data.cachedRemainingCount', 8);

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $period->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.cardType', 'period')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.validFrom', now()->toDateString())
            ->assertJsonPath('data.validUntil', now()->addDays(29)->toDateString());
    }

    public function test_issue_applies_opening_type_atomically_and_instance_override_drives_activation(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $period = $this->createProduct($site, CardType::Period, [
            'validity_days' => 30,
            'activation_mode' => 'manual',
        ]);

        $immediate = $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $period->id,
            'openingType' => 'immediate',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.openingType', 'immediate')
            ->assertJsonPath('data.snapshot.activationModeOverride', 'immediate')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.validUntil', now()->addDays(29)->toDateString());

        foreach ([
            'first_use' => 'first-use',
            'first_class' => 'first-class',
            'keep_pending' => 'manual',
        ] as $openingType => $mode) {
            $response = $this->postJson($this->issuePath($site, $member), [
                'cardProductId' => $period->id,
                'openingType' => $openingType,
                'commandKey' => (string) Str::uuid(),
            ])->assertCreated()
                ->assertJsonPath('data.openingType', $openingType)
                ->assertJsonPath('data.snapshot.activationModeOverride', $mode)
                ->assertJsonPath('data.status', 'pending_activation')
                ->assertJsonPath('data.validFrom', null)
                ->assertJsonPath('data.validUntil', null);

            $card = MemberCard::findOrFail($response->json('data.id'));
            $this->assertSame($mode, app(\App\Services\Cards\MemberCardAutoActivationService::class)->activationMode($card));
        }

        $this->assertSame('immediate', MemberCard::findOrFail($immediate->json('data.id'))->product_snapshot['openingType']);
    }

    public function test_issue_opening_type_is_part_of_idempotency_fingerprint_and_validation_is_atomic(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $product = $this->createProduct($site, CardType::Count, ['initial_count' => 10]);
        $commandKey = (string) Str::uuid();

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'openingType' => 'first_use',
            'commandKey' => $commandKey,
        ])->assertCreated();
        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'openingType' => 'keep_pending',
            'commandKey' => $commandKey,
        ])->assertStatus(409);

        $before = MemberCard::query()->where('member_id', $member->id)->count();
        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'openingType' => 'not-a-mode',
            'commandKey' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertSame($before, MemberCard::query()->where('member_id', $member->id)->count());
    }

    public function test_paid_staff_issue_requires_payment_method_and_auditable_reason(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $product = $this->createProduct($site, CardType::StoredValue, ['face_value' => 100]);

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'actualAmount' => '100.00',
            'commandKey' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('details.paymentMethod.0', fn ($message) => is_string($message));
        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'paymentMethod' => 'online',
            'actualAmount' => '100.00',
            'reason' => '短',
            'commandKey' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('details.reason.0', fn ($message) => is_string($message));

        $this->assertDatabaseCount('member_cards', 0);
        $this->assertDatabaseCount('member_card_orders', 0);
    }

    public function test_issue_creates_opening_ledger_entries(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $product = $this->createProduct($site, CardType::StoredValue, ['face_value' => 500]);
        $commandKey = (string) Str::uuid();

        $response = $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])->assertCreated();

        $cardId = $response->json('data.id');

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'tenant_id' => $staff->tenant_id,
            'member_card_id' => $cardId,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue->value,
            'amount_delta' => '500.00',
            'command_key' => $commandKey,
            'actor_staff_id' => $staff->id,
        ]);
        $this->assertSame(1, EntitlementLedgerEntry::query()->where('member_card_id', $cardId)->count());
    }

    public function test_product_snapshot_is_frozen_after_template_edit(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.issue', 'card-product.editor.write']);
        $product = $this->createProduct($site, CardType::StoredValue, ['face_value' => 1000, 'name' => '原始卡名']);

        $issued = $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $cardId = $issued->json('data.id');

        $this->putJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}", [
            'version' => 1,
            'name' => '更新后的卡名',
            'price' => 1200,
            'faceValue' => 1200,
        ])->assertOk();

        $this->assertSame('原始卡名', MemberCard::findOrFail($cardId)->product_snapshot['name']);
        $this->assertSame('1000.00', MemberCard::findOrFail($cardId)->product_snapshot['faceValue']);
        $this->assertSame(1, MemberCard::findOrFail($cardId)->product_snapshot['productVersion']);
    }

    public function test_issue_is_idempotent_on_command_key(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $product = $this->createProduct($site, CardType::StoredValue, ['face_value' => 300]);
        $commandKey = (string) Str::uuid();

        $first = $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])->assertCreated();

        $second = $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'commandKey' => $commandKey,
        ])->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, MemberCard::query()->where('member_id', $member->id)->count());
        $this->assertSame(1, EntitlementLedgerEntry::query()->where('command_key', $commandKey)->count());
    }

    public function test_staff_without_issue_permission_is_denied(): void
    {
        [, $site, $member] = $this->actAsStaff(['crm.member.read']);
        $product = $this->createProduct($site, CardType::StoredValue, ['face_value' => 100]);

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_issue_enforces_tenant_site_and_member_isolation(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $product = $this->createProduct($site, CardType::StoredValue, ['face_value' => 100]);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-issue']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherProduct = CardProduct::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'card_type' => CardType::StoredValue,
            'name' => '外馆卡',
            'price' => 100,
            'face_value' => 100,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
        ]);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$otherMember->id}/member-cards", [
            'cardProductId' => $product->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $otherProduct->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_blocked_member_cannot_receive_cards(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $member->update(['app_access_status' => 'blocked']);
        $product = $this->createProduct($site, CardType::StoredValue, ['face_value' => 100]);

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'MEMBER_APP_ACCESS_BLOCKED');
    }

    public function test_archived_card_product_cannot_be_issued(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.issue']);
        $product = $this->createProduct($site, CardType::StoredValue, [
            'face_value' => 100,
            'catalog_status' => CardProductCatalogStatus::Archived,
        ]);

        $this->postJson($this->issuePath($site, $member), [
            'cardProductId' => $product->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CARD_PRODUCT_NOT_ISSUABLE');
    }

    private function issuePath(Site $site, Member $member): string
    {
        return "/api/v1/staff/sites/{$site->id}/members/{$member->id}/member-cards";
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProduct(Site $site, CardType $cardType, array $overrides = []): CardProduct
    {
        $defaults = [
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => $cardType,
            'name' => fake()->words(3, true),
            'price' => 100,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'activation_mode' => 'immediate',
            'version' => 1,
        ];

        if ($cardType === CardType::StoredValue) {
            $defaults['face_value'] = 100;
        }
        if ($cardType === CardType::Count) {
            $defaults['initial_count'] = 10;
        }
        if ($cardType === CardType::Period) {
            $defaults['validity_days'] = 30;
        }

        return CardProduct::create([...$defaults, ...$overrides]);
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
        $account = Account::create(['display_name' => 'Card Issuer', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Card Issuer',
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
