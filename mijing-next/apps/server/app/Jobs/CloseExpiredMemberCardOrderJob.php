<?php

namespace App\Jobs;

use App\Services\Payments\MemberCardPaymentLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CloseExpiredMemberCardOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 30;

    public function __construct(public readonly int $orderId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 180, 300, 600, 900];
    }

    public function handle(MemberCardPaymentLifecycleService $payments): void
    {
        $payments->closeExpiredOrder($this->orderId);
    }
}
