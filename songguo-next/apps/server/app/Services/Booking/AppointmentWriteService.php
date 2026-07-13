<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class AppointmentWriteService
{
    public function __construct(
        private BookingPolicyService $policy,
        private BookingPayableCardService $payableCards,
        private BookingEntitlementService $entitlements,
    ) {}

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function createForMember(
        Member $member,
        ScheduleSession $session,
        MemberCard $memberCard,
        string $commandKey,
        ?int $accountId = null,
    ): array {
        return $this->create(
            $member,
            $session,
            $memberCard,
            $commandKey,
            bookedByAccountId: $accountId,
            createdByStaffId: null,
        );
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function createForStaff(
        Staff $staff,
        Member $member,
        ScheduleSession $session,
        MemberCard $memberCard,
        string $commandKey,
    ): array {
        return $this->create(
            $member,
            $session,
            $memberCard,
            $commandKey,
            bookedByAccountId: null,
            createdByStaffId: $staff->id,
            actorStaffId: $staff->id,
        );
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function cancelForMember(
        Member $member,
        Appointment $appointment,
        string $commandKey,
        ?int $accountId = null,
    ): array {
        abort_unless($appointment->member_id === $member->id, 404);

        return $this->cancel($member->tenant_id, $appointment, $commandKey, actorAccountId: $accountId);
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function cancelForStaff(
        Staff $staff,
        Appointment $appointment,
        string $commandKey,
    ): array {
        abort_unless($appointment->tenant_id === $staff->tenant_id, 404);

        return $this->cancel($staff->tenant_id, $appointment, $commandKey, actorStaffId: $staff->id);
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function promoteForStaff(
        Staff $staff,
        Appointment $appointment,
        string $commandKey,
    ): array {
        abort_unless($appointment->tenant_id === $staff->tenant_id, 404);

        return DB::transaction(fn () => $this->promoteInTransaction(
            $staff->tenant_id,
            $appointment->id,
            $commandKey,
            actorStaffId: $staff->id,
        ));
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    public function promoteForMember(
        Member $member,
        Appointment $appointment,
        string $commandKey,
        ?int $accountId = null,
    ): array {
        abort_unless($appointment->member_id === $member->id, 404);

        return DB::transaction(fn () => $this->promoteInTransaction(
            $member->tenant_id,
            $appointment->id,
            $commandKey,
            actorAccountId: $accountId,
        ));
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    private function create(
        Member $member,
        ScheduleSession $session,
        MemberCard $memberCard,
        string $commandKey,
        ?int $bookedByAccountId = null,
        ?int $createdByStaffId = null,
        ?int $actorStaffId = null,
    ): array {
        abort_unless($member->tenant_id === $session->tenant_id, 404);
        abort_unless($memberCard->member_id === $member->id, 409, 'BOOKING_CARD_NOT_PAYABLE');

        $existing = Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('command_key', $commandKey)
            ->first();

        if ($existing) {
            return ['appointment' => $existing, 'created' => false];
        }

        return DB::transaction(function () use (
            $member,
            $session,
            $memberCard,
            $commandKey,
            $bookedByAccountId,
            $createdByStaffId,
            $actorStaffId,
        ) {
            $lockedSession = ScheduleSession::query()
                ->where('tenant_id', $member->tenant_id)
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $site = Site::query()
                ->whereKey($lockedSession->site_id)
                ->where('tenant_id', $member->tenant_id)
                ->firstOrFail();

            $policy = $this->policy->policyForTenantSite($member->tenant_id, $site->id);
            $this->assertSessionBookable($lockedSession, $site, $policy);
            $this->policy->assertBookingAllowed($site, $lockedSession, $policy);

            $duplicate = Appointment::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('session_id', $lockedSession->id)
                ->where('member_id', $member->id)
                ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
                ->lockForUpdate()
                ->exists();

            abort_if($duplicate, 409, 'APPOINTMENT_ALREADY_EXISTS');

            abort_unless($this->payableCards->isEligibleForSession($memberCard, $lockedSession), 409, 'BOOKING_CARD_NOT_PAYABLE');

            $waitlistEnabled = $lockedSession->session_kind === ScheduleSessionKind::Group
                ? (bool) $policy['group']['waitlistEnabled']
                : false;

            $hasSeat = $lockedSession->booked_count < $lockedSession->capacity;
            $status = $hasSeat ? AppointmentStatus::Confirmed : ($waitlistEnabled ? AppointmentStatus::Waitlisted : null);
            abort_if($status === null, 409, 'BOOKING_SESSION_FULL');

            $ledgerEntryId = null;

            if ($status === AppointmentStatus::Confirmed) {
                $spec = $this->payableCards->deductSpec($memberCard, $lockedSession);
                $deduct = $this->entitlements->deductForBooking(
                    $memberCard,
                    $site,
                    $commandKey,
                    $spec['type'],
                    $spec['count'],
                    $spec['amount'],
                    actorAccountId: $bookedByAccountId,
                    actorStaffId: $actorStaffId,
                );
                $ledgerEntryId = $deduct['ledgerEntryId'];
                $lockedSession->booked_count = $lockedSession->booked_count + 1;
                $lockedSession->save();
            }

            $appointment = Appointment::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $site->id,
                'session_id' => $lockedSession->id,
                'member_id' => $member->id,
                'status' => $status,
                'command_key' => $commandKey,
                'member_card_id' => $memberCard->id,
                'ledger_entry_id' => $ledgerEntryId,
                'booked_by_account_id' => $bookedByAccountId,
                'created_by_staff_id' => $createdByStaffId,
                'booked_at' => now(),
            ]);

            return ['appointment' => $appointment, 'created' => true];
        });
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    private function cancel(
        int $tenantId,
        Appointment $appointment,
        string $commandKey,
        ?int $actorAccountId = null,
        ?int $actorStaffId = null,
    ): array {
        return DB::transaction(function () use ($tenantId, $appointment, $commandKey, $actorAccountId, $actorStaffId) {
            $locked = Appointment::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === AppointmentStatus::Cancelled) {
                return ['appointment' => $locked, 'created' => false];
            }

            abort_unless(
                in_array($locked->status, [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted], true),
                409,
                'APPOINTMENT_CANCEL_INVALID',
            );

            $session = ScheduleSession::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->session_id)
                ->lockForUpdate()
                ->firstOrFail();

            $site = Site::query()
                ->whereKey($locked->site_id)
                ->where('tenant_id', $locked->tenant_id)
                ->firstOrFail();

            $policy = $this->policy->policyForTenantSite($locked->tenant_id, $site->id);
            $this->policy->assertCancellationAllowed($site, $session, $policy);

            $wasConfirmed = $locked->status === AppointmentStatus::Confirmed;

            if ($wasConfirmed) {
                abort_if($session->booked_count < 1, 409, 'BOOKING_SESSION_COUNT_INVALID');
                $session->booked_count = $session->booked_count - 1;
                $session->save();

                if ($locked->ledger_entry_id !== null) {
                    $original = $locked->ledgerEntry()->firstOrFail();
                    $this->entitlements->refundForCancellation(
                        $original,
                        $site,
                        $commandKey,
                        actorAccountId: $actorAccountId,
                        actorStaffId: $actorStaffId,
                    );
                }
            }

            $locked->status = AppointmentStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();

            if ($wasConfirmed) {
                $this->tryAutoPromoteFirstWaitlisted(
                    $locked->tenant_id,
                    $session,
                    $site,
                    $commandKey,
                    $actorAccountId,
                    $actorStaffId,
                );
            }

            return ['appointment' => $locked->fresh(), 'created' => true];
        });
    }

    /**
     * @return array{appointment: Appointment, created: bool}
     */
    private function promoteInTransaction(
        int $tenantId,
        int $appointmentId,
        string $commandKey,
        ?int $actorAccountId = null,
        ?int $actorStaffId = null,
    ): array {
        $locked = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($appointmentId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status === AppointmentStatus::Confirmed) {
            return ['appointment' => $locked, 'created' => false];
        }

        abort_unless($locked->status === AppointmentStatus::Waitlisted, 409, 'APPOINTMENT_PROMOTE_INVALID');

        $session = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($locked->session_id)
            ->lockForUpdate()
            ->firstOrFail();

        $site = Site::query()
            ->whereKey($locked->site_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        abort_if($session->booked_count >= $session->capacity, 409, 'BOOKING_SESSION_FULL');

        $memberCard = MemberCard::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($locked->member_card_id)
            ->firstOrFail();

        abort_unless(
            $this->payableCards->isPayableForSession($memberCard, $session),
            409,
            'BOOKING_CARD_NOT_PAYABLE',
        );

        $spec = $this->payableCards->deductSpec($memberCard, $session);
        $deduct = $this->entitlements->deductForBooking(
            $memberCard,
            $site,
            $commandKey,
            $spec['type'],
            $spec['count'],
            $spec['amount'],
            actorAccountId: $actorAccountId,
            actorStaffId: $actorStaffId,
            reason: '排队转正扣费',
        );

        $locked->status = AppointmentStatus::Confirmed;
        $locked->ledger_entry_id = $deduct['ledgerEntryId'];
        $locked->save();

        $session->booked_count = $session->booked_count + 1;
        $session->save();

        return ['appointment' => $locked->fresh(), 'created' => true];
    }

    private function tryAutoPromoteFirstWaitlisted(
        int $tenantId,
        ScheduleSession $session,
        Site $site,
        string $cancelCommandKey,
        ?int $actorAccountId = null,
        ?int $actorStaffId = null,
    ): void {
        if ($session->booked_count >= $session->capacity) {
            return;
        }

        $policy = $this->policy->policyForTenantSite($tenantId, $site->id);
        if ($session->session_kind !== ScheduleSessionKind::Group
            || ! (bool) ($policy['group']['waitlistEnabled'] ?? false)) {
            return;
        }

        $first = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $session->id)
            ->where('status', AppointmentStatus::Waitlisted)
            ->orderBy('booked_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($first === null) {
            return;
        }

        $memberCard = MemberCard::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($first->member_card_id)
            ->first();

        if ($memberCard === null || ! $this->payableCards->isPayableForSession($memberCard, $session)) {
            return;
        }

        $promoteCommandKey = $this->derivePromoteCommandKey($cancelCommandKey, $first->id);

        $this->promoteInTransaction(
            $tenantId,
            $first->id,
            $promoteCommandKey,
            $actorAccountId,
            $actorStaffId,
        );
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function assertSessionBookable(ScheduleSession $session, Site $site, array $policy): void
    {
        abort_unless($session->status === ScheduleSessionStatus::Scheduled, 409, 'BOOKING_SESSION_NOT_BOOKABLE');

        $sessionDate = $session->starts_at->timezone($this->siteTimezone($site))->toDateString();
        abort_unless(
            $this->policy->memberSessionBookableOnDate($site, $sessionDate, $session->session_kind->value, $policy),
            422,
            'BOOKING_DATE_OUT_OF_ADVANCE_WINDOW',
        );
    }

    private function siteTimezone(Site $site): string
    {
        return $site->timezone ?: (string) config('app.timezone');
    }

    private function derivePromoteCommandKey(string $cancelCommandKey, int $appointmentId): string
    {
        $hash = md5($cancelCommandKey.':'.$appointmentId);

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
