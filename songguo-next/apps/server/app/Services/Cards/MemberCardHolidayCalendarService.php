<?php

namespace App\Services\Cards;

use App\Enums\AppointmentStatus;
use App\Enums\EntitlementLedgerEntryType;
use App\Models\Appointment;
use App\Models\EntitlementLedgerEntry;
use App\Models\EntitlementReservation;
use App\Models\MemberCard;
use App\Models\Site;
use Carbon\Carbon;

class MemberCardHolidayCalendarService
{
    public function assertNoReservedService(MemberCard $card, Site $site, string $from, string $to): void
    {
        $timezone = $site->timezone ?: (string) config('app.timezone');
        $fromUtc = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $toUtc = Carbon::parse($to, $timezone)->endOfDay()->utc();
        $sessionRange = fn ($query) => $query
            ->where('tenant_id', $card->tenant_id)
            ->where('site_id', $site->id)
            ->whereBetween('starts_at', [$fromUtc, $toUtc]);

        $hasAppointment = Appointment::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_card_id', $card->id)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
            ->whereHas('session', $sessionRange)
            ->exists();
        $hasReservation = EntitlementReservation::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_card_id', $card->id)
            ->where('status', 'reserved')
            ->whereIn('session_id', fn ($query) => $query->from('schedule_sessions')
                ->select('id')
                ->where('tenant_id', $card->tenant_id)
                ->where('site_id', $site->id)
                ->whereBetween('starts_at', [$fromUtc, $toUtc]))
            ->exists();

        abort_if($hasAppointment || $hasReservation, 409, 'MEMBER_CARD_HOLIDAY_HAS_BOOKINGS');
    }

    public function isBlockedOn(MemberCard $card, string $businessDate): bool
    {
        $date = Carbon::parse($businessDate)->startOfDay();
        $active = data_get($card->freeze_state, 'holiday');
        if (is_array($active) && isset($active['startedAt'])
            && $date->gte(Carbon::parse((string) $active['startedAt'])->startOfDay())) {
            // An overdue planned end is still blocked until an explicit, audited
            // holiday-end command records the actual last blackout day.
            return true;
        }

        return EntitlementLedgerEntry::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('entry_type', EntitlementLedgerEntryType::HolidayCancel)
            ->whereDate('valid_from_after', '<=', $date->toDateString())
            ->whereDate('valid_until_after', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * Count completed blackout dates in [from, before). Overlapping historical
     * intervals are merged so a corrected/imported ledger cannot double-shift
     * the paid period-day ordinal.
     */
    public function completedBlackoutDaysBefore(MemberCard $card, string $from, string $before): int
    {
        $lower = Carbon::parse($from)->startOfDay();
        $upper = Carbon::parse($before)->startOfDay()->subDay();
        if ($upper->lt($lower)) {
            return 0;
        }

        $intervals = EntitlementLedgerEntry::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('entry_type', EntitlementLedgerEntryType::HolidayCancel)
            ->whereNotNull('valid_from_after')
            ->whereNotNull('valid_until_after')
            ->orderBy('valid_from_after')
            ->orderBy('id')
            ->get(['valid_from_after', 'valid_until_after'])
            ->map(function (EntitlementLedgerEntry $entry) use ($lower, $upper) {
                $start = Carbon::parse($entry->valid_from_after)->startOfDay()->max($lower);
                $end = Carbon::parse($entry->valid_until_after)->startOfDay()->min($upper);

                return $end->lt($start) ? null : [$start, $end];
            })
            ->filter()
            ->values();

        $days = 0;
        $currentStart = null;
        $currentEnd = null;
        foreach ($intervals as [$start, $end]) {
            if ($currentStart === null) {
                [$currentStart, $currentEnd] = [$start, $end];

                continue;
            }
            if ($start->lte($currentEnd->copy()->addDay())) {
                if ($end->gt($currentEnd)) {
                    $currentEnd = $end;
                }

                continue;
            }
            $days += (int) $currentStart->diffInDays($currentEnd) + 1;
            [$currentStart, $currentEnd] = [$start, $end];
        }
        if ($currentStart !== null) {
            $days += (int) $currentStart->diffInDays($currentEnd) + 1;
        }

        return $days;
    }
}
