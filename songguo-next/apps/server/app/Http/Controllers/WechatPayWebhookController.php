<?php

namespace App\Http\Controllers;

use App\Services\Cards\MemberCardPurchaseService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class WechatPayWebhookController extends Controller
{
    public function __invoke(Request $request, MemberCardPurchaseService $purchases)
    {
        if (! $purchases->paymentGateway()->validateWebhookSignature($request)) {
            return ApiResponse::error('WEBHOOK_SIGNATURE_INVALID', '支付回调签名校验失败', 401);
        }

        $payload = $purchases->paymentGateway()->parseWebhookPayload($request);
        if ($payload === null) {
            return ApiResponse::error('WEBHOOK_PAYLOAD_INVALID', '支付回调内容无效', 422);
        }

        if (($payload['eventType'] ?? '') !== 'TRANSACTION.SUCCESS') {
            return ApiResponse::success(['accepted' => true, 'ignored' => true]);
        }

        $result = $purchases->fulfillWechatPaidOrder((string) $payload['orderNo'], $payload);

        return ApiResponse::success([
            'accepted' => true,
            'orderId' => $result['order']->id,
            'status' => $result['order']->status->value,
            'memberCardId' => $result['memberCard']?->id,
            'created' => $result['created'],
        ]);
    }
}
