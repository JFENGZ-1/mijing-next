<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MemberCardOrder;
use App\Services\Members\TenantMemberAccessService;
use App\Services\Orders\MemberCardOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberMemberCardOrderController extends Controller
{
    public function index(
        Request $request,
        TenantMemberAccessService $access,
        MemberCardOrderService $orders,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        $account = $request->user();
        $member = $access->member($account, $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);

        $paginator = $orders->memberSelfOrdersQuery($member)
            ->with('amountCorrections')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => collect($paginator->items())
                ->map(fn (MemberCardOrder $order) => $orders->memberOrderDetail($order))
                ->values()
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(
        Request $request,
        int $order,
        TenantMemberAccessService $access,
        MemberCardOrderService $orders,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        $account = $request->user();
        $member = $access->member($account, $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);

        $orderModel = MemberCardOrder::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->whereKey($order)
            ->with(['amountCorrections', 'memberCard'])
            ->firstOrFail();

        return ApiResponse::success($orders->memberOrderDetail($orderModel));
    }
}
