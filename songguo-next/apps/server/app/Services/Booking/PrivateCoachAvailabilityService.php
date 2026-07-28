<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Models\CoachPrivateProfile;
use App\Models\ScheduleSession;
use Illuminate\Support\Collection;

/**
 * 私教可约时段：时间槽生成、课前休息、与团课重合策略。
 */
class PrivateCoachAvailabilityService
{
    /**
     * @param  Collection<int, ScheduleSession>  $coachDaySessions
     * @return list<array{start: string, startsAt: string, available: bool, groupOverlapWarn?: bool}>
     */
    public function buildSlotsForDay(
        \Carbon\Carbon $day,
        Collection $coachDaySessions,
        array $privatePolicy,
        int $durationMinutes,
        int $slotStepMinutes,
        array $bookingWindows,
        ?int $excludeSessionId = null,
        bool $grayOutBookedSlots = true,
    ): array {
        $weekday = $day->dayOfWeekIso;
        $windows = collect($bookingWindows)
            ->filter(fn ($window) => in_array($weekday, $window['days'] ?? [], true));

        $sessions = $coachDaySessions
            ->when($excludeSessionId !== null, fn (Collection $items) => $items->where('id', '!=', $excludeSessionId))
            ->values();

        $slots = [];
        $duration = max(1, $durationMinutes);
        $prep = max(0, (int) ($privatePolicy['preparationMinutes'] ?? 0));
        $groupMode = (string) ($privatePolicy['groupConflictMode'] ?? 'block');

        foreach ($windows as $window) {
            $cursor = $day->copy()->setTimeFromTimeString((string) ($window['start'] ?? '00:00'));
            $windowEnd = $day->copy()->setTimeFromTimeString((string) ($window['end'] ?? '24:00'));
            while ($cursor->copy()->addMinutes($duration) <= $windowEnd) {
                $startsAt = $cursor->copy();
                $endsAt = $cursor->copy()->addMinutes($duration);
                $evaluation = $this->evaluateSlot($startsAt, $endsAt, $sessions, $prep, $groupMode);

                $available = $startsAt->isFuture() && $evaluation['bookable'];
                $key = $startsAt->format('H:i');

                if (! $grayOutBookedSlots && ! $available) {
                    $cursor->addMinutes(max(5, $slotStepMinutes));
                    continue;
                }

                $entry = [
                    'start' => $key,
                    'startsAt' => $startsAt->toIso8601String(),
                    'available' => $available,
                ];
                if ($evaluation['groupOverlapWarn']) {
                    $entry['groupOverlapWarn'] = true;
                }
                $slots[$key] ??= $entry;
                $cursor->addMinutes(max(5, $slotStepMinutes));
            }
        }
        ksort($slots);

        return array_values($slots);
    }

    /**
     * @param  Collection<int, ScheduleSession>  $overlappingSessions
     */
    public function assertBookableSlot(
        \Carbon\Carbon $startsAt,
        \Carbon\Carbon $endsAt,
        Collection $overlappingSessions,
        array $privatePolicy,
        bool $allowGroupOverlapWarn = false,
    ): void {
        $prep = max(0, (int) ($privatePolicy['preparationMinutes'] ?? 0));
        $groupMode = (string) ($privatePolicy['groupConflictMode'] ?? 'block');
        $evaluation = $this->evaluateSlot($startsAt, $endsAt, $overlappingSessions, $prep, $groupMode);

        if ($evaluation['bookable']) {
            return;
        }

        if ($evaluation['groupOverlapWarn'] && $groupMode === 'overlap_warn' && $allowGroupOverlapWarn) {
            return;
        }

        if ($evaluation['groupOverlapWarn']) {
            abort(422, 'COACH_PRIVATE_GROUP_OVERLAP');
        }

        abort(422, 'COACH_PRIVATE_TIME_CONFLICT');
    }

    /**
     * @param  Collection<int, ScheduleSession>  $sessions
     * @return array{bookable: bool, groupOverlapWarn: bool}
     */
    private function evaluateSlot(
        \Carbon\Carbon $startsAt,
        \Carbon\Carbon $endsAt,
        Collection $sessions,
        int $preparationMinutes,
        string $groupConflictMode,
    ): array {
        $groupOverlapWarn = false;

        foreach ($sessions as $session) {
            $verdict = $this->sessionBlocksSlot($startsAt, $endsAt, $session, $preparationMinutes, $groupConflictMode);
            if ($verdict === 'none') {
                continue;
            }
            if ($verdict === 'warn') {
                $groupOverlapWarn = true;
                continue;
            }

            return ['bookable' => false, 'groupOverlapWarn' => false];
        }

        if ($groupOverlapWarn && $groupConflictMode === 'overlap_warn') {
            return ['bookable' => false, 'groupOverlapWarn' => true];
        }

        if ($groupOverlapWarn && $groupConflictMode === 'block') {
            return ['bookable' => false, 'groupOverlapWarn' => false];
        }

        return ['bookable' => true, 'groupOverlapWarn' => $groupOverlapWarn];
    }

    /**
     * @return 'none'|'hard'|'warn'
     */
    private function sessionBlocksSlot(
        \Carbon\Carbon $startsAt,
        \Carbon\Carbon $endsAt,
        ScheduleSession $session,
        int $preparationMinutes,
        string $groupConflictMode,
    ): string {
        $busyStart = $session->starts_at->copy();
        $busyEnd = $session->ends_at->copy()->addMinutes($preparationMinutes);

        if ($startsAt >= $busyEnd || $endsAt <= $busyStart) {
            return 'none';
        }

        $isGroup = $session->session_kind === ScheduleSessionKind::Group;
        $hasActive = $this->sessionHasActiveAppointments($session);

        if ($isGroup) {
            if ($groupConflictMode === 'allow') {
                return $hasActive ? 'hard' : 'none';
            }
            if ($groupConflictMode === 'overlap_warn') {
                // 团课重合：会员不可约；员工端以 groupOverlapWarn + acknowledgeGroupOverlap 代约（与是否已有团课预约无关）
                return 'warn';
            }

            return 'hard';
        }

        return $hasActive ? 'hard' : 'none';
    }

    public function sessionHasActiveAppointments(ScheduleSession $session): bool
    {
        if ($session->relationLoaded('appointments')) {
            return $session->appointments
                ->whereIn('status', [
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::Completed,
                    AppointmentStatus::Waitlisted,
                ])
                ->isNotEmpty();
        }

        return $session->appointments()
            ->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
                AppointmentStatus::Waitlisted,
            ])
            ->exists();
    }

    /**
     * @return Collection<int, ScheduleSession>
     */
    public function coachSessionsOnDay(CoachPrivateProfile $profile, \Carbon\Carbon $day): Collection
    {
        return ScheduleSession::query()
            ->where('tenant_id', $profile->tenant_id)
            ->where('site_id', $profile->site_id)
            ->where('coach_staff_id', $profile->coach_staff_id)
            ->whereIn('status', ['scheduled', 'suspended'])
            ->where('starts_at', '<', $day->copy()->addDay())
            ->where('ends_at', '>', $day)
            ->with(['appointments' => fn ($query) => $query->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
                AppointmentStatus::Waitlisted,
            ])])
            ->get();
    }
}
