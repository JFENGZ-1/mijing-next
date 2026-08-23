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
        private \App\Services\Cards\CardProductBookingRulesService $cardRules,
        private \App\Services\Cards\MemberCardAutoActivationService $cardActivation,
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
    /**
     * 自动签到（对齐原版「下课后，将由系统5分钟内自动签到」）：
     * 仅在课后 `group.autoCheckInMinutesAfterEnd`（默认 5）分钟内，把仍 confirmed 的预约转 completed。
     * 由调度每 5 分钟调用；停课（suspended/cancelled）的课不自动签到。
     */
    public function autoCheckInEndedSessions(int $limit = 500): int
    {
        $policyMinutesCache = [];
        $candidates = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed)
            ->whereHas('session', fn ($query) => $query
                ->where('status', ScheduleSessionStatus::Scheduled->value)
                ->where('ends_at', '<=', now())
                ->where('ends_at', '>', now()->subMinutes(60)))
            ->with('session')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $dueIds = [];
        foreach ($candidates as $appointment) {
            $session = $appointment->session;
            if ($session === null || ! $this->isWithinAutoCheckInWindow(
                $session,
                $appointment->tenant_id,
                $appointment->site_id,
                $policyMinutesCache,
            )) {
                continue;
            }
            $dueIds[] = $appointment->id;
        }

        $count = 0;
        foreach ($dueIds as $appointmentId) {
            DB::transaction(function () use ($appointmentId, &$count, &$policyMinutesCache) {
                $locked = Appointment::query()
                    ->whereKey($appointmentId)
                    ->lockForUpdate()
                    ->first();
                if ($locked === null || $locked->status !== AppointmentStatus::Confirmed) {
                    return;
                }

                // 锁内二次确认「已下课且未停课」
                $session = ScheduleSession::query()->whereKey($locked->session_id)->first();
                if ($session === null
                    || $session->status !== ScheduleSessionStatus::Scheduled
                    || $session->ends_at->isFuture()
                    || ! $this->isWithinAutoCheckInWindow(
                        $session,
                        $locked->tenant_id,
                        $locked->site_id,
                        $policyMinutesCache,
                    )) {
                    return;
                }

                $locked->status = AppointmentStatus::Completed;
                $locked->save();

                // 自动开卡：first-class 首次上课激活（与手动签到一致）
                if ($locked->member_card_id !== null) {
                    $card = MemberCard::query()
                        ->where('tenant_id', $locked->tenant_id)
                        ->find($locked->member_card_id);
                    if ($card !== null) {
                        $this->cardActivation->maybeActivateForAttendance($card, null);
                    }
                }

                AppointmentEvent::create([
                    'tenant_id' => $locked->tenant_id,
                    'appointment_id' => $locked->id,
                    'event_type' => 'checked_in',
                    'payload' => [
                        'sessionId' => $session->id,
                        'checkedInAt' => now()->toIso8601String(),
                        'auto' => true,
                    ],
                    'command_key' => 'auto-check-in:'.$locked->id,
                    'actor_staff_id' => null,
                    'occurred_at' => now(),
                ]);

                $count++;
            });
        }

        return $count;
    }

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
        $signWindowMinutes = (int) ($policy['group']['signMinutesBeforeStart'] ?? 30);

        $earliestCheckIn = $session->starts_at->copy()->subMinutes(max(0, $signWindowMinutes));
        abort_unless(now()->greaterThanOrEqualTo($earliestCheckIn), 409, 'CHECK_IN_TOO_EARLY');

        $locked->status = AppointmentStatus::Completed;
        $locked->save();

        // 自动开卡：first-class 首次上课激活（first-use/delayed 到期在此兜底）
        if ($locked->member_card_id !== null) {
            $checkInCard = MemberCard::query()
                ->where('tenant_id', $tenantId)
                ->find($locked->member_card_id);
            if ($checkInCard !== null) {
                $this->cardActivation->maybeActivateForAttendance($checkInCard, $staff->id);
            }
        }

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

        $memberCard = null;
        if ($locked->member_card_id !== null) {
            $memberCard = MemberCard::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($locked->member_card_id)
                ->firstOrFail();

            // 卡级旷课处罚规则（原版「高级选项」）优先于场馆级开关
            $cardDecision = $this->cardRules->absenceChargeApplies($memberCard, $site);
            if ($cardDecision !== null) {
                $penaltyEnabled = $cardDecision;
            }
        }

        $penaltyLedgerEntryId = null;

        if ($penaltyEnabled && $memberCard !== null) {
            $penalty = $this->entitlements->applyAbsentPenalty(
                $memberCard,
                $site,
                $this->derivePenaltyCommandKey($commandKey),
                $locked->id,
                $staff,
            );
            $penaltyLedgerEntryId = $penalty['ledgerEntryId'];
        }

        // 卡级旷课处罚「扣除」动作（原版：扣X元/次/天），达阈值时执行
        if ($memberCard !== null && $penaltyLedgerEntryId === null) {
            $deduction = $this->cardRules->absenceDeductionSpec($memberCard, $site);
            if ($deduction !== null) {
                $result = $this->entitlements->applyAbsentDeduction(
                    $memberCard,
                    $site,
                    $this->derivePenaltyCommandKey($commandKey),
                    $deduction,
                    $staff,
                );
                $penaltyLedgerEntryId = $result['ledgerEntryId'];
            }
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

        // 员工改约私教：不受会员预约截止/提前天数限制
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

    /**
     * @param  array<int, int>  $policyMinutesCache
     */
    private function isWithinAutoCheckInWindow(
        ScheduleSession $session,
        int $tenantId,
        int $siteId,
        array &$policyMinutesCache,
    ): bool {
        if ($session->ends_at->isFuture()) {
            return false;
        }

        if (! array_key_exists($siteId, $policyMinutesCache)) {
            $policy = $this->policy->policyForTenantSite($tenantId, $siteId);
            $policyMinutesCache[$siteId] = max(1, (int) ($policy['group']['autoCheckInMinutesAfterEnd'] ?? 5));
        }

        $deadline = $session->ends_at->copy()->addMinutes($policyMinutesCache[$siteId]);

        return now()->lte($deadline);
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
