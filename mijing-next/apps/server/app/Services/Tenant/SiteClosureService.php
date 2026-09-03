<?php

namespace App\Services\Tenant;

use App\Models\Site;
use App\Models\SiteClosurePeriod;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SiteClosureService
{
    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     summary: array{total: int, completed: int}
     * }
     */
    public function list(Site $site): array
    {
        $items = SiteClosurePeriod::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('begin_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SiteClosurePeriod $period) => $this->present($period))
            ->values()
            ->all();

        return [
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'completed' => collect($items)->where('lifecycleStatus', 'completed')->count(),
            ],
        ];
    }

    /**
     * @param  array{reason?: ?string, beginDate: string, endDate: string}  $payload
     * @return array<string, mixed>
     */
    public function create(Site $site, Staff $actor, array $payload): array
    {
        $beginDate = Carbon::parse($payload['beginDate'])->startOfDay();
        $endDate = Carbon::parse($payload['endDate'])->startOfDay();
        abort_if($endDate->lt($beginDate), 422, 'VALIDATION_FAILED');

        $period = SiteClosurePeriod::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'reason' => $payload['reason'] ?? null,
            'begin_date' => $beginDate,
            'end_date' => $endDate,
            'status' => $this->lifecycleStatus($beginDate, $endDate),
            'created_by_staff_id' => $actor->id,
        ]);

        return $this->present($period);
    }

    /**
     * @param  array{reason?: ?string, beginDate?: string, endDate?: string, status?: string}  $payload
     * @return array<string, mixed>
     */
    public function update(SiteClosurePeriod $period, array $payload): array
    {
        abort_if($period->status === 'cancelled', 409, 'CONFLICT');

        $beginDate = isset($payload['beginDate'])
            ? Carbon::parse($payload['beginDate'])->startOfDay()
            : $period->begin_date;
        $endDate = isset($payload['endDate'])
            ? Carbon::parse($payload['endDate'])->startOfDay()
            : $period->end_date;
        abort_if($endDate->lt($beginDate), 422, 'VALIDATION_FAILED');

        $period->fill([
            'reason' => array_key_exists('reason', $payload) ? $payload['reason'] : $period->reason,
            'begin_date' => $beginDate,
            'end_date' => $endDate,
            'status' => $payload['status'] ?? $this->lifecycleStatus($beginDate, $endDate),
        ])->save();

        return $this->present($period->fresh());
    }

    public function findForSite(Site $site, int $closureId): SiteClosurePeriod
    {
        return SiteClosurePeriod::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($closureId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SiteClosurePeriod $period): array
    {
        $period->loadMissing('createdBy');
        $lifecycleStatus = $period->status === 'cancelled'
            ? 'cancelled'
            : $this->lifecycleStatus($period->begin_date, $period->end_date);

        return [
            'id' => $period->id,
            'reason' => $period->reason,
            'beginDate' => $period->begin_date?->toDateString(),
            'endDate' => $period->end_date?->toDateString(),
            'lifecycleStatus' => $lifecycleStatus,
            'createdBy' => $period->createdBy ? [
                'id' => $period->createdBy->id,
                'displayName' => $period->createdBy->name,
            ] : null,
            'createdAt' => $period->created_at?->toIso8601String(),
        ];
    }

    private function lifecycleStatus(Carbon $beginDate, Carbon $endDate): string
    {
        $today = now()->startOfDay();

        if ($endDate->lt($today)) {
            return 'completed';
        }

        if ($beginDate->lte($today) && $endDate->gte($today)) {
            return 'active';
        }

        return 'scheduled';
    }
}
