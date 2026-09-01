<?php

namespace App\Services\Cards;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\MemberCardOrderStatus;
use App\Jobs\CloseExpiredMemberCardOrderJob;
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
        $expiresAt = $this->paymentExpiresAt($order);
        if (! $expiresAt->isFuture()) {
            $this->dispatchExpiry($order, now());
            abort(409, 'ORDER_PAYMENT_EXPIRED');
        }
        if ($order->payment_expires_at === null) {
            $order->update(['payment_expires_at' => $expiresAt]);
            $order->refresh();
        }

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

        $payment = $this->createAndStoreCheckout($order, $account, $member, $site, $product);
        abort_unless(
            ($payment['driver'] ?? null) === 'wechat' && is_array($payment['paymentParams'] ?? null),
            409,
            'ORDER_PAYMENT_UNAVAILABLE',
        );

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
        $driver = $this->paymentGateway->driver();

        $result = DB::transaction(function () use ($account, $member, $site, $payload, $commandKey, $driver) {
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

            if ($driver === 'demo') {
                return $this->submitDemoPaid($account, $member, $site, $product, $commandKey);
            }

            return [
                'order' => $this->createWechatPendingOrder($member, $site, $product, $commandKey),
                'memberCard' => null,
                'payment' => null,
                'created' => true,
                'checkoutProduct' => $product,
            ];
        });

        /** @var MemberCardOrder $order */
        $order = $result['order'];
        if ($order->status !== MemberCardOrderStatus::PendingPayment) {
            return $result;
        }

        $this->dispatchExpiry($order);
        if (! is_array($result['payment'] ?? null)) {
            $product = $result['checkoutProduct'] ?? null;
            if ($product instanceof CardProduct) {
                $payment = $this->createAndStoreCheckout($order, $account, $member, $site, $product);
            } else {
                $payment = $this->resumePayment($account, $member, $order);
            }
            $result['payment'] = $payment;
            $result['order'] = $order->fresh();
        }

        unset($result['checkoutProduct']);

        return $result;
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
                $incomingTransactionId = $webhookPayload['transactionId'] ?? null;
                abort_if(
                    is_string($incomingTransactionId)
                    && $order->payment_transaction_id !== null
                    && ! hash_equals($order->payment_transaction_id, $incomingTransactionId),
                    409,
                    'PAYMENT_TRANSACTION_CONFLICT',
                );

                return [
                    'order' => $order,
                    'memberCard' => MemberCard::query()
                        ->where('tenant_id', $order->tenant_id)
                        ->whereKey($order->member_card_id)
                        ->firstOrFail(),
                    'created' => false,
                ];
            }

            abort_unless(
                in_array($order->status, [
                    MemberCardOrderStatus::PendingPayment,
                    MemberCardOrderStatus::Closing,
                    MemberCardOrderStatus::Closed,
                    MemberCardOrderStatus::Voided,
                ], true),
                409,
                'ORDER_PAYMENT_INVALID',
            );

            $this->assertPaidPayloadMatchesOrder($order, $webhookPayload);

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
            $product = CardProduct::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($productId)
                ->firstOrFail();

            $issueResult = $this->issuer->purchaseIssue($account, $site, $member, $product, (string) $order->command_key);
            $memberCard = $issueResult['memberCard'];

            $metadata['channel'] = 'wechat_pay';
            $metadata['transactionId'] = $webhookPayload['transactionId'] ?? null;
            $metadata['paidAt'] = $webhookPayload['successTime'] ?? now()->toIso8601String();
            if (in_array($order->status, [
                MemberCardOrderStatus::Closing,
                MemberCardOrderStatus::Closed,
                MemberCardOrderStatus::Voided,
            ], true)) {
                $metadata['latePaymentReconciled'] = true;
            }

            $order->update([
                'member_card_id' => $memberCard->id,
                'status' => MemberCardOrderStatus::Paid,
                'metadata' => $metadata,
                'payment_transaction_id' => $webhookPayload['transactionId'] ?? $order->payment_transaction_id,
                'payment_state_version' => $order->payment_state_version + 1,
                'voided_at' => null,
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
    private function createWechatPendingOrder(
        Member $member,
        Site $site,
        CardProduct $product,
        string $commandKey,
    ): MemberCardOrder {
        return MemberCardOrder::create([
            'tenant_id' => $member->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => null,
            'order_no' => $this->nextOrderNo(),
            'amount' => $product->price,
            'status' => MemberCardOrderStatus::PendingPayment,
            'command_key' => $commandKey,
            'payment_expires_at' => now()->addMinutes((int) config('payment.order_ttl_minutes', 5)),
            'metadata' => [
                'channel' => 'wechat_pay',
                'cardProductId' => $product->id,
                'productVersion' => $product->version,
            ],
        ]);
    }

    /**
     * Network checkout happens after the order transaction commits so no product row lock is held.
     *
     * @return array<string, mixed>
     */
    private function createAndStoreCheckout(
        MemberCardOrder $order,
        Account $account,
        Member $member,
        Site $site,
        CardProduct $product,
    ): array {
        $payment = $this->paymentGateway->createMemberCardCheckout(
            $order,
            $account,
            $member,
            $site,
            $product,
        );

        DB::transaction(function () use ($order, $payment) {
            $locked = MemberCardOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === MemberCardOrderStatus::PendingPayment, 409, 'ORDER_PAYMENT_INVALID');
            abort_if(! $this->paymentExpiresAt($locked)->isFuture(), 409, 'ORDER_PAYMENT_EXPIRED');

            $metadata = $locked->metadata ?? [];
            $metadata['payment'] = $payment;
            $locked->update(['metadata' => $metadata]);
        });

        return $payment;
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

    private function paymentExpiresAt(MemberCardOrder $order)
    {
        return $order->payment_expires_at
            ?? $order->created_at?->copy()->addMinutes((int) config('payment.order_ttl_minutes', 5))
            ?? now();
    }

    private function dispatchExpiry(MemberCardOrder $order, $delay = null): void
    {
        if ((string) config('queue.default') === 'sync') {
            return;
        }

        CloseExpiredMemberCardOrderJob::dispatch($order->id)
            ->delay($delay ?? $this->paymentExpiresAt($order));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertPaidPayloadMatchesOrder(MemberCardOrder $order, array $payload): void
    {
        // ManagedPaymentGateway hydrates the active Admin-managed runtime values.
        $this->paymentGateway->driver();

        $amountTotal = $payload['amountTotal'] ?? null;
        abort_if(
            $amountTotal !== null
            && (int) $amountTotal !== (int) round(((float) $order->amount) * 100),
            422,
            'PAYMENT_AMOUNT_MISMATCH',
        );
        abort_if(
            isset($payload['currency']) && $payload['currency'] !== 'CNY',
            422,
            'PAYMENT_CURRENCY_MISMATCH',
        );

        $expectedAppid = (string) config('wechat.apps.member.appid');
        abort_if(
            isset($payload['appid'])
            && $expectedAppid !== ''
            && ! hash_equals($expectedAppid, (string) $payload['appid']),
            422,
            'PAYMENT_APPID_MISMATCH',
        );

        $expectedMerchantId = (string) config('payment.wechat.merchant_id');
        abort_if(
            isset($payload['merchantId'])
            && $expectedMerchantId !== ''
            && ! hash_equals($expectedMerchantId, (string) $payload['merchantId']),
            422,
            'PAYMENT_MERCHANT_MISMATCH',
        );
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
