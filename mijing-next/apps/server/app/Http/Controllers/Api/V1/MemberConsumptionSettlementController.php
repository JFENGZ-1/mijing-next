<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConsumptionEvent;
use App\Services\Compensation\ConsumptionSettlementService;
use App\Services\Members\TenantMemberAccessService;
use App\Support\ApiResponse;
use App\Support\Finance\Money;
use Illuminate\Http\Request;

class MemberConsumptionSettlementController extends Controller
{
    public function index(
        Request $request,
        TenantMemberAccessService $access,
        ConsumptionSettlementService $settlements,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'status' => ['sometimes', 'nullable', 'in:provisional,final,reversed,adjusted'],
            'memberCardId' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $member = $access->member($request->user(), $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);
        $query = $settlements->queryForMember($member->tenant_id, $member->id, $validated);
        $summaryQuery = clone $query;
        $summaryQuery->setEagerLoads([])->reorder();
        $totals = $summaryQuery->selectRaw(
            "COALESCE(SUM(CASE WHEN status <> 'reversed' THEN consumed_value_cents ELSE 0 END), 0) AS consumption_value_cents, ".
            "COALESCE(SUM(CASE WHEN status = 'final' THEN consumed_value_cents ELSE 0 END), 0) AS finalized_value_cents, ".
            "COALESCE(SUM(CASE WHEN status = 'provisional' THEN consumed_value_cents ELSE 0 END), 0) AS pending_value_cents, ".
            "COUNT(CASE WHEN status <> 'reversed' AND consumed_value_cents IS NULL THEN 1 END) AS unvalued_count",
        )->first();
        $paginator = $query->paginate($validated['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(
                fn (ConsumptionEvent $event) => $settlements->presentForMember($event),
            )->values(),
            'summary' => [
                'consumptionValue' => Money::centsToDecimal((int) ($totals?->consumption_value_cents ?? 0)),
                'finalizedValue' => Money::centsToDecimal((int) ($totals?->finalized_value_cents ?? 0)),
                'pendingValue' => Money::centsToDecimal((int) ($totals?->pending_value_cents ?? 0)),
                'unvaluedCount' => (int) ($totals?->unvalued_count ?? 0),
                'hasUnvalued' => (int) ($totals?->unvalued_count ?? 0) > 0,
            ],
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }
}
