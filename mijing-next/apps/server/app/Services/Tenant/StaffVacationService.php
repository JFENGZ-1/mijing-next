<?php

namespace App\Services\Tenant;

use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffVacation;
use Carbon\Carbon;

class StaffVacationService
{
    private const GROUP_POLICY_LABELS = [
        'allow' => '团课可约',
        'block' => '团课不可约',
    ];

    private const PRIVATE_POLICY_LABELS = [
        'allow' => '私教可约',
        'block' => '私教不可约',
    ];

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function rollup(Site $site): array
    {
        $staffMembers = $site->staff()
            ->where('staff.tenant_id', $site->tenant_id)
            ->where('staff.status', 'active')
            ->with(['sites' => fn ($query) => $query->whereKey($site->id)])
            ->get()
            ->filter(fn (Staff $staff) => $this->isCoach($staff, $site->id))
            ->values();

        $vacations = StaffVacation::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('begin_at')
            ->get()
            ->groupBy('staff_id');

        return [
            'items' => $staffMembers
                ->map(function (Staff $staff) use ($vacations) {
                    $entries = ($vacations->get($staff->id) ?? collect())
                        ->map(fn (StaffVacation $vacation) => $this->present($vacation))
                        ->values()
                        ->all();

                    return [
                        'staff' => $this->staffSummary($staff),
                        'vacations' => $entries,
                        'activeCount' => collect($entries)->where('lifecycleStatus', 'active')->count(),
                        'upcomingCount' => collect($entries)->where('lifecycleStatus', 'scheduled')->count(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{staff: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function forStaff(Site $site, Staff $staffMember): array
    {
        $this->assertSiteStaff($site, $staffMember);

        $items = StaffVacation::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('staff_id', $staffMember->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('begin_at')
            ->get()
            ->map(fn (StaffVacation $vacation) => $this->present($vacation))
            ->values()
            ->all();

        return [
            'staff' => $this->staffSummary($staffMember),
            'items' => $items,
        ];
    }

    /**
     * @param  array{
     *     beginAt: string,
     *     endAt: string,
     *     groupBookingPolicy?: string,
     *     privateBookingPolicy?: string,
     *     remark?: ?string
     * }  $payload
     * @return array<string, mixed>
     */
    public function create(Site $site, Staff $staffMember, Staff $actor, array $payload): array
    {
        $this->assertSiteStaff($site, $staffMember);

        $beginAt = Carbon::parse($payload['beginAt']);
        $endAt = Carbon::parse($payload['endAt']);
        abort_if($endAt->lte($beginAt), 422, 'VALIDATION_FAILED');

        $vacation = StaffVacation::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'staff_id' => $staffMember->id,
            'begin_at' => $beginAt,
            'end_at' => $endAt,
            'group_booking_policy' => $payload['groupBookingPolicy'] ?? 'block',
            'private_booking_policy' => $payload['privateBookingPolicy'] ?? 'allow',
            'status' => $this->lifecycleStatus($beginAt, $endAt),
            'remark' => $payload['remark'] ?? null,
            'created_by_staff_id' => $actor->id,
        ]);

        return $this->present($vacation);
    }

    /**
     * @param  array{
     *     beginAt?: string,
     *     endAt?: string,
     *     groupBookingPolicy?: string,
     *     privateBookingPolicy?: string,
     *     remark?: ?string,
     *     status?: string
     * }  $payload
     * @return array<string, mixed>
     */
    public function update(StaffVacation $vacation, array $payload): array
    {
        abort_if($vacation->status === 'cancelled', 409, 'CONFLICT');

        $beginAt = isset($payload['beginAt']) ? Carbon::parse($payload['beginAt']) : $vacation->begin_at;
        $endAt = isset($payload['endAt']) ? Carbon::parse($payload['endAt']) : $vacation->end_at;
        abort_if($endAt->lte($beginAt), 422, 'VALIDATION_FAILED');

        $vacation->fill([
            'begin_at' => $beginAt,
            'end_at' => $endAt,
            'group_booking_policy' => $payload['groupBookingPolicy'] ?? $vacation->group_booking_policy,
            'private_booking_policy' => $payload['privateBookingPolicy'] ?? $vacation->private_booking_policy,
            'remark' => array_key_exists('remark', $payload) ? $payload['remark'] : $vacation->remark,
            'status' => $payload['status'] ?? $this->lifecycleStatus($beginAt, $endAt),
        ])->save();

        return $this->present($vacation->fresh());
    }

    public function findForStaff(Site $site, Staff $staffMember, int $vacationId): StaffVacation
    {
        $this->assertSiteStaff($site, $staffMember);

        return StaffVacation::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('staff_id', $staffMember->id)
            ->whereKey($vacationId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(StaffVacation $vacation): array
    {
        $lifecycleStatus = $vacation->status === 'cancelled'
            ? 'cancelled'
            : $this->lifecycleStatus($vacation->begin_at, $vacation->end_at);

        return [
            'id' => $vacation->id,
            'staffId' => $vacation->staff_id,
            'beginAt' => $vacation->begin_at?->toIso8601String(),
            'endAt' => $vacation->end_at?->toIso8601String(),
            'groupBookingPolicy' => $vacation->group_booking_policy,
            'groupBookingPolicyLabel' => self::GROUP_POLICY_LABELS[$vacation->group_booking_policy] ?? $vacation->group_booking_policy,
            'privateBookingPolicy' => $vacation->private_booking_policy,
            'privateBookingPolicyLabel' => self::PRIVATE_POLICY_LABELS[$vacation->private_booking_policy] ?? $vacation->private_booking_policy,
            'remark' => $vacation->remark,
            'lifecycleStatus' => $lifecycleStatus,
            'createdAt' => $vacation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, displayName: string}
     */
    private function staffSummary(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'displayName' => $staff->name,
        ];
    }

    private function assertSiteStaff(Site $site, Staff $staffMember): void
    {
        abort_unless(
            $staffMember->tenant_id === $site->tenant_id
            && $staffMember->sites()->whereKey($site->id)->exists(),
            404,
        );
    }

    private function isCoach(Staff $staff, int $siteId): bool
    {
        $pivot = $staff->sites->firstWhere('id', $siteId)?->pivot;
        $capabilities = is_string($pivot?->capabilities)
            ? json_decode($pivot->capabilities, true)
            : ($pivot?->capabilities ?? []);

        return in_array('coach', $capabilities ?? [], true);
    }

    private function lifecycleStatus(Carbon $beginAt, Carbon $endAt): string
    {
        $now = now();

        if ($endAt->lt($now)) {
            return 'completed';
        }

        if ($beginAt->lte($now) && $endAt->gte($now)) {
            return 'active';
        }

        return 'scheduled';
    }
}
