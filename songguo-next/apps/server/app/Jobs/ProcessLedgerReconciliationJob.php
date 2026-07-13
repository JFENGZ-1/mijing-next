<?php

namespace App\Jobs;

use App\Services\Reconciliation\LedgerReconciliationJobService;
use App\Support\JobActorContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessLedgerReconciliationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $ledgerReconciliationJobId,
        public readonly int $staffId,
        public readonly ?string $requestId = null,
    ) {}

    public function handle(LedgerReconciliationJobService $service): void
    {
        $service->runJob(
            $this->ledgerReconciliationJobId,
            new JobActorContext($this->staffId, $this->requestId),
        );
    }
}
