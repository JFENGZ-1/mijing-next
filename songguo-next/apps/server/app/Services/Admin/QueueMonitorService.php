<?php

namespace App\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueMonitorService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $now = now()->timestamp;
        $jobs = $this->jobsTable();
        $failed = $this->failedTable();

        $queueRows = DB::table($jobs)
            ->selectRaw('queue, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as reserved_count')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) as delayed_count', [$now])
            ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN created_at ELSE NULL END) as oldest_ready_at', [$now])
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');
        $failedRows = DB::table($failed)
            ->selectRaw('queue, COUNT(*) as aggregate')
            ->groupBy('queue')
            ->pluck('aggregate', 'queue');

        $queueNames = $queueRows->keys()->merge($failedRows->keys())->unique()->sort()->values();
        $queues = $queueNames->map(function (string $name) use ($queueRows, $failedRows, $now) {
            $row = $queueRows->get($name);
            $total = (int) ($row->total ?? 0);
            $reserved = (int) ($row->reserved_count ?? 0);
            $delayed = (int) ($row->delayed_count ?? 0);
            $oldestReadyAt = isset($row->oldest_ready_at) ? (int) $row->oldest_ready_at : null;

            return [
                'name' => $name,
                'ready' => max(0, $total - $reserved - $delayed),
                'reserved' => $reserved,
                'delayed' => $delayed,
                'failed' => (int) ($failedRows[$name] ?? 0),
                'oldestWaitSeconds' => $oldestReadyAt ? max(0, $now - $oldestReadyAt) : 0,
            ];
        });

        return [
            'connection' => (string) config('queue.default'),
            'driver' => (string) config('queue.connections.'.config('queue.default').'.driver', 'unknown'),
            'totals' => [
                'ready' => $queues->sum('ready'),
                'reserved' => $queues->sum('reserved'),
                'delayed' => $queues->sum('delayed'),
                'failed' => $queues->sum('failed'),
                'batchesPending' => (int) DB::table($this->batchesTable())->whereNull('finished_at')->count(),
            ],
            'queues' => $queues,
            'generatedAt' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jobs(array $filters): array
    {
        $now = now()->timestamp;
        $status = $filters['status'] ?? null;
        $query = DB::table($this->jobsTable())
            ->when($filters['queue'] ?? null, fn ($builder, $queue) => $builder->where('queue', $queue))
            ->when($status === 'ready', fn ($builder) => $builder->whereNull('reserved_at')->where('available_at', '<=', $now))
            ->when($status === 'reserved', fn ($builder) => $builder->whereNotNull('reserved_at'))
            ->when($status === 'delayed', fn ($builder) => $builder->whereNull('reserved_at')->where('available_at', '>', $now))
            ->orderBy('id');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($filters['perPage'] ?? 30);

        return $this->paginate($paginator, fn ($job) => [
            'id' => $job->id,
            'queue' => $job->queue,
            'name' => $this->payloadName($job->payload),
            'status' => $job->reserved_at !== null ? 'reserved' : ((int) $job->available_at > $now ? 'delayed' : 'ready'),
            'attempts' => (int) $job->attempts,
            'createdAt' => $this->timestamp((int) $job->created_at),
            'availableAt' => $this->timestamp((int) $job->available_at),
            'reservedAt' => $job->reserved_at ? $this->timestamp((int) $job->reserved_at) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function failed(array $filters): array
    {
        $query = DB::table($this->failedTable())
            ->when($filters['queue'] ?? null, fn ($builder, $queue) => $builder->where('queue', $queue))
            ->latest('failed_at');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($filters['perPage'] ?? 30);

        return $this->paginate($paginator, fn ($job) => [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'name' => $this->payloadName($job->payload),
            'exception' => $this->exceptionSummary($job->exception),
            'failedAt' => Carbon::parse($job->failed_at)->toISOString(),
        ]);
    }

    public function retry(string $uuid): bool
    {
        if (! DB::table($this->failedTable())->where('uuid', $uuid)->exists()) {
            return false;
        }

        return Artisan::call('queue:retry', ['id' => [$uuid]]) === 0;
    }

    public function forget(string $uuid): bool
    {
        return DB::table($this->failedTable())->where('uuid', $uuid)->delete() === 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function batches(int $perPage): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = DB::table($this->batchesTable())->latest('created_at')->paginate($perPage);

        return $this->paginate($paginator, fn ($batch) => [
            'id' => $batch->id,
            'name' => $batch->name,
            'totalJobs' => (int) $batch->total_jobs,
            'pendingJobs' => (int) $batch->pending_jobs,
            'failedJobs' => (int) $batch->failed_jobs,
            'cancelledAt' => $batch->cancelled_at ? $this->timestamp((int) $batch->cancelled_at) : null,
            'createdAt' => $this->timestamp((int) $batch->created_at),
            'finishedAt' => $batch->finished_at ? $this->timestamp((int) $batch->finished_at) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paginate(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return [
            'items' => collect($paginator->items())->map($mapper)->values(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    private function payloadName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return 'UnknownJob';
        }

        foreach (['displayName', 'job'] as $field) {
            if (isset($decoded[$field]) && is_string($decoded[$field]) && $decoded[$field] !== '') {
                return mb_substr($decoded[$field], 0, 240);
            }
        }

        $commandName = $decoded['data']['commandName'] ?? null;

        return is_string($commandName) && $commandName !== '' ? mb_substr($commandName, 0, 240) : 'UnknownJob';
    }

    private function exceptionSummary(string $exception): string
    {
        $firstLine = preg_split('/\R/', $exception, 2)[0] ?? '任务执行失败';

        return mb_substr($firstLine, 0, 500);
    }

    private function timestamp(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp)->toISOString();
    }

    private function jobsTable(): string
    {
        return (string) config('queue.connections.database.table', 'jobs');
    }

    private function failedTable(): string
    {
        return (string) config('queue.failed.table', 'failed_jobs');
    }

    private function batchesTable(): string
    {
        return (string) config('queue.batching.table', 'job_batches');
    }
}
