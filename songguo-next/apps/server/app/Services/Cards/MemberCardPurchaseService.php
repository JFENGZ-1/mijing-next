<?php

namespace App\Services\Cards;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\MemberCardOrderStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Services\Members\MemberPurchaseGateService;
use App\Services\Orders\MemberCardOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberCardPurchaseService
{
    public function __construct(
        private readonly MemberPurchaseGateService $purchaseGate,
        private readonly MemberCardIssueService $issuer,
        private readonly MemberCardOrderService $orders,
        private readonly PaymentGateway $paymentGateway,
    ) {}

    public function paymentGateway(): PaymentGateway
    {
        return $this->paymentGateway;
    }

    /**
     * Return a member-owned pending order checkout so the miniapp can invoke wx.requestPayment again.
     * Existing signed checkout parameters are reused; a missing checkout is rebuilt through the active gateway.
     *
     * @return array<string, mixed>
     */
    public function resumePayment(Account $account, Member $member, MemberCardOrder $order): array
    {
        abort_unless(
            $order->tenant_id === $member->tenant_id && $order->member_id === $member->id,
            404,
        );
        abort_unless($order->status === MemberCardOrderStatus::PendingPayment, 409, 'ORDER_PAYMENT_INVALID');

        $metadata = $order->metadata ?? [];
        $payment = $metadata['payment'] ?? null;
        if (is_array($payment) && is_array($payment['paymentParams'] ?? null)) {
            return $payment;
        }

        $site = Site::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereKey($order->site_id)
            ->firstOrFail();
        $product = CardProduct::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereKey((int) ($metadata['cardProductId'] ?? 0))
            ->firstOrFail();

        $payment = $this->paymentGateway->createMemberCardCheckout($order, $account, $member, $site, $product);
        abort_unless(
            ($payment['driver'] ?? null) === 'wechat' && is_array($payment['paymentParams'] ?? null),
            409,
            'ORDER_PAYMENT_UNAVAILABLE',
        );

        $metadata['payment'] = $payment;
        $order->update(['metadata' => $metadata]);

        return $payment;
    }

    public function sellableProductsQuery(Site $site): Builder
    {
        return CardProduct::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('sale_status', CardProductSaleStatus::OnSale)
            ->where('catalog_status', CardProductCatalogStatus::Active)
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    /**
     * @return array{order: MemberCardOrder, memberCard: ?MemberCard, payment: ?array<string, mixed>, created: bool}
     */
    public function submit(Account $account, Member $member, Site $site, array $payload): array
    {
        $commandKey = $payload['commandKey'];

        return DB::transaction(function () use ($account, $member, $site, $payload, $commandKey) {
            $existingOrder = MemberCardOrder::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                return $this->existingSubmitResult($existingOrder);
            }

            $gate = $this->purchaseGate->evaluate($account, $member);
            abort_unless($gate['allowed'], 422, 'MEMBER_PURCHASE_PROFILE_INCOMPLETE');

            $product = $this->sellableProductsQuery($site)
                ->whereKey($payload['cardProductId'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->paymentGateway->driver() === 'demo') {
                return $this->submitDemoPaid($account, $member, $site, $product, $commandKey);
            }

            return $this->submitWechatPending($account, $member, $site, $product, $commandKey);
        });
    }

    /**
     * @param  array<string, mixed>  $webhookPayload
     * @return array{order: MemberCardOrder, memberCard: MemberCard, created: bool}
     */
    public function fulfillWechatPaidOrder(string $orderNo, array $webhookPayload): array
    {
        return DB::transaction(function () use ($orderNo, $webhookPayload) {
            $order = MemberCardOrder::query()
                ->where('order_no', $orderNo)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === MemberCardOrderStatus::Paid) {
                return [
                    'order' => $order,
                    'memberCard' => MemberCard::query()
                        ->where('tenant_id', $order->tenant_id)
                        ->whereKey($order->member_card_id)
                        ->firstOrFail(),
                    'created' => false,
                ];
            }

            abort_unless($order->status === MemberCardOrderStatus::PendingPayment, 409, 'ORDER_PAYMENT_INVALID');

            $metadata = $order->metadata ?? [];
            $productId = (int) ($metadata['cardProductId'] ?? 0);
            abort_unless($productId > 0, 422, 'ORDER_PRODUCT_MISSING');

            $site = Site::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->site_id)
                ->firstOrFail();
            $member = Member::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->member_id)
                ->firstOrFail();
            $account = Account::query()->whereKey($member->account_id)->firstOrFail();
            $product = $this->sellableProductsQuery($site)->whereKey($productId)->firstOrFail();

            $issueResult = $this->issuer->purchaseIssue($account, $site, $member, $product, (string) $order->command_key);
            $memberCard = $issueResult['memberCard'];

            $metadata['channel'] = 'wechat_pay';
            $metadata['transactionId'] = $webhookPayload['transactionId'] ?? null;
            $metadata['paidAt'] = now()->toIso8601String();

            $order->update([
                'member_card_id' => $memberCard->id,
                'status' => MemberCardOrderStatus::Paid,
                'metadata' => $metadata,
            ]);

            return [
                'order' => $order->fresh(),
                'memberCard' => $memberCard,
                'created' => true,
            ];
        });
    }

    /**
     * @return array{order: MemberCardOrder, memberCard: ?MemberCard, payment: ?array<string, mixed>, created: bool}
     */
    private function existingSubmitResult(MemberCardOrder $order): array
    {
        $memberCard = $order->member_card_id
            ? MemberCard::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->member_card_id)
                ->firstOrFail()
            : null;

        $payment = $order->status === MemberCardOrderStatus::PendingPayment
            ? ($order->metadata['payment'] ?? null)
            : null;

        return [
            'order' => $order,
            'memberCard' => $memberCard,
            'payment' => is_array($payment) ? $payment : null,
            'created' => false,
        ];
    }

    /**
     * @return array{order: MemberCardOrder, memberCard: MemberCard, payment: null, created: bool}
     */
    private function submitDemoPaid(
        Account $account,
        Member $member,
        Site $site,
        CardProduct $product,
        string $commandKey,
    ): array {
        $issueResult = $this->issuer->purchaseIssue($account, $site, $member, $product, $commandKey);
        $memberCard = $issueResult['memberCard'];

        $order = MemberCardOrder::create([
            'tenant_id' => $member->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $memberCard->id,
            'order_no' => $this->nextOrderNo(),
            'amount' => $product->price,
            'status' => MemberCardOrderStatus::Paid,
            'command_key' => $commandKey,
            'metadata' => [
                'channel' => 'demo_auto_paid',
                'cardProductId' => $product->id,
                'productVersion' => $product->version,
            ],
        ]);

        return ['order' => $order->fresh(), 'memberCard' => $memberCard, 'payment' => null, 'created' => true];
    }

    /**
     * @return array{order: MemberCardOrder, memberCard: null, payment: array<string, mixed>, created: bool}
     */
    private function submitWechatPending(
        Account $account,
        Member $member,
        Site $site,
        CardProduct $product,
        string $commandKey,
    ): array {
        $order = MemberCardOrder::create([
            'tenant_id' => $member->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => null,
            'order_no' => $this->nextOrderNo(),
            'amount' => $product->price,
            'status' => MemberCardOrderStatus::PendingPayment,
            'command_key' => $commandKey,
            'metadata' => [
                'channel' => 'wechat_pay',
                'cardProductId' => $product->id,
                'productVersion' => $product->version,
            ],
        ]);

        $payment = $this->paymentGateway->createMemberCardCheckout(
            $order,
            $account,
            $member,
            $site,
            $product,
        );

        $metadata = $order->metadata ?? [];
        $metadata['payment'] = $payment;
        $order->update(['metadata' => $metadata]);

        return [
            'order' => $order->fresh(),
            'memberCard' => null,
            'payment' => $payment,
            'created' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogItem(CardProduct $product): array
    {
        return [
            'id' => $product->id,
            'cardType' => $product->card_type->value,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $this->decimal($product->price),
            'faceValue' => $this->nullableDecimal($product->face_value),
            'initialCount' => $product->initial_count,
            'validityDays' => $product->validity_days,
            'validityMode' => $product->validity_mode,
            'activationMode' => $product->activation_mode,
            'faceStyle' => (int) ($product->scope_config['faceStyle'] ?? 0),
            'faceGradient' => app(CardFaceLibraryService::class)
                ->gradientFor((int) ($product->scope_config['faceStyle'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submitResponse(
        MemberCardOrder $order,
        ?MemberCard $memberCard = null,
        ?array $payment = null,
    ): array {
        $response = [
            'order' => [
                ...$this->orders->orderSummary($order),
                'memberCardId' => $order->member_card_id,
            ],
        ];

        if ($memberCard !== null) {
            $snapshot = $memberCard->product_snapshot ?? [];
            $response['memberCard'] = [
                'id' => $memberCard->id,
                'cardType' => $memberCard->card_type->value,
                'status' => $memberCard->status->value,
                'name' => $snapshot['name'] ?? null,
                'cachedBalance' => $this->nullableDecimal($memberCard->cached_balance),
                'cachedRemainingCount' => $memberCard->cached_remaining_count,
                'validFrom' => $memberCard->valid_from?->toDateString(),
                'validUntil' => $memberCard->valid_until?->toDateString(),
            ];
        }

        if ($payment !== null) {
            $response['payment'] = $payment;
        }

        return $response;
    }

    private function nextOrderNo(): string
    {
        return 'ORD-'.strtoupper((string) Str::ulid());
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null ? null : $this->decimal($value);
    }
}
