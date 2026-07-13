<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class AppointmentFulfillmentService
{
    public function __construct(
        private BookingPolicyService $policy,
        private BookingPayableCardService $payableCards,
        private BookingEntitlementService $entitlements,
    ) {}

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function markAbsentForStaff(
        Staff $staff,
        Appointment $appointment,
        string $commandKey,
    ): array {
        abort_unless($appointment->tenant_id === $staff->tenant_id, 404);

        return DB::transaction(fn () => $this->markAbsentInTransaction(
            $staff->tenant_id,
            $appointment->id,
            $commandKey,
            $staff,
        ));
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function updateStaffNotesForStaff(
        Staff $staff,
        Appointment $appointment,
        string $staffNotes,
    ): array {
        abort_unless($appointment->tenant_id === $staff->tenant_id, 404);

        return DB::transaction(function () use ($staff, $appointment, $staffNotes) {
            $locked = Appointment::query()
                ->where('tenant_id', $staff->tenant_id)
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previous = $locked->staff_notes;
            if ($previous === $staffNotes) {
                return ['appointment' => $locked, 'created' => false];
            }

            $locked->staff_notes = $staffNotes;
            $locked->save();

            AppointmentEvent::create([
                'tenant_id' => $staff->tenant_id,
                'appointment_id' => $locked->id,
                'event_type' => 'staff_note_updated',
                'payload' => [
                    'previousText' => $previous,
                    'newText' => $staffNotes,
                ],
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);

            return ['appointment' => $locked->fresh(), 'created' => true];
        });
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function markCheckInForStaff(
        Staff $staff,
        Appointment $appointment,
        string $commandKey,
    ): array {
        abort_unless($appointment->tenant_id === $staff->tenant_id, 404);

        return DB::transaction(fn () => $this->markCheckInInTransaction(
            $staff->tenant_id,
            $appointment->id,
            $commandKey,
            $staff,
        ));
    }

    public function reschedulePrivateForStaff(
        Staff $staff,
        Appointment $appointment,
        ScheduleSession $targetSession,
        string $commandKey,
    ): array {
        abort_unless($appointment->tenant_id === $staff->tenant_id, 404);
        abort_unless($targetSession->tenant_id === $staff->tenant_id, 404);

        $existingEvent = AppointmentEvent::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('command_key', $commandKey)
            ->first();

        if ($existingEvent) {
            $appointment = Appointment::query()
                ->where('tenant_id', $staff->tenant_id)
                ->whereKey($existingEvent->appointment_id)
                ->firstOrFail();

            return ['appointment' => $appointment, 'created' => false];
        }

        return DB::transaction(fn () => $this->reschedulePrivateInTransaction(
            $staff->tenant_id,
            $appointment->id,
            $targetSession->id,
            $commandKey,
            $staff,
        ));
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    /**
     * @return array{appointment: Appointment, created: bool}
     */
    private function markCheckInInTransaction(
        int $tenantId,
        int $appointmentId,
        string $commandKey,
        Staff $staff,
    ): array {
        $existingEvent = AppointmentEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('command_key', $commandKey)
            ->lockForUpdate()
            ->first();

        if ($existingEvent) {
            $appointment = Appointment::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($existingEvent->appointment_id)
                ->firstOrFail();

            return ['appointment' => $appointment, 'created' => false];
        }

        $locked = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($appointmentId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status === AppointmentStatus::Completed) {
            return ['appointment' => $locked, 'created' => false];
        }

        abort_unless($locked->status === AppointmentStatus::Confirmed, 409, 'APPOINTMENT_CHECK_IN_INVALID');

        $session = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($locked->session_id)
            ->firstOrFail();

        $site = Site::query()
            ->whereKey($locked->site_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $policy = $this->policy->policyForTenantSite($tenantId, $site->id);
        $signWindowMinutes = $session->session_kind === ScheduleSessionKind::Group
            ? (int) ($policy['group']['signMinutesBeforeStart'] ?? 30)
            : (int) ($policy['private']['signMinutesBeforeStart'] ?? 30);

        $earliestCheckIn = $session->starts_at->copy()->subMinutes(max(0, $signWindowMinutes));
        abort_unless(now()->greaterThanOrEqualTo($earliestCheckIn), 409, 'CHECK_IN_TOO_EARLY');

        $locked->status = AppointmentStatus::Completed;
        $locked->save();

        AppointmentEvent::create([
            'tenant_id' => $tenantId,
            'appointment_id' => $locked->id,
            'event_type' => 'checked_in',
            'payload' => [
                'sessionId' => $session->id,
                'checkedInAt' => now()->toIso8601String(),
            ],
            'command_key' => $commandKey,
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        return ['appointment' => $locked->fresh(), 'created' => true];
    }

    private function markAbsentInTransaction(
        int $tenantId,
        int $appointmentId,
        string $commandKey,
        Staff $staff,
    ): array {
        $existingEvent = AppointmentEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('command_key', $commandKey)
            ->lockForUpdate()
            ->first();

        if ($existingEvent) {
            $appointment = Appointment::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($existingEvent->appointment_id)
                ->firstOrFail();

            return ['appointment' => $appointment, 'created' => false];
        }

        $locked = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($appointmentId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status === AppointmentStatus::Absent) {
            return ['appointment' => $locked, 'created' => false];
        }

        abort_unless($locked->status === AppointmentStatus::Confirmed, 409, 'APPOINTMENT_ABSENT_INVALID');

        $session = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($locked->session_id)
            ->firstOrFail();

        $site = Site::query()
            ->whereKey($locked->site_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $policy = $this->policy->policyForTenantSite($tenantId, $site->id);
        $penaltyEnabled = $session->session_kind === ScheduleSessionKind::Group
            ? (bool) ($policy['group']['absentPenaltyEnabled'] ?? false)
            : (bool) ($policy['private']['absentPenaltyEnabled'] ?? false);

        $penaltyLedgerEntryId = null;

        if ($penaltyEnabled && $locked->member_card_id !== null) {
            $memberCard = MemberCard::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($locked->member_card_id)
                ->firstOrFail();

            $penalty = $this->entitlements->applyAbsentPenalty(
                $memberCard,
                $site,
                $this->derivePenaltyCommandKey($commandKey),
                $locked->id,
                $staff,
            );
            $penaltyLedgerEntryId = $penalty['ledgerEntryId'];
        }

        $locked->status = AppointmentStatus::Absent;
        $locked->absent_marked_at = now();
        $locked->penalty_ledger_entry_id = $penaltyLedgerEntryId;
        $locked->save();

        AppointmentEvent::create([
            'tenant_id' => $tenantId,
            'appointment_id' => $locked->id,
            'event_type' => 'absent_marked',
            'payload' => [
                'sessionId' => $session->id,
                'penaltyApplied' => $penaltyEnabled,
                'penaltyLedgerEntryId' => $penaltyLedgerEntryId,
            ],
            'command_key' => $commandKey,
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        return ['appointment' => $locked->fresh(), 'created' => true];
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    private function reschedulePrivateInTransaction(
        int $tenantId,
        int $appointmentId,
        int $targetSessionId,
        string $commandKey,
        Staff $staff,
    ): array {
        $locked = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($appointmentId)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless($locked->status === AppointmentStatus::Confirmed, 409, 'APPOINTMENT_RESCHEDULE_INVALID');

        $sourceSession = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($locked->session_id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless($sourceSession->session_kind === ScheduleSessionKind::Private, 409, 'APPOINTMENT_RESCHEDULE_PRIVATE_ONLY');
        abort_if($sourceSession->id === $targetSessionId, 409, 'APPOINTMENT_RESCHEDULE_SAME_SESSION');

        $targetSession = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($targetSessionId)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless($targetSession->site_id === $locked->site_id, 404);
        abort_unless($targetSession->session_kind === ScheduleSessionKind::Private, 409, 'APPOINTMENT_RESCHEDULE_PRIVATE_ONLY');
        abort_unless($targetSession->status === ScheduleSessionStatus::Scheduled, 409, 'BOOKING_SESSION_NOT_BOOKABLE');
        abort_if($targetSession->booked_count >= $targetSession->capacity, 409, 'BOOKING_SESSION_FULL');

        $site = Site::query()
            ->whereKey($locked->site_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $policy = $this->policy->policyForTenantSite($tenantId, $site->id);
        $this->policy->assertBookingAllowed($site, $targetSession, $policy);

        $sessionDate = $targetSession->starts_at->timezone($this->siteTimezone($site))->toDateString();
        abort_unless(
            $this->policy->memberSessionBookableOnDate($site, $sessionDate, ScheduleSessionKind::Private->value, $policy),
            422,
            'BOOKING_DATE_OUT_OF_ADVANCE_WINDOW',
        );

        $duplicate = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $targetSession->id)
            ->where('member_id', $locked->member_id)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
            ->exists();

        abort_if($duplicate, 409, 'APPOINTMENT_ALREADY_EXISTS');

        if ($locked->member_card_id !== null) {
            $memberCard = MemberCard::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($locked->member_card_id)
                ->firstOrFail();

            abort_unless(
                $this->payableCards->isPayableForSession($memberCard, $targetSession),
                409,
                'BOOKING_CARD_NOT_PAYABLE',
            );
        }

        $fromSessionId = $locked->session_id;
        $rescheduledFrom = $locked->rescheduled_from_session_id ?? $fromSessionId;

        $sourceSession->booked_count = max(0, $sourceSession->booked_count - 1);
        $sourceSession->save();

        $targetSession->booked_count = $targetSession->booked_count + 1;
        $targetSession->save();

        $locked->rescheduled_from_session_id = $rescheduledFrom;
        $locked->session_id = $targetSession->id;
        $locked->save();

        AppointmentEvent::create([
            'tenant_id' => $tenantId,
            'appointment_id' => $locked->id,
            'event_type' => 'rescheduled',
            'payload' => [
                'fromSessionId' => $fromSessionId,
                'toSessionId' => $targetSession->id,
            ],
            'command_key' => $commandKey,
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        return ['appointment' => $locked->fresh(), 'created' => true];
    }

    private function siteTimezone(Site $site): string
    {
        return $site->timezone ?: (string) config('app.timezone');
    }

    private function derivePenaltyCommandKey(string $markAbsentCommandKey): string
    {
        $hash = md5($markAbsentCommandKey.':penalty');

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
