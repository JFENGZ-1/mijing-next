<?php

namespace Tests\Feature;

use App\Enums\MemberCardOrderStatus;
use App\Enums\OrderAmountCorrectionType;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\OrderAmountCorrection;
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

class StaffMemberCardOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_member_orders(): void
    {
        [, $site, $member] = $this->actAsStaff(['order.read']);
        $order = $this->createOrder($site, $member, 1200, MemberCardOrderStatus::Paid);

        $this->getJson($this->listPath($site, $member))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $order->id)
            ->assertJsonPath('data.items.0.originalAmount', '1200.00')
            ->assertJsonPath('data.items.0.effectiveAmount', '1200.00');
    }

    public function test_amount_correction_appends_rows_without_updating_order_amount(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['order.amount.correct']);
        $order = $this->createOrder($site, $member, 1000, MemberCardOrderStatus::Paid);
        $originalAmount = $order->amount;

        $this->postJson($this->correctionPath($site, $order), [
            'amount' => 850,
            'reason' => '财务更正',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.originalAmount', '1000.00')
            ->assertJsonPath('data.effectiveAmount', '850.00')
            ->assertJsonCount(1, 'data.correctionEntryIds');

        $order->refresh();
        $this->assertSame(number_format((float) $originalAmount, 2, '.', ''), number_format((float) $order->amount, 2, '.', ''));
        $this->assertDatabaseHas('order_amount_corrections', [
            'order_id' => $order->id,
            'entry_type' => OrderAmountCorrectionType::Correction->value,
            'corrected_amount' => '850.00',
            'actor_staff_id' => $staff->id,
        ]);
    }

    public function test_amount_correction_is_idempotent_by_command_key(): void
    {
        [, $site, $member] = $this->actAsStaff(['order.amount.correct']);
        $order = $this->createOrder($site, $member, 1000, MemberCardOrderStatus::Paid);
        $commandKey = (string) Str::uuid();

        $this->postJson($this->correctionPath($site, $order), [
            'amount' => 900,
            'reason' => '更正',
            'commandKey' => $commandKey,
        ])->assertCreated();

        $this->postJson($this->correctionPath($site, $order), [
            'amount' => 900,
            'reason' => '更正',
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.correctionEntryIds');

        $this->assertSame(1, OrderAmountCorrection::query()->where('order_id', $order->id)->count());
    }

    public function test_void_only_allows_pending_payment_orders(): void
    {
        [, $site, $member] = $this->actAsStaff(['order.void']);
        $pending = $this->createOrder($site, $member, 500, MemberCardOrderStatus::PendingPayment);
        $paid = $this->createOrder($site, $member, 800, MemberCardOrderStatus::Paid);

        $this->postJson($this->voidPath($site, $pending), [
            'reason' => '误下单',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberCardOrderStatus::Voided->value);

        $this->postJson($this->voidPath($site, $paid), [
            'reason' => '尝试作废已支付',
            'commandKey' => (string) Str::uuid(),
        ])->assertStatus(409);
    }

    public function test_physical_order_delete_is_forbidden(): void
    {
        [, $site, $member] = $this->actAsStaff(['order.void']);
        $order = $this->createOrder($site, $member, 300, MemberCardOrderStatus::PendingPayment);

        $this->deleteJson("/api/v1/staff/sites/{$site->id}/orders/{$order->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('member_card_orders', ['id' => $order->id]);
    }

    public function test_staff_without_order_permissions_is_denied(): void
    {
        [, $site, $member] = $this->actAsStaff(['member-card.read']);
        $order = $this->createOrder($site, $member, 100, MemberCardOrderStatus::PendingPayment);

        $this->getJson($this->listPath($site, $member))->assertForbidden();
        $this->postJson($this->correctionPath($site, $order), [
            'amount' => 90,
            'reason' => 'x',
            'commandKey' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->postJson($this->voidPath($site, $order), [
            'reason' => 'x',
            'commandKey' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    public function test_orders_are_tenant_isolated(): void
    {
        [, $site, $member] = $this->actAsStaff(['order.read', 'order.void']);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => fake()->unique()->slug(1)]);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        $otherOrder = $this->createOrder($otherSite, $otherMember, 100, MemberCardOrderStatus::Paid);

        $this->postJson("/api/v1/staff/sites/{$site->id}/orders/{$otherOrder->id}/void", [
            'reason' => 'x',
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    private function listPath(Site $site, Member $member): string
    {
        return "/api/v1/staff/sites/{$site->id}/members/{$member->id}/orders";
    }

    private function correctionPath(Site $site, MemberCardOrder $order): string
    {
        return "/api/v1/staff/sites/{$site->id}/orders/{$order->id}/amount-corrections";
    }

    private function voidPath(Site $site, MemberCardOrder $order): string
    {
        return "/api/v1/staff/sites/{$site->id}/orders/{$order->id}/void";
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

    private function createOrder(Site $site, Member $member, float $amount, MemberCardOrderStatus $status): MemberCardOrder
    {
        return MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-'.fake()->unique()->numerify('#####'),
            'amount' => $amount,
            'status' => $status,
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
        $account = Account::create(['display_name' => 'Orders', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Orders',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Orders', 'code' => 'orders', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'order']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
