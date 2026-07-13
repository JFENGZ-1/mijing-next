<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WechatPaymentGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'wechat';
    }

    /**
     * @return array<string, mixed>
     */
    public function createMemberCardCheckout(
        MemberCardOrder $order,
        Account $account,
        Member $member,
        Site $site,
        CardProduct $product,
    ): array {
        $prepayId = 'wx_prepay_'.Str::lower((string) Str::ulid());

        return [
            'driver' => $this->driver(),
            'autoPaid' => false,
            'orderNo' => $order->order_no,
            'prepayId' => $prepayId,
            'paymentParams' => [
                'timeStamp' => (string) now()->timestamp,
                'nonceStr' => Str::random(16),
                'package' => "prepay_id={$prepayId}",
                'signType' => 'RSA',
                'paySign' => 'PLACEHOLDER_PAY_SIGN',
            ],
        ];
    }

    public function validateWebhookSignature(Request $request): bool
    {
        $secret = (string) config('payment.wechat.webhook_secret');
        if ($secret === '') {
            return false;
        }

        $provided = (string) $request->header('X-Wechat-Signature', '');
        if ($provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return null;
        }

        $orderNo = $payload['out_trade_no'] ?? $payload['orderNo'] ?? null;
        $eventType = $payload['event_type'] ?? $payload['eventType'] ?? null;

        if (! is_string($orderNo) || $orderNo === '') {
            return null;
        }

        return [
            'orderNo' => $orderNo,
            'eventType' => is_string($eventType) ? $eventType : 'TRANSACTION.SUCCESS',
            'transactionId' => $payload['transaction_id'] ?? $payload['transactionId'] ?? null,
        ];
    }
}
