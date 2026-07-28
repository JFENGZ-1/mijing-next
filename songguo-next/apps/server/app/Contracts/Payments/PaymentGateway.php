<?php

namespace App\Contracts\Payments;

use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function driver(): string;

    /**
     * @return array<string, mixed>
     */
    public function createMemberCardCheckout(
        MemberCardOrder $order,
        Account $account,
        Member $member,
        Site $site,
        CardProduct $product,
    ): array;

    public function validateWebhookSignature(Request $request): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function parseWebhookPayload(Request $request): ?array;

    /**
     * 商户主动查单：支付成功返回交易信息（orderNo/eventType/transactionId），否则返回 null。
     *
     * @return array<string, mixed>|null
     */
    public function queryOrderPaid(string $orderNo): ?array;
}
