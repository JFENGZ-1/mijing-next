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
            'memberRemark' => $appointment->member_remark,
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

        // 卡信息（对标原版行内：卡名 + 余额 + 单位）
        if ($appointment->relationLoaded('memberCard') && $appointment->memberCard !== null) {
            $card = $appointment->memberCard;
            $payload['cardName'] = $card->product_snapshot['name'] ?? null;
            $cardType = $card->card_type->value ?? (string) $card->card_type;
            $payload['cardUnit'] = $cardType === 'count' ? '次' : ($cardType === 'period' ? '天' : '元');
            $payload['cardBalance'] = $cardType === 'count'
                ? ($card->cached_remaining_count !== null ? (string) $card->cached_remaining_count : null)
                : ($cardType === 'period'
                    ? ($card->valid_until?->diffInDays(now()) !== null && $card->valid_until !== null
                        ? (string) max(0, (int) now()->diffInDays($card->valid_until, false))
                        : null)
                    : ($card->cached_balance !== null ? (string) $card->cached_balance : null));
        }

        // 本次扣费（对标原版 cardChangeAmount：-2次 / -99元）
        if ($appointment->relationLoaded('ledgerEntry') && $appointment->ledgerEntry !== null) {
            $entry = $appointment->ledgerEntry;
            $amount = $entry->count_delta !== null && (int) $entry->count_delta !== 0
                ? (string) abs((int) $entry->count_delta)
                : ($entry->amount_delta !== null ? (string) abs((float) $entry->amount_delta) : null);
            $payload['deductAmount'] = $amount;
        }

        // 代约操作人（对标原版「XX操作」）
        if ($appointment->relationLoaded('createdByStaff')) {
            $payload['operatorStaffName'] = $appointment->createdByStaff?->name;
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
