<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemberCardOrderStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CloseExpiredMemberCardOrderJob;
use App\Models\MemberCardOrder;
use App\Services\Cards\MemberCardPurchaseService;
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

    public function resumePayment(
        Request $request,
        int $order,
        TenantMemberAccessService $access,
        MemberCardOrderService $orders,
        MemberCardPurchaseService $purchases,
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

        $payment = $purchases->resumePayment($account, $member, $orderModel);

        return ApiResponse::success([
            'order' => $orders->memberOrderDetail($orderModel->fresh(['amountCorrections', 'memberCard'])),
            'payment' => $payment,
        ]);
    }

    /**
     * 待支付订单主动向支付渠道查单，支付成功则完成发卡（回调丢失时的兜底）。
     */
    public function syncPayment(
        Request $request,
        int $order,
        TenantMemberAccessService $access,
        MemberCardOrderService $orders,
        MemberCardPurchaseService $purchases,
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
            ->firstOrFail();

        if ($orderModel->status === MemberCardOrderStatus::PendingPayment) {
            $provider = $purchases->paymentGateway()->queryOrder((string) $orderModel->order_no);
            if (($provider['state'] ?? '') === 'SUCCESS') {
                $purchases->fulfillWechatPaidOrder((string) $orderModel->order_no, [
                    ...$provider,
                    'eventType' => 'TRANSACTION.SUCCESS',
                ]);
            } elseif (
                ! ($orderModel->payment_expires_at?->isFuture() ?? true)
                || ($provider['state'] ?? '') === 'CLOSED'
            ) {
                CloseExpiredMemberCardOrderJob::dispatch($orderModel->id);
            }
        }

        $orderModel = MemberCardOrder::query()
            ->whereKey($orderModel->id)
            ->with(['amountCorrections', 'memberCard'])
            ->firstOrFail();

        return ApiResponse::success($orders->memberOrderDetail($orderModel));
    }
}
