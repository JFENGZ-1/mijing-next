<?php

namespace App\Jobs;

use App\Services\Payroll\PayrollRecomputeJobService;
use App\Support\JobActorContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPayrollRecomputeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $payrollRecomputeJobId,
        public int $staffId,
        public ?string $requestId = null,
    ) {}

    public function handle(PayrollRecomputeJobService $jobs): void
    {
        $jobs->runRecomputeJob(
            $this->payrollRecomputeJobId,
            new JobActorContext($this->staffId, $this->requestId),
        );
    }
}
