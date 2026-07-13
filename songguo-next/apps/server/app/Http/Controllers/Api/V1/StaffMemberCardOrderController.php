<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderAmountCorrectionRequest;
use App\Http\Requests\VoidMemberCardOrderRequest;
use App\Models\MemberCardOrder;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Orders\MemberCardOrderService;
use App\Services\Orders\OrderInternalNoteService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffMemberCardOrderController extends Controller
{
    public function index(
        Request $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MemberCardOrderService $orders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'order.read', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);

        $paginator = $orders->memberOrdersQuery($staff, $siteModel, $memberModel)
            ->with('amountCorrections')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => collect($paginator->items())
                ->map(fn (MemberCardOrder $order) => $orders->orderSummary($order))
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

    public function correctAmount(
        OrderAmountCorrectionRequest $request,
        int $site,
        int $order,
        StaffMemberAccessService $access,
        MemberCardOrderService $orders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'order.amount.correct', $siteModel->id);
        $orderModel = $this->order($staff, $siteModel->id, $order);

        $result = $orders->correctAmount($staff, $siteModel, $orderModel, $request->validated());

        return ApiResponse::success([
            'orderId' => $result['order']->id,
            'correctionEntryIds' => $result['correctionEntryIds'],
            'originalAmount' => $orders->orderSummary($result['order'])['originalAmount'],
            'effectiveAmount' => $orders->effectiveAmount($result['order']),
        ], $result['created'] ? 201 : 200);
    }

    public function void(
        VoidMemberCardOrderRequest $request,
        int $site,
        int $order,
        StaffMemberAccessService $access,
        MemberCardOrderService $orders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'order.void', $siteModel->id);
        $orderModel = $this->order($staff, $siteModel->id, $order);

        $result = $orders->voidOrder($staff, $siteModel, $orderModel, $request->validated());

        return ApiResponse::success([
            'orderId' => $result['order']->id,
            'status' => $result['order']->status->value,
            'voidedAt' => $result['order']->voided_at?->toIso8601String(),
        ], $result['created'] ? 201 : 200);
    }

    public function destroy(int $site, int $order): never
    {
        abort(403, 'ORDER_DELETE_FORBIDDEN');
    }

    public function appendInternalNote(
        Request $request,
        int $site,
        int $order,
        StaffMemberAccessService $access,
        OrderInternalNoteService $notes,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'order.read', $siteModel->id);
        $orderModel = $this->order($staff, $siteModel->id, $order);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        return ApiResponse::success(
            $notes->append($staff, $siteModel, $orderModel, $payload),
            201,
        );
    }

    private function order(Staff $staff, int $siteId, int $orderId): MemberCardOrder
    {
        return MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteId)
            ->whereKey($orderId)
            ->firstOrFail();
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
