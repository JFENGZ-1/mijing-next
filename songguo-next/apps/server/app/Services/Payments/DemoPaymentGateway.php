<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use Illuminate\Http\Request;

class DemoPaymentGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'demo';
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
        return [
            'driver' => $this->driver(),
            'autoPaid' => true,
            'orderNo' => $order->order_no,
        ];
    }

    public function validateWebhookSignature(Request $request): bool
    {
        return false;
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        return null;
    }

    public function queryOrderPaid(string $orderNo): ?array
    {
        return null;
    }
}
