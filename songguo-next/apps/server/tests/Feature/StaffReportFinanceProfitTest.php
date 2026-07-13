<?php

namespace Tests\Feature;

use App\Enums\MemberCardOrderStatus;
use App\Enums\OrderAmountCorrectionType;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCard;
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

class StaffReportFinanceProfitTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_summary_aggregates_match_seed_orders(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.finance.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Profit Member', now()->startOfMonth());
        $card = $this->createCard($site, $member);

        $order = $this->createPaidOrder($site, $member, $card, $staff, 'ORD-PROFIT-01', 1200, now()->startOfMonth()->addDays(2));
        OrderAmountCorrection::create([
            'tenant_id' => $site->tenant_id,
            'order_id' => $order->id,
            'entry_type' => OrderAmountCorrectionType::Correction,
            'corrected_amount' => 1500,
            'reason' => '改价',
            'command_key' => (string) Str::uuid(),
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        $oldMember = $this->createMemberAtSite($staff->tenant_id, $site, 'Old Member', now()->subYear());
        $oldCard = $this->createCard($site, $oldMember);
        $this->createPaidOrder($site, $oldMember, $oldCard, $staff, 'ORD-PROFIT-OLD', 800, now()->subYear()->startOfMonth()->addDays(5));

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/finance/profit-summary")
            ->assertOk();

        $currentYear = collect($response->json('data.years'))->firstWhere('year', now()->year);
        $this->assertNotNull($currentYear);
        $this->assertTrue($currentYear['isCurrentYear']);
        $this->assertSame(1, $currentYear['newMemberCount']);
        $this->assertSame(1, $currentYear['cardSalesCount']);
        $this->assertSame('1500.00', $currentYear['revenue']);

        $currentMonth = collect($currentYear['months'])->firstWhere('month', now()->month);
        $this->assertSame(1, $currentMonth['newMemberCount']);
        $this->assertSame(1, $currentMonth['cardSalesCount']);
        $this->assertSame('1500.00', $currentMonth['revenue']);
    }

    public function test_profit_calendar_returns_twelve_month_rollups_for_year(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.finance.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Calendar Member', now()->setMonth(3)->startOfMonth());
        $card = $this->createCard($site, $member);

        $this->createPaidOrder(
            $site,
            $member,
            $card,
            $staff,
            'ORD-CAL-MAR',
            900,
            now()->setMonth(3)->startOfMonth()->addDays(1),
        );
        $this->createPaidOrder(
            $site,
            $member,
            $card,
            $staff,
            'ORD-CAL-APR',
            1100,
            now()->setMonth(4)->startOfMonth()->addDays(2),
        );

        $year = now()->year;
        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/finance/profit-calendar?year={$year}")
            ->assertOk()
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.totals.cardSalesCount', 2)
            ->assertJsonPath('data.totals.revenue', '2000.00');

        $months = $response->json('data.months');
        $this->assertCount(now()->month, $months);
        $this->assertSame('900.00', collect($months)->firstWhere('month', 3)['revenue']);
        $this->assertSame('1100.00', collect($months)->firstWhere('month', 4)['revenue']);
        $this->assertSame(0, collect($months)->firstWhere('month', 1)['cardSalesCount']);
    }

    public function test_profit_daily_returns_day_breakdown_for_month(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.finance.read']);
        $dayOne = now()->startOfMonth()->addDays(1);
        $dayTwo = now()->startOfMonth()->addDays(2);

        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Daily Member', $dayOne);
        $card = $this->createCard($site, $member);

        $this->createPaidOrder($site, $member, $card, $staff, 'ORD-DAY-01', 500, $dayOne);
        $this->createPaidOrder($site, $member, $card, $staff, 'ORD-DAY-02', 700, $dayTwo);

        $year = now()->year;
        $month = now()->month;

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/reports/finance/profit-daily?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.totals.cardSalesCount', 2)
            ->assertJsonPath('data.totals.revenue', '1200.00');

        $days = $response->json('data.days');
        $this->assertCount(2, $days);
        $this->assertSame('500.00', collect($days)->firstWhere('day', (int) $dayOne->day)['revenue']);
        $this->assertSame('700.00', collect($days)->firstWhere('day', (int) $dayTwo->day)['revenue']);
    }

    public function test_profit_endpoints_require_permission(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/finance/profit-summary")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/finance/profit-calendar?year=2026")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/finance/profit-daily?year=2026&month=1")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_profit_endpoints_are_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['report.finance.read']);
        $member = $this->createMemberAtSite($staff->tenant_id, $site, 'Scoped Member', now());
        $card = $this->createCard($site, $member);
        $this->createPaidOrder($site, $member, $card, $staff, 'ORD-SCOPED', 300, now());

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-finance']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);

        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'M-OTHER-001',
            'status' => 'active',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'joined_at' => now(),
        ]);
        MemberCardOrder::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'member_id' => $otherMember->id,
            'order_no' => 'ORD-OTHER',
            'amount' => 9999,
            'status' => MemberCardOrderStatus::Paid,
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/reports/finance/profit-summary")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/finance/profit-summary")
            ->assertOk()
            ->assertJsonPath('data.years.0.cardSalesCount', 1)
            ->assertJsonPath('data.years.0.revenue', '300.00');
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
        $account = Account::create(['display_name' => 'Finance Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Finance Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Finance', 'code' => 'finance', 'status' => 'active']);
        foreach ($permissions as $code) {
            $module = str_starts_with($code, 'report.') ? 'reporting' : 'identity';
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => $module]);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createMemberAtSite(int $tenantId, Site $site, string $name, $joinedAt): Member
    {
        $member = Member::create([
            'tenant_id' => $tenantId,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => $joinedAt,
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

    private function createCard(Site $site, Member $member): MemberCard
    {
        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => 'stored_value',
            'card_no' => 'MC-'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'product_snapshot' => ['name' => '年卡'],
            'issued_at' => now(),
        ]);
    }

    private function createPaidOrder(
        Site $site,
        Member $member,
        MemberCard $card,
        Staff $staff,
        string $orderNo,
        float $amount,
        $createdAt,
    ): MemberCardOrder {
        $order = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => $orderNo,
            'amount' => $amount,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $order;
    }
}
