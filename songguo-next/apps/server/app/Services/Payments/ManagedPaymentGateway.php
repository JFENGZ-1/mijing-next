<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Services\Admin\PlatformPaymentConfigService;
use Illuminate\Http\Request;

class ManagedPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly PlatformPaymentConfigService $configs,
        private readonly WechatPaymentGateway $wechat,
        private readonly DemoPaymentGateway $demo,
    ) {}

    public function driver(): string
    {
        return $this->checkoutGateway()->driver();
    }

    public function createMemberCardCheckout(
        MemberCardOrder $order,
        Account $account,
        Member $member,
        Site $site,
        CardProduct $product,
    ): array {
        return $this->checkoutGateway()->createMemberCardCheckout($order, $account, $member, $site, $product);
    }

    public function validateWebhookSignature(Request $request): bool
    {
        return $this->webhookGateway()->validateWebhookSignature($request);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        return $this->webhookGateway()->parseWebhookPayload($request);
    }

    public function queryOrderPaid(string $orderNo): ?array
    {
        return $this->webhookGateway()->queryOrderPaid($orderNo);
    }

    public function queryOrder(string $orderNo): array
    {
        return $this->webhookGateway()->queryOrder($orderNo);
    }

    public function closeOrder(string $orderNo): array
    {
        return $this->webhookGateway()->closeOrder($orderNo);
    }

    private function checkoutGateway(): PaymentGateway
    {
        $snapshot = $this->hydrateWechatConfig();

        return $snapshot['enabled'] ? $this->wechat : $this->demo;
    }

    private function webhookGateway(): PaymentGateway
    {
        $snapshot = $this->hydrateWechatConfig();

        return $snapshot['exists'] || $snapshot['enabled'] ? $this->wechat : $this->demo;
    }

    /**
     * @return array{exists: bool, enabled: bool, values: array<string, string>}
     */
    private function hydrateWechatConfig(): array
    {
        $snapshot = $this->configs->wechatRuntimeConfig();
        foreach ($snapshot['values'] as $key => $value) {
            config(["payment.wechat.{$key}" => $value]);
        }

        return $snapshot;
    }
}
