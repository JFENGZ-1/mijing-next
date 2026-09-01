<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Compensation\EntitlementReservationService;
use Illuminate\Support\Facades\DB;

class AppointmentWriteService
{
    public function __construct(
        private BookingPolicyService $policy,
        private BookingPayableCardService $payableCards,
        private BookingEntitlementService $entitlements,
        private \App\Services\Cards\CardProductBookingRulesService $cardRules,
        private \App\Services\Cards\MemberCardAutoActivationService $autoActivation,
        private EntitlementReservationService $reservations,
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
        ?string $memberRemark = null,
    ): array {
        return $this->create(
            $member,
            $session,
            $memberCard,
            $commandKey,
            bookedByAccountId: null,
            createdByStaffId: $staff->id,
            actorStaffId: $staff->id,
            memberRemark: $memberRemark,
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
    public function cancelForSystem(int $tenantId, Appointment $appointment, string $commandKey): array
    {
        return $this->cancel($tenantId, $appointment, $commandKey, skipBookingPolicy: true);
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

        return DB::transaction(function () use ($staff, $appointment, $commandKey) {
            $this->lockPromotionSession($staff->tenant_id, $appointment->id);

            return $this->promoteInTransaction(
                $staff->tenant_id,
                $appointment->id,
                $commandKey,
                actorStaffId: $staff->id,
            );
        });
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

        return DB::transaction(function () use ($member, $appointment, $commandKey, $accountId) {
            $this->lockPromotionSession($member->tenant_id, $appointment->id);

            return $this->promoteInTransaction(
                $member->tenant_id,
                $appointment->id,
                $commandKey,
                actorAccountId: $accountId,
            );
        });
    }

    /**
     * Repair vacancies left when the best-effort post-cancellation promotion
     * was interrupted. Each session is locked before its first waitlisted
     * appointment, preserving the global session -> appointment -> card order.
     *
     * @return array{sessionsScanned:int,appointmentsPromoted:int,appointmentsCancelled:int}
     */
    public function reconcileWaitlists(int $limit = 200): array
    {
        $sessionIds = ScheduleSession::query()
            ->where('status', ScheduleSessionStatus::Scheduled)
            ->where('session_kind', ScheduleSessionKind::Group)
            ->whereColumn('booked_count', '<', 'capacity')
            ->whereHas('appointments', fn ($query) => $query->where('status', AppointmentStatus::Waitlisted))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->pluck('id');
        $promoted = 0;
        $cancelled = 0;

        foreach ($sessionIds as $sessionId) {
            DB::transaction(function () use ($sessionId, &$promoted, &$cancelled) {
                $session = ScheduleSession::query()->whereKey($sessionId)->lockForUpdate()->first();
                if ($session === null
                    || $session->status !== ScheduleSessionStatus::Scheduled
                    || $session->session_kind !== ScheduleSessionKind::Group) {
                    return;
                }
                $site = Site::query()
                    ->where('tenant_id', $session->tenant_id)
                    ->whereKey($session->site_id)
                    ->firstOrFail();

                while ((int) $session->booked_count < (int) $session->capacity) {
                    $outcome = $this->tryAutoPromoteFirstWaitlisted(
                        (int) $session->tenant_id,
                        $session,
                        $site,
                        'waitlist-reconcile:'.$session->id,
                    );
                    $cancelled += $outcome['cancelled'];
                    $session->refresh();
                    if (! $outcome['promoted']) {
                        break;
                    }
                    $promoted++;
                }
            });
        }

        return [
            'sessionsScanned' => $sessionIds->count(),
            'appointmentsPromoted' => $promoted,
            'appointmentsCancelled' => $cancelled,
        ];
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
        ?string $memberRemark = null,
    ): array {
        abort_unless($member->tenant_id === $session->tenant_id, 404);
        abort_unless($memberCard->member_id === $member->id, 409, 'BOOKING_CARD_NOT_PAYABLE');
        $payloadHash = $this->commandHash([
            'operation' => 'create', 'memberId' => $member->id, 'sessionId' => $session->id,
            'memberCardId' => $memberCard->id, 'bookedByAccountId' => $bookedByAccountId,
            'createdByStaffId' => $createdByStaffId, 'actorStaffId' => $actorStaffId,
            'memberRemark' => $memberRemark,
        ]);

        $existing = Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('command_key', $commandKey)
            ->first();

        if ($existing) {
            $this->assertCreateReplay($existing, $member, $session, $memberCard, $payloadHash);

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
            $memberRemark,
            $payloadHash,
        ) {
            $lockedSession = ScheduleSession::query()
                ->where('tenant_id', $member->tenant_id)
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = Appointment::query()
                ->where('tenant_id', $member->tenant_id)->where('command_key', $commandKey)
                ->lockForUpdate()->first();
            if ($existing) {
                $this->assertCreateReplay($existing, $member, $lockedSession, $memberCard, $payloadHash);

                return ['appointment' => $existing, 'created' => false];
            }

            $site = Site::query()
                ->whereKey($lockedSession->site_id)
                ->where('tenant_id', $member->tenant_id)
                ->firstOrFail();

            $policy = $this->policy->policyForTenantSite($member->tenant_id, $site->id);
            $this->assertSessionBookable($lockedSession, $site, $policy, forMemberSelfBook: $createdByStaffId === null);
            // 开课截止、最少提前等仅约束会员自约；员工代约不受预约设置限制（对标原版管理端）
            if ($createdByStaffId === null) {
                $this->policy->assertBookingAllowed($site, $lockedSession, $policy);
                $this->policy->assertMemberDailyBookingQuota($site, $member->id, $lockedSession, $policy);
            }

            $duplicate = Appointment::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('session_id', $lockedSession->id)
                ->where('member_id', $member->id)
                ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
                ->lockForUpdate()
                ->exists();

            abort_if($duplicate, 409, 'APPOINTMENT_ALREADY_EXISTS');

            $memberCard = MemberCard::query()
                ->where('tenant_id', $member->tenant_id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless((int) $memberCard->member_id === (int) $member->id, 409, 'BOOKING_CARD_NOT_PAYABLE');

            abort_unless($this->payableCards->isEligibleForSession($memberCard, $lockedSession), 409, 'BOOKING_CARD_NOT_PAYABLE');

            // 自动开卡：first-use 首次使用激活；delayed 到期懒激活兜底
            $memberCard = $this->autoActivation->maybeActivateForBooking($memberCard, $bookedByAccountId);

            // 卡级预约规则（原版「高级选项」）：仅约束会员自约，员工代约不受限
            if ($createdByStaffId === null) {
                $this->cardRules->assertBookingAllowed($memberCard, $lockedSession, $site);
            }

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
                    // first-class：约课不开卡、上课才开卡，允许待激活扣费
                    allowPendingActivation: $this->autoActivation->allowsPendingDeduction($memberCard),
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
                'command_payload_hash' => $payloadHash,
                'member_card_id' => $memberCard->id,
                'ledger_entry_id' => $ledgerEntryId,
                'booked_by_account_id' => $bookedByAccountId,
                'created_by_staff_id' => $createdByStaffId,
                'member_remark' => $memberRemark,
                'booked_at' => now(),
            ]);
            $this->reservations->reserve($appointment);

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
        bool $skipBookingPolicy = false,
    ): array {
        $payloadHash = $this->commandHash([
            'operation' => 'cancel', 'appointmentId' => $appointment->id,
            'actorAccountId' => $actorAccountId, 'actorStaffId' => $actorStaffId,
            'skipBookingPolicy' => $skipBookingPolicy,
        ]);
        $sessionId = (int) Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($appointment->id)
            ->value('session_id');
        abort_if($sessionId < 1, 404);

        $promotion = null;
        $result = DB::transaction(function () use ($tenantId, $appointment, $sessionId, $commandKey, $actorAccountId, $actorStaffId, $skipBookingPolicy, $payloadHash, &$promotion) {
            // Global booking lock order: session -> appointment -> card/ledger.
            // This matches create/promote and prevents cancel-vs-rebook deadlocks.
            $session = ScheduleSession::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = Appointment::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless((int) $locked->session_id === $sessionId, 409, 'APPOINTMENT_SESSION_CHANGED');

            if ($locked->status === AppointmentStatus::Cancelled) {
                abort_unless(
                    $locked->cancel_command_key === $commandKey
                    && hash_equals((string) $locked->cancel_payload_hash, $payloadHash),
                    409,
                    'IDEMPOTENCY_KEY_REUSED',
                );

                return ['appointment' => $locked, 'created' => false];
            }
            abort_if(Appointment::query()->where('tenant_id', $tenantId)
                ->where('cancel_command_key', $commandKey)->whereKeyNot($locked->id)->exists(),
                409, 'IDEMPOTENCY_KEY_REUSED');

            abort_unless(
                in_array($locked->status, [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted], true),
                409,
                'APPOINTMENT_CANCEL_INVALID',
            );

            $site = Site::query()
                ->whereKey($locked->site_id)
                ->where('tenant_id', $locked->tenant_id)
                ->firstOrFail();

            $policy = $this->policy->policyForTenantSite($locked->tenant_id, $site->id);
            $this->policy->assertCancellationAllowed(
                $site,
                $session,
                $policy,
                staffOverride: $actorStaffId !== null || $skipBookingPolicy,
            );

            // 卡级取消次数限制（原版「高级选项」）：仅约束会员自取消
            if ($actorStaffId === null && $locked->member_card_id !== null) {
                $memberCard = MemberCard::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->find($locked->member_card_id);
                if ($memberCard) {
                    $this->cardRules->assertCancellationAllowed($memberCard, $site);
                }
            }

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
                $this->reservations->release($locked);
            }

            $locked->status = AppointmentStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->cancel_command_key = $commandKey;
            $locked->cancel_payload_hash = $payloadHash;
            $locked->save();

            if ($wasConfirmed) {
                $promotion = [
                    'tenantId' => (int) $locked->tenant_id,
                    'sessionId' => (int) $session->id,
                    'siteId' => (int) $site->id,
                    'commandKey' => $commandKey,
                    'actorAccountId' => $actorAccountId,
                    'actorStaffId' => $actorStaffId,
                ];
            }

            return ['appointment' => $locked->fresh(), 'created' => true];
        });

        // Promotion is a follow-up command after the cancellation commit. This
        // avoids appointment-A -> session -> appointment-B deadlocks while keeping
        // the API synchronous. A promotion failure never rolls back the cancellation.
        if ($promotion !== null && $result['created']) {
            try {
                DB::transaction(function () use ($promotion) {
                    $session = ScheduleSession::query()
                        ->where('tenant_id', $promotion['tenantId'])
                        ->whereKey($promotion['sessionId'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    $site = Site::query()
                        ->where('tenant_id', $promotion['tenantId'])
                        ->whereKey($promotion['siteId'])
                        ->firstOrFail();
                    $this->tryAutoPromoteFirstWaitlisted(
                        $promotion['tenantId'],
                        $session,
                        $site,
                        $promotion['commandKey'],
                        $promotion['actorAccountId'],
                        $promotion['actorStaffId'],
                    );
                });
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $result;
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
            $ledger = $locked->ledger_entry_id !== null
                ? $locked->ledgerEntry()->first()
                : null;
            abort_unless(
                $ledger !== null
                && $ledger->command_key === $commandKey
                && (int) ($ledger->actor_account_id ?? 0) === (int) ($actorAccountId ?? 0)
                && (int) ($ledger->actor_staff_id ?? 0) === (int) ($actorStaffId ?? 0),
                409,
                'IDEMPOTENCY_KEY_REUSED',
            );

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

        // 候补转正同样触发自动开卡（first-use / delayed 到期兜底）
        $memberCard = $this->autoActivation->maybeActivateForBooking($memberCard);

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
            allowPendingActivation: $this->autoActivation->allowsPendingDeduction($memberCard),
        );

        $locked->status = AppointmentStatus::Confirmed;
        $locked->ledger_entry_id = $deduct['ledgerEntryId'];
        $locked->save();
        $this->reservations->reserve($locked->fresh());

        $session->booked_count = $session->booked_count + 1;
        $session->save();

        return ['appointment' => $locked->fresh(), 'created' => true];
    }

    /**
     * Terminally invalid queue heads are explicitly cancelled and audited so
     * they cannot block every member behind them forever. Reversible failures
     * (for example a frozen card or temporarily insufficient entitlement) keep
     * their place and block promotion, preserving FIFO fairness.
     *
     * @return array{promoted:bool,cancelled:int}
     */
    private function tryAutoPromoteFirstWaitlisted(
        int $tenantId,
        ScheduleSession $session,
        Site $site,
        string $cancelCommandKey,
        ?int $actorAccountId = null,
        ?int $actorStaffId = null,
    ): array {
        if ($session->booked_count >= $session->capacity) {
            return ['promoted' => false, 'cancelled' => 0];
        }

        $policy = $this->policy->policyForTenantSite($tenantId, $site->id);
        if ($session->session_kind !== ScheduleSessionKind::Group
            || ! (bool) ($policy['group']['waitlistEnabled'] ?? false)) {
            return ['promoted' => false, 'cancelled' => 0];
        }

        $cancelled = 0;

        while ($session->booked_count < $session->capacity) {
            $first = Appointment::query()
                ->where('tenant_id', $tenantId)
                ->where('session_id', $session->id)
                ->where('status', AppointmentStatus::Waitlisted)
                ->orderBy('booked_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($first === null) {
                return ['promoted' => false, 'cancelled' => $cancelled];
            }

            // Global booking lock order remains session -> appointment -> card.
            // Locking the card makes the terminal/transient classification stable
            // against a concurrent restore, unfreeze or entitlement adjustment.
            $memberCard = MemberCard::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($first->member_card_id)
                ->lockForUpdate()
                ->first();

            $terminalReason = $this->terminalWaitlistIneligibility($first, $memberCard);
            if ($terminalReason !== null) {
                $this->cancelTerminalWaitlisted(
                    $first,
                    $terminalReason,
                    $cancelCommandKey,
                    $actorAccountId,
                    $actorStaffId,
                );
                $cancelled++;

                continue;
            }

            try {
                $payable = $memberCard !== null
                    && $this->payableCards->isPayableForSession($memberCard, $session);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                // Missing pricing/rules can be repaired by an operator. Keep the
                // member at the head instead of converting a temporary error into
                // an irreversible cancellation.
                return ['promoted' => false, 'cancelled' => $cancelled];
            }

            if (! $payable) {
                return ['promoted' => false, 'cancelled' => $cancelled];
            }

            $promoteCommandKey = $this->derivePromoteCommandKey($cancelCommandKey, $first->id);

            $this->promoteInTransaction(
                $tenantId,
                $first->id,
                $promoteCommandKey,
                $actorAccountId,
                $actorStaffId,
            );

            return ['promoted' => true, 'cancelled' => $cancelled];
        }

        return ['promoted' => false, 'cancelled' => $cancelled];
    }

    private function terminalWaitlistIneligibility(Appointment $appointment, ?MemberCard $memberCard): ?string
    {
        if ($memberCard === null) {
            return 'WAITLIST_MEMBER_CARD_MISSING';
        }

        if ((int) $memberCard->member_id !== (int) $appointment->member_id) {
            return 'WAITLIST_MEMBER_CARD_OWNER_MISMATCH';
        }

        if ((int) $memberCard->site_id !== (int) $appointment->site_id) {
            return 'WAITLIST_MEMBER_CARD_SITE_MISMATCH';
        }

        if ($memberCard->archived_at !== null
            && ! in_array($memberCard->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)) {
            return 'WAITLIST_MEMBER_CARD_ARCHIVE_STATE_INVALID';
        }

        return match ($memberCard->status) {
            MemberCardStatus::Voided => 'WAITLIST_MEMBER_CARD_VOIDED',
            MemberCardStatus::Expired => 'WAITLIST_MEMBER_CARD_EXPIRED',
            MemberCardStatus::Exhausted => 'WAITLIST_MEMBER_CARD_EXHAUSTED',
            default => null,
        };
    }

    private function cancelTerminalWaitlisted(
        Appointment $appointment,
        string $reasonCode,
        string $sourceCommandKey,
        ?int $actorAccountId,
        ?int $actorStaffId,
    ): void {
        $cancelledAt = now();
        $commandKey = $this->deriveWaitlistCancelCommandKey((int) $appointment->id);
        $payloadHash = $this->commandHash([
            'operation' => 'waitlist_terminal_cancel',
            'appointmentId' => (int) $appointment->id,
            'memberCardId' => $appointment->member_card_id !== null ? (int) $appointment->member_card_id : null,
            'reasonCode' => $reasonCode,
        ]);
        $systemNote = "系统取消候补：{$reasonCode}";
        $existingNote = trim((string) ($appointment->staff_notes ?? ''));

        $appointment->status = AppointmentStatus::Cancelled;
        $appointment->cancelled_at = $cancelledAt;
        $appointment->cancel_command_key = $commandKey;
        $appointment->cancel_payload_hash = $payloadHash;
        $appointment->staff_notes = $existingNote === '' ? $systemNote : $existingNote."\n".$systemNote;
        $appointment->save();

        AuditEvent::create([
            'tenant_id' => $appointment->tenant_id,
            'site_id' => $appointment->site_id,
            'actor_account_id' => $actorAccountId,
            'actor_staff_id' => $actorStaffId,
            'action' => 'booking.waitlist.auto_cancelled_unpromotable',
            'subject_type' => 'appointment',
            'subject_id' => $appointment->id,
            'metadata' => [
                'reasonCode' => $reasonCode,
                'sessionId' => (int) $appointment->session_id,
                'memberCardId' => $appointment->member_card_id !== null ? (int) $appointment->member_card_id : null,
                'sourceCommandKey' => $sourceCommandKey,
                'cancelCommandKey' => $commandKey,
            ],
            'occurred_at' => $cancelledAt,
        ]);
    }

    private function deriveWaitlistCancelCommandKey(int $appointmentId): string
    {
        $hash = hash('sha256', 'waitlist-terminal-cancel:'.$appointmentId);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function lockPromotionSession(int $tenantId, int $appointmentId): ScheduleSession
    {
        $snapshot = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($appointmentId)
            ->firstOrFail();

        return ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($snapshot->session_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function assertSessionBookable(ScheduleSession $session, Site $site, array $policy, bool $forMemberSelfBook = true): void
    {
        abort_unless($session->status === ScheduleSessionStatus::Scheduled, 409, 'BOOKING_SESSION_NOT_BOOKABLE');

        if (! $forMemberSelfBook) {
            return;
        }

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

    private function assertCreateReplay(
        Appointment $appointment,
        Member $member,
        ScheduleSession $session,
        MemberCard $card,
        string $payloadHash,
    ): void {
        abort_unless(
            $appointment->member_id === $member->id
            && $appointment->session_id === $session->id
            && $appointment->member_card_id === $card->id
            && hash_equals((string) $appointment->command_payload_hash, $payloadHash),
            409,
            'IDEMPOTENCY_KEY_REUSED',
        );
    }

    private function commandHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
