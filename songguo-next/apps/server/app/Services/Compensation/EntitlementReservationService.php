<?php

namespace App\Services\Compensation;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Models\Appointment;
use App\Models\EntitlementReservation;
use App\Support\Finance\Money;

class EntitlementReservationService
{
    public function __construct(private CardProductCourseRuleService $rules) {}

    public function reserve(Appointment $appointment): ?EntitlementReservation
    {
        if ($appointment->member_card_id === null
            || ! in_array($appointment->status, [AppointmentStatus::Confirmed, AppointmentStatus::Completed], true)) {
            return null;
        }

        $existing = EntitlementReservation::query()
            ->where('tenant_id', $appointment->tenant_id)
            ->where('appointment_id', $appointment->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $appointment->loadMissing(['session.course', 'memberCard', 'ledgerEntry']);
        $card = $appointment->memberCard;
        $course = $appointment->session?->course;
        if ($card === null || $course === null) {
            return null;
        }
        $preview = $this->preview($appointment);
        if ($preview === null) {
            return null;
        }

        return EntitlementReservation::query()->firstOrCreate([
            'tenant_id' => $appointment->tenant_id,
            'appointment_id' => $appointment->id,
        ], [
            'site_id' => $appointment->site_id,
            'session_id' => $appointment->session_id,
            'course_id' => $course->id,
            'member_id' => $appointment->member_id,
            'member_card_id' => $card->id,
            'card_product_course_rule_id' => $preview['cardProductCourseRuleId'],
            'ledger_entry_id' => $appointment->ledger_entry_id,
            'deduction_type' => $preview['deductionType'],
            'reserved_amount_cents' => $preview['amountCents'],
            'reserved_count' => $preview['count'],
            'status' => 'reserved',
            'command_key' => 'reservation:appointment:'.$appointment->id,
            'metadata' => [
                'countValueAllocations' => $appointment->ledgerEntry?->metadata['countValueAllocations'] ?? [],
                'knownValueCents' => $appointment->ledgerEntry?->metadata['knownValueCents'] ?? null,
                'unknownCount' => $appointment->ledgerEntry?->metadata['unknownCount'] ?? 0,
                'valueProvenance' => $appointment->ledgerEntry?->metadata['valueProvenance'] ?? null,
                'reservedValueCents' => $appointment->ledgerEntry?->metadata['reservedValueCents'] ?? null,
                'valueLotId' => $appointment->ledgerEntry?->metadata['valueLotId'] ?? null,
                'ruleEffectiveAt' => $appointment->session?->starts_at?->toIso8601String(),
            ],
            'reserved_at' => $appointment->booked_at ?? now(),
        ]);
    }

    /** @return array{cardProductCourseRuleId:?int,deductionType:string,amountCents:?int,count:?int}|null */
    public function preview(Appointment $appointment): ?array
    {
        if ($appointment->member_card_id === null) {
            return null;
        }
        $appointment->loadMissing(['session.course', 'memberCard', 'ledgerEntry']);
        $card = $appointment->memberCard;
        $course = $appointment->session?->course;
        if ($card === null || $course === null) {
            return null;
        }
        $rule = $this->rules->activeRuleFor($card, $course, $appointment->session?->starts_at);

        return [
            'cardProductCourseRuleId' => $rule?->id,
            'deductionType' => $rule?->deduction_type ?? match ($card->card_type) {
                CardType::StoredValue => 'amount',
                CardType::Count => 'count',
                CardType::Period => 'period_auto',
            },
            'amountCents' => $appointment->ledgerEntry?->amount_delta !== null
                ? Money::decimalToCents($appointment->ledgerEntry->amount_delta)
                : $rule?->amount_cents,
            'count' => $appointment->ledgerEntry?->count_delta ?? $rule?->count_units,
        ];
    }

    public function release(Appointment $appointment): ?EntitlementReservation
    {
        $reservation = EntitlementReservation::query()
            ->where('tenant_id', $appointment->tenant_id)
            ->where('appointment_id', $appointment->id)
            ->lockForUpdate()
            ->first();
        if ($reservation === null || in_array($reservation->status, ['released', 'reversed'], true)) {
            return $reservation;
        }
        abort_if($reservation->status === 'consumed', 409, 'CONSUMPTION_ALREADY_SETTLED');
        $reservation->update(['status' => 'released', 'released_at' => now()]);

        return $reservation->fresh();
    }

    public function replaceForReschedule(Appointment $appointment): ?EntitlementReservation
    {
        $appointment->unsetRelations();
        $appointment->load(['session.course', 'memberCard', 'ledgerEntry']);
        $preview = $this->preview($appointment);
        if ($preview === null) {
            return null;
        }
        $reservation = EntitlementReservation::query()
            ->where('tenant_id', $appointment->tenant_id)
            ->where('appointment_id', $appointment->id)
            ->lockForUpdate()
            ->first();
        if ($reservation === null) {
            return $this->reserve($appointment);
        }
        abort_if($reservation->status === 'consumed', 409, 'CONSUMPTION_ALREADY_SETTLED');
        $history = $reservation->metadata['rescheduleHistory'] ?? [];
        $history[] = [
            'sessionId' => $reservation->session_id,
            'courseId' => $reservation->course_id,
            'ledgerEntryId' => $reservation->ledger_entry_id,
            'cardProductCourseRuleId' => $reservation->card_product_course_rule_id,
            'deductionType' => $reservation->deduction_type,
            'reservedAmountCents' => $reservation->reserved_amount_cents,
            'reservedCount' => $reservation->reserved_count,
            'replacedAt' => now()->toIso8601String(),
        ];
        $ledgerMetadata = $appointment->ledgerEntry?->metadata ?? [];
        $reservation->update([
            'session_id' => $appointment->session_id,
            'course_id' => $appointment->session->course_id,
            'card_product_course_rule_id' => $preview['cardProductCourseRuleId'],
            'ledger_entry_id' => $appointment->ledger_entry_id,
            'deduction_type' => $preview['deductionType'],
            'reserved_amount_cents' => $preview['amountCents'],
            'reserved_count' => $preview['count'],
            'status' => 'reserved',
            'released_at' => null,
            'consumed_at' => null,
            'reversed_at' => null,
            'reserved_at' => now(),
            'metadata' => [
                'rescheduleHistory' => $history,
                'countValueAllocations' => $ledgerMetadata['countValueAllocations'] ?? [],
                'knownValueCents' => $ledgerMetadata['knownValueCents'] ?? null,
                'unknownCount' => $ledgerMetadata['unknownCount'] ?? 0,
                'valueProvenance' => $ledgerMetadata['valueProvenance'] ?? null,
                'reservedValueCents' => $ledgerMetadata['reservedValueCents'] ?? null,
                'valueLotId' => $ledgerMetadata['valueLotId'] ?? null,
                'ruleEffectiveAt' => $appointment->session?->starts_at?->toIso8601String(),
            ],
        ]);

        return $reservation->fresh();
    }

    public function consume(Appointment $appointment): ?EntitlementReservation
    {
        $reservation = $this->reserve($appointment);
        if ($reservation === null) {
            return null;
        }

        $reservation = EntitlementReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
        abort_if($reservation->status === 'released', 409, 'ENTITLEMENT_RESERVATION_RELEASED');
        abort_if($reservation->status === 'reversed', 409, 'ENTITLEMENT_RESERVATION_REVERSED');
        if ($reservation->status !== 'consumed') {
            $reservation->update(['status' => 'consumed', 'consumed_at' => now()]);
        }

        return $reservation->fresh();
    }

    public function reverseConsumed(int $reservationId): EntitlementReservation
    {
        $reservation = EntitlementReservation::query()
            ->whereKey($reservationId)
            ->lockForUpdate()
            ->firstOrFail();
        if ($reservation->status === 'reversed') {
            return $reservation;
        }
        abort_unless($reservation->status === 'consumed', 409, 'ENTITLEMENT_RESERVATION_NOT_CONSUMED');
        $reservation->update(['status' => 'reversed', 'reversed_at' => now()]);

        return $reservation->fresh();
    }
}
