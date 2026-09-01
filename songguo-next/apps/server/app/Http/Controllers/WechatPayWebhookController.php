<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentNotificationJob;
use App\Models\PaymentNotificationInbox;
use App\Services\Cards\MemberCardPurchaseService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        if (($payload['official'] ?? false) === true) {
            foreach (['transactionId', 'amountTotal', 'currency', 'appid', 'merchantId', 'successTime'] as $field) {
                if (! isset($payload[$field]) || $payload[$field] === '') {
                    return ApiResponse::error('WEBHOOK_PAYLOAD_INVALID', '支付回调内容无效', 422);
                }
            }
        }

        $occurredAt = null;
        if (is_string($payload['successTime'] ?? null) && $payload['successTime'] !== '') {
            try {
                $occurredAt = Carbon::parse($payload['successTime']);
            } catch (\Throwable) {
                return ApiResponse::error('WEBHOOK_PAYLOAD_INVALID', '支付回调内容无效', 422);
            }
        }

        $notification = PaymentNotificationInbox::query()->firstOrCreate(
            [
                'provider' => 'wechat',
                'notification_id' => (string) $payload['notificationId'],
            ],
            [
                'event_type' => (string) $payload['eventType'],
                'order_no' => (string) $payload['orderNo'],
                'transaction_id' => $payload['transactionId'] ?? null,
                'amount_total' => $payload['amountTotal'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'appid' => $payload['appid'] ?? null,
                'merchant_id' => $payload['merchantId'] ?? null,
                'occurred_at' => $occurredAt,
                'status' => 'pending',
            ],
        );

        if ($notification->status !== 'processed') {
            ProcessPaymentNotificationJob::dispatchAfterResponse($notification->id);
        }

        return ApiResponse::success([
            'accepted' => true,
            'notificationId' => $notification->id,
            'created' => $notification->wasRecentlyCreated,
        ]);
    }
}
