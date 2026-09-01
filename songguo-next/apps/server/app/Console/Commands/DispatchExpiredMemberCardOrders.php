<?php

namespace App\Console\Commands;

use App\Enums\MemberCardOrderStatus;
use App\Jobs\CloseExpiredMemberCardOrderJob;
use App\Models\MemberCardOrder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class DispatchExpiredMemberCardOrders extends Command
{
    protected $signature = 'payments:close-expired-orders {--limit=500}';

    protected $description = 'Dispatch reconciliation and close jobs for expired member-card payment orders';

    public function handle(): int
    {
        $remaining = max(1, (int) $this->option('limit'));
        $dispatched = 0;

        MemberCardOrder::query()
            ->where(function (Builder $query) {
                $query->where(function (Builder $pending) {
                    $pending
                        ->where('status', MemberCardOrderStatus::PendingPayment)
                        ->where(function (Builder $expiry) {
                            $expiry
                                ->where('payment_expires_at', '<=', now())
                                ->orWhere(function (Builder $legacy) {
                                    $legacy
                                        ->whereNull('payment_expires_at')
                                        ->where('created_at', '<=', now()->subMinutes(
                                            (int) config('payment.order_ttl_minutes', 5),
                                        ));
                                });
                        });
                })->orWhere(function (Builder $closing) {
                    $closing
                        ->where('status', MemberCardOrderStatus::Closing)
                        ->where('updated_at', '<=', now()->subMinute());
                });
            })
            ->orderBy('id')
            ->limit($remaining)
            ->pluck('id')
            ->each(function (int $orderId) use (&$dispatched) {
                CloseExpiredMemberCardOrderJob::dispatch($orderId);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} expired payment order(s).");

        return self::SUCCESS;
    }
}
