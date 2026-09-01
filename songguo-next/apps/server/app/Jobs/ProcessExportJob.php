<?php

namespace App\Jobs;

use App\Services\Exports\ExportJobService;
use App\Support\JobActorContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $exportJobId,
        public int $staffId,
        public ?string $requestId = null,
        public array $filters = [],
    ) {}

    public function handle(ExportJobService $exports): void
    {
        $exports->runExportJob(
            $this->exportJobId,
            new JobActorContext($this->staffId, $this->requestId),
            $this->filters,
            max(1, $this->attempts()),
            $this->tries,
        );
    }
}
