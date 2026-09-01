<?php

namespace App\Services\Compensation;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Models\Appointment;
use App\Models\CommissionSettlementLine;
use App\Models\CompensationRole;
use App\Models\ConsumptionEvent;
use App\Models\CourseCompensationRoleRate;
use App\Models\CourseCompensationRule;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use App\Models\MemberCardValueLot;
use App\Models\PeriodDayBucket;
use App\Models\PeriodDayBucketRevision;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionStaffAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Services\Booking\BookingEntitlementService;
use App\Services\Cards\MemberCardHolidayCalendarService;
use App\Support\DomainActor;
use App\Support\Finance\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsumptionSettlementService
{
    public function __construct(
        private EntitlementReservationService $reservations,
        private MemberCardValueLotService $valueLots,
        private MemberCardShareAssignmentService $shares,
        private CourseCompensationRuleService $courseRules,
        private DeterministicAllocationCalculator $allocator,
        private CommissionCalculator $commissions,
        private CommissionPostingService $posting,
        private BookingEntitlementService $entitlements,
        private PayrollPeriodService $payrollPeriods,
        private MemberCardHolidayCalendarService $holidays,
    ) {}

    public function settle(
        Appointment $appointment,
        string $source = 'manual',
        ?int $actorStaffId = null,
    ): ?ConsumptionEvent {
        abort_unless(in_array($source, ['manual', 'auto', 'backfill'], true), 422);

        return DB::transaction(function () use ($appointment, $source, $actorStaffId) {
            // Acquire the shared payroll barrier before locking appointment/session/
            // card facts. Fulfillment callers acquire it too, before their outer
            // locks; this repeat is intentional for direct settlement commands.
            $this->payrollPeriods->acquireSiteBarrier(
                (int) $appointment->tenant_id,
                (int) $appointment->site_id,
            );
            $existing = ConsumptionEvent::query()
                ->where('tenant_id', $appointment->tenant_id)
                ->where('appointment_id', $appointment->id)
                ->first();
            if ($existing) {
                return $existing->fresh(['lines']);
            }

            $appointmentSnapshot = Appointment::query()
                ->where('tenant_id', $appointment->tenant_id)
                ->whereKey($appointment->id)
                ->firstOrFail();
            $session = ScheduleSession::query()
                ->where('tenant_id', $appointmentSnapshot->tenant_id)
                ->whereKey($appointmentSnapshot->session_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = Appointment::query()
                ->where('tenant_id', $appointment->tenant_id)
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless((int) $locked->session_id === (int) $session->id, 409, 'APPOINTMENT_SESSION_CHANGED');
            abort_unless($locked->status === AppointmentStatus::Completed, 409, 'CONSUMPTION_REQUIRES_COMPLETED');
            if ($locked->member_card_id === null) {
                return null;
            }

            $session->load('course');
            $card = MemberCard::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->member_card_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked->loadMissing('ledgerEntry');
            $locked->setRelation('session', $session);
            $locked->setRelation('memberCard', $card);
            abort_unless($session->course, 409, 'CONSUMPTION_CONTEXT_MISSING');

            // Global financial lock order is site barrier -> session -> appointment
            // -> member card -> reservation/event/settlement lines.
            $existing = ConsumptionEvent::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('appointment_id', $locked->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing->fresh(['lines']);
            }
            $site = Site::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->site_id)
                ->firstOrFail();
            $businessDate = $session->starts_at
                ->copy()
                ->timezone($site->timezone ?: config('app.timezone'))
                ->toDateString();
            $businessPeriod = $this->payrollPeriods->forPostingDate(
                (int) $locked->tenant_id,
                (int) $site->id,
                $businessDate,
            );
            abort_if($this->holidays->isBlockedOn($card, $businessDate), 409, 'MEMBER_CARD_HOLIDAY_ACTIVE');
            $reservation = $this->reservations->consume($locked);
            // Completed appointments imported from the legacy system are durable
            // historical snapshots. They never generate retroactive consumption or
            // payroll merely because an operator retries the settlement command.
            if (($reservation?->metadata['noCommission'] ?? false) === true
                || ($reservation?->metadata['commissionEligibility'] ?? null) === 'disabled_legacy_import') {
                return null;
            }
            $compensationRule = $this->courseRules->currentForCourse(
                $locked->tenant_id,
                $site->id,
                $session->course_id,
                $session->starts_at,
            );
            $deliveryRecipients = $this->deliveryRecipients($session, $businessDate);
            $shareRecipients = $this->shares->activeForCard($card, $businessDate)
                ->map(fn ($assignment) => [
                    'staffId' => $assignment->staff_id,
                    'compensationRoleId' => $assignment->compensation_role_id,
                    'allocationBps' => $assignment->allocation_bps,
                ])->values()->all();

            $valueCents = null;
            $provenance = 'unknown';
            $valueLotId = null;
            $valueAllocations = [];
            $allocation = [];
            $deductedAmount = $reservation?->reserved_amount_cents;
            $deductedCount = $reservation?->reserved_count;

            if ($card->card_type === CardType::StoredValue) {
                $valueCents = $deductedAmount;
                $provenance = $valueCents === null ? 'unknown' : 'entitlement';
            } elseif ($card->card_type === CardType::Count) {
                $reservedAllocations = $reservation?->metadata['countValueAllocations'] ?? [];
                $allocation = $reservedAllocations !== []
                    ? [
                        'valueCents' => $reservation->metadata['reservedValueCents'] ?? null,
                        'knownValueCents' => (int) ($reservation->metadata['knownValueCents'] ?? 0),
                        'unknownCount' => (int) ($reservation->metadata['unknownCount'] ?? 0),
                        'provenance' => $reservation->metadata['valueProvenance'] ?? 'unknown',
                        'valueLotId' => $reservation->metadata['valueLotId'] ?? null,
                        'allocations' => $reservedAllocations,
                    ]
                    : $this->valueLots->allocateCountValue($card, max(1, (int) $deductedCount));
                $valueCents = $allocation['valueCents'];
                $provenance = $allocation['provenance'];
                $valueLotId = $allocation['valueLotId'];
                $valueAllocations = $allocation['allocations'];
            }

            $event = ConsumptionEvent::create([
                'tenant_id' => $locked->tenant_id,
                'site_id' => $site->id,
                'appointment_id' => $locked->id,
                'session_id' => $session->id,
                'course_id' => $session->course_id,
                'member_id' => $locked->member_id,
                'member_card_id' => $card->id,
                'coach_staff_id' => $session->coach_staff_id,
                'delivery_role_id' => $deliveryRecipients[0]['compensationRoleId'] ?? null,
                'entitlement_reservation_id' => $reservation?->id,
                'ledger_entry_id' => $locked->ledger_entry_id,
                'card_product_course_rule_id' => $reservation?->card_product_course_rule_id,
                'course_compensation_rule_id' => $compensationRule?->id,
                'value_lot_id' => $valueLotId,
                'business_date' => $businessDate,
                'card_type' => $card->card_type->value,
                'deducted_amount_cents' => $deductedAmount,
                'deducted_count' => $deductedCount,
                'consumed_value_cents' => $valueCents,
                'value_provenance' => $provenance,
                'status' => $card->card_type === CardType::Period && $businessPeriod?->status !== 'closed'
                    ? 'provisional'
                    : 'final',
                'source' => $source,
                'command_key' => 'consumption:appointment:'.$locked->id,
                'metadata' => [
                    'calculationVersion' => 1,
                    'actorStaffId' => $actorStaffId,
                    'deliveryRecipients' => $deliveryRecipients,
                    'shareRecipients' => $shareRecipients,
                    'valueLotAllocations' => $valueAllocations,
                    'knownValueCents' => $allocation['knownValueCents'] ?? $valueCents,
                    'unknownCount' => $allocation['unknownCount'] ?? 0,
                    'courseCompensationRuleVersion' => $compensationRule?->version,
                    'legacySalesCommissionSeparate' => true,
                ],
                'occurred_at' => now(),
            ]);

            if ($card->card_type === CardType::Period) {
                $this->recomputePeriodDay($card, $businessDate, 'consumption_added');
            } else {
                $this->postCompensation($event);
            }

            return $event->fresh(['lines']);
        });
    }

    public function recompute(ConsumptionEvent $event, string $reason = 'manual_recompute'): ConsumptionEvent
    {
        return DB::transaction(function () use ($event, $reason) {
            $this->payrollPeriods->acquireSiteBarrier((int) $event->tenant_id, (int) $event->site_id);
            $locked = ConsumptionEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->card_type === CardType::Period->value) {
                $card = MemberCard::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->whereKey($locked->member_card_id)
                    ->firstOrFail();
                $this->recomputePeriodDay($card, $locked->business_date->toDateString(), $reason);
            } else {
                $this->postCompensation($locked);
            }

            return $locked->fresh(['lines']);
        });
    }

    public function reverse(
        ConsumptionEvent $event,
        string $reason,
        string $commandKey,
        DomainActor $actor,
    ): ConsumptionEvent {
        $reversalFingerprint = hash('sha256', json_encode([
            'eventId' => $event->id, 'reason' => $reason,
            'actorType' => $actor->type, 'actorId' => $actor->id,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($event, $reason, $commandKey, $actor, $reversalFingerprint) {
            $this->payrollPeriods->acquireSiteBarrier((int) $event->tenant_id, (int) $event->site_id);
            $snapshot = ConsumptionEvent::query()
                ->where('tenant_id', $event->tenant_id)
                ->whereKey($event->id)
                ->firstOrFail();
            ScheduleSession::query()->whereKey($snapshot->session_id)->lockForUpdate()->firstOrFail();
            Appointment::query()->whereKey($snapshot->appointment_id)->lockForUpdate()->firstOrFail();
            $card = MemberCard::query()
                ->where('tenant_id', $snapshot->tenant_id)
                ->whereKey($snapshot->member_card_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = ConsumptionEvent::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'reversed') {
                abort_unless(
                    $locked->reversal_command_key === $commandKey
                    && hash_equals(
                        (string) ($locked->metadata['reversal']['fingerprint'] ?? ''),
                        $reversalFingerprint,
                    ),
                    409,
                    'IDEMPOTENCY_KEY_REUSED',
                );

                return $locked->fresh(['lines']);
            }
            abort_if(ConsumptionEvent::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('reversal_command_key', $commandKey)
                ->where('id', '!=', $locked->id)
                ->exists(), 409, 'IDEMPOTENCY_KEY_REUSED');

            $valueLotsRestoredByLedger = false;
            if ($locked->ledger_entry_id !== null) {
                $originalLedger = EntitlementLedgerEntry::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->whereKey($locked->ledger_entry_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $site = Site::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->whereKey($locked->site_id)
                    ->firstOrFail();
                $this->entitlements->refundForCancellation(
                    $originalLedger,
                    $site,
                    $this->deriveUuid($commandKey.':consumption-entitlement-reversal'),
                    $actor->type === 'account' ? $actor->id : null,
                    $actor->staffId(),
                    '耗卡冲正：'.$reason,
                    $actor->metadata(),
                );
                $valueLotsRestoredByLedger = ($originalLedger->metadata['countValueAllocations'] ?? []) !== [];
            }

            if ($locked->entitlement_reservation_id !== null) {
                $this->reservations->reverseConsumed($locked->entitlement_reservation_id);
            }

            if ($locked->card_type === CardType::Count->value && ! $valueLotsRestoredByLedger) {
                $allocations = collect($locked->metadata['valueLotAllocations'] ?? [])->sortBy('valueLotId');
                foreach ($allocations as $allocation) {
                    if (($allocation['valueLotId'] ?? null) === null) {
                        continue;
                    }
                    $lot = MemberCardValueLot::query()
                        ->where('tenant_id', $locked->tenant_id)
                        ->whereKey((int) $allocation['valueLotId'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    $restored = (int) $lot->remaining_count + (int) $allocation['count'];
                    abort_if($restored > (int) $lot->entitlement_count, 409, 'COUNT_VALUE_LOT_RESTORE_INVALID');
                    $lot->update(['remaining_count' => $restored]);
                }
            }

            $this->zeroEventCommissions($locked);
            $survivingEvent = ConsumptionEvent::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('session_id', $locked->session_id)
                ->where('id', '!=', $locked->id)
                ->where('status', '!=', 'reversed')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            // Keep session fee attached to an effective event: zero its current session target,
            // then re-accrue on the earliest survivor. The final reversal leaves the target at zero.
            $this->zeroSessionFee($locked);
            if ($survivingEvent !== null) {
                $this->postSessionFees($survivingEvent);
            }

            $metadata = $locked->metadata ?? [];
            $metadata['reversal'] = [
                ...$actor->metadata(),
                'reason' => $reason,
                'commandKey' => $commandKey,
                'fingerprint' => $reversalFingerprint,
                'occurredAt' => now()->toIso8601String(),
            ];
            $locked->update([
                'status' => 'reversed',
                'reversal_command_key' => $commandKey,
                'reversal_reason' => $reason,
                'reversed_by_type' => $actor->type,
                'reversed_by_id' => $actor->id,
                'reversed_at' => now(),
                'metadata' => $metadata,
            ]);

            if ($locked->card_type === CardType::Period->value) {
                $this->recomputePeriodDay($card, $locked->business_date->toDateString(), 'consumption_reversed');
            }

            return $locked->fresh(['lines']);
        });
    }

    public function finalizeDue(Site $site, ?CarbonInterface $clock = null, int $graceMinutes = 30): int
    {
        $localClock = ($clock ? Carbon::instance($clock) : now())
            ->copy()
            ->timezone($site->timezone ?: config('app.timezone'))
            ->subMinutes(max(0, $graceMinutes));
        $cutoffDate = $localClock->toDateString();
        $bucketIds = PeriodDayBucket::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'open')
            ->where('business_date', '<', $cutoffDate)
            ->orderBy('id')
            ->pluck('id');
        $finalized = 0;

        foreach ($bucketIds as $bucketId) {
            DB::transaction(function () use ($bucketId, &$finalized) {
                $snapshot = PeriodDayBucket::query()->whereKey($bucketId)->first();
                if ($snapshot === null || $snapshot->status !== 'open') {
                    return;
                }
                $this->payrollPeriods->acquireSiteBarrier(
                    (int) $snapshot->tenant_id,
                    (int) $snapshot->site_id,
                );
                $card = MemberCard::query()
                    ->where('tenant_id', $snapshot->tenant_id)
                    ->whereKey($snapshot->member_card_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $bucket = PeriodDayBucket::query()->whereKey($bucketId)->lockForUpdate()->firstOrFail();
                if ($bucket->status !== 'open') {
                    return;
                }
                $this->recomputePeriodDay($card, $bucket->business_date->toDateString(), 'day_finalized');
                $bucket->fresh()->update(['status' => 'closed', 'closed_at' => now()]);
                ConsumptionEvent::query()
                    ->where('tenant_id', $bucket->tenant_id)
                    ->where('member_card_id', $bucket->member_card_id)
                    ->where('business_date', $bucket->business_date)
                    ->where('status', 'provisional')
                    ->update(['status' => 'final']);
                $finalized++;
            });
        }

        return $finalized;
    }

    public function finalizeAllDue(?CarbonInterface $clock = null, int $graceMinutes = 30): int
    {
        return Site::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->sum(fn (Site $site) => $this->finalizeDue($site, $clock, $graceMinutes));
    }

    public function preview(Appointment $appointment): array
    {
        $appointment->loadMissing(['session.course', 'memberCard', 'ledgerEntry']);
        $card = $appointment->memberCard;
        $session = $appointment->session;
        if ($card === null || $session === null) {
            return ['appointmentId' => $appointment->id, 'settleable' => false];
        }
        $site = Site::query()->where('tenant_id', $appointment->tenant_id)->findOrFail($appointment->site_id);
        $date = $session->starts_at->copy()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
        $reservation = \App\Models\EntitlementReservation::query()
            ->where('tenant_id', $appointment->tenant_id)
            ->where('appointment_id', $appointment->id)
            ->first();
        $reservationPreview = $reservation === null ? $this->reservations->preview($appointment) : null;
        $reservedAmountCents = $reservation?->reserved_amount_cents ?? $reservationPreview['amountCents'] ?? null;
        $reservedCount = $reservation?->reserved_count ?? $reservationPreview['count'] ?? null;
        $value = null;
        $provenance = 'unknown';
        if ($card->card_type === CardType::StoredValue) {
            $value = $reservedAmountCents;
            $provenance = $value === null ? 'unknown' : 'entitlement';
        } elseif ($card->card_type === CardType::Count) {
            $preview = $this->valueLots->previewCountValue($card, max(1, (int) $reservedCount));
            $value = $preview['valueCents'];
            $provenance = $preview['provenance'];
        } else {
            $day = $this->valueLots->periodDayValue($card, $date);
            $existingIds = ConsumptionEvent::query()
                ->where('tenant_id', $card->tenant_id)
                ->where('member_card_id', $card->id)
                ->where('business_date', $date)
                ->where('status', '!=', 'reversed')
                ->orderBy('id')
                ->pluck('id')
                ->all();
            if ($day['valueCents'] !== null) {
                $allocations = $this->allocator->equal($day['valueCents'], [...$existingIds, 'preview']);
                $value = $allocations['preview'];
            }
            $provenance = $day['provenance'];
        }

        return [
            'appointmentId' => $appointment->id,
            'settleable' => $appointment->status === AppointmentStatus::Completed,
            'businessDate' => $date,
            'cardType' => $card->card_type->value,
            'deduction' => [
                'amountCents' => $reservedAmountCents,
                'count' => $reservedCount,
            ],
            'estimatedConsumedValueCents' => $value,
            'valueProvenance' => $provenance,
            'existingSettlementId' => ConsumptionEvent::query()
                ->where('tenant_id', $appointment->tenant_id)
                ->where('appointment_id', $appointment->id)
                ->value('id'),
        ];
    }

    public function queryForSite(
        int $tenantId,
        int $siteId,
        array $filters = [],
        bool $canSearchMemberNames = true,
    ): Builder {
        return ConsumptionEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('business_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('business_date', '<=', $to))
            ->when($filters['memberId'] ?? null, fn ($query, $id) => $query->where('member_id', $id))
            ->when($filters['courseId'] ?? null, fn ($query, $id) => $query->where('course_id', $id))
            ->when($filters['memberCardId'] ?? null, fn ($query, $id) => $query->where('member_card_id', $id))
            ->when($filters['coachStaffId'] ?? null, fn ($query, $id) => $query->where(function ($nested) use ($id) {
                $nested->where('coach_staff_id', $id)
                    ->orWhereExists(fn ($recipients) => $recipients->selectRaw('1')
                        ->from('consumption_event_recipient_allocations')
                        ->whereColumn('consumption_event_recipient_allocations.consumption_event_id', 'consumption_events.id')
                        ->where('consumption_event_recipient_allocations.recipient_type', 'delivery')
                        ->where('consumption_event_recipient_allocations.staff_id', $id));
            }))
            ->when(
                $filters['query'] ?? null,
                fn ($query, $term) => $this->applySearch($query, (string) $term, $canSearchMemberNames),
            )
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'adjusted') {
                    return $query->where('status', '!=', 'reversed')
                        ->whereHas('lines', fn ($lines) => $lines->where('line_type', 'adjustment'));
                }

                return $query->where('status', $status);
            })
            ->with(['session.course', 'member.crmProfile', 'member.account', 'memberCard.cardProduct', 'valueLot', 'coach', 'lines.staff', 'lines.role'])
            ->orderByDesc('business_date')
            ->orderByDesc('id');
    }

    public function queryForMember(int $tenantId, int $memberId, array $filters = []): Builder
    {
        return ConsumptionEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('member_id', $memberId)
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('business_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('business_date', '<=', $to))
            ->when($filters['memberCardId'] ?? null, fn ($query, $id) => $query->where('member_card_id', $id))
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'adjusted') {
                    return $query->where('status', '!=', 'reversed')
                        ->whereHas('lines', fn ($lines) => $lines->where('line_type', 'adjustment'));
                }

                return $query->where('status', $status);
            })
            ->with(['session.course', 'member.crmProfile', 'member.account', 'memberCard.cardProduct', 'valueLot', 'coach', 'lines.staff', 'lines.role'])
            ->orderByDesc('business_date')
            ->orderByDesc('id');
    }

    public function present(ConsumptionEvent $event, bool $includeCommission = true): array
    {
        $event->loadMissing(['session.course', 'member.crmProfile', 'member.account', 'memberCard.cardProduct', 'valueLot', 'coach', 'lines.staff', 'lines.role']);
        $lines = $includeCommission
            ? $event->lines->map(fn (CommissionSettlementLine $line) => [
                'id' => $line->id,
                'staffId' => $line->staff_id,
                'staffName' => $line->staff?->name,
                'compensationRoleId' => $line->compensation_role_id,
                'roleName' => $line->role?->name,
                'roleType' => $line->role?->role_type,
                'component' => $line->component,
                'lineType' => $line->line_type,
                'baseValueCents' => $line->base_value_cents,
                'rateBps' => $line->rate_bps,
                'allocationBps' => $line->allocation_bps,
                'amountCents' => $line->amount_cents,
                'postCloseAdjustment' => (bool) ($line->metadata['postCloseAdjustment'] ?? false),
                'occurredAt' => $line->occurred_at?->toIso8601String(),
            ])->values()->all()
            : [];
        $hasAdjustments = $event->status !== 'reversed'
            && $event->lines->contains(fn (CommissionSettlementLine $line) => $line->line_type === 'adjustment');

        return [
            'id' => $event->id,
            'appointmentId' => $event->appointment_id,
            'sessionId' => $event->session_id,
            'courseId' => $event->course_id,
            'courseName' => $event->session?->course?->name,
            'memberId' => $event->member_id,
            'memberName' => $event->member?->crmProfile?->name
                ?? $event->member?->account?->display_name
                ?? $event->member?->member_no,
            'memberCardId' => $event->member_card_id,
            'cardName' => $event->memberCard?->product_snapshot['name'] ?? null,
            'coachStaffId' => $event->coach_staff_id,
            'coachName' => $event->coach?->name,
            'businessDate' => $event->business_date?->toDateString(),
            'cardType' => $event->card_type,
            'deduction' => [
                'amountCents' => $event->deducted_amount_cents,
                'count' => $event->deducted_count,
            ],
            'consumedValueCents' => $event->consumed_value_cents,
            'valueProvenance' => $event->value_provenance,
            'status' => $event->status,
            'viewStatus' => $hasAdjustments ? 'adjusted' : $event->status,
            'hasAdjustments' => $hasAdjustments,
            'reversalReason' => $event->reversal_reason,
            'source' => $event->source,
            'courseCompensationRuleVersion' => $event->metadata['courseCompensationRuleVersion'] ?? null,
            'formulaVersion' => (int) ($event->metadata['calculationVersion']
                ?? $event->metadata['courseCompensationRuleVersion']
                ?? 1),
            'formulaInputs' => $this->formulaInputs($event),
            'commissionLines' => $lines,
            'commissionTotalCents' => collect($lines)->sum('amountCents'),
            'occurredAt' => $event->occurred_at?->toIso8601String(),
        ];
    }

    public function presentForMember(ConsumptionEvent $event): array
    {
        $event->loadMissing(['session.course', 'memberCard', 'coach', 'lines']);
        $hasAdjustments = $event->status !== 'reversed'
            && $event->lines->contains(fn (CommissionSettlementLine $line) => $line->line_type === 'adjustment');

        return [
            'id' => $event->id,
            'appointmentId' => $event->appointment_id,
            'memberCardId' => $event->member_card_id,
            'memberCardName' => $event->memberCard?->product_snapshot['name'] ?? null,
            'cardType' => $event->card_type,
            'courseName' => $event->session?->course?->name,
            'coachName' => $event->coach?->name,
            'serviceDate' => $event->business_date?->toDateString(),
            'startsAt' => $event->session?->starts_at?->toIso8601String(),
            'deductionAmount' => $event->deducted_amount_cents === null
                ? null
                : Money::centsToDecimal((int) $event->deducted_amount_cents),
            'deductionCount' => $event->deducted_count,
            'consumptionValue' => $event->consumed_value_cents === null
                ? null
                : Money::centsToDecimal((int) $event->consumed_value_cents),
            'status' => $hasAdjustments ? 'adjusted' : $event->status,
            'calculationVersion' => (int) ($event->metadata['calculationVersion']
                ?? $event->metadata['courseCompensationRuleVersion']
                ?? 1),
            'settledAt' => $event->occurred_at?->toIso8601String(),
        ];
    }

    private function formulaInputs(ConsumptionEvent $event): array
    {
        $lot = $event->valueLot;
        $dayValueCents = null;
        $dayCount = $event->card_type === CardType::Period->value
            ? ConsumptionEvent::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('member_card_id', $event->member_card_id)
                ->where('business_date', $event->business_date?->toDateString())
                ->where('status', '!=', 'reversed')
                ->count()
            : null;
        if ($event->card_type === CardType::Period->value) {
            $dayValueCents = PeriodDayBucket::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('site_id', $event->site_id)
                ->where('member_card_id', $event->member_card_id)
                ->where('business_date', $event->business_date?->toDateString())
                ->value('day_value_cents');
        }

        return [
            'paidAmountCents' => $lot?->paid_amount_cents,
            'entitlementCount' => $lot?->entitlement_count,
            'entitlementDays' => $lot?->entitlement_days,
            'dayValueCents' => $dayValueCents === null ? null : (int) $dayValueCents,
            'activeDayConsumptionCount' => $dayCount,
            'deductedAmountCents' => $event->deducted_amount_cents,
            'deductedCount' => $event->deducted_count,
            'consumedValueCents' => $event->consumed_value_cents,
            'knownValueCents' => $event->metadata['knownValueCents'] ?? $event->consumed_value_cents,
            'unknownCount' => (int) ($event->metadata['unknownCount'] ?? 0),
        ];
    }

    private function applySearch(Builder $query, string $term, bool $canSearchMemberNames): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }
        $query->where(function (Builder $nested) use ($term, $canSearchMemberNames) {
            $nested->whereHas('member', fn (Builder $members) => $members
                ->where('member_no', 'like', "%{$term}%")
                ->when($canSearchMemberNames, fn (Builder $allowedNames) => $allowedNames
                    ->orWhereHas('crmProfile', fn (Builder $profiles) => $profiles->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('account', fn (Builder $accounts) => $accounts->where('display_name', 'like', "%{$term}%"))))
                ->orWhereHas('session.course', fn (Builder $courses) => $courses->where('name', 'like', "%{$term}%"))
                ->orWhereHas('memberCard', fn (Builder $cards) => $cards
                    ->where('card_no', 'like', "%{$term}%")
                    ->orWhereHas('cardProduct', fn (Builder $products) => $products->where('name', 'like', "%{$term}%")))
                ->orWhereHas('coach', fn (Builder $staff) => $staff->where('name', 'like', "%{$term}%"))
                ->orWhereHas('lines.staff', fn (Builder $staff) => $staff->where('name', 'like', "%{$term}%"))
                ->orWhereHas('lines.role', fn (Builder $roles) => $roles->where('name', 'like', "%{$term}%"));
        });
    }

    public function summarize(Collection $events, string $dimension): array
    {
        abort_unless(in_array($dimension, ['coach', 'share', 'member', 'course', 'card'], true), 422, 'CONSUMPTION_DIMENSION_INVALID');
        $rows = [];

        if ($dimension === 'share') {
            $shareRoleIds = CompensationRole::query()
                ->whereIn('id', $events->flatMap->lines->pluck('compensation_role_id')->filter()->unique())
                ->where('role_type', 'share')
                ->pluck('id');
            foreach ($events as $event) {
                $staffLines = $event->lines
                    ->whereIn('compensation_role_id', $shareRoleIds)
                    ->groupBy('staff_id');
                foreach ($staffLines as $staffId => $lines) {
                    $key = (string) $staffId;
                    $rows[$key] ??= ['key' => (int) $staffId, 'consumptionCount' => 0, 'consumedValueCents' => 0, 'commissionCents' => 0];
                    $rows[$key]['commissionCents'] += (int) $lines->sum('amount_cents');
                    if ($event->status !== 'reversed') {
                        $rows[$key]['consumptionCount']++;
                        $rows[$key]['consumedValueCents'] += (int) ($event->consumed_value_cents ?? 0);
                    }
                }
            }

            return array_values($rows);
        }

        if ($dimension === 'coach') {
            $deliveryRoleIds = CompensationRole::query()
                ->whereIn('id', $events->flatMap->lines->pluck('compensation_role_id')->filter()->unique())
                ->where('role_type', 'delivery')
                ->pluck('id');
            foreach ($events as $event) {
                $recipients = collect($event->metadata['deliveryRecipients'] ?? [[
                    'staffId' => $event->coach_staff_id,
                    'allocationBps' => 10000,
                ]])->groupBy('staffId');
                foreach ($recipients as $staffId => $staffRecipients) {
                    $key = (string) $staffId;
                    $rows[$key] ??= ['key' => (int) $staffId, 'consumptionCount' => 0, 'consumedValueCents' => 0, 'commissionCents' => 0];
                    if ($event->status !== 'reversed') {
                        $allocationBps = min(10000, (int) $staffRecipients->sum(fn ($recipient) => (int) ($recipient['allocationBps'] ?? 10000)));
                        $rows[$key]['consumptionCount']++;
                        $rows[$key]['consumedValueCents'] += $this->commissions->calculate(
                            (int) ($event->consumed_value_cents ?? 0),
                            10000,
                            $allocationBps,
                        );
                    }
                    $rows[$key]['commissionCents'] += (int) $event->lines
                        ->where('staff_id', (int) $staffId)
                        ->filter(fn ($line) => $line->compensation_role_id === null
                            || $deliveryRoleIds->contains($line->compensation_role_id))
                        ->sum('amount_cents');
                }
            }

            return array_values($rows);
        }

        foreach ($events as $event) {
            $key = match ($dimension) {
                'member' => $event->member_id,
                'course' => $event->course_id,
                'card' => $event->member_card_id,
            };
            $rows[$key] ??= ['key' => $key, 'consumptionCount' => 0, 'consumedValueCents' => 0, 'commissionCents' => 0];
            if ($event->status !== 'reversed') {
                $rows[$key]['consumptionCount']++;
                $rows[$key]['consumedValueCents'] += (int) ($event->consumed_value_cents ?? 0);
            }
            $rows[$key]['commissionCents'] += (int) $event->lines->sum('amount_cents');
        }

        return array_values($rows);
    }

    private function recomputePeriodDay(MemberCard $card, string $businessDate, string $reason): void
    {
        $dayValue = $this->valueLots->periodDayValue($card, $businessDate);
        $bucket = PeriodDayBucket::query()->firstOrCreate([
            'tenant_id' => $card->tenant_id,
            'member_card_id' => $card->id,
            'business_date' => $businessDate,
        ], [
            'site_id' => $card->site_id,
            'value_lot_id' => $dayValue['valueLotId'],
            'day_value_cents' => $dayValue['valueCents'],
            'event_count' => 0,
            'latest_revision' => 0,
            'value_provenance' => $dayValue['provenance'],
            'status' => 'open',
        ]);
        $bucket = PeriodDayBucket::query()->whereKey($bucket->id)->lockForUpdate()->firstOrFail();
        $events = ConsumptionEvent::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('business_date', $businessDate)
            ->where('status', '!=', 'reversed')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $allocations = $dayValue['valueCents'] === null || $events->isEmpty()
            ? []
            : $this->allocator->equal($dayValue['valueCents'], $events->pluck('id')->all());
        $revisionNumber = $bucket->latest_revision + 1;
        $allocatedValue = array_sum($allocations);
        $revision = PeriodDayBucketRevision::create([
            'tenant_id' => $card->tenant_id,
            'period_day_bucket_id' => $bucket->id,
            'revision' => $revisionNumber,
            'day_value_cents' => $dayValue['valueCents'],
            'event_count' => $events->count(),
            'allocated_value_cents' => $allocatedValue,
            'reason' => $reason,
            'command_key' => 'period-bucket:'.$bucket->id.':revision:'.$revisionNumber,
            'occurred_at' => now(),
        ]);
        $bucket->update([
            'value_lot_id' => $dayValue['valueLotId'],
            'day_value_cents' => $dayValue['valueCents'],
            'event_count' => $events->count(),
            'latest_revision' => $revisionNumber,
            'value_provenance' => $dayValue['provenance'],
        ]);

        foreach ($events as $event) {
            $event->update([
                'value_lot_id' => $dayValue['valueLotId'],
                'consumed_value_cents' => $allocations[$event->id] ?? null,
                'value_provenance' => $dayValue['provenance'],
                'status' => $bucket->status === 'closed' ? 'final' : 'provisional',
            ]);
        }
        // Persist every event's revised daily value before calculating role totals;
        // financial rounding is performed once per day + role + rate group.
        foreach ($events as $event) {
            $this->postCompensation($event->fresh(), $revision);
        }
    }

    private function postCompensation(ConsumptionEvent $event, ?PeriodDayBucketRevision $revision = null): void
    {
        $this->persistRecipientValueAllocations($event);
        $event->refresh();

        if ($event->course_compensation_rule_id === null) {
            return;
        }
        $rule = CourseCompensationRule::query()
            ->where('tenant_id', $event->tenant_id)
            ->whereKey($event->course_compensation_rule_id)
            ->with('roleRates')
            ->firstOrFail();
        $rates = $rule->roleRates->keyBy('compensation_role_id');
        $base = $event->consumed_value_cents;
        $deliveryGroups = collect($event->metadata['deliveryRecipients'] ?? [])->groupBy(
            fn ($recipient) => (string) ($recipient['compensationRoleId'] ?? 0),
        );
        $allDeliveryRecipients = $deliveryGroups->flatten(1)->values();
        if ($allDeliveryRecipients->isNotEmpty()) {
            // session_fee_cents is one course-session fact, not one fee per A-role.
            // Allocate it once across all actual delivery recipients.
            $feeTargets = $this->allocator->weighted(
                (int) $rule->session_fee_cents,
                $this->recipientWeights($allDeliveryRecipients),
            );
            foreach ($allDeliveryRecipients as $recipient) {
                $this->posting->postSessionTargetOnce(
                    $event,
                    (int) $recipient['staffId'],
                    isset($recipient['compensationRoleId']) ? (int) $recipient['compensationRoleId'] : null,
                    $feeTargets[$this->recipientKey($recipient)],
                    $rule,
                    (int) ($recipient['allocationBps'] ?? 10000),
                );
            }
        }
        foreach ($deliveryGroups as $roleKey => $recipients) {
            $roleId = (int) $roleKey ?: null;
            $weights = $this->recipientWeights($recipients);
            $rate = $roleId !== null ? $rates->get($roleId) : null;
            if ($rate === null) {
                continue;
            }
            $roleTarget = $this->roleEventTarget($event, $roleId, $rate->rate_bps, $base, $revision);
            $targets = $this->allocator->weighted($roleTarget, $weights);
            foreach ($recipients as $recipient) {
                $key = $this->recipientKey($recipient);
                $this->posting->postEventTarget(
                    $event,
                    (int) $recipient['staffId'],
                    $roleId,
                    'consumption_commission',
                    $targets[$key],
                    (int) ($base ?? 0),
                    $rate->rate_bps,
                    (int) ($recipient['allocationBps'] ?? 10000),
                    $rule,
                    $revision,
                );
            }
        }

        foreach (collect($event->metadata['shareRecipients'] ?? [])->groupBy('compensationRoleId') as $roleId => $recipients) {
            $rate = $rates->get((int) $roleId);
            if ($rate === null) {
                continue;
            }
            $weights = $this->recipientWeights($recipients);
            $roleTarget = $this->roleEventTarget($event, (int) $roleId, $rate->rate_bps, $base, $revision);
            $targets = $this->allocator->weighted($roleTarget, $weights);
            foreach ($recipients as $recipient) {
                $key = $this->recipientKey($recipient);
                $this->posting->postEventTarget(
                    $event,
                    (int) $recipient['staffId'],
                    (int) $roleId,
                    'consumption_commission',
                    $targets[$key],
                    (int) ($base ?? 0),
                    $rate->rate_bps,
                    (int) ($recipient['allocationBps'] ?? 10000),
                    $rule,
                    $revision,
                );
            }
        }
    }

    private function roleEventTarget(
        ConsumptionEvent $event,
        int $roleId,
        int $rateBps,
        ?int $base,
        ?PeriodDayBucketRevision $revision,
    ): int {
        if ($revision === null) {
            return $base === null ? 0 : $this->commissions->calculate($base, $rateBps);
        }

        $candidates = ConsumptionEvent::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('member_card_id', $event->member_card_id)
            ->where('business_date', $event->business_date)
            ->where('status', '!=', 'reversed')
            ->orderBy('id')
            ->get();
        $ruleIds = CourseCompensationRoleRate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('compensation_role_id', $roleId)
            ->where('rate_bps', $rateBps)
            ->whereIn('course_compensation_rule_id', $candidates->pluck('course_compensation_rule_id')->filter())
            ->pluck('course_compensation_rule_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $relevant = $candidates->filter(function (ConsumptionEvent $candidate) use ($roleId, $ruleIds) {
            if (! in_array((int) $candidate->course_compensation_rule_id, $ruleIds, true)) {
                return false;
            }

            return collect([
                ...($candidate->metadata['deliveryRecipients'] ?? []),
                ...($candidate->metadata['shareRecipients'] ?? []),
            ])->contains(fn ($recipient) => (int) ($recipient['compensationRoleId'] ?? 0) === $roleId);
        });
        $baseTotal = (int) $relevant->sum(fn (ConsumptionEvent $candidate) => (int) ($candidate->consumed_value_cents ?? 0));
        if ($baseTotal === 0 || ! $relevant->contains('id', $event->id)) {
            return 0;
        }
        $dayRoleTarget = $this->commissions->calculate($baseTotal, $rateBps);
        $weights = $relevant->mapWithKeys(fn (ConsumptionEvent $candidate) => [
            (string) $candidate->id => (int) ($candidate->consumed_value_cents ?? 0),
        ])->all();
        $targets = $this->allocator->weighted($dayRoleTarget, $weights);

        return $targets[(string) $event->id] ?? 0;
    }

    private function zeroEventCommissions(ConsumptionEvent $event): void
    {
        $rule = $event->course_compensation_rule_id !== null
            ? CourseCompensationRule::query()->whereKey($event->course_compensation_rule_id)->first()
            : null;
        $targets = CommissionSettlementLine::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('consumption_event_id', $event->id)
            ->where('component', 'consumption_commission')
            ->get()
            ->groupBy(fn ($line) => $line->staff_id.':'.($line->compensation_role_id ?? 0));
        foreach ($targets as $lines) {
            $latest = $lines->last();
            $this->posting->postEventTarget(
                $event,
                $latest->staff_id,
                $latest->compensation_role_id,
                'consumption_commission',
                0,
                0,
                $latest->rate_bps,
                $latest->allocation_bps,
                $rule,
            );
        }
    }

    private function zeroSessionFee(ConsumptionEvent $event): void
    {
        if ($event->course_compensation_rule_id === null) {
            return;
        }
        $rule = CourseCompensationRule::query()->whereKey($event->course_compensation_rule_id)->firstOrFail();
        foreach ($event->metadata['deliveryRecipients'] ?? [] as $recipient) {
            $this->posting->postSessionTargetOnce(
                $event,
                (int) $recipient['staffId'],
                isset($recipient['compensationRoleId']) ? (int) $recipient['compensationRoleId'] : null,
                0,
                $rule,
                (int) ($recipient['allocationBps'] ?? 10000),
            );
        }
    }

    private function postSessionFees(ConsumptionEvent $event): void
    {
        if ($event->course_compensation_rule_id === null) {
            return;
        }
        $rule = CourseCompensationRule::query()
            ->where('tenant_id', $event->tenant_id)
            ->whereKey($event->course_compensation_rule_id)
            ->firstOrFail();
        $recipients = collect($event->metadata['deliveryRecipients'] ?? [])->values();
        if ($recipients->isEmpty()) {
            return;
        }
        $targets = $this->allocator->weighted((int) $rule->session_fee_cents, $this->recipientWeights($recipients));
        foreach ($recipients as $recipient) {
            $allocationBps = (int) ($recipient['allocationBps'] ?? 10000);
            $this->posting->postSessionTargetOnce(
                $event,
                (int) $recipient['staffId'],
                isset($recipient['compensationRoleId']) ? (int) $recipient['compensationRoleId'] : null,
                $targets[$this->recipientKey($recipient)],
                $rule,
                $allocationBps,
            );
        }
    }

    /** @return array<string,int> */
    private function recipientWeights(Collection $recipients): array
    {
        return $recipients->mapWithKeys(fn ($recipient) => [
            $this->recipientKey($recipient) => (int) ($recipient['allocationBps'] ?? 10000),
        ])->all();
    }

    private function recipientKey(array $recipient): string
    {
        return (int) $recipient['staffId'].':'.(int) ($recipient['compensationRoleId'] ?? 0);
    }

    private function deriveUuid(string $seed): string
    {
        $hash = md5($seed);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4),
            substr($hash, 16, 4), substr($hash, 20, 12),
        );
    }

    /**
     * Persist the exact largest-remainder allocation used by staff-dimension reports.
     * Allocation is conserved independently inside every business-role group; a person
     * assigned to two different share roles may therefore receive attributed value in
     * both roles, while recipients inside one role always sum exactly to the event value.
     */
    private function persistRecipientValueAllocations(ConsumptionEvent $event): void
    {
        $metadata = $event->metadata ?? [];
        $changed = false;
        $projectionKeys = [];
        foreach (['deliveryRecipients', 'shareRecipients'] as $key) {
            $recipients = collect($metadata[$key] ?? [])->values();
            if ($recipients->isEmpty()) {
                continue;
            }
            $allocated = [];
            // Delivery value is one fulfilled-consumption fact and is divided once
            // across all A recipients. Share attribution is independent per B role.
            $groups = $key === 'deliveryRecipients'
                ? collect(['delivery' => $recipients])
                : $recipients->groupBy(fn ($recipient) => (string) ($recipient['compensationRoleId'] ?? 0));
            foreach ($groups as $group) {
                $weights = $this->recipientWeights($group);
                if (array_sum($weights) < 1) {
                    $weights = $group->mapWithKeys(fn ($recipient) => [$this->recipientKey($recipient) => 1])->all();
                }
                $targets = $event->consumed_value_cents === null
                    ? array_fill_keys(array_keys($weights), null)
                    : $this->allocator->weighted((int) $event->consumed_value_cents, $weights);
                foreach ($group as $recipient) {
                    $recipient['allocatedValueCents'] = $targets[$this->recipientKey($recipient)] ?? null;
                    $allocated[] = $recipient;
                    $roleId = isset($recipient['compensationRoleId']) ? (int) $recipient['compensationRoleId'] : null;
                    $projectionKey = implode(':', [$key, $roleId ?? 0, (int) $recipient['staffId']]);
                    $projectionKeys[] = $projectionKey;
                    DB::table('consumption_event_recipient_allocations')->updateOrInsert([
                        'consumption_event_id' => $event->id,
                        'recipient_type' => $key === 'deliveryRecipients' ? 'delivery' : 'share',
                        'compensation_role_id' => $roleId,
                        'role_key' => $roleId ?? 0,
                        'staff_id' => (int) $recipient['staffId'],
                    ], [
                        'tenant_id' => $event->tenant_id,
                        'site_id' => $event->site_id,
                        'allocation_bps' => (int) ($recipient['allocationBps'] ?? 10000),
                        'allocated_value_cents' => $recipient['allocatedValueCents'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $metadata[$key] = $allocated;
            $changed = true;
        }
        if ($changed && $metadata !== $event->metadata) {
            $event->metadata = $metadata;
            $event->save();
        }
        $valid = collect($projectionKeys)->map(fn ($key) => explode(':', $key));
        DB::table('consumption_event_recipient_allocations')
            ->where('consumption_event_id', $event->id)
            ->when($valid->isNotEmpty(), function ($query) use ($valid) {
                $query->whereNot(function ($keep) use ($valid) {
                    foreach ($valid as [$metadataKey, $roleId, $staffId]) {
                        $keep->orWhere(function ($row) use ($metadataKey, $roleId, $staffId) {
                            $row->where('recipient_type', $metadataKey === 'deliveryRecipients' ? 'delivery' : 'share')
                                ->where('role_key', (int) $roleId)
                                ->where('staff_id', (int) $staffId);
                        });
                    }
                });
            })
            ->when($valid->isEmpty(), fn ($query) => $query)
            ->delete();
    }

    /** @return list<array{staffId:int, compensationRoleId:?int, allocationBps:int}> */
    private function deliveryRecipients(ScheduleSession $session, string $businessDate): array
    {
        $assignments = ScheduleSessionStaffAssignment::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('schedule_session_id', $session->id)
            ->whereHas('role', fn ($query) => $query
                ->where('role_type', 'delivery')
                ->whereIn('status', ['active', 'archived']))
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
        if ($assignments->isNotEmpty()) {
            return $assignments->map(fn ($assignment) => [
                'staffId' => $assignment->staff_id,
                'compensationRoleId' => $assignment->compensation_role_id,
                'allocationBps' => $assignment->allocation_bps,
            ])->values()->all();
        }

        $roleId = $session->delivery_role_id;
        if ($roleId === null) {
            $roleId = StaffCompensationRoleAssignment::query()
                ->where('tenant_id', $session->tenant_id)
                ->where('site_id', $session->site_id)
                ->where('staff_id', $session->coach_staff_id)
                ->whereIn('status', ['active', 'archived'])
                ->where(fn ($query) => $query->whereNull('active_from')->orWhere('active_from', '<=', $businessDate))
                ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $businessDate))
                ->whereHas('role', fn ($query) => $query
                    ->where('role_type', 'delivery')
                    ->whereIn('status', ['active', 'archived']))
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderByDesc('version')
                ->orderBy('id')
                ->value('compensation_role_id');
        }

        return [[
            'staffId' => $session->coach_staff_id,
            'compensationRoleId' => $roleId !== null ? (int) $roleId : null,
            'allocationBps' => 10000,
        ]];
    }
}
