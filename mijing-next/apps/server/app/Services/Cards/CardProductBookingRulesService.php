<?php

namespace App\Services\Cards;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Models\Site;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * 卡级预约规则（对标原版会员卡「高级选项」）。
 * 仅约束会员端自主操作；员工代约/代取消不受限（原版前台特权行为）。
 */
class CardProductBookingRulesService
{
    /**
     * 卡种实时规则优先（改规则立即生效），回退发卡快照。
     *
     * @return array<string, mixed>
     */
    public function rulesFor(MemberCard $card): array
    {
        $card->loadMissing('cardProduct');
        $rules = $card->cardProduct?->booking_rules;
        if (is_array($rules) && $rules !== []) {
            return $rules;
        }
        $snapshot = $card->product_snapshot['bookingRules'] ?? null;

        return is_array($snapshot) ? $snapshot : [];
    }

    public function assertBookingAllowed(MemberCard $card, ScheduleSession $session, Site $site): void
    {
        $rules = $this->rulesFor($card);
        if ($rules === []) {
            return;
        }

        $timezone = $site->timezone ?: (string) config('app.timezone');
        $sessionStart = Carbon::parse($session->starts_at)->setTimezone($timezone);

        $this->assertWithinTimeRanges($rules, $sessionStart);
        $this->assertBookingCountLimits($card, $rules, $sessionStart, $timezone);
        $this->assertAdvanceLimit($card, $rules, $timezone);
        $this->assertRepeatBooking($card, $rules, $session, $timezone);
        $this->assertNotForbiddenByAbsence($card, $rules, $timezone);
    }

    /**
     * 归一化处罚配置（兼容旧结构 window/threshold + mark/no_refund）。
     *
     * @return array{weekThreshold: ?int, monthThreshold: ?int, action: string, forbidDays: int, deductValue: float}|null
     */
    public function normalizedAbsencePenalty(MemberCard $card): ?array
    {
        $penalty = $this->rulesFor($card)['absencePenalty'] ?? null;
        if (! is_array($penalty) || ! isset($penalty['action'])) {
            return null;
        }

        $action = (string) $penalty['action'];
        if ($action === 'mark' || $action === 'no_refund') {
            $action = 'mark_or_no_refund';
        }

        $weekThreshold = isset($penalty['weekThreshold']) ? (int) $penalty['weekThreshold'] : null;
        $monthThreshold = isset($penalty['monthThreshold']) ? (int) $penalty['monthThreshold'] : null;
        // 旧结构兼容
        if ($weekThreshold === null && $monthThreshold === null && isset($penalty['threshold'])) {
            if (($penalty['window'] ?? 'month') === 'week') {
                $weekThreshold = (int) $penalty['threshold'];
            } else {
                $monthThreshold = (int) $penalty['threshold'];
            }
        }
        if (($weekThreshold === null || $weekThreshold < 1) && ($monthThreshold === null || $monthThreshold < 1)) {
            return null;
        }

        return [
            'weekThreshold' => $weekThreshold,
            'monthThreshold' => $monthThreshold,
            'action' => $action,
            'forbidDays' => (int) ($penalty['forbidDays'] ?? 0),
            'deductValue' => (float) ($penalty['deductValue'] ?? 0),
        ];
    }

    /**
     * 旷课时是否执行「冻卡」处罚。
     * null = 无卡级规则（沿用场馆级开关）；卡级规则存在时一律 false——
     * 原版卡级动作为 仅标记/不退课费、禁止约课、扣除，均不冻卡。
     */
    public function absenceChargeApplies(MemberCard $card, Site $site): ?bool
    {
        return $this->normalizedAbsencePenalty($card) === null ? null : false;
    }

    /**
     * 旷课扣除动作：达到阈值且 action=deduct 时返回扣除额（含本次旷课计数）。
     *
     * @return array{kind: 'money'|'count'|'days', value: float}|null
     */
    public function absenceDeductionSpec(MemberCard $card, Site $site): ?array
    {
        $penalty = $this->normalizedAbsencePenalty($card);
        if ($penalty === null || $penalty['action'] !== 'deduct' || $penalty['deductValue'] <= 0) {
            return null;
        }
        if (! $this->absenceThresholdReached($card, $site->timezone ?: (string) config('app.timezone'), $penalty, includeCurrent: true)) {
            return null;
        }

        $kind = match ($card->card_type?->value) {
            'stored_value' => 'money',
            'count' => 'count',
            default => 'days',
        };

        return ['kind' => $kind, 'value' => $penalty['deductValue']];
    }

    /**
     * 周/月双窗口任一达标即触发（对标原版 weekLimit/monthLimit 可同设）。
     *
     * @param  array{weekThreshold: ?int, monthThreshold: ?int}  $penalty
     */
    private function absenceThresholdReached(MemberCard $card, string $timezone, array $penalty, bool $includeCurrent): bool
    {
        $now = Carbon::now($timezone);
        $bonus = $includeCurrent ? 1 : 0;

        if (($penalty['weekThreshold'] ?? 0) >= 1) {
            $count = $this->absentCountBetween(
                $card,
                $now->copy()->startOfWeek(CarbonInterface::MONDAY),
                $now->copy()->endOfWeek(CarbonInterface::SUNDAY),
            );
            if (($count + $bonus) >= $penalty['weekThreshold']) {
                return true;
            }
        }
        if (($penalty['monthThreshold'] ?? 0) >= 1) {
            $count = $this->absentCountBetween($card, $now->copy()->startOfMonth(), $now->copy()->endOfMonth());
            if (($count + $bonus) >= $penalty['monthThreshold']) {
                return true;
            }
        }

        return false;
    }

    private function absentCountBetween(MemberCard $card, Carbon $from, Carbon $to): int
    {
        return Appointment::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('status', AppointmentStatus::Absent)
            ->whereNotNull('absent_marked_at')
            ->whereBetween('absent_marked_at', [$from, $to])
            ->count();
    }

    public function assertCancellationAllowed(MemberCard $card, Site $site): void
    {
        $rules = $this->rulesFor($card);
        $limit = $rules['cancelLimit'] ?? null;
        if (! is_array($limit)) {
            return;
        }

        $timezone = $site->timezone ?: (string) config('app.timezone');
        $now = Carbon::now($timezone);

        foreach ([
            'perDay' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'perWeek' => [$now->copy()->startOfWeek(CarbonInterface::MONDAY), $now->copy()->endOfWeek(CarbonInterface::SUNDAY)],
            'perMonth' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        ] as $key => [$from, $to]) {
            $max = $limit[$key] ?? null;
            if ($max === null || (int) $max < 0) {
                continue;
            }
            $cancelled = Appointment::query()
                ->where('tenant_id', $card->tenant_id)
                ->where('member_card_id', $card->id)
                ->where('status', AppointmentStatus::Cancelled)
                ->whereNotNull('cancelled_at')
                ->whereBetween('cancelled_at', [$from, $to])
                ->count();
            abort_if($cancelled >= (int) $max, 409, 'CARD_CANCEL_LIMIT_REACHED');
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function assertWithinTimeRanges(array $rules, Carbon $sessionStart): void
    {
        $ranges = $rules['timeRanges'] ?? null;
        if (! is_array($ranges) || $ranges === []) {
            return;
        }

        $clock = $sessionStart->format('H:i');
        foreach ($ranges as $range) {
            $start = is_array($range) ? ($range['start'] ?? null) : null;
            $end = is_array($range) ? ($range['end'] ?? null) : null;
            if (is_string($start) && is_string($end) && $clock >= $start && $clock <= $end) {
                return;
            }
        }

        abort(409, 'CARD_BOOKING_TIME_RESTRICTED');
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function assertBookingCountLimits(MemberCard $card, array $rules, Carbon $sessionStart, string $timezone): void
    {
        $limit = $rules['bookingLimit'] ?? null;
        if (! is_array($limit)) {
            return;
        }

        foreach ([
            'perDay' => [$sessionStart->copy()->startOfDay(), $sessionStart->copy()->endOfDay()],
            'perWeek' => [$sessionStart->copy()->startOfWeek(CarbonInterface::MONDAY), $sessionStart->copy()->endOfWeek(CarbonInterface::SUNDAY)],
            'perMonth' => [$sessionStart->copy()->startOfMonth(), $sessionStart->copy()->endOfMonth()],
        ] as $key => [$from, $to]) {
            $max = $limit[$key] ?? null;
            if ($max === null || (int) $max < 1) {
                continue;
            }
            $count = $this->activeAppointmentsQuery($card)
                ->whereHas('session', fn ($query) => $query->whereBetween('starts_at', [
                    $from->copy()->setTimezone(config('app.timezone')),
                    $to->copy()->setTimezone(config('app.timezone')),
                ]))
                ->count();
            abort_if($count >= (int) $max, 409, 'CARD_BOOKING_LIMIT_REACHED');
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function assertAdvanceLimit(MemberCard $card, array $rules, string $timezone): void
    {
        $max = $rules['advanceLimit'] ?? null;
        if ($max === null || (int) $max < 1) {
            return;
        }

        $now = Carbon::now($timezone)->setTimezone(config('app.timezone'));
        $count = $this->activeAppointmentsQuery($card)
            ->whereHas('session', fn ($query) => $query->where('starts_at', '>', $now))
            ->count();
        abort_if($count >= (int) $max, 409, 'CARD_ADVANCE_LIMIT_REACHED');
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function assertRepeatBooking(MemberCard $card, array $rules, ScheduleSession $session, string $timezone): void
    {
        $repeat = $rules['repeatBooking'] ?? null;
        if (! is_array($repeat)) {
            return;
        }
        $mode = $repeat['mode'] ?? 'allow';
        if ($mode === 'allow') {
            return;
        }

        $now = Carbon::now($timezone)->setTimezone(config('app.timezone'));
        $count = $this->activeAppointmentsQuery($card)
            ->whereHas('session', fn ($query) => $query
                ->where('course_id', $session->course_id)
                ->where('starts_at', '>', $now))
            ->count();

        if ($mode === 'deny') {
            abort_if($count >= 1, 409, 'CARD_REPEAT_BOOKING_DENIED');

            return;
        }

        $max = (int) ($repeat['max'] ?? 1);
        abort_if($count >= max(1, $max), 409, 'CARD_REPEAT_BOOKING_DENIED');
    }

    /**
     * 旷课处罚 action=forbid：周/月窗口内旷课达阈值后，自最近一次旷课起禁约 N 天。
     *
     * @param  array<string, mixed>  $rules
     */
    private function assertNotForbiddenByAbsence(MemberCard $card, array $rules, string $timezone): void
    {
        $penalty = $this->normalizedAbsencePenalty($card);
        if ($penalty === null || $penalty['action'] !== 'forbid' || $penalty['forbidDays'] < 1) {
            return;
        }

        if (! $this->absenceThresholdReached($card, $timezone, $penalty, includeCurrent: false)) {
            return;
        }

        $lastAbsent = Appointment::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('status', AppointmentStatus::Absent)
            ->whereNotNull('absent_marked_at')
            ->orderByDesc('absent_marked_at')
            ->value('absent_marked_at');

        if ($lastAbsent === null) {
            return;
        }

        $lastAbsentAt = Carbon::parse($lastAbsent)->setTimezone($timezone);
        abort_if(
            $lastAbsentAt->copy()->addDays($penalty['forbidDays'])->isFuture(),
            409,
            'CARD_BOOKING_FORBIDDEN_BY_ABSENCE',
        );
    }

    private function activeAppointmentsQuery(MemberCard $card)
    {
        return Appointment::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted]);
    }
}
