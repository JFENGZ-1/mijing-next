<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Enums\OrderAmountCorrectionType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\CardProduct;
use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\OrderAmountCorrection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffReportDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_read_masks_member_names_until_crm_read_is_granted(): void
    {
        [$staff, $site, $role] = $this->staffContext(['report.read']);
        $member = $this->member($site, '张小明');
        $card = $this->card($site, $member, CardType::StoredValue, MemberCardStatus::Active, [
            'cached_balance' => 100,
        ]);
        MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => 'ORD-REPORT-PII',
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
            'paid_at' => now(),
        ]);
        EntitlementLedgerEntry::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 100,
            'reason' => '测试发卡',
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/card-sales/detail")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '张**');
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/change-log")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '张**');

        $crmRead = Permission::firstOrCreate(
            ['code' => 'crm.member.read'],
            ['name' => 'crm.member.read', 'module' => 'crm'],
        );
        $role->permissions()->attach($crmRead->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/card-sales/detail")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '张小明');
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/change-log")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '张小明');
    }

    public function test_card_analysis_uses_spendable_card_rules_and_effective_paid_amount(): void
    {
        [$staff, $site] = $this->staffContext(['report.read']);
        $member = $this->member($site, '卡分析会员');
        $active = $this->card($site, $member, CardType::StoredValue, MemberCardStatus::Active, [
            'cached_balance' => 100,
        ]);
        $this->card($site, $member, CardType::StoredValue, MemberCardStatus::Active, [
            'cached_balance' => 0,
        ]);
        $this->card($site, $member, CardType::Period, MemberCardStatus::PendingActivation);
        $this->card($site, $member, CardType::Period, MemberCardStatus::Frozen);
        $this->card($site, $member, CardType::Period, MemberCardStatus::Expired, [
            'valid_until' => now()->subDay(),
        ]);

        $order = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $active->id,
            'order_no' => 'ORD-CARD-ANALYZE',
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
            'paid_at' => now(),
        ]);
        OrderAmountCorrection::create([
            'tenant_id' => $site->tenant_id,
            'order_id' => $order->id,
            'entry_type' => OrderAmountCorrectionType::Correction,
            'corrected_amount' => 80,
            'command_key' => (string) Str::uuid(),
            'reason' => '测试有效金额',
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/card-analyze/summary")
            ->assertOk()
            ->assertJsonPath('data.cards.0.count', 5)
            ->assertJsonPath('data.cards.1.count', 1)
            ->assertJsonPath('data.cards.2.count', 2)
            ->assertJsonPath('data.cards.3.count', 1)
            ->assertJsonPath('data.cards.4.count', 1)
            ->assertJsonPath('data.cards.6.count', 1)
            ->assertJsonPath('data.cards.8.count', 1)
            ->assertJsonPath('data.balanceSheet.totalRevenue', '80.00');
    }

    public function test_card_analysis_balance_sheet_reconciles_all_card_types_and_marks_unknown_values(): void
    {
        [$staff, $site] = $this->staffContext(['report.read']);
        $member = $this->member($site, '三类卡会员');
        $storedCard = $this->card($site, $member, CardType::StoredValue, MemberCardStatus::Active, [
            'cached_balance' => 75,
        ]);
        $countCard = $this->card($site, $member, CardType::Count, MemberCardStatus::Active, [
            'cached_remaining_count' => 8,
        ]);
        $periodCard = $this->card($site, $member, CardType::Period, MemberCardStatus::Active);

        foreach ([[$storedCard, 100], [$countCard, 200], [$periodCard, 300]] as $index => [$card, $amount]) {
            MemberCardOrder::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'member_card_id' => $card->id,
                'order_no' => 'ORD-ASSET-'.$index,
                'amount' => $amount,
                'status' => MemberCardOrderStatus::Paid,
                'created_by_staff_id' => $staff->id,
                'paid_at' => now(),
            ]);
        }
        MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-ASSET-PENDING',
            'amount' => 999,
            'status' => MemberCardOrderStatus::PendingPayment,
            'created_by_staff_id' => $staff->id,
        ]);

        $this->consumptionEvent($site, $staff, $member, $storedCard, 2_500);
        $this->consumptionEvent($site, $staff, $member, $countCard, 5_000, 'provisional');
        $this->consumptionEvent($site, $staff, $member, $periodCard, 7_500);
        $this->consumptionEvent($site, $staff, $member, $countCard, null);
        $this->consumptionEvent($site, $staff, $member, $storedCard, 10_000, 'reversed');

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $path = "/api/v1/staff/sites/{$site->id}/reports/card-analyze/summary";
        $this->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.balanceSheet.totalRevenue', '600.00')
            ->assertJsonPath('data.balanceSheet.consumedValue', '150.00')
            ->assertJsonPath('data.balanceSheet.remainingValue', '450.00')
            ->assertJsonPath('data.balanceSheet.excessConsumedValue', '0.00')
            ->assertJsonPath('data.balanceSheet.unvaluedCount', 1)
            ->assertJsonPath('data.balanceSheet.hasUnvalued', true)
            ->assertJsonPath('data.balanceSheet.remainingValueIsEstimate', true);

        // Gift/imported value can legitimately exceed paid revenue. The report
        // exposes the difference instead of producing a negative asset value.
        $this->consumptionEvent($site, $staff, $member, $periodCard, 50_000);
        $this->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.balanceSheet.consumedValue', '650.00')
            ->assertJsonPath('data.balanceSheet.remainingValue', '0.00')
            ->assertJsonPath('data.balanceSheet.excessConsumedValue', '50.00');
    }

    public function test_card_sales_detail_database_paginates_and_totals_effective_amount(): void
    {
        [$staff, $site] = $this->staffContext(['report.read']);
        $member = $this->member($site, '售卡明细会员');
        $orders = collect([
            ['orderNo' => 'ORD-DETAIL-NEWEST', 'paidAt' => now()->subMinute()],
            ['orderNo' => 'ORD-DETAIL-MIDDLE', 'paidAt' => now()->subMinutes(2)],
            ['orderNo' => 'ORD-DETAIL-OLDEST', 'paidAt' => now()->subMinutes(3)],
        ])->map(fn (array $row) => MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => $row['orderNo'],
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $staff->id,
            'paid_at' => $row['paidAt'],
        ]));
        OrderAmountCorrection::create([
            'tenant_id' => $site->tenant_id,
            'order_id' => $orders[1]->id,
            'entry_type' => OrderAmountCorrectionType::Correction,
            'corrected_amount' => 50,
            'command_key' => (string) Str::uuid(),
            'reason' => '售卡明细有效金额',
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/card-sales/detail?page=2&perPage=1")
            ->assertOk()
            ->assertJsonPath('data.totals.salesCount', 3)
            ->assertJsonPath('data.totals.revenue', '250.00')
            ->assertJsonPath('data.items.0.orderNo', 'ORD-DETAIL-MIDDLE')
            ->assertJsonPath('data.items.0.amount', '50.00')
            ->assertJsonPath('data.pagination.page', 2)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.lastPage', 3);
    }

    public function test_change_log_prefers_ledger_product_name_snapshot_then_falls_back_to_current_name(): void
    {
        [$staff, $site] = $this->staffContext(['report.read']);
        $member = $this->member($site, '历史卡名会员');
        $product = CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::StoredValue,
            'name' => '原始当前卡名',
            'price' => 100,
            'face_value' => 100,
            'activation_mode' => 'immediate',
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
            'version' => 1,
            'created_by_staff_id' => $staff->id,
        ]);
        $snapshotCard = $this->card($site, $member, CardType::StoredValue, MemberCardStatus::Active, [
            'card_product_id' => $product->id,
            'product_snapshot' => [],
        ]);
        $fallbackCard = $this->card($site, $member, CardType::StoredValue, MemberCardStatus::Active, [
            'card_product_id' => $product->id,
            'product_snapshot' => [],
        ]);

        EntitlementLedgerEntry::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_card_id' => $fallbackCard->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 100,
            'reason' => '旧数据无快照',
            'actor_staff_id' => $staff->id,
            'occurred_at' => now()->subMinute(),
        ]);
        EntitlementLedgerEntry::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_card_id' => $snapshotCard->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 100,
            'reason' => '新数据有快照',
            'metadata' => ['cardProductName' => '发卡时历史卡名'],
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);
        $product->update(['name' => '更名后当前卡名']);

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/change-log")
            ->assertOk()
            ->assertJsonPath('data.items.0.cardName', '发卡时历史卡名')
            ->assertJsonPath('data.items.1.cardName', '更名后当前卡名');
    }

    /** @return array{0: Staff, 1: Site, 2: Role} */
    private function staffContext(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Report Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Report Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Report',
            'code' => fake()->unique()->slug(1),
            'status' => 'active',
        ]);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => 'reporting'],
            );
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site, $role];
    }

    private function member(Site $site, string $name): Member
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
            'name' => $name,
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

    private function card(
        Site $site,
        Member $member,
        CardType $type,
        MemberCardStatus $status,
        array $overrides = [],
    ): MemberCard {
        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => $type,
            'card_no' => 'MC-'.fake()->unique()->numerify('#####'),
            'status' => $status,
            'member_visibility' => MemberCardVisibility::Visible,
            'product_snapshot' => ['name' => '分析测试卡'],
            'valid_until' => now()->addMonth(),
            'issued_at' => now(),
            ...$overrides,
        ]);
    }

    private function consumptionEvent(
        Site $site,
        Staff $coach,
        Member $member,
        MemberCard $card,
        ?int $consumedValueCents,
        string $status = 'final',
    ): ConsumptionEvent {
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '资产负债测试课-'.Str::random(6),
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
            'created_by_staff_id' => $coach->id,
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Completed,
            'session_kind' => ScheduleSessionKind::Group,
            'created_by_staff_id' => $coach->id,
        ]);
        $appointment = Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => AppointmentStatus::Completed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now()->subHours(2),
        ]);

        return ConsumptionEvent::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'appointment_id' => $appointment->id,
            'session_id' => $session->id,
            'course_id' => $course->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'coach_staff_id' => $coach->id,
            'business_date' => now()->toDateString(),
            'card_type' => $card->card_type->value,
            'consumed_value_cents' => $consumedValueCents,
            'value_provenance' => $consumedValueCents === null ? 'unknown' : 'actual',
            'status' => $status,
            'source' => 'manual',
            'command_key' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
    }
}
