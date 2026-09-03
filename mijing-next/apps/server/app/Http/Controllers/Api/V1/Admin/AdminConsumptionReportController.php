<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\InteractsWithAdminBusinessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClosePayrollPeriodRequest;
use App\Http\Requests\Admin\ReverseConsumptionEventRequest;
use App\Http\Requests\Admin\StorePayrollPeriodRequest;
use App\Models\CommissionSettlementLine;
use App\Models\CompensationRole;
use App\Models\ConsumptionEvent;
use App\Models\CourseCompensationRule;
use App\Models\MemberCardValueLot;
use App\Models\PayrollPeriod;
use App\Models\PeriodDayBucket;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Compensation\ConsumptionReportQueryService;
use App\Services\Compensation\ConsumptionSettlementService;
use App\Services\Compensation\PayrollPeriodService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminConsumptionReportController extends Controller
{
    use InteractsWithAdminBusinessScope;

    public function __construct(
        private readonly PayrollPeriodService $periods,
        private readonly ConsumptionSettlementService $settlements,
        private readonly ConsumptionReportQueryService $reportQueries,
    ) {}

    public function reports(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'dimension' => ['required', 'in:delivery,share,member,course,card'],
            'status' => ['sometimes', 'nullable', 'in:provisional,final,adjusted'],
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        // "delivery" is the public Admin API term and maps to the shared
        // reporting service's legacy "coach" dimension. All allocation and
        // cent-conservation rules remain in the shared domain service.
        $domainDimension = $filters['dimension'] === 'delivery' ? 'coach' : $filters['dimension'];
        $paginator = $this->reportQueries->paginate(
            $tenant->id,
            $site->id,
            [...$filters, 'from' => $from, 'to' => $to],
            $domainDimension,
            (int) ($filters['perPage'] ?? 20),
        );

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(function ($row) use ($filters) {
                $presented = $this->reportQueries->present($row);

                return [
                    'key' => $filters['dimension'].':'.$presented['key'],
                    'subjectId' => $presented['key'],
                    'subjectName' => $presented['dimensionName'],
                    'roleType' => in_array($filters['dimension'], ['delivery', 'share'], true)
                        ? $filters['dimension']
                        : null,
                    'consumptionCount' => $presented['consumptionCount'],
                    'consumedAmountCents' => $presented['consumedValueCents'],
                    'sessionFeeCents' => $presented['sessionFeeCents'],
                    'commissionCents' => $presented['commissionCents'],
                    'totalCompensationCents' => $presented['sessionFeeCents'] + $presented['commissionCents'],
                    'formulaVersion' => null,
                ];
            })->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function events(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:provisional,final,reversed,adjusted'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = $this->settlements->queryForSite($tenant->id, $site->id, [
            'status' => $filters['status'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ])
            ->with([
                'member.crmProfile:id,member_id,name', 'member.account:id,display_name',
                'memberCard:id,card_no', 'course:id,name', 'session:id,starts_at',
                'lines.staff:id,name', 'lines.role:id,name,role_type',
            ])
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->whereHas('member', fn ($members) => $members
                    ->where('member_no', 'like', "%{$term}%")
                    ->orWhereHas('crmProfile', fn ($profiles) => $profiles->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('account', fn ($accounts) => $accounts->where('display_name', 'like', "%{$term}%")))
                    ->orWhereHas('memberCard', fn ($cards) => $cards->where('card_no', 'like', "%{$term}%"))
                    ->orWhereHas('course', fn ($courses) => $courses->where('name', 'like', "%{$term}%"));
            }))
            ->paginate($filters['perPage'] ?? 20);
        $items = collect($paginator->items());
        $allLines = $items->flatMap(fn (ConsumptionEvent $event) => $event->lines);
        $recipientSnapshots = $items->flatMap(fn (ConsumptionEvent $event) => [
            ...($event->metadata['deliveryRecipients'] ?? []),
            ...($event->metadata['shareRecipients'] ?? []),
        ]);
        $staff = Staff::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $allLines->pluck('staff_id')->merge($recipientSnapshots->pluck('staffId'))->filter()->unique())
            ->get(['id', 'name'])
            ->keyBy('id');
        $roles = CompensationRole::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $allLines->pluck('compensation_role_id')->merge($recipientSnapshots->pluck('compensationRoleId'))->filter()->unique())
            ->get(['id', 'name', 'role_type'])
            ->keyBy('id');
        $valueLots = MemberCardValueLot::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $items->pluck('value_lot_id')->filter())
            ->get()
            ->keyBy('id');
        $rules = CourseCompensationRule::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $items->pluck('course_compensation_rule_id')->filter())
            ->get()
            ->keyBy('id');
        $buckets = PeriodDayBucket::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->whereIn('member_card_id', $items->pluck('member_card_id')->unique())
            ->whereIn('business_date', $items->pluck('business_date')->map->toDateString()->unique())
            ->get()
            ->keyBy(fn (PeriodDayBucket $bucket) => $bucket->member_card_id.'|'.$bucket->business_date->toDateString());

        return ApiResponse::success([
            'items' => $items->map(function (ConsumptionEvent $event) use ($valueLots, $rules, $buckets, $staff, $roles) {
                $lot = $valueLots->get($event->value_lot_id);
                $rule = $rules->get($event->course_compensation_rule_id);
                $bucket = $buckets->get($event->member_card_id.'|'.$event->business_date->toDateString());

                return [
                    'id' => $event->id,
                    'memberName' => $this->memberName($event->member),
                    'memberCardNo' => $event->memberCard?->card_no,
                    'courseName' => $event->course?->name,
                    'sessionStartsAt' => $event->session?->starts_at?->toIso8601String(),
                    'businessDate' => $event->business_date?->toDateString(),
                    'cardType' => $event->card_type,
                    'consumedAmountCents' => $event->deducted_amount_cents,
                    'consumedCount' => $event->deducted_count,
                    'formulaInputs' => [
                        'paidAmountCents' => $lot?->paid_amount_cents,
                        'entitlementAmountCents' => $lot?->entitlement_amount_cents,
                        'entitlementCount' => $lot?->entitlement_count,
                        'entitlementDays' => $lot?->entitlement_days,
                        'activeDayConsumptionCount' => $bucket?->event_count,
                        'consumedValueCents' => $event->consumed_value_cents,
                        'deductedAmountCents' => $event->deducted_amount_cents,
                        'deductedCount' => $event->deducted_count,
                        'courseRuleVersion' => $rule?->version,
                        'valueProvenance' => $event->value_provenance,
                    ],
                    'deliveryRecipients' => $this->presentRecipientSnapshots(
                        $event->metadata['deliveryRecipients'] ?? [],
                        $staff,
                        $roles,
                    ),
                    'shareRecipients' => $this->presentRecipientSnapshots(
                        $event->metadata['shareRecipients'] ?? [],
                        $staff,
                        $roles,
                    ),
                    'valueLotAllocations' => collect($event->metadata['valueLotAllocations'] ?? [])->map(fn (array $allocation) => [
                        'valueLotId' => isset($allocation['valueLotId']) ? (int) $allocation['valueLotId'] : null,
                        'count' => (int) ($allocation['count'] ?? 0),
                        'valueCents' => isset($allocation['valueCents']) ? (int) $allocation['valueCents'] : null,
                    ])->values(),
                    'commissionLines' => $this->presentCommissionLines($event->lines, $staff, $roles),
                    'calculationVersion' => max(1, (int) ($bucket?->latest_revision ?? $rule?->version ?? 1)),
                    'status' => $this->eventDisplayStatus($event),
                    'reversalOfId' => $event->metadata['reversalOfId'] ?? null,
                ];
            })->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function periodDays(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2200'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'status' => ['sometimes', 'nullable', 'in:open,closed'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $year = $filters['year'] ?? (int) now()->format('Y');
        $month = $filters['month'] ?? (int) now()->format('n');
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $bucketCommissions = DB::table('commission_settlement_lines')
            ->join('period_day_bucket_revisions', 'period_day_bucket_revisions.id', '=', 'commission_settlement_lines.period_day_bucket_revision_id')
            ->where('commission_settlement_lines.tenant_id', $tenant->id)
            ->select('period_day_bucket_revisions.period_day_bucket_id', DB::raw('SUM(commission_settlement_lines.amount_cents) as commission_cents'))
            ->groupBy('period_day_bucket_revisions.period_day_bucket_id');
        $paginator = PeriodDayBucket::query()
            ->where('period_day_buckets.tenant_id', $tenant->id)
            ->where('period_day_buckets.site_id', $site->id)
            ->whereBetween('period_day_buckets.business_date', [$start->toDateString(), $end->toDateString()])
            ->join('member_cards', 'member_cards.id', '=', 'period_day_buckets.member_card_id')
            ->join('members', 'members.id', '=', 'member_cards.member_id')
            ->leftJoin('member_crm_profiles', 'member_crm_profiles.member_id', '=', 'members.id')
            ->leftJoin('accounts', 'accounts.id', '=', 'members.account_id')
            ->leftJoinSub($bucketCommissions, 'bucket_commissions', fn ($join) => $join
                ->on('bucket_commissions.period_day_bucket_id', '=', 'period_day_buckets.id'))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('period_day_buckets.status', $status))
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->where('member_cards.card_no', 'like', "%{$term}%")
                    ->orWhere('members.member_no', 'like', "%{$term}%")
                    ->orWhere('member_crm_profiles.name', 'like', "%{$term}%")
                    ->orWhere('accounts.display_name', 'like', "%{$term}%");
            }))
            ->select([
                'period_day_buckets.*', 'member_cards.card_no', 'members.member_no',
                'member_crm_profiles.name as profile_name', 'accounts.display_name as account_name',
                'bucket_commissions.commission_cents',
            ])
            ->orderByDesc('period_day_buckets.business_date')
            ->orderByDesc('period_day_buckets.id')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (PeriodDayBucket $bucket) => [
                'id' => $bucket->id,
                'businessDate' => $bucket->business_date?->toDateString(),
                'memberCardId' => $bucket->member_card_id,
                'memberCardNo' => $bucket->card_no,
                'memberName' => $bucket->profile_name ?? $bucket->account_name ?? $bucket->member_no,
                'activeConsumptionCount' => $bucket->event_count,
                'dailyValueCents' => $bucket->day_value_cents,
                'commissionCents' => (int) ($bucket->commission_cents ?? 0),
                'calculationVersion' => $bucket->latest_revision,
                'status' => $bucket->status,
                'finalizedAt' => $bucket->closed_at?->toIso8601String(),
            ])->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function reverseEvent(
        ReverseConsumptionEventRequest $request,
        Tenant $tenant,
        Site $site,
        ConsumptionEvent $consumptionEvent,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless(
            $consumptionEvent->tenant_id === $tenant->id && $consumptionEvent->site_id === $site->id,
            404,
        );
        $validated = $request->validated();
        $event = $this->settlements->reverse(
            $consumptionEvent,
            $validated['reason'],
            $validated['commandKey'],
            DomainActor::superAdmin($request->user()),
        );

        return ApiResponse::success($this->settlements->present($event));
    }

    public function payrollPeriods(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2200'],
            'status' => ['sometimes', 'nullable', 'in:open,closed'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->periods->queryForSite($tenant->id, $site->id)
            ->when($filters['year'] ?? null, fn ($builder, $year) => $builder
                ->whereBetween('starts_on', ["{$year}-01-01", "{$year}-12-31"]))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('payroll_periods.status', $status));
        $paginator = $query->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())
                ->map(fn (PayrollPeriod $period) => $this->presentPayrollPeriod($period, $site))
                ->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function storePayrollPeriod(
        StorePayrollPeriodRequest $request,
        Tenant $tenant,
        Site $site,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $validated = $request->validated();
        $start = Carbon::create((int) $validated['year'], (int) $validated['month'], 1)->startOfMonth();
        $period = DB::transaction(function () use ($request, $site, $start, $validated) {
            Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            return $this->periods->create(DomainActor::superAdmin($request->user()), $site, [
                'startsOn' => $start->toDateString(),
                'endsOn' => $start->copy()->endOfMonth()->toDateString(),
                'reason' => $validated['reason'],
                'commandKey' => $validated['commandKey'],
            ]);
        });

        return ApiResponse::success($this->presentPayrollPeriod($period, $site), 201);
    }

    public function closePayrollPeriod(
        ClosePayrollPeriodRequest $request,
        Tenant $tenant,
        Site $site,
        PayrollPeriod $payrollPeriod,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $validated = $request->validated();
        $period = $this->periods->close(
            DomainActor::superAdmin($request->user()),
            $site,
            $payrollPeriod,
            (int) $validated['expectedVersion'],
            ['reason' => $validated['reason'], 'commandKey' => $validated['commandKey']],
        );

        return ApiResponse::success($this->presentPayrollPeriod($period, $site));
    }

    private function presentPayrollPeriod(PayrollPeriod $period, Site $site): array
    {
        $metrics = $this->periods->metrics($period);
        $closeState = $this->periods->closeState($site, $period);

        return [
            'id' => $period->id,
            'year' => (int) $period->starts_on->format('Y'),
            'month' => (int) $period->starts_on->format('n'),
            'startsOn' => $period->starts_on->toDateString(),
            'endsOn' => $period->ends_on->toDateString(),
            'status' => $period->status,
            'settlementLineCount' => (int) ($metrics['settlementCount'] ?? 0),
            'consumedValueCents' => (int) ($metrics['consumedValueCents'] ?? 0),
            'sessionFeeCents' => (int) ($metrics['sessionFeeCents'] ?? 0),
            'commissionCents' => (int) ($metrics['commissionCents'] ?? 0),
            'compensationCents' => (int) (($metrics['sessionFeeCents'] ?? 0) + ($metrics['commissionCents'] ?? 0)),
            'adjustmentCents' => (int) ($metrics['adjustmentAmountCents'] ?? 0),
            'version' => $period->version,
            'canClose' => (bool) $closeState['canClose'],
            'pendingCount' => (int) $closeState['pendingCount'],
            'blockedReason' => $closeState['blockedReason'],
            'closedAt' => $period->closed_at?->toIso8601String(),
            'metricsSnapshottedAt' => $period->metrics_snapshotted_at?->toIso8601String(),
        ];
    }

    private function presentRecipientSnapshots(array $snapshots, $staff, $roles): array
    {
        return collect($snapshots)->map(function (array $snapshot) use ($staff, $roles) {
            $staffId = (int) ($snapshot['staffId'] ?? 0);
            $roleId = isset($snapshot['compensationRoleId']) ? (int) $snapshot['compensationRoleId'] : null;
            $role = $roleId === null ? null : $roles->get($roleId);

            return [
                'staffId' => $staffId,
                'staffName' => $staff->get($staffId)?->name,
                'compensationRoleId' => $roleId,
                'roleName' => $role?->name,
                'roleType' => $role?->role_type,
                'allocationBps' => (int) ($snapshot['allocationBps'] ?? 10000),
            ];
        })->values()->all();
    }

    private function presentCommissionLines($lines, $staff, $roles): array
    {
        $running = [];

        return collect($lines)->sortBy('id')->map(function (CommissionSettlementLine $line) use ($staff, $roles, &$running) {
            $role = $line->compensation_role_id === null ? null : $roles->get($line->compensation_role_id);
            $key = implode(':', [$line->staff_id, $line->compensation_role_id ?? 0, $line->component]);
            $running[$key] = ($running[$key] ?? 0) + (int) $line->amount_cents;

            return [
                'id' => $line->id,
                'staffId' => $line->staff_id,
                'staffName' => $staff->get($line->staff_id)?->name,
                'compensationRoleId' => $line->compensation_role_id,
                'roleName' => $role?->name,
                'roleType' => $role?->role_type,
                'component' => $line->component,
                'lineType' => $line->line_type,
                'baseValueCents' => $line->base_value_cents,
                'rateBps' => $line->rate_bps,
                'allocationBps' => $line->allocation_bps,
                'deltaCents' => $line->amount_cents,
                'netCents' => $running[$key],
                'postCloseAdjustment' => (bool) ($line->metadata['postCloseAdjustment'] ?? false),
                'occurredAt' => $line->occurred_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    private function eventDisplayStatus(ConsumptionEvent $event): string
    {
        if ($event->status !== 'reversed' && $event->lines->contains('line_type', 'adjustment')) {
            return 'adjusted';
        }

        return $event->status;
    }
}
