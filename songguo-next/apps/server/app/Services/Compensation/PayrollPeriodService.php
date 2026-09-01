<?php

namespace App\Services\Compensation;

use App\Models\CommissionSettlementLine;
use App\Models\ConsumptionEvent;
use App\Models\PayrollPeriod;
use App\Models\PeriodDayBucket;
use App\Models\Site;
use App\Support\DomainActor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PayrollPeriodService
{
    public function queryForSite(int $tenantId, int $siteId): Builder
    {
        return PayrollPeriod::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
    }

    public function closeState(Site $site, PayrollPeriod $period): array
    {
        abort_unless($period->tenant_id === $site->tenant_id && $period->site_id === $site->id, 404);
        $today = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
        $openBuckets = PeriodDayBucket::query()
            ->where('tenant_id', $period->tenant_id)->where('site_id', $period->site_id)
            ->whereBetween('business_date', [$period->starts_on, $period->ends_on])
            ->where('status', 'open')->count();
        $notEnded = $period->ends_on->toDateString() >= $today;
        $canClose = $period->status === 'open' && ! $notEnded && $openBuckets === 0;

        return [
            'canClose' => $canClose,
            'pendingCount' => $openBuckets,
            'blockedReason' => $period->status === 'closed' || $canClose
                ? null
                : ($notEnded ? 'PAYROLL_PERIOD_NOT_ENDED' : 'PAYROLL_PERIOD_BUCKETS_OPEN'),
        ];
    }

    public function metrics(PayrollPeriod $period): array
    {
        if ($period->status === 'closed' && is_array($period->metrics_snapshot)) {
            return $period->metrics_snapshot;
        }

        return $this->computeMetrics($period);
    }

    private function computeMetrics(PayrollPeriod $period): array
    {
        $events = ConsumptionEvent::query()
            ->where('tenant_id', $period->tenant_id)->where('site_id', $period->site_id)
            ->whereBetween('business_date', [$period->starts_on, $period->ends_on]);
        $eventRow = (clone $events)->selectRaw(
            "COUNT(CASE WHEN status <> 'reversed' THEN 1 END) AS settlement_count, COALESCE(SUM(CASE WHEN status <> 'reversed' THEN consumed_value_cents ELSE 0 END), 0) AS consumed_value_cents",
        )->first();
        $lineRow = CommissionSettlementLine::query()
            ->join('consumption_events', 'consumption_events.id', '=', 'commission_settlement_lines.consumption_event_id')
            ->where('commission_settlement_lines.tenant_id', $period->tenant_id)
            ->where('commission_settlement_lines.site_id', $period->site_id)
            ->where(function ($query) use ($period) {
                $query->where('commission_settlement_lines.payroll_period_id', $period->id)
                    ->orWhere(function ($unassigned) use ($period) {
                        $unassigned->whereNull('commission_settlement_lines.payroll_period_id')
                            ->whereBetween('consumption_events.business_date', [$period->starts_on, $period->ends_on])
                            ->whereRaw("COALESCE(JSON_EXTRACT(commission_settlement_lines.metadata, '$.postCloseAdjustment'), false) = false");
                    });
            })
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN component = 'session_fee' THEN amount_cents ELSE 0 END), 0) AS session_fee_cents, ".
                "COALESCE(SUM(CASE WHEN component = 'consumption_commission' THEN amount_cents ELSE 0 END), 0) AS commission_cents, ".
                "COALESCE(SUM(CASE WHEN JSON_EXTRACT(commission_settlement_lines.metadata, '$.postCloseAdjustment') = true THEN amount_cents ELSE 0 END), 0) AS adjustment_amount_cents",
            )->first();

        return [
            'settlementCount' => (int) ($eventRow?->settlement_count ?? 0),
            'consumedValueCents' => (int) ($eventRow?->consumed_value_cents ?? 0),
            'sessionFeeCents' => (int) ($lineRow?->session_fee_cents ?? 0),
            'commissionCents' => (int) ($lineRow?->commission_cents ?? 0),
            'adjustmentAmountCents' => (int) ($lineRow?->adjustment_amount_cents ?? 0),
        ];
    }

    public function create(DomainActor $actor, Site $site, array $payload): PayrollPeriod
    {
        abort_if($payload['endsOn'] < $payload['startsOn'], 422, 'PAYROLL_PERIOD_RANGE_INVALID');
        $commandKey = $payload['commandKey'] ?? null;

        return DB::transaction(function () use ($actor, $site, $payload, $commandKey) {
            Site::query()
                ->where('tenant_id', $site->tenant_id)
                ->whereKey($site->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($commandKey !== null) {
                $existing = PayrollPeriod::query()
                    ->where('tenant_id', $site->tenant_id)
                    ->where('create_command_key', $commandKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    abort_unless(
                        $existing->site_id === $site->id
                        && $existing->starts_on->toDateString() === $payload['startsOn']
                        && $existing->ends_on->toDateString() === $payload['endsOn']
                        && $existing->create_reason === ($payload['reason'] ?? null),
                        409,
                        'IDEMPOTENCY_KEY_REUSED',
                    );

                    return $existing;
                }
            }
            $overlap = $this->queryForSite($site->tenant_id, $site->id)
                ->where('starts_on', '<=', $payload['endsOn'])
                ->where('ends_on', '>=', $payload['startsOn'])
                ->exists();
            abort_if($overlap, 409, 'PAYROLL_PERIOD_OVERLAP');

            $period = PayrollPeriod::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'starts_on' => $payload['startsOn'],
                'ends_on' => $payload['endsOn'],
                'status' => 'open',
                'version' => 1,
                'create_command_key' => $commandKey,
                'create_reason' => $payload['reason'] ?? null,
                'created_by_type' => $actor->type,
                'created_by_id' => $actor->id,
                'created_by_staff_id' => $actor->staffId(),
            ]);
            $this->assignUnallocatedNormalLines($period);
            $this->assignPendingPostCloseAdjustments($period);

            return $period;
        });
    }

    public function close(
        DomainActor $actor,
        Site $site,
        PayrollPeriod $period,
        int $version,
        array $payload = [],
    ): PayrollPeriod {
        abort_unless($period->tenant_id === $site->tenant_id && $period->site_id === $site->id, 404);

        $localToday = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
        abort_unless($period->ends_on->toDateString() < $localToday, 409, 'PAYROLL_PERIOD_NOT_ENDED');
        app(ConsumptionSettlementService::class)->finalizeDue($site, now(), 0);

        return DB::transaction(function () use ($actor, $site, $period, $version, $payload) {
            // Global payroll/posting barrier. Every consumption or commission write
            // for this site takes the same site row before touching financial facts.
            // This makes the close snapshot a real serialization point.
            $this->acquireSiteBarrier((int) $site->tenant_id, (int) $site->id);
            $commandKey = $payload['commandKey'] ?? null;
            if ($commandKey !== null) {
                $commandPeriod = PayrollPeriod::query()
                    ->where('tenant_id', $period->tenant_id)
                    ->where('close_command_key', $commandKey)
                    ->lockForUpdate()
                    ->first();
                if ($commandPeriod) {
                    abort_unless(
                        $commandPeriod->id === $period->id
                        && $commandPeriod->close_reason === ($payload['reason'] ?? null)
                        && $commandPeriod->version === $version + 1
                        && $commandPeriod->closed_by_type === $actor->type
                        && $commandPeriod->closed_by_id === $actor->id,
                        409,
                        'IDEMPOTENCY_KEY_REUSED',
                    );

                    return $commandPeriod;
                }
            }
            $locked = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'closed') {
                abort_if($commandKey !== null, 409, 'PAYROLL_PERIOD_ALREADY_CLOSED');

                return $locked;
            }
            abort_unless($locked->version === $version, 409, 'PAYROLL_PERIOD_VERSION_CONFLICT');
            abort_if(PeriodDayBucket::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('site_id', $locked->site_id)
                ->whereBetween('business_date', [$locked->starts_on, $locked->ends_on])
                ->where('status', 'open')
                ->exists(), 409, 'PAYROLL_PERIOD_BUCKETS_OPEN');

            $metricsSnapshot = $this->computeMetrics($locked);

            $locked->update([
                'status' => 'closed',
                'version' => $locked->version + 1,
                'close_command_key' => $commandKey,
                'close_reason' => $payload['reason'] ?? null,
                'closed_by_type' => $actor->type,
                'closed_by_id' => $actor->id,
                'closed_by_staff_id' => $actor->staffId(),
                'closed_at' => now(),
                'metrics_snapshot' => $metricsSnapshot,
                'metrics_snapshotted_at' => now(),
            ]);
            PeriodDayBucket::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('site_id', $locked->site_id)
                ->whereBetween('business_date', [$locked->starts_on, $locked->ends_on])
                ->update(['status' => 'closed', 'closed_at' => now()]);
            ConsumptionEvent::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('site_id', $locked->site_id)
                ->whereBetween('business_date', [$locked->starts_on, $locked->ends_on])
                ->where('status', 'provisional')
                ->update(['status' => 'final']);

            return $locked->fresh();
        });
    }

    public function forDate(int $tenantId, int $siteId, string $date): ?PayrollPeriod
    {
        return $this->queryForSite($tenantId, $siteId)
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Serialize payroll creation/closing with commission posting. The site row
     * is the barrier even before a period exists; a matching period is locked too.
     */
    public function forPostingDate(int $tenantId, int $siteId, string $date): ?PayrollPeriod
    {
        $this->acquireSiteBarrier($tenantId, $siteId);

        return $this->queryForSite($tenantId, $siteId)
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Acquire the site-scoped financial serialization barrier.
     *
     * Callers must be inside a database transaction. Re-acquiring the same row in
     * a nested domain command is safe and keeps the global order site -> facts.
     */
    public function acquireSiteBarrier(int $tenantId, int $siteId): Site
    {
        return Site::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($siteId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function openAdjustmentPeriodAfter(PayrollPeriod $closedPeriod, bool $lock = false): ?PayrollPeriod
    {
        $query = $this->queryForSite((int) $closedPeriod->tenant_id, (int) $closedPeriod->site_id)
            ->where('status', 'open')
            ->where('starts_on', '>', $closedPeriod->ends_on)
            ->orderBy('starts_on')
            ->orderBy('id');

        return $lock ? $query->lockForUpdate()->first() : $query->first();
    }

    private function assignPendingPostCloseAdjustments(PayrollPeriod $newPeriod): void
    {
        $pending = CommissionSettlementLine::query()
            ->where('tenant_id', $newPeriod->tenant_id)
            ->where('site_id', $newPeriod->site_id)
            ->whereNull('payroll_period_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (CommissionSettlementLine $line) => (bool) ($line->metadata['postCloseAdjustment'] ?? false));
        $eligibleIds = [];
        foreach ($pending as $line) {
            $originalId = (int) ($line->metadata['originalPayrollPeriodId'] ?? 0);
            if ($originalId < 1) {
                continue;
            }
            $original = PayrollPeriod::query()
                ->where('tenant_id', $newPeriod->tenant_id)
                ->where('site_id', $newPeriod->site_id)
                ->whereKey($originalId)
                ->first();
            if ($original === null || $original->status !== 'closed') {
                continue;
            }
            if ($this->openAdjustmentPeriodAfter($original)?->id === $newPeriod->id) {
                $eligibleIds[] = $line->id;
            }
        }
        if ($eligibleIds !== []) {
            // Assignment changes no financial amount and is the only mutable payroll
            // classification on an otherwise append-only settlement line.
            DB::table('commission_settlement_lines')
                ->whereIn('id', $eligibleIds)
                ->whereNull('payroll_period_id')
                ->update(['payroll_period_id' => $newPeriod->id]);
        }
    }

    private function assignUnallocatedNormalLines(PayrollPeriod $period): void
    {
        DB::table('commission_settlement_lines')
            ->join('consumption_events', 'consumption_events.id', '=', 'commission_settlement_lines.consumption_event_id')
            ->where('commission_settlement_lines.tenant_id', $period->tenant_id)
            ->where('commission_settlement_lines.site_id', $period->site_id)
            ->whereNull('commission_settlement_lines.payroll_period_id')
            ->whereBetween('consumption_events.business_date', [$period->starts_on, $period->ends_on])
            ->whereRaw("COALESCE(JSON_EXTRACT(commission_settlement_lines.metadata, '$.postCloseAdjustment'), false) = false")
            ->update(['commission_settlement_lines.payroll_period_id' => $period->id]);
    }

    public function present(PayrollPeriod $period): array
    {
        return [
            'id' => $period->id,
            'siteId' => $period->site_id,
            'startsOn' => $period->starts_on?->toDateString(),
            'endsOn' => $period->ends_on?->toDateString(),
            'status' => $period->status,
            'version' => $period->version,
            'createReason' => $period->create_reason,
            'closeReason' => $period->close_reason,
            'closedAt' => $period->closed_at?->toIso8601String(),
            'metricsSnapshottedAt' => $period->metrics_snapshotted_at?->toIso8601String(),
        ];
    }
}
