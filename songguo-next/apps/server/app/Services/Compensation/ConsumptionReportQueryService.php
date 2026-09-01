<?php

namespace App\Services\Compensation;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConsumptionReportQueryService
{
    public function __construct(private readonly DeterministicAllocationCalculator $allocator) {}

    public function paginate(
        int $tenantId,
        int $siteId,
        array $filters,
        string $dimension,
        int $perPage = 20,
        bool $canSearchMemberNames = true,
    ): LengthAwarePaginator {
        abort_unless(in_array($dimension, ['coach', 'share', 'member', 'course', 'card'], true), 422, 'CONSUMPTION_DIMENSION_INVALID');

        if (in_array($dimension, ['coach', 'share'], true)) {
            $this->ensureRecipientProjection($tenantId, $siteId, $filters, $dimension, $canSearchMemberNames);
        }

        return in_array($dimension, ['coach', 'share'], true)
            ? $this->staffDimension($tenantId, $siteId, $filters, $dimension, $perPage, $canSearchMemberNames)
            : $this->entityDimension($tenantId, $siteId, $filters, $dimension, $perPage, $canSearchMemberNames);
    }

    public function totals(
        int $tenantId,
        int $siteId,
        array $filters,
        bool $canSearchMemberNames = true,
    ): array {
        $reversedAudit = ($filters['status'] ?? null) === 'reversed';
        $countExpression = $reversedAudit
            ? 'COUNT(consumption_events.id)'
            : "COUNT(CASE WHEN consumption_events.status <> 'reversed' THEN 1 END)";
        $valueExpression = $reversedAudit
            ? 'COALESCE(SUM(consumption_events.consumed_value_cents), 0)'
            : "COALESCE(SUM(CASE WHEN consumption_events.status <> 'reversed' THEN consumption_events.consumed_value_cents ELSE 0 END), 0)";
        $lines = $this->eventLineTotals($tenantId, $siteId, true);
        $query = DB::table('consumption_events')
            ->leftJoinSub($lines, 'line_totals', fn ($join) => $join->on('line_totals.consumption_event_id', '=', 'consumption_events.id'));
        $this->applyEventFilters(
            $query,
            $tenantId,
            $siteId,
            $filters,
            canSearchMemberNames: $canSearchMemberNames,
        );
        $row = $query->selectRaw(
            $countExpression.' AS consumption_count, '.
            "COUNT(CASE WHEN consumption_events.status = 'provisional' THEN 1 END) AS pending_count, ".
            $valueExpression.' AS consumed_value_cents, '.
            'COALESCE(SUM(line_totals.session_fee_cents), 0) AS session_fee_cents, '.
            'COALESCE(SUM(line_totals.commission_cents), 0) AS commission_cents',
        )->first();

        return [
            'consumptionCount' => (int) ($row->consumption_count ?? 0),
            'pendingCount' => (int) ($row->pending_count ?? 0),
            'consumedValueCents' => (int) ($row->consumed_value_cents ?? 0),
            'sessionFeeCents' => (int) ($row->session_fee_cents ?? 0),
            'commissionCents' => (int) ($row->commission_cents ?? 0),
        ];
    }

    public function present(object $row): array
    {
        return [
            'key' => (int) $row->subject_id,
            'dimensionName' => $row->subject_name,
            'consumptionCount' => (int) $row->consumption_count,
            'consumedValueCents' => (int) $row->consumed_value_cents,
            'sessionFeeCents' => (int) $row->session_fee_cents,
            'commissionCents' => (int) $row->commission_cents,
        ];
    }

    private function entityDimension(
        int $tenantId,
        int $siteId,
        array $filters,
        string $dimension,
        int $perPage,
        bool $canSearchMemberNames,
    ): LengthAwarePaginator {
        $reversedAudit = ($filters['status'] ?? null) === 'reversed';
        $countExpression = $reversedAudit
            ? 'COUNT(consumption_events.id)'
            : "COUNT(CASE WHEN consumption_events.status <> 'reversed' THEN consumption_events.id END)";
        $valueExpression = $reversedAudit
            ? 'COALESCE(SUM(consumption_events.consumed_value_cents), 0)'
            : "COALESCE(SUM(CASE WHEN consumption_events.status <> 'reversed' THEN consumption_events.consumed_value_cents ELSE 0 END), 0)";
        $includeSessionFees = $dimension === 'course';
        $lines = $this->eventLineTotals($tenantId, $siteId, $includeSessionFees);
        $query = DB::table('consumption_events')
            ->leftJoinSub($lines, 'line_totals', fn ($join) => $join->on('line_totals.consumption_event_id', '=', 'consumption_events.id'));
        if ($dimension === 'member') {
            $query->join('members', 'members.id', '=', 'consumption_events.member_id')
                ->leftJoin('member_crm_profiles', 'member_crm_profiles.member_id', '=', 'members.id')
                ->leftJoin('accounts', 'accounts.id', '=', 'members.account_id')
                ->groupBy('consumption_events.member_id', 'members.member_no', 'member_crm_profiles.name', 'accounts.display_name')
                ->addSelect([
                    'consumption_events.member_id as subject_id',
                    DB::raw('COALESCE(member_crm_profiles.name, accounts.display_name, members.member_no) as subject_name'),
                ]);
        } elseif ($dimension === 'course') {
            $query->join('courses', 'courses.id', '=', 'consumption_events.course_id')
                ->groupBy('consumption_events.course_id', 'courses.name')
                ->addSelect(['consumption_events.course_id as subject_id', 'courses.name as subject_name']);
        } else {
            $query->join('member_cards', 'member_cards.id', '=', 'consumption_events.member_card_id')
                ->leftJoin('card_products', 'card_products.id', '=', 'member_cards.card_product_id')
                ->groupBy('consumption_events.member_card_id', 'member_cards.card_no', 'card_products.name')
                ->addSelect([
                    'consumption_events.member_card_id as subject_id',
                    DB::raw('COALESCE(card_products.name, member_cards.card_no) as subject_name'),
                ]);
        }
        $this->applyEventFilters(
            $query,
            $tenantId,
            $siteId,
            $filters,
            effectiveOnly: true,
            canSearchMemberNames: $canSearchMemberNames,
        );
        $query->addSelect([
            DB::raw($countExpression.' as consumption_count'),
            DB::raw($valueExpression.' as consumed_value_cents'),
            $includeSessionFees
                ? DB::raw('COALESCE(SUM(line_totals.session_fee_cents), 0) as session_fee_cents')
                : DB::raw('0 as session_fee_cents'),
            DB::raw('COALESCE(SUM(line_totals.commission_cents), 0) as commission_cents'),
        ])->orderBy('subject_name');

        return $query->paginate(max(1, min($perPage, 100)));
    }

    private function staffDimension(
        int $tenantId,
        int $siteId,
        array $filters,
        string $dimension,
        int $perPage,
        bool $canSearchMemberNames,
    ): LengthAwarePaginator {
        $reversedAudit = ($filters['status'] ?? null) === 'reversed';
        $countExpression = $reversedAudit
            ? '1'
            : "CASE WHEN consumption_events.status <> 'reversed' THEN 1 ELSE 0 END";
        $valueExpression = $reversedAudit
            ? 'COALESCE(recipients.allocated_value_cents, 0)'
            : "CASE WHEN consumption_events.status <> 'reversed' THEN COALESCE(recipients.allocated_value_cents, 0) ELSE 0 END";
        $legacyValueExpression = $reversedAudit
            ? 'COALESCE(consumption_events.consumed_value_cents, 0)'
            : "CASE WHEN consumption_events.status <> 'reversed' THEN COALESCE(consumption_events.consumed_value_cents, 0) ELSE 0 END";
        $recipientType = $dimension === 'coach' ? 'delivery' : 'share';
        $recipientEvents = DB::table('consumption_event_recipient_allocations as recipients')
            ->join('consumption_events', 'consumption_events.id', '=', 'recipients.consumption_event_id')
            ->where('recipients.recipient_type', $recipientType);
        $this->applyEventFilters(
            $recipientEvents,
            $tenantId,
            $siteId,
            $filters,
            effectiveOnly: true,
            canSearchMemberNames: $canSearchMemberNames,
        );
        $recipientEvents->groupBy(
            'consumption_events.id', 'recipients.staff_id',
        )->select([
            'consumption_events.id as consumption_event_id',
            'recipients.staff_id',
            DB::raw('MAX('.$countExpression.') as effective_count'),
            DB::raw('SUM(COALESCE(recipients.allocation_bps, 10000)) as allocation_bps'),
            DB::raw('SUM('.$valueExpression.') as allocated_value_cents'),
        ]);

        if ($dimension === 'coach') {
            // Legacy/backfill events may have no recipient projection. Attribute those
            // facts once to the historical primary coach without using JSON operators.
            $legacy = DB::table('consumption_events')
                ->whereNotNull('consumption_events.coach_staff_id')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')
                    ->from('consumption_event_recipient_allocations')
                    ->whereColumn('consumption_event_recipient_allocations.consumption_event_id', 'consumption_events.id')
                    ->where('consumption_event_recipient_allocations.recipient_type', 'delivery'));
            $this->applyEventFilters(
                $legacy,
                $tenantId,
                $siteId,
                $filters,
                effectiveOnly: true,
                canSearchMemberNames: $canSearchMemberNames,
            );
            $legacy->select([
                'consumption_events.id as consumption_event_id',
                'consumption_events.coach_staff_id as staff_id',
                DB::raw($countExpression.' as effective_count'),
                DB::raw('10000 as allocation_bps'),
                DB::raw($legacyValueExpression.' as allocated_value_cents'),
            ]);
            $recipientEvents->unionAll($legacy);
        }

        $roleType = $dimension === 'coach' ? 'delivery' : 'share';
        $staffLines = DB::table('commission_settlement_lines')
            ->leftJoin('compensation_roles', 'compensation_roles.id', '=', 'commission_settlement_lines.compensation_role_id')
            ->where('commission_settlement_lines.tenant_id', $tenantId)
            ->where('commission_settlement_lines.site_id', $siteId)
            ->where(fn (Builder $query) => $query->where('compensation_roles.role_type', $roleType)
                ->when($dimension === 'coach', fn (Builder $delivery) => $delivery
                    ->orWhere(fn (Builder $legacy) => $legacy
                        ->whereNull('commission_settlement_lines.compensation_role_id')
                        ->where('commission_settlement_lines.component', 'session_fee'))))
            ->groupBy('commission_settlement_lines.consumption_event_id', 'commission_settlement_lines.staff_id')
            ->select([
                'commission_settlement_lines.consumption_event_id', 'commission_settlement_lines.staff_id',
                $dimension === 'coach'
                    ? DB::raw("SUM(CASE WHEN component = 'session_fee' THEN amount_cents ELSE 0 END) AS session_fee_cents")
                    : DB::raw('0 AS session_fee_cents'),
                DB::raw("SUM(CASE WHEN component = 'consumption_commission' THEN amount_cents ELSE 0 END) AS commission_cents"),
            ]);

        return DB::query()->fromSub($recipientEvents, 'recipient_events')
            ->join('staff', 'staff.id', '=', 'recipient_events.staff_id')
            ->leftJoinSub($staffLines, 'staff_lines', fn ($join) => $join
                ->on('staff_lines.consumption_event_id', '=', 'recipient_events.consumption_event_id')
                ->on('staff_lines.staff_id', '=', 'recipient_events.staff_id'))
            ->groupBy('recipient_events.staff_id', 'staff.name')
            ->select([
                'recipient_events.staff_id as subject_id', 'staff.name as subject_name',
                DB::raw('COALESCE(SUM(recipient_events.effective_count), 0) as consumption_count'),
                DB::raw('COALESCE(SUM(recipient_events.allocated_value_cents), 0) as consumed_value_cents'),
                DB::raw('COALESCE(SUM(staff_lines.session_fee_cents), 0) as session_fee_cents'),
                DB::raw('COALESCE(SUM(staff_lines.commission_cents), 0) as commission_cents'),
            ])
            ->orderBy('staff.name')
            ->paginate(max(1, min($perPage, 100)));
    }

    private function eventLineTotals(int $tenantId, int $siteId, bool $includeSessionFees): Builder
    {
        return DB::table('commission_settlement_lines')
            ->where('tenant_id', $tenantId)->where('site_id', $siteId)
            ->groupBy('consumption_event_id')
            ->select([
                'consumption_event_id',
                $includeSessionFees
                    ? DB::raw("SUM(CASE WHEN component = 'session_fee' THEN amount_cents ELSE 0 END) as session_fee_cents")
                    : DB::raw('0 as session_fee_cents'),
                DB::raw("SUM(CASE WHEN component = 'consumption_commission' THEN amount_cents ELSE 0 END) as commission_cents"),
            ]);
    }

    /**
     * Historical events stored recipient snapshots in JSON before the portable
     * projection table existed. Materialize only missing derived rows; this does
     * not alter the consumption or commission facts themselves.
     */
    private function ensureRecipientProjection(
        int $tenantId,
        int $siteId,
        array $filters,
        string $dimension,
        bool $canSearchMemberNames,
    ): void {
        $type = $dimension === 'coach' ? 'delivery' : 'share';
        $key = $dimension === 'coach' ? 'deliveryRecipients' : 'shareRecipients';
        $query = DB::table('consumption_events')
            ->whereNotExists(fn ($projection) => $projection->selectRaw('1')
                ->from('consumption_event_recipient_allocations')
                ->whereColumn('consumption_event_recipient_allocations.consumption_event_id', 'consumption_events.id')
                ->where('consumption_event_recipient_allocations.recipient_type', $type));
        $this->applyEventFilters(
            $query,
            $tenantId,
            $siteId,
            $filters,
            effectiveOnly: true,
            canSearchMemberNames: $canSearchMemberNames,
        );
        $query->select(['consumption_events.id', 'consumption_events.tenant_id', 'consumption_events.site_id',
            'consumption_events.coach_staff_id', 'consumption_events.consumed_value_cents', 'consumption_events.metadata'])
            ->orderBy('consumption_events.id')
            ->chunkById(200, function ($events) use ($dimension, $type, $key) {
                foreach ($events as $event) {
                    $metadata = is_string($event->metadata)
                        ? (json_decode($event->metadata, true) ?: [])
                        : (array) ($event->metadata ?? []);
                    $recipients = collect($metadata[$key] ?? [])->values();
                    if ($recipients->isEmpty() && $dimension === 'coach' && $event->coach_staff_id !== null) {
                        $recipients = collect([[
                            'staffId' => (int) $event->coach_staff_id,
                            'compensationRoleId' => null,
                            'allocationBps' => 10000,
                        ]]);
                    }
                    if ($recipients->isEmpty()) {
                        continue;
                    }
                    $groups = $dimension === 'coach'
                        ? collect(['delivery' => $recipients])
                        : $recipients->groupBy(fn ($recipient) => (string) ($recipient['compensationRoleId'] ?? 0));
                    foreach ($groups as $group) {
                        $weights = $group->mapWithKeys(fn ($recipient) => [
                            $this->recipientProjectionKey($recipient) => max(0, (int) ($recipient['allocationBps'] ?? 10000)),
                        ])->all();
                        if (array_sum($weights) < 1) {
                            $weights = array_fill_keys(array_keys($weights), 1);
                        }
                        $targets = $event->consumed_value_cents === null
                            ? array_fill_keys(array_keys($weights), null)
                            : $this->allocator->weighted((int) $event->consumed_value_cents, $weights);
                        foreach ($group as $recipient) {
                            $roleId = isset($recipient['compensationRoleId']) ? (int) $recipient['compensationRoleId'] : null;
                            DB::table('consumption_event_recipient_allocations')->updateOrInsert([
                                'consumption_event_id' => (int) $event->id,
                                'recipient_type' => $type,
                                'role_key' => $roleId ?? 0,
                                'staff_id' => (int) $recipient['staffId'],
                            ], [
                                'tenant_id' => (int) $event->tenant_id,
                                'site_id' => (int) $event->site_id,
                                'compensation_role_id' => $roleId,
                                'allocation_bps' => (int) ($recipient['allocationBps'] ?? 10000),
                                'allocated_value_cents' => $targets[$this->recipientProjectionKey($recipient)] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }, 'consumption_events.id', 'id');
    }

    private function recipientProjectionKey(array $recipient): string
    {
        return (int) $recipient['staffId'].':'.(int) ($recipient['compensationRoleId'] ?? 0);
    }

    private function applyEventFilters(
        Builder $query,
        int $tenantId,
        int $siteId,
        array $filters,
        bool $effectiveOnly = false,
        bool $canSearchMemberNames = true,
    ): void {
        $query->where('consumption_events.tenant_id', $tenantId)
            ->where('consumption_events.site_id', $siteId)
            ->when($filters['from'] ?? null, fn ($builder, $from) => $builder->where('consumption_events.business_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($builder, $to) => $builder->where('consumption_events.business_date', '<=', $to))
            ->when($filters['memberId'] ?? null, fn ($builder, $id) => $builder->where('consumption_events.member_id', $id))
            ->when($filters['courseId'] ?? null, fn ($builder, $id) => $builder->where('consumption_events.course_id', $id))
            ->when($filters['memberCardId'] ?? null, fn ($builder, $id) => $builder->where('consumption_events.member_card_id', $id))
            ->when($filters['coachStaffId'] ?? null, fn ($builder, $id) => $builder->where(function ($nested) use ($id) {
                $nested->where('consumption_events.coach_staff_id', $id)
                    ->orWhereExists(fn ($recipients) => $recipients->selectRaw('1')
                        ->from('consumption_event_recipient_allocations')
                        ->whereColumn('consumption_event_recipient_allocations.consumption_event_id', 'consumption_events.id')
                        ->where('consumption_event_recipient_allocations.recipient_type', 'delivery')
                        ->where('consumption_event_recipient_allocations.staff_id', $id));
            }));
        if ($effectiveOnly) {
            if (($filters['status'] ?? null) === 'reversed') {
                $query->where('consumption_events.status', 'reversed');
            } else {
                $query->whereIn('consumption_events.status', ['provisional', 'final']);
            }
            if (($filters['status'] ?? null) === 'adjusted') {
                $query->whereExists(fn ($lines) => $lines->selectRaw('1')->from('commission_settlement_lines')
                    ->whereColumn('commission_settlement_lines.consumption_event_id', 'consumption_events.id')
                    ->where('commission_settlement_lines.line_type', 'adjustment'));
            } elseif (in_array($filters['status'] ?? null, ['provisional', 'final'], true)) {
                $query->where('consumption_events.status', $filters['status']);
            }
        } elseif (($filters['status'] ?? null) === 'adjusted') {
            $query->where('consumption_events.status', '!=', 'reversed')
                ->whereExists(fn ($lines) => $lines->selectRaw('1')->from('commission_settlement_lines')
                    ->whereColumn('commission_settlement_lines.consumption_event_id', 'consumption_events.id')
                    ->where('commission_settlement_lines.line_type', 'adjustment'));
        } elseif (($filters['status'] ?? null) !== null) {
            $query->where('consumption_events.status', $filters['status']);
        }
        if (($term = trim((string) ($filters['query'] ?? ''))) !== '') {
            $query->where(function (Builder $search) use ($term, $canSearchMemberNames) {
                $like = "%{$term}%";
                $search->whereExists(fn (Builder $members) => $members
                    ->selectRaw('1')
                    ->from('members')
                    ->when($canSearchMemberNames, fn (Builder $memberNames) => $memberNames
                        ->leftJoin('member_crm_profiles', 'member_crm_profiles.member_id', '=', 'members.id')
                        ->leftJoin('accounts', 'accounts.id', '=', 'members.account_id'))
                    ->whereColumn('members.id', 'consumption_events.member_id')
                    ->where(fn (Builder $names) => $names->where('members.member_no', 'like', $like)
                        ->when($canSearchMemberNames, fn (Builder $allowedNames) => $allowedNames
                            ->orWhere('member_crm_profiles.name', 'like', $like)
                            ->orWhere('accounts.display_name', 'like', $like))))
                    ->orWhereExists(fn (Builder $courses) => $courses->selectRaw('1')
                        ->from('courses')->whereColumn('courses.id', 'consumption_events.course_id')
                        ->where('courses.name', 'like', $like))
                    ->orWhereExists(fn (Builder $cards) => $cards->selectRaw('1')
                        ->from('member_cards')->leftJoin('card_products', 'card_products.id', '=', 'member_cards.card_product_id')
                        ->whereColumn('member_cards.id', 'consumption_events.member_card_id')
                        ->where(fn (Builder $names) => $names->where('member_cards.card_no', 'like', $like)
                            ->orWhere('card_products.name', 'like', $like)))
                    ->orWhereExists(fn (Builder $staff) => $staff->selectRaw('1')
                        ->from('staff')->whereColumn('staff.id', 'consumption_events.coach_staff_id')
                        ->where('staff.name', 'like', $like))
                    ->orWhereExists(fn (Builder $lines) => $lines->selectRaw('1')
                        ->from('commission_settlement_lines')
                        ->leftJoin('staff', 'staff.id', '=', 'commission_settlement_lines.staff_id')
                        ->leftJoin('compensation_roles', 'compensation_roles.id', '=', 'commission_settlement_lines.compensation_role_id')
                        ->whereColumn('commission_settlement_lines.consumption_event_id', 'consumption_events.id')
                        ->where(fn (Builder $names) => $names->where('staff.name', 'like', $like)
                            ->orWhere('compensation_roles.name', 'like', $like)));
            });
        }
    }
}
