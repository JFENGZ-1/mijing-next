<?php

namespace App\Jobs;

use App\Models\PaymentNotificationInbox;
use App\Services\Cards\MemberCardPurchaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessPaymentNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $timeout = 30;

    public function __construct(public readonly int $notificationId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60, 180, 300, 600, 900, 1800];
    }

    public function handle(MemberCardPurchaseService $purchases): void
    {
        $notification = DB::transaction(function () {
            $row = PaymentNotificationInbox::query()
                ->whereKey($this->notificationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($row->status === 'processed') {
                return null;
            }
            if ($row->status === 'processing' && $row->updated_at?->isAfter(now()->subMinutes(5))) {
                return null;
            }

            $row->update([
                'status' => 'processing',
                'attempts' => $row->attempts + 1,
                'last_error' => null,
            ]);

            return $row->fresh();
        });

        if ($notification === null) {
            return;
        }

        try {
            if ($notification->event_type === 'TRANSACTION.SUCCESS') {
                $purchases->fulfillWechatPaidOrder((string) $notification->order_no, [
                    'eventType' => $notification->event_type,
                    'transactionId' => $notification->transaction_id,
                    'amountTotal' => $notification->amount_total,
                    'currency' => $notification->currency,
                    'appid' => $notification->appid,
                    'merchantId' => $notification->merchant_id,
                    'successTime' => $notification->occurred_at?->toIso8601String(),
                ]);
            }

            $notification->update([
                'status' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $notification->update([
                'status' => 'failed',
                'last_error' => Str::limit(class_basename($exception).': '.$exception->getMessage(), 255, ''),
            ]);

            throw $exception;
        }
    }
}
