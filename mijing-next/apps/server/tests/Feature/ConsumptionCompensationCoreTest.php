<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\CommissionSettlementLine;
use App\Models\CompensationRole;
use App\Models\Course;
use App\Models\CourseCompensationRoleRate;
use App\Models\CourseCompensationRule;
use App\Models\EntitlementLedgerEntry;
use App\Models\EntitlementReservation;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardValueLot;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionStaffAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Models\Tenant;
use App\Services\Booking\BookingEntitlementService;
use App\Services\Cards\MemberCardAdjustService;
use App\Services\Compensation\CompensationRoleService;
use App\Services\Compensation\ConsumptionDomainBackfillService;
use App\Services\Compensation\ConsumptionReportQueryService;
use App\Services\Compensation\ConsumptionSettlementService;
use App\Services\Compensation\MemberCardShareAssignmentService;
use App\Services\Compensation\MemberCardValueLotService;
use App\Support\DomainActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConsumptionCompensationCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_booking_reserves_paid_lot_idempotently_and_cancellation_restores_exact_units(): void
    {
        [$site, , $card] = $this->countCardFixture(10);
        $lot = $this->paidLot($card, 200_000, 10, 10);
        $service = app(BookingEntitlementService::class);
        $key = (string) Str::uuid();

        $first = $service->deductForBooking($card, $site, $key, CardType::Count, 1, null);
        $replay = $service->deductForBooking($card->fresh(), $site, $key, CardType::Count, 1, null);

        $this->assertTrue($first['created']);
        $this->assertFalse($replay['created']);
        $this->assertSame($first['ledgerEntryId'], $replay['ledgerEntryId']);
        $this->assertSame(9, $card->fresh()->cached_remaining_count);
        $this->assertSame(9, $lot->fresh()->remaining_count);
        $entry = EntitlementLedgerEntry::findOrFail($first['ledgerEntryId']);
        $this->assertSame(20_000, $entry->metadata['knownValueCents']);

        $refund = $service->refundForCancellation($entry, $site, (string) Str::uuid());
        $this->assertTrue($refund['created']);
        $this->assertSame(10, $card->fresh()->cached_remaining_count);
        $this->assertSame(10, $lot->fresh()->remaining_count);
    }

    public function test_booking_command_key_reuse_with_different_financial_payload_is_rejected(): void
    {
        [$site, , $card] = $this->countCardFixture(10);
        $this->paidLot($card, 200_000, 10, 10);
        $service = app(BookingEntitlementService::class);
        $key = (string) Str::uuid();
        $service->deductForBooking($card, $site, $key, CardType::Count, 1, null);

        try {
            $service->deductForBooking($card->fresh(), $site, $key, CardType::Count, 2, null);
            $this->fail('Expected the reused command key to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('IDEMPOTENCY_KEY_REUSED', $exception->getMessage());
        }
        $this->assertSame(9, $card->fresh()->cached_remaining_count);
    }

    public function test_correcting_manual_count_credit_never_consumes_an_older_paid_lot(): void
    {
        [$site, $staff, $card] = $this->countCardFixture(10);
        $paidLot = $this->paidLot($card, 200_000, 10, 10);
        $adjustments = app(MemberCardAdjustService::class);
        $credit = $adjustments->adjustCount($staff, $site, $card, [
            'direction' => EntitlementLedgerDirection::Credit->value,
            'count' => 2,
            'reason' => '测试赠送两次',
            'commandKey' => (string) Str::uuid(),
        ]);
        $originalId = $credit['ledgerEntryIds'][0];
        $originalFreeLot = MemberCardValueLot::query()->where('source_ledger_entry_id', $originalId)->firstOrFail();

        $adjustments->adjustCount($staff, $site, $card->fresh(), [
            'direction' => EntitlementLedgerDirection::Credit->value,
            'count' => 1,
            'correctsEntryId' => $originalId,
            'reason' => '纠正为赠送一次',
            'commandKey' => (string) Str::uuid(),
        ]);

        $this->assertSame(11, $card->fresh()->cached_remaining_count);
        $this->assertSame(10, $paidLot->fresh()->remaining_count);
        $this->assertSame(0, $originalFreeLot->fresh()->remaining_count);
        $this->assertDatabaseHas('member_card_value_lots', [
            'member_card_id' => $card->id,
            'source_type' => 'manual_adjustment',
            'remaining_count' => 1,
        ]);
    }

    public function test_legacy_count_backfill_keeps_original_denominator_and_current_remainder(): void
    {
        [, , $card] = $this->countCardFixture(5, initialCount: 10);

        $lot = app(MemberCardValueLotService::class)->recordDerivedBackfill($card, 200_000, 'derived');

        $this->assertSame(10, $lot->entitlement_count);
        $this->assertSame(5, $lot->remaining_count);
        $this->assertSame(40_000, app(MemberCardValueLotService::class)->previewCountValue($card, 2)['valueCents']);
    }

    public function test_settle_and_full_reverse_restore_card_lot_reservation_and_commission_net(): void
    {
        [$site, $staff, $card] = $this->countCardFixture(10);
        $lot = $this->paidLot($card, 200_000, 10, 10);
        [$course, $role] = $this->compensatedCourse($site, $staff, 1_000, 1_000);
        $session = $this->completedSession($site, $course, $staff, $role);
        $ledger = app(BookingEntitlementService::class)->deductForBooking(
            $card, $site, (string) Str::uuid(), CardType::Count, 1, null,
        );
        $appointment = $this->completedAppointment($site, $session, $card, $ledger['ledgerEntryId']);

        $settlements = app(ConsumptionSettlementService::class);
        $event = $settlements->settle($appointment, 'manual', $staff->id);

        $this->assertSame(20_000, $event->consumed_value_cents);
        $this->assertSame(3_000, (int) $event->lines->sum('amount_cents'));
        $this->assertSame('consumed', EntitlementReservation::findOrFail($event->entitlement_reservation_id)->status);
        $this->assertSame(9, $card->fresh()->cached_remaining_count);
        $this->assertSame(9, $lot->fresh()->remaining_count);

        $reversed = $settlements->reverse(
            $event,
            '会员请假审批后完整冲正',
            (string) Str::uuid(),
            DomainActor::staff($staff),
        );

        $this->assertSame('reversed', $reversed->status);
        $this->assertSame(0, (int) CommissionSettlementLine::query()
            ->where('consumption_event_id', $event->id)->sum('amount_cents'));
        $this->assertSame('reversed', EntitlementReservation::findOrFail($reversed->entitlement_reservation_id)->status);
        $this->assertSame(10, $card->fresh()->cached_remaining_count);
        $this->assertSame(10, $lot->fresh()->remaining_count);

        $reports = app(ConsumptionReportQueryService::class);
        $reversedPage = $reports->paginate(
            $site->tenant_id,
            $site->id,
            ['status' => 'reversed'],
            'member',
        );
        $reversedRow = $reports->present($reversedPage->items()[0]);
        $this->assertSame(1, $reversedRow['consumptionCount']);
        $this->assertSame(20_000, $reversedRow['consumedValueCents']);
        $this->assertSame(1, $reports->totals($site->tenant_id, $site->id, ['status' => 'reversed'])['consumptionCount']);
    }

    public function test_two_members_in_one_session_generate_session_fee_once_across_multiple_delivery_staff(): void
    {
        [$site, $coachA, $cardA] = $this->countCardFixture(10);
        [$course, $role] = $this->compensatedCourse($site, $coachA, 1_000, 1_000);
        $coachB = $this->siteStaff($site, 'Coach B');
        foreach ([$coachA, $coachB] as $coach) {
            StaffCompensationRoleAssignment::create([
                'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
                'staff_id' => $coach->id, 'compensation_role_id' => $role->id,
                'status' => 'active', 'version' => 1, 'active_from' => now()->subDay()->toDateString(),
            ]);
        }
        $session = $this->completedSession($site, $course, $coachA, $role, addRoleAssignment: false);
        foreach ([[$coachA, true], [$coachB, false]] as [$coach, $primary]) {
            ScheduleSessionStaffAssignment::create([
                'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
                'schedule_session_id' => $session->id, 'staff_id' => $coach->id,
                'compensation_role_id' => $role->id, 'allocation_bps' => 5_000,
                'is_primary' => $primary, 'assignment_version' => 1,
            ]);
        }
        $cardB = $this->memberCardAtSite($site, 10);
        foreach ([$cardA, $cardB] as $card) {
            $this->paidLot($card, 200_000, 10, 10);
        }
        $appointments = collect([$cardA, $cardB])->map(function (MemberCard $card) use ($site, $session) {
            $ledger = app(BookingEntitlementService::class)->deductForBooking(
                $card, $site, (string) Str::uuid(), CardType::Count, 1, null,
            );

            return $this->completedAppointment($site, $session, $card, $ledger['ledgerEntryId']);
        });
        $settlements = app(ConsumptionSettlementService::class);
        $events = $appointments->map(fn (Appointment $appointment) => $settlements->settle($appointment, 'manual', $coachA->id));
        $lineCount = CommissionSettlementLine::query()->whereIn('consumption_event_id', $events->pluck('id'))->count();

        $this->assertSame(1_000, (int) CommissionSettlementLine::query()
            ->whereIn('consumption_event_id', $events->pluck('id'))->where('component', 'session_fee')->sum('amount_cents'));
        $this->assertSame(4_000, (int) CommissionSettlementLine::query()
            ->whereIn('consumption_event_id', $events->pluck('id'))->where('component', 'consumption_commission')->sum('amount_cents'));
        $this->assertSame(40_000, (int) DB::table('consumption_event_recipient_allocations')
            ->whereIn('consumption_event_id', $events->pluck('id'))->where('recipient_type', 'delivery')->sum('allocated_value_cents'));
        $this->assertEqualsCanonicalizing(
            $events->pluck('id')->all(),
            $settlements->queryForSite($site->tenant_id, $site->id, ['coachStaffId' => $coachB->id])
                ->pluck('id')
                ->all(),
        );

        $replay = $settlements->settle($appointments->first(), 'manual', $coachA->id);
        $this->assertSame($events->first()->id, $replay->id);
        $this->assertSame($lineCount, CommissionSettlementLine::query()->whereIn('consumption_event_id', $events->pluck('id'))->count());
    }

    public function test_period_day_value_is_stable_across_two_consumptions_and_presenter_exposes_formula_and_reversal_reason(): void
    {
        [$site, $staff, $card] = $this->countCardFixture(0);
        $validFrom = now()->subDays(10)->startOfDay();
        $card->update([
            'card_type' => CardType::Period,
            'cached_remaining_count' => null,
            'valid_from' => $validFrom->toDateString(),
            'valid_until' => $validFrom->copy()->addDays(364)->toDateString(),
            'product_snapshot' => ['name' => '核心测试年卡', 'validityDays' => 365],
        ]);
        $lot = MemberCardValueLot::create([
            'tenant_id' => $card->tenant_id,
            'site_id' => $card->site_id,
            'member_id' => $card->member_id,
            'member_card_id' => $card->id,
            'source_type' => 'purchase',
            'payment_method' => 'online',
            'value_provenance' => 'actual',
            'paid_amount_cents' => 3_650_000,
            'entitlement_days' => 365,
            'valid_from' => $card->fresh()->valid_from,
            'valid_until' => $card->fresh()->valid_until,
            'command_key' => 'period-lot-'.Str::uuid(),
            'occurred_at' => now(),
        ]);
        [$course, $role] = $this->compensatedCourse($site, $staff, 0, 1_000);
        $sessionA = $this->completedSession($site, $course, $staff, $role);
        $sessionB = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'delivery_role_id' => $role->id,
            'starts_at' => $sessionA->starts_at->copy()->addMinutes(30),
            'ends_at' => $sessionA->ends_at->copy()->addMinutes(30),
            'capacity' => 12,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Completed,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
        $settlements = app(ConsumptionSettlementService::class);
        $events = collect([$sessionA, $sessionB])->map(function (ScheduleSession $session) use ($card, $site, $settlements, $staff) {
            $ledger = app(BookingEntitlementService::class)->deductForBooking(
                $card->fresh(),
                $site,
                (string) Str::uuid(),
                CardType::Period,
                null,
                null,
            );
            $appointment = $this->completedAppointment($site, $session, $card, $ledger['ledgerEntryId']);

            return $settlements->settle($appointment, 'manual', $staff->id);
        });

        $events = $events->map->fresh('lines');
        $this->assertSame([5_000, 5_000], $events->pluck('consumed_value_cents')->sort()->values()->all());
        $this->assertSame(1_000, (int) CommissionSettlementLine::query()
            ->whereIn('consumption_event_id', $events->pluck('id'))
            ->where('component', 'consumption_commission')
            ->sum('amount_cents'));
        foreach ($events as $event) {
            $presented = $settlements->present($event);
            $this->assertSame(3_650_000, $presented['formulaInputs']['paidAmountCents']);
            $this->assertSame(365, $presented['formulaInputs']['entitlementDays']);
            $this->assertSame(10_000, $presented['formulaInputs']['dayValueCents']);
            $this->assertSame(2, $presented['formulaInputs']['activeDayConsumptionCount']);
            $this->assertSame(5_000, $presented['formulaInputs']['consumedValueCents']);
        }

        $reason = '会员请假后冲正';
        $reversed = $settlements->reverse(
            $events->first(),
            $reason,
            (string) Str::uuid(),
            DomainActor::staff($staff),
        );
        $this->assertSame($reason, $settlements->present($reversed)['reversalReason']);
        $this->assertSame(10_000, $events->last()->fresh()->consumed_value_cents);
        $this->assertSame($lot->id, $events->last()->fresh()->value_lot_id);
    }

    public function test_staff_consumption_reports_mask_member_names_without_crm_permission(): void
    {
        [$site, $staff, $card] = $this->countCardFixture(10);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $card->member_id,
            'name' => '张小明',
        ]);
        $this->paidLot($card, 200_000, 10, 10);
        [$course, $deliveryRole] = $this->compensatedCourse($site, $staff, 1_000, 1_000);
        $session = $this->completedSession($site, $course, $staff, $deliveryRole);
        $ledger = app(BookingEntitlementService::class)->deductForBooking(
            $card,
            $site,
            (string) Str::uuid(),
            CardType::Count,
            1,
            null,
        );
        $appointment = $this->completedAppointment($site, $session, $card, $ledger['ledgerEntryId']);
        $event = app(ConsumptionSettlementService::class)->settle($appointment, 'manual', $staff->id);

        $role = Role::create([
            'tenant_id' => $site->tenant_id,
            'name' => '耗卡查看者',
            'code' => 'consumption-reader-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);
        $consumptionPermission = Permission::firstOrCreate(
            ['code' => 'consumption.read'],
            ['name' => '查看耗卡', 'module' => 'compensation'],
        );
        $adjustPermission = Permission::firstOrCreate(
            ['code' => 'consumption.adjust'],
            ['name' => '调整耗卡', 'module' => 'compensation'],
        );
        $role->permissions()->attach([$consumptionPermission->id, $adjustPermission->id]);
        $staff->roles()->attach($role->id, ['tenant_id' => $site->tenant_id, 'site_id' => $site->id]);

        $crmPermission = Permission::firstOrCreate(
            ['code' => 'crm.member.read'],
            ['name' => '查看会员资料', 'module' => 'crm'],
        );
        $otherSite = Site::create([
            'tenant_id' => $site->tenant_id,
            'name' => '其他有姓名权限的场馆',
            'code' => 'other-crm-site',
            'status' => 'active',
        ]);
        $staff->sites()->attach($otherSite->id, ['tenant_id' => $site->tenant_id, 'is_primary' => false]);
        $otherSiteCrmRole = Role::create([
            'tenant_id' => $site->tenant_id,
            'name' => '其他场馆会员查看者',
            'code' => 'other-site-crm-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);
        $otherSiteCrmRole->permissions()->attach($crmPermission->id);
        $staff->roles()->attach($otherSiteCrmRole->id, [
            'tenant_id' => $site->tenant_id,
            'site_id' => $otherSite->id,
        ]);
        $this->assertTrue($staff->hasPermission('crm.member.read', $otherSite->id));
        $this->assertFalse($staff->hasPermission('crm.member.read', $site->id));

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $path = "/api/v1/staff/sites/{$site->id}/consumption-settlements";
        $this->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '张**');
        $this->getJson($path.'?dimension=member')
            ->assertOk()
            ->assertJsonPath('data.items.0.dimensionName', '张**');
        $this->getJson($path."/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.memberName', '张**');

        $encodedName = rawurlencode('张小明');
        $this->getJson($path.'?query='.$encodedName)
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.summary.consumptionCount', 0);
        $this->getJson($path.'?dimension=member&query='.$encodedName)
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.summary.consumptionCount', 0);

        $memberNo = Member::findOrFail($card->member_id)->member_no;
        $this->getJson($path.'?query='.rawurlencode($memberNo))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $event->id)
            ->assertJsonPath('data.items.0.memberName', '张**')
            ->assertJsonPath('data.pagination.total', 1);
        $this->getJson($path.'?dimension=member&query='.rawurlencode($memberNo))
            ->assertOk()
            ->assertJsonPath('data.items.0.key', $card->member_id)
            ->assertJsonPath('data.items.0.dimensionName', '张**')
            ->assertJsonPath('data.pagination.total', 1);

        foreach ([$course->name, $card->card_no, $staff->name, $deliveryRole->name] as $allowedTerm) {
            $this->getJson($path.'?query='.rawurlencode($allowedTerm))
                ->assertOk()
                ->assertJsonPath('data.items.0.id', $event->id);
        }

        $appointmentPath = "/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/consumption-settlement";
        $this->getJson($appointmentPath)
            ->assertOk()
            ->assertJsonPath('data.memberName', '张**');
        $this->postJson($appointmentPath)
            ->assertOk()
            ->assertJsonPath('data.memberName', '张**');
        $this->postJson($path."/{$event->id}/reverse", [
            'reason' => '会员申请撤销耗卡',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('data.memberName', '张**')
            ->assertJsonPath('data.status', 'reversed');

        $role->permissions()->attach($crmPermission->id);
        $this->assertTrue($staff->hasPermission('crm.member.read', $site->id));

        $this->getJson($path.'?query='.$encodedName)
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', '张小明');
        $this->getJson($path.'?dimension=member&status=reversed&query='.$encodedName)
            ->assertOk()
            ->assertJsonPath('data.items.0.dimensionName', '张小明');
        $this->getJson($path."/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.memberName', '张小明');
    }

    public function test_completed_legacy_backfill_is_durably_excluded_from_retroactive_commission(): void
    {
        [$site, $staff, $card] = $this->countCardFixture(10);
        $this->paidLot($card, 200_000, 10, 10);
        [$course, $role] = $this->compensatedCourse($site, $staff, 1_000, 1_000);
        $session = $this->completedSession($site, $course, $staff, $role);
        $deduct = app(BookingEntitlementService::class)->deductForBooking(
            $card,
            $site,
            (string) Str::uuid(),
            CardType::Count,
            1,
            null,
        );
        $appointment = $this->completedAppointment($site, $session, $card, $deduct['ledgerEntryId']);

        $stats = app(ConsumptionDomainBackfillService::class)->run(true, $site->tenant_id, 100);
        $reservation = EntitlementReservation::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertSame(1, $stats['completedReservationsImported']);
        $this->assertSame('consumed', $reservation->status);
        $this->assertTrue($reservation->metadata['noCommission']);
        $this->assertSame('disabled_legacy_import', $reservation->metadata['commissionEligibility']);
        $this->assertNull(app(ConsumptionSettlementService::class)->settle($appointment, 'backfill', $staff->id));
        $this->assertDatabaseMissing('consumption_events', ['appointment_id' => $appointment->id]);
        $this->assertDatabaseCount('commission_settlement_lines', 0);
    }

    public function test_future_share_replacement_keeps_current_owner_until_the_cutover_date(): void
    {
        [$site, $currentOwner, $card] = $this->countCardFixture(10);
        $futureOwner = $this->siteStaff($site, 'Future owner');
        $role = CompensationRole::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'code' => 'share-'.Str::lower(Str::random(6)),
            'name' => '会籍归属',
            'role_type' => 'share',
            'status' => 'active',
            'version' => 1,
        ]);
        foreach ([$currentOwner, $futureOwner] as $staff) {
            StaffCompensationRoleAssignment::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'staff_id' => $staff->id,
                'compensation_role_id' => $role->id,
                'status' => 'active',
                'version' => 1,
                'active_from' => now()->subDay()->toDateString(),
            ]);
        }
        $service = app(MemberCardShareAssignmentService::class);
        $today = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
        $cutover = now()->timezone($site->timezone ?: config('app.timezone'))->addDays(5)->toDateString();
        $service->replace($card, $site, [[
            'staffId' => $currentOwner->id,
            'compensationRoleId' => $role->id,
            'allocationBps' => 10_000,
            'effectiveFrom' => $today,
        ]], DomainActor::staff($currentOwner), (string) Str::uuid(), '初始归属');
        $service->replace($card->fresh(), $site, [[
            'staffId' => $futureOwner->id,
            'compensationRoleId' => $role->id,
            'allocationBps' => 10_000,
            'effectiveFrom' => $cutover,
        ]], DomainActor::staff($currentOwner), (string) Str::uuid(), '预约未来转交');

        $this->assertSame([$currentOwner->id], $service->activeForCard($card, $today)->pluck('staff_id')->all());
        $this->assertSame([$futureOwner->id], $service->activeForCard($card, $cutover)->pluck('staff_id')->all());
    }

    public function test_future_staff_role_replacement_preserves_the_current_effective_interval(): void
    {
        [$site, $staff] = $this->countCardFixture(1);
        $role = CompensationRole::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'code' => 'delivery-'.Str::lower(Str::random(6)),
            'name' => '实际履约者',
            'role_type' => 'delivery',
            'status' => 'active',
            'version' => 1,
        ]);
        $today = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
        $cutover = now()->timezone($site->timezone ?: config('app.timezone'))->addDays(7)->toDateString();
        $service = app(CompensationRoleService::class);
        $service->assignStaff(DomainActor::staff($staff), $site, $staff, [[
            'compensationRoleId' => $role->id,
            'activeFrom' => $today,
        ]], (string) Str::uuid(), '初始角色');
        $visible = $service->assignStaff(DomainActor::staff($staff), $site, $staff, [[
            'compensationRoleId' => $role->id,
            'activeFrom' => $cutover,
        ]], (string) Str::uuid(), '预排新版归属');

        $this->assertCount(2, $visible);
        $old = collect($visible)->firstWhere('status', 'archived');
        $new = collect($visible)->firstWhere('status', 'active');
        $this->assertSame(now()->timezone($site->timezone ?: config('app.timezone'))->addDays(6)->toDateString(), $old->active_until->toDateString());
        $this->assertSame($cutover, $new->active_from->toDateString());
    }

    /** @return array{Site,Staff,MemberCard} */
    private function countCardFixture(int $remaining, ?int $initialCount = null): array
    {
        $tenant = Tenant::create(['name' => 'Core Tenant', 'code' => 'core-'.Str::lower(Str::random(8))]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Core Site', 'code' => 'core', 'status' => 'active']);
        $account = Account::create(['display_name' => 'Core Staff', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => 'CORE-'.Str::upper(Str::random(6)),
            'name' => 'Core Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'CORE-M-'.Str::upper(Str::random(6)),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'CORE-C-'.Str::upper(Str::random(6)),
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '核心测试次卡', 'initialCount' => $initialCount ?? $remaining],
            'cached_remaining_count' => $remaining,
            'issued_at' => now(),
        ]);

        return [$site, $staff, $card];
    }

    private function paidLot(MemberCard $card, int $paidCents, int $count, int $remaining): MemberCardValueLot
    {
        return MemberCardValueLot::create([
            'tenant_id' => $card->tenant_id,
            'site_id' => $card->site_id,
            'member_id' => $card->member_id,
            'member_card_id' => $card->id,
            'source_type' => 'purchase',
            'payment_method' => 'online',
            'value_provenance' => 'actual',
            'paid_amount_cents' => $paidCents,
            'entitlement_count' => $count,
            'remaining_count' => $remaining,
            'command_key' => 'core-lot-'.Str::uuid(),
            'occurred_at' => now(),
        ]);
    }

    /** @return array{Course,CompensationRole} */
    private function compensatedCourse(Site $site, Staff $staff, int $sessionFeeCents, int $rateBps): array
    {
        $course = Course::create([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
            'course_type' => CourseType::Group, 'name' => '核心测试课程',
            'duration_minutes' => 60, 'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $role = CompensationRole::create([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
            'code' => 'delivery-'.Str::lower(Str::random(6)), 'name' => '授课者',
            'role_type' => 'delivery', 'status' => 'active', 'version' => 1,
        ]);
        $rule = CourseCompensationRule::create([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'course_id' => $course->id,
            'session_fee_cents' => $sessionFeeCents, 'version' => 1, 'status' => 'active',
            'created_by_staff_id' => $staff->id, 'effective_at' => now()->subDay(),
        ]);
        CourseCompensationRoleRate::create([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
            'course_compensation_rule_id' => $rule->id, 'compensation_role_id' => $role->id,
            'rate_bps' => $rateBps,
        ]);

        return [$course, $role];
    }

    private function completedSession(
        Site $site,
        Course $course,
        Staff $coach,
        CompensationRole $role,
        bool $addRoleAssignment = true,
    ): ScheduleSession {
        if ($addRoleAssignment) {
            StaffCompensationRoleAssignment::create([
                'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
                'staff_id' => $coach->id, 'compensation_role_id' => $role->id,
                'status' => 'active', 'version' => 1, 'active_from' => now()->subDay()->toDateString(),
            ]);
        }

        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
            'course_id' => $course->id, 'coach_staff_id' => $coach->id,
            'delivery_role_id' => $role->id, 'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(), 'capacity' => 12, 'booked_count' => 1,
            'status' => ScheduleSessionStatus::Completed, 'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }

    private function completedAppointment(Site $site, ScheduleSession $session, MemberCard $card, int $ledgerId): Appointment
    {
        return Appointment::create([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id,
            'session_id' => $session->id, 'member_id' => $card->member_id,
            'member_card_id' => $card->id, 'ledger_entry_id' => $ledgerId,
            'status' => AppointmentStatus::Completed, 'command_key' => (string) Str::uuid(),
            'booked_at' => now()->subHours(3),
        ]);
    }

    private function siteStaff(Site $site, string $name): Staff
    {
        $staff = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => $name, 'status' => 'active'])->id,
            'employee_no' => 'CORE-'.Str::upper(Str::random(6)), 'name' => $name, 'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $site->tenant_id, 'is_primary' => false]);

        return $staff;
    }

    private function memberCardAtSite(Site $site, int $remaining): MemberCard
    {
        $member = Member::create([
            'tenant_id' => $site->tenant_id, 'member_no' => 'CORE-M-'.Str::upper(Str::random(6)),
            'registration_site_id' => $site->id, 'home_site_id' => $site->id, 'status' => 'active',
        ]);

        return MemberCard::create([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'member_id' => $member->id,
            'card_type' => CardType::Count, 'card_no' => 'CORE-C-'.Str::upper(Str::random(6)),
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '核心测试次卡', 'initialCount' => $remaining],
            'cached_remaining_count' => $remaining, 'issued_at' => now(),
        ]);
    }
}
