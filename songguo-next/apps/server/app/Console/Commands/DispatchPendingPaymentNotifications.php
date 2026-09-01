<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPaymentNotificationJob;
use App\Models\PaymentNotificationInbox;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class DispatchPendingPaymentNotifications extends Command
{
    protected $signature = 'payments:dispatch-notifications {--limit=500}';

    protected $description = 'Redispatch durable payment notifications that still need processing';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $ids = PaymentNotificationInbox::query()
            ->where(function (Builder $query) {
                $query
                    ->whereIn('status', ['pending', 'failed'])
                    ->orWhere(function (Builder $stale) {
                        $stale
                            ->where('status', 'processing')
                            ->where('updated_at', '<=', now()->subMinutes(5));
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $ids->each(fn (int $id) => ProcessPaymentNotificationJob::dispatch($id));
        $this->info("Dispatched {$ids->count()} payment notification(s).");

        return self::SUCCESS;
    }
}
