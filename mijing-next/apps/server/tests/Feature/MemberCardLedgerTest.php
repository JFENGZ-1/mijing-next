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
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MemberCardLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_domain_schema_supports_template_issue_and_ledger_append(): void
    {
        [$tenant, $site, $staff, $member] = $this->seedCardFixture();

        $product = CardProduct::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => CardType::StoredValue,
            'name' => '储值卡 1000',
            'price' => 1000,
            'face_value' => 1000,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'created_by_staff_id' => $staff->id,
        ]);

        $memberCard = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-0001',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => $product->name, 'faceValue' => '1000.00'],
            'cached_balance' => 1000,
            'issued_at' => now(),
            'issued_by_staff_id' => $staff->id,
        ]);

        $entry = EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $memberCard->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 1000,
            'reason' => 'Staff issue',
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('card_products', ['id' => $product->id, 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('member_cards', ['id' => $memberCard->id, 'card_no' => 'MC-0001']);
        $this->assertDatabaseHas('entitlement_ledger_entries', ['id' => $entry->id, 'entry_type' => 'issue']);
        $this->assertNull($entry->fresh()->updated_at ?? null);
    }

    public function test_ledger_entries_cannot_be_updated_or_deleted(): void
    {
        [$tenant, $site, $staff, $member] = $this->seedCardFixture();
        $entry = $this->createLedgerEntry($tenant, $site, $staff, $member);

        try {
            $entry->update(['reason' => 'mutated']);
            $this->fail('Expected ledger update to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Expected ledger delete to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('entitlement_ledger_entries', ['id' => $entry->id, 'reason' => 'Opening balance']);
    }

    public function test_card_domain_rejects_cross_tenant_foreign_keys(): void
    {
        [$tenantA, $siteA, $staffA, $memberA] = $this->seedCardFixture();
        $tenantB = Tenant::create(['name' => 'Tenant B', 'code' => 'tenant-b']);
        $siteB = Site::create(['tenant_id' => $tenantB->id, 'name' => 'Branch B', 'code' => 'branch-b', 'status' => 'active']);

        $this->expectException(QueryException::class);
        CardProduct::create([
            'tenant_id' => $tenantA->id,
            'site_id' => $siteB->id,
            'card_type' => CardType::Count,
            'name' => 'Cross-tenant product',
            'price' => 100,
            'initial_count' => 10,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'created_by_staff_id' => $staffA->id,
        ]);
    }

    public function test_ledger_command_key_is_unique_per_tenant(): void
    {
        [$tenant, $site, $staff, $member] = $this->seedCardFixture();
        $commandKey = (string) Str::uuid();

        $this->createLedgerEntry($tenant, $site, $staff, $member, ['command_key' => $commandKey]);

        $this->expectException(QueryException::class);
        $this->createLedgerEntry($tenant, $site, $staff, $member, ['command_key' => $commandKey]);
    }

    public function test_member_cards_are_tenant_scoped(): void
    {
        [$tenantA, $siteA, , $memberA] = $this->seedCardFixture();
        $tenantB = Tenant::create(['name' => 'Tenant B', 'code' => 'tenant-b-2']);
        $siteB = Site::create(['tenant_id' => $tenantB->id, 'name' => 'Branch B', 'code' => 'branch-b-2', 'status' => 'active']);
        $productB = CardProduct::create([
            'tenant_id' => $tenantB->id,
            'site_id' => $siteB->id,
            'card_type' => CardType::Period,
            'name' => '期限卡',
            'price' => 500,
            'validity_days' => 30,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
        ]);

        $this->expectException(QueryException::class);
        MemberCard::create([
            'tenant_id' => $tenantA->id,
            'site_id' => $siteA->id,
            'member_id' => $memberA->id,
            'card_product_id' => $productB->id,
            'card_type' => CardType::Period,
            'card_no' => 'MC-CROSS',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '期限卡'],
            'issued_at' => now(),
        ]);
    }

    /**
     * @return array{0: Tenant, 1: Site, 2: Staff, 3: Member}
     */
    private function seedCardFixture(): array
    {
        $tenant = Tenant::create(['name' => 'Card Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Card Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Card Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM0001',
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
        ]);
        MemberCrmProfile::create(['tenant_id' => $tenant->id, 'member_id' => $member->id, 'name' => 'Card Member']);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'home',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $site, $staff, $member];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLedgerEntry(Tenant $tenant, Site $site, Staff $staff, Member $member, array $overrides = []): EntitlementLedgerEntry
    {
        $product = CardProduct::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => CardType::StoredValue,
            'name' => 'Ledger Card',
            'price' => 100,
            'face_value' => 100,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'created_by_staff_id' => $staff->id,
        ]);

        $memberCard = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::StoredValue,
            'card_no' => fake()->unique()->numerify('MC-#####'),
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => $product->name],
            'cached_balance' => 100,
            'issued_at' => now(),
            'issued_by_staff_id' => $staff->id,
        ]);

        return EntitlementLedgerEntry::create(array_merge([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $memberCard->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 100,
            'reason' => 'Opening balance',
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ], $overrides));
    }
}
