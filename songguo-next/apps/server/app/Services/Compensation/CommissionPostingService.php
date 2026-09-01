<?php

namespace App\Services\Compensation;

use App\Models\CommissionSettlementLine;
use App\Models\ConsumptionEvent;
use App\Models\CourseCompensationRule;
use App\Models\PayrollPeriod;
use App\Models\PeriodDayBucketRevision;

class CommissionPostingService
{
    public function __construct(private PayrollPeriodService $periods) {}

    public function postEventTarget(
        ConsumptionEvent $event,
        int $staffId,
        ?int $roleId,
        string $component,
        int $targetAmountCents,
        int $baseValueCents,
        ?int $rateBps,
        ?int $allocationBps,
        ?CourseCompensationRule $rule,
        ?PeriodDayBucketRevision $revision = null,
    ): ?CommissionSettlementLine {
        [$businessPeriod, $postingPeriod, $postClose] = $this->postingContext($event);
        $lines = CommissionSettlementLine::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('consumption_event_id', $event->id)
            ->where('staff_id', $staffId)
            ->where('component', $component)
            ->when($roleId === null, fn ($query) => $query->whereNull('compensation_role_id'))
            ->when($roleId !== null, fn ($query) => $query->where('compensation_role_id', $roleId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $this->appendDelta(
            $event,
            $lines,
            $staffId,
            $roleId,
            $component,
            $targetAmountCents,
            $baseValueCents,
            $rateBps,
            $allocationBps,
            $rule,
            $revision,
            $businessPeriod,
            $postingPeriod,
            $postClose,
        );
    }

    public function postSessionTargetOnce(
        ConsumptionEvent $event,
        int $staffId,
        ?int $roleId,
        int $targetAmountCents,
        CourseCompensationRule $rule,
        int $allocationBps,
    ): ?CommissionSettlementLine {
        [$businessPeriod, $postingPeriod, $postClose] = $this->postingContext($event);
        $eventIds = ConsumptionEvent::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('session_id', $event->session_id)
            ->pluck('id');
        $lines = CommissionSettlementLine::query()
            ->where('tenant_id', $event->tenant_id)
            ->whereIn('consumption_event_id', $eventIds)
            ->where('staff_id', $staffId)
            ->where('component', 'session_fee')
            ->when($roleId === null, fn ($query) => $query->whereNull('compensation_role_id'))
            ->when($roleId !== null, fn ($query) => $query->where('compensation_role_id', $roleId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $this->appendDelta(
            $event,
            $lines,
            $staffId,
            $roleId,
            'session_fee',
            $targetAmountCents,
            $rule->session_fee_cents,
            null,
            $allocationBps,
            $rule,
            null,
            $businessPeriod,
            $postingPeriod,
            $postClose,
        );
    }

    private function appendDelta(
        ConsumptionEvent $event,
        $lines,
        int $staffId,
        ?int $roleId,
        string $component,
        int $targetAmountCents,
        int $baseValueCents,
        ?int $rateBps,
        ?int $allocationBps,
        ?CourseCompensationRule $rule,
        ?PeriodDayBucketRevision $revision,
        ?PayrollPeriod $businessPeriod,
        ?PayrollPeriod $postingPeriod,
        bool $postClose,
    ): ?CommissionSettlementLine {
        $current = (int) $lines->sum('amount_cents');
        $delta = $targetAmountCents - $current;
        if ($delta === 0) {
            return null;
        }

        $lineType = $current === 0 && $delta > 0
            ? 'accrual'
            : ($targetAmountCents === 0 && $delta < 0 ? 'reversal' : 'adjustment');
        $lastPositive = $lineType === 'reversal'
            ? $lines->reverse()->first(fn ($line) => $line->amount_cents > 0)
            : null;

        return CommissionSettlementLine::create([
            'tenant_id' => $event->tenant_id,
            'site_id' => $event->site_id,
            'consumption_event_id' => $event->id,
            'period_day_bucket_revision_id' => $revision?->id,
            'staff_id' => $staffId,
            'compensation_role_id' => $roleId,
            'course_compensation_rule_id' => $rule?->id,
            'payroll_period_id' => $postingPeriod?->id,
            'component' => $component,
            'line_type' => $lineType,
            'base_value_cents' => $baseValueCents,
            'rate_bps' => $rateBps,
            'allocation_bps' => $allocationBps,
            'amount_cents' => $delta,
            'reverses_line_id' => $lastPositive?->id,
            'command_key' => implode(':', [
                'commission', $event->id, $component, $staffId, $roleId ?? 0, $lines->count() + 1,
            ]),
            'metadata' => [
                'targetAmountCents' => $targetAmountCents,
                'previousAmountCents' => $current,
                'postCloseAdjustment' => $postClose,
                'originalPayrollPeriodId' => $postClose ? $businessPeriod?->id : null,
                'adjustmentAllocation' => $postClose
                    ? ($postingPeriod === null ? 'pending_review' : 'next_open_period')
                    : null,
                'legacySalesCommissionSeparate' => true,
            ],
            'occurred_at' => now(),
        ]);
    }

    /**
     * Lock order is site -> payroll period -> settlement lines. Keeping the
     * context acquisition before every line lock prevents create/close from
     * deadlocking with a concurrent posting command.
     *
     * @return array{0: ?PayrollPeriod, 1: ?PayrollPeriod, 2: bool}
     */
    private function postingContext(ConsumptionEvent $event): array
    {
        $businessPeriod = $this->periods->forPostingDate(
            (int) $event->tenant_id,
            (int) $event->site_id,
            $event->business_date->toDateString(),
        );
        $postClose = $businessPeriod?->status === 'closed';
        $postingPeriod = $postClose
            ? $this->periods->openAdjustmentPeriodAfter($businessPeriod, lock: true)
            : $businessPeriod;

        return [$businessPeriod, $postingPeriod, $postClose];
    }
}
