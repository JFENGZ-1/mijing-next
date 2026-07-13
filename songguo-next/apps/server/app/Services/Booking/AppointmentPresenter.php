<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Member;

class AppointmentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'siteId' => $appointment->site_id,
            'sessionId' => $appointment->session_id,
            'memberId' => $appointment->member_id,
            'status' => $appointment->status->value,
            'memberCardId' => $appointment->member_card_id,
            'ledgerEntryId' => $appointment->ledger_entry_id,
            'bookedByAccountId' => $appointment->booked_by_account_id,
            'createdByStaffId' => $appointment->created_by_staff_id,
            'bookedAt' => $appointment->booked_at?->toIso8601String(),
            'cancelledAt' => $appointment->cancelled_at?->toIso8601String(),
            'staffNotes' => $appointment->staff_notes,
            'absentMarkedAt' => $appointment->absent_marked_at?->toIso8601String(),
            'rescheduledFromSessionId' => $appointment->rescheduled_from_session_id,
            'penaltyLedgerEntryId' => $appointment->penalty_ledger_entry_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toStaffArray(Appointment $appointment, bool $canReadMemberNames): array
    {
        $payload = self::toArray($appointment);

        if ($appointment->relationLoaded('member')) {
            /** @var Member|null $member */
            $member = $appointment->member;
            $rawName = $member?->crmProfile?->name ?? $member?->account?->display_name;
            $payload['memberName'] = $canReadMemberNames ? $rawName : self::maskName($rawName);
            $payload['memberNo'] = $canReadMemberNames ? $member?->member_no : null;
        }

        return $payload;
    }

    private static function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
    }
}
