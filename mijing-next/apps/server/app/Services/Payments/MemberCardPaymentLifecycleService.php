<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\MemberCardOrderStatus;
use App\Models\MemberCardOrder;
use App\Services\Cards\MemberCardPurchaseService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MemberCardPaymentLifecycleService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly MemberCardPurchaseService $purchases,
    ) {}

    public function closeExpiredOrder(int $orderId): void
    {
        $order = $this->claimExpiredOrder($orderId);
        if ($order === null) {
            return;
        }

        $provider = $this->gateway->queryOrder($order->order_no);
        if (($provider['state'] ?? 'UNKNOWN') === 'SUCCESS') {
            $this->fulfill($order, $provider);

            return;
        }
        if (($provider['state'] ?? 'UNKNOWN') === 'CLOSED') {
            $this->markClosed($order->id);

            return;
        }
        if (($provider['state'] ?? 'UNKNOWN') === 'UNKNOWN' && ($provider['configured'] ?? true)) {
            throw new RuntimeException('PAYMENT_QUERY_UNCERTAIN');
        }

        $closed = $this->gateway->closeOrder($order->order_no);
        if (($closed['state'] ?? 'UNKNOWN') === 'CLOSED') {
            $this->markClosed($order->id);

            return;
        }

        // A close request can race a successful payment. Query once more before retrying.
        $reconciled = $this->gateway->queryOrder($order->order_no);
        if (($reconciled['state'] ?? 'UNKNOWN') === 'SUCCESS') {
            $this->fulfill($order, $reconciled);

            return;
        }
        if (($reconciled['state'] ?? 'UNKNOWN') === 'CLOSED') {
            $this->markClosed($order->id);

            return;
        }

        throw new RuntimeException('PAYMENT_CLOSE_UNCERTAIN');
    }

    private function claimExpiredOrder(int $orderId): ?MemberCardOrder
    {
        return DB::transaction(function () use ($orderId) {
            $order = MemberCardOrder::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null || in_array($order->status, [
                MemberCardOrderStatus::Paid,
                MemberCardOrderStatus::Closed,
                MemberCardOrderStatus::Voided,
            ], true)) {
                return null;
            }

            $expiresAt = $order->payment_expires_at
                ?? $order->created_at?->copy()->addMinutes((int) config('payment.order_ttl_minutes', 5));
            if ($expiresAt === null || $expiresAt->isFuture()) {
                return null;
            }

            if ($order->status === MemberCardOrderStatus::PendingPayment) {
                $order->update([
                    'status' => MemberCardOrderStatus::Closing,
                    'payment_expires_at' => $expiresAt,
                    'payment_state_version' => $order->payment_state_version + 1,
                ]);
            }

            return $order->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $provider
     */
    private function fulfill(MemberCardOrder $order, array $provider): void
    {
        $this->purchases->fulfillWechatPaidOrder($order->order_no, [
            'eventType' => 'TRANSACTION.SUCCESS',
            'transactionId' => $provider['transactionId'] ?? null,
            'amountTotal' => $provider['amountTotal'] ?? null,
            'currency' => $provider['currency'] ?? null,
            'appid' => $provider['appid'] ?? null,
            'merchantId' => $provider['merchantId'] ?? null,
            'successTime' => $provider['successTime'] ?? null,
        ]);
    }

    private function markClosed(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = MemberCardOrder::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            if ($order->status === MemberCardOrderStatus::Paid) {
                return;
            }
            if ($order->status === MemberCardOrderStatus::Closed) {
                return;
            }
            if (! in_array($order->status, [
                MemberCardOrderStatus::PendingPayment,
                MemberCardOrderStatus::Closing,
            ], true)) {
                return;
            }

            $order->update([
                'status' => MemberCardOrderStatus::Closed,
                'closed_at' => now(),
                'close_reason' => 'payment_timeout',
                'payment_state_version' => $order->payment_state_version + 1,
            ]);
        });
    }
}
