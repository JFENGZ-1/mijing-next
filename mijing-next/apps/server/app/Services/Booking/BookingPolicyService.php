<?php

namespace App\Services\Booking;

use App\Enums\ScheduleSessionKind;
use App\Models\BookingPolicy;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class BookingPolicyService
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultPolicy(): array
    {
        return [
            'group' => [
                'signMinutesBeforeStart' => 30,
                'autoCheckInMinutesAfterEnd' => 5,
                'advanceBookingDays' => 7,
                'advanceBookingDailyCutoffHour' => 0,
                'advanceBookingDailyCutoffMinute' => 0,
                'bookingCutoffMinutesBeforeStart' => 60,
                'cancelCutoffMinutesBeforeStart' => 120,
                'waitlistEnabled' => true,
                'showBookedCount' => true,
                'autoCancelUnderMinStudentsEnabled' => false,
                'autoCancelUnderMinStudentsMinutesBeforeStart' => 180,
                'calendarDisplayDays' => 7,
                'absentPenaltyEnabled' => false,
                'maxBookingsPerDay' => null,
            ],
            'private' => [
                'advanceBookingDays' => 14,
                'minimumLeadMinutes' => 60,
                'cancelCutoffMinutesBeforeStart' => 120,
                'slotIntervalMinutes' => 30,
                'preparationMinutes' => 0,
                'grayOutBookedSlots' => true,
                'groupConflictMode' => 'block',
                'absentPenaltyEnabled' => false,
                'maxBookingsPerDay' => null,
            ],
            'rules' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function policyForSite(Staff $staff, Site $site): array
    {
        return $this->policyForTenantSite($staff->tenant_id, $site->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function policyForTenantSite(int $tenantId, int $siteId): array
    {
        $stored = BookingPolicy::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->first();

        if (! $stored) {
            return $this->formatResponse(null, self::defaultPolicy());
        }

        return $this->formatResponse($stored);
    }

    public function assertMemberCatalogDateAllowed(Site $site, string $date, array $policy): void
    {
        $today = now($this->siteTimezone($site))->startOfDay();
        $requested = \Carbon\Carbon::parse($date, $this->siteTimezone($site))->startOfDay();
        $lastDisplayDay = $today->copy()->addDays((int) $policy['group']['calendarDisplayDays']);

        abort_if($requested->gt($lastDisplayDay), 422, 'BOOKING_DATE_OUT_OF_DISPLAY_WINDOW');
    }

    public function memberSessionBookableOnDate(Site $site, string $date, string $sessionKind, array $policy): bool
    {
        $timezone = $this->siteTimezone($site);
        $today = now($timezone)->startOfDay();
        $requested = \Carbon\Carbon::parse($date, $timezone)->startOfDay();
        $advanceDays = $sessionKind === ScheduleSessionKind::Private->value
            ? (int) $policy['private']['advanceBookingDays']
            : (int) $policy['group']['advanceBookingDays'];
        $effectiveAdvance = $this->effectiveAdvanceBookingDays($site, $advanceDays, $sessionKind, $policy);
        $lastBookableDay = $today->copy()->addDays($effectiveAdvance);

        return $requested->lte($lastBookableDay);
    }

    /**
     * 团课「提前预约」含每日刷新时刻：未到当日刷新点前，最远可约日少 1 天（对标 aheadAppointTime_team 带 param2）。
     */
    public function effectiveAdvanceBookingDays(Site $site, int $advanceDays, string $sessionKind, array $policy): int
    {
        $advanceDays = max(0, $advanceDays);
        if ($sessionKind !== ScheduleSessionKind::Group->value) {
            return $advanceDays;
        }

        $timezone = $this->siteTimezone($site);
        $now = now($timezone);
        $hour = (int) ($policy['group']['advanceBookingDailyCutoffHour'] ?? 0);
        $minute = (int) ($policy['group']['advanceBookingDailyCutoffMinute'] ?? 0);
        $cutoff = $now->copy()->startOfDay()->setTime($hour, $minute, 0);

        if ($now->lt($cutoff)) {
            return max(0, $advanceDays - 1);
        }

        return $advanceDays;
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public function assertMemberDailyBookingQuota(
        Site $site,
        int $memberId,
        ScheduleSession $session,
        array $policy,
    ): void {
        $limit = $session->session_kind === ScheduleSessionKind::Group
            ? $policy['group']['maxBookingsPerDay'] ?? null
            : $policy['private']['maxBookingsPerDay'] ?? null;

        if ($limit === null) {
            return;
        }

        $timezone = $this->siteTimezone($site);
        $dayStart = $session->starts_at->copy()->timezone($timezone)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();

        $count = \App\Models\Appointment::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('member_id', $memberId)
            ->whereIn('status', [
                \App\Enums\AppointmentStatus::Confirmed,
                \App\Enums\AppointmentStatus::Waitlisted,
            ])
            ->whereHas('session', function ($query) use ($session, $dayStart, $dayEnd) {
                $query->where('session_kind', $session->session_kind)
                    ->whereBetween('starts_at', [$dayStart, $dayEnd]);
            })
            ->count();

        abort_if($count >= (int) $limit, 422, 'BOOKING_DAILY_LIMIT_REACHED');
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public function assertBookingAllowed(Site $site, ScheduleSession $session, array $policy): void
    {
        $now = now($this->siteTimezone($site));
        $startsAt = $session->starts_at->timezone($this->siteTimezone($site));

        if ($session->session_kind === ScheduleSessionKind::Group) {
            $cutoffMinutes = (int) $policy['group']['bookingCutoffMinutesBeforeStart'];
            abort_if($now->gte($startsAt->copy()->subMinutes($cutoffMinutes)), 422, 'BOOKING_CUTOFF_PASSED');

            return;
        }

        $minimumLead = (int) $policy['private']['minimumLeadMinutes'];
        abort_if($now->gte($startsAt->copy()->subMinutes($minimumLead)), 422, 'BOOKING_CUTOFF_PASSED');
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public function assertCancellationAllowed(Site $site, ScheduleSession $session, array $policy, bool $staffOverride = false): void
    {
        if ($staffOverride) {
            return;
        }

        $now = now($this->siteTimezone($site));
        $startsAt = $session->starts_at->timezone($this->siteTimezone($site));
        $cutoffMinutes = $session->session_kind === ScheduleSessionKind::Group
            ? (int) $policy['group']['cancelCutoffMinutesBeforeStart']
            : (int) $policy['private']['cancelCutoffMinutesBeforeStart'];

        abort_if($now->gte($startsAt->copy()->subMinutes($cutoffMinutes)), 422, 'BOOKING_CANCEL_CUTOFF_PASSED');
    }

    private function siteTimezone(Site $site): string
    {
        return $site->timezone ?: (string) config('app.timezone');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function savePolicy(Staff $staff, Site $site, array $payload): array
    {
        $existing = BookingPolicy::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        $expectedVersion = (int) $payload['version'];
        $normalized = $this->normalizePolicy($payload);

        if ($existing) {
            abort_unless($existing->version === $expectedVersion, 409, 'BOOKING_POLICY_VERSION_CONFLICT');

            $updated = BookingPolicy::query()
                ->whereKey($existing->id)
                ->where('tenant_id', $staff->tenant_id)
                ->where('version', $expectedVersion)
                ->update([
                    'policy' => [
                        'group' => $normalized['group'],
                        'private' => $normalized['private'],
                    ],
                    'rules' => $normalized['rules'],
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            abort_if($updated !== 1, 409, 'BOOKING_POLICY_VERSION_CONFLICT');

            return $this->formatResponse($existing->fresh());
        }

        abort_unless($expectedVersion === 0, 409, 'BOOKING_POLICY_VERSION_CONFLICT');

        $created = BookingPolicy::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => $normalized['group'],
                'private' => $normalized['private'],
            ],
            'rules' => $normalized['rules'],
        ]);

        return $this->formatResponse($created);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{group: array<string, mixed>, private: array<string, mixed>, rules: array<string, mixed>}
     */
    private function normalizePolicy(array $payload): array
    {
        $defaults = self::defaultPolicy();

        return [
            'group' => $this->normalizeGroup($payload['group'] ?? [], $defaults['group']),
            'private' => $this->normalizePrivate($payload['private'] ?? [], $defaults['private']),
            'rules' => is_array($payload['rules'] ?? null) ? $payload['rules'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeGroup(array $group, array $defaults): array
    {
        $autoCancelEnabled = (bool) ($group['autoCancelUnderMinStudentsEnabled'] ?? $defaults['autoCancelUnderMinStudentsEnabled']);
        $autoCancelMinutes = (int) ($group['autoCancelUnderMinStudentsMinutesBeforeStart']
            ?? $defaults['autoCancelUnderMinStudentsMinutesBeforeStart']);

        return [
            'signMinutesBeforeStart' => max(0, (int) ($group['signMinutesBeforeStart'] ?? $defaults['signMinutesBeforeStart'])),
            'autoCheckInMinutesAfterEnd' => max(1, (int) ($group['autoCheckInMinutesAfterEnd'] ?? $defaults['autoCheckInMinutesAfterEnd'])),
            'advanceBookingDays' => max(0, (int) ($group['advanceBookingDays'] ?? $defaults['advanceBookingDays'])),
            'advanceBookingDailyCutoffHour' => min(23, max(0, (int) ($group['advanceBookingDailyCutoffHour'] ?? $defaults['advanceBookingDailyCutoffHour']))),
            'advanceBookingDailyCutoffMinute' => min(59, max(0, (int) ($group['advanceBookingDailyCutoffMinute'] ?? $defaults['advanceBookingDailyCutoffMinute']))),
            'bookingCutoffMinutesBeforeStart' => max(0, (int) ($group['bookingCutoffMinutesBeforeStart'] ?? $defaults['bookingCutoffMinutesBeforeStart'])),
            'cancelCutoffMinutesBeforeStart' => max(0, (int) ($group['cancelCutoffMinutesBeforeStart'] ?? $defaults['cancelCutoffMinutesBeforeStart'])),
            'waitlistEnabled' => (bool) ($group['waitlistEnabled'] ?? $defaults['waitlistEnabled']),
            'showBookedCount' => (bool) ($group['showBookedCount'] ?? $defaults['showBookedCount']),
            'autoCancelUnderMinStudentsEnabled' => $autoCancelEnabled,
            'autoCancelUnderMinStudentsMinutesBeforeStart' => $autoCancelEnabled
                ? min(180, max(0, $autoCancelMinutes))
                : max(0, $autoCancelMinutes),
            'calendarDisplayDays' => max(1, (int) ($group['calendarDisplayDays'] ?? $defaults['calendarDisplayDays'])),
            'absentPenaltyEnabled' => (bool) ($group['absentPenaltyEnabled'] ?? $defaults['absentPenaltyEnabled']),
            'maxBookingsPerDay' => $this->normalizeNullablePositiveInt($group['maxBookingsPerDay'] ?? $defaults['maxBookingsPerDay']),
        ];
    }

    /**
     * @param  array<string, mixed>  $private
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizePrivate(array $private, array $defaults): array
    {
        $mode = (string) ($private['groupConflictMode'] ?? $defaults['groupConflictMode']);

        return [
            'advanceBookingDays' => max(0, (int) ($private['advanceBookingDays'] ?? $defaults['advanceBookingDays'])),
            'minimumLeadMinutes' => max(0, (int) ($private['minimumLeadMinutes'] ?? $defaults['minimumLeadMinutes'])),
            'cancelCutoffMinutesBeforeStart' => max(0, (int) ($private['cancelCutoffMinutesBeforeStart'] ?? $defaults['cancelCutoffMinutesBeforeStart'])),
            'slotIntervalMinutes' => max(5, (int) ($private['slotIntervalMinutes'] ?? $defaults['slotIntervalMinutes'])),
            'preparationMinutes' => max(0, (int) ($private['preparationMinutes'] ?? $defaults['preparationMinutes'])),
            'grayOutBookedSlots' => (bool) ($private['grayOutBookedSlots'] ?? $defaults['grayOutBookedSlots']),
            'groupConflictMode' => in_array($mode, ['block', 'allow', 'overlap_warn'], true) ? $mode : $defaults['groupConflictMode'],
            'absentPenaltyEnabled' => (bool) ($private['absentPenaltyEnabled'] ?? $defaults['absentPenaltyEnabled']),
            'maxBookingsPerDay' => $this->normalizeNullablePositiveInt($private['maxBookingsPerDay'] ?? $defaults['maxBookingsPerDay']),
        ];
    }

    private function normalizeNullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    /**
     * @param  array<string, mixed>|null  $defaults
     * @return array<string, mixed>
     */
    private function formatResponse(?BookingPolicy $stored, ?array $defaults = null): array
    {
        if (! $stored) {
            $policy = $defaults ?? self::defaultPolicy();

            return [
                'version' => 0,
                'group' => $policy['group'],
                'private' => $policy['private'],
                'rules' => $policy['rules'],
                'updatedAt' => null,
            ];
        }

        $policy = $this->normalizePolicy([
            'group' => $stored->policy['group'] ?? [],
            'private' => $stored->policy['private'] ?? [],
            'rules' => $stored->rules ?? [],
        ]);

        return [
            'version' => $stored->version,
            'group' => $policy['group'],
            'private' => $policy['private'],
            'rules' => $policy['rules'],
            'updatedAt' => $stored->updated_at?->toIso8601String(),
        ];
    }
}
