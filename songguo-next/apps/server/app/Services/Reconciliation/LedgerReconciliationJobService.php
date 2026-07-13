<?php

namespace App\Services\Reconciliation;

use App\Enums\LedgerReconciliationJobStatus;
use App\Jobs\ProcessLedgerReconciliationJob;
use App\Models\EntitlementLedgerEntry;
use App\Models\LedgerReconciliationJob;
use App\Models\Site;
use App\Models\Staff;
use App\Support\JobActorContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class LedgerReconciliationJobService
{
    /**
     * @return array<string, mixed>
     */
    public function createJob(Staff $staff, Site $site, Request $request, array $payload): array
    {
        $commandKey = (string) $payload['commandKey'];
        $existing = LedgerReconciliationJob::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('command_key', $commandKey)
            ->first();

        if ($existing !== null) {
            return $this->jobPayload($existing->fresh(['requestedBy']));
        }

        $requestId = $request->attributes->get('request_id');

        $job = DB::transaction(function () use ($staff, $site, $payload, $commandKey) {
            return LedgerReconciliationJob::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'status' => LedgerReconciliationJobStatus::Pending,
                'from_date' => $payload['fromDate'],
                'to_date' => $payload['toDate'],
                'dry_run' => (bool) ($payload['dryRun'] ?? false),
                'command_key' => $commandKey,
                'requested_by_staff_id' => $staff->id,
            ]);
        });

        ProcessLedgerReconciliationJob::dispatch($job->id, $staff->id, $requestId);

        return $this->jobPayload($job->fresh(['requestedBy']));
    }

    public function runJob(int $jobId, JobActorContext $context): void
    {
        $job = LedgerReconciliationJob::query()->whereKey($jobId)->firstOrFail();

        if (in_array($job->status, [LedgerReconciliationJobStatus::Completed, LedgerReconciliationJobStatus::Failed], true)) {
            return;
        }

        $job->update(['status' => LedgerReconciliationJobStatus::Processing]);

        try {
            $entryCount = EntitlementLedgerEntry::query()
                ->where('tenant_id', $job->tenant_id)
                ->where('site_id', $job->site_id)
                ->whereDate('occurred_at', '>=', $job->from_date)
                ->whereDate('occurred_at', '<=', $job->to_date)
                ->count();

            $job->update([
                'status' => LedgerReconciliationJobStatus::Completed,
                'completed_at' => now(),
                'result' => [
                    'entryCount' => $entryCount,
                    'dryRun' => $job->dry_run,
                ],
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $job->update([
                'status' => LedgerReconciliationJobStatus::Failed,
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function listJobs(Staff $staff, Site $site, int $page, int $perPage): array
    {
        $paginator = LedgerReconciliationJob::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->with('requestedBy')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())
                ->map(fn (LedgerReconciliationJob $job) => $this->jobPayload($job))
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(LedgerReconciliationJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status->value,
            'fromDate' => $job->from_date?->toDateString(),
            'toDate' => $job->to_date?->toDateString(),
            'dryRun' => $job->dry_run,
            'commandKey' => $job->command_key,
            'result' => $job->result,
            'requestedByStaffId' => $job->requested_by_staff_id,
            'requestedByStaffName' => $job->requestedBy?->name,
            'errorMessage' => $job->error_message,
            'createdAt' => $job->created_at?->toIso8601String(),
            'completedAt' => $job->completed_at?->toIso8601String(),
        ];
    }
}
