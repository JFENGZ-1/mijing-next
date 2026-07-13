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
}
