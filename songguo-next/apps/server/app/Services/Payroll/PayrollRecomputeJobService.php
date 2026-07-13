<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRecomputeJobStatus;
use App\Enums\PayrollRecomputeScope;
use App\Enums\PayrollReportType;
use App\Jobs\ProcessPayrollRecomputeJob;
use App\Models\PayrollRecomputeJob;
use App\Models\PayrollReportSnapshot;
use App\Models\Site;
use App\Models\Staff;
use App\Support\JobActorContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayrollRecomputeJobService
{
    public function __construct(
        private readonly PayrollReportEngine $engine,
        private readonly PayrollAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createJob(Staff $staff, Site $site, Request $request, array $payload): array
    {
        $scope = PayrollRecomputeScope::from($payload['scope']);
        $year = (int) $payload['year'];
        $month = (int) $payload['month'];
        $commandKey = (string) $payload['commandKey'];
        $targetStaffId = isset($payload['staffId']) ? (int) $payload['staffId'] : null;

        $this->validateScope($staff, $site, $scope, $targetStaffId);

        $existing = PayrollRecomputeJob::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('command_key', $commandKey)
            ->first();

        if ($existing !== null) {
            return $this->jobPayload($existing->fresh(['requestedBy', 'staff']));
        }

        $requestId = $request->attributes->get('request_id');

        $job = DB::transaction(function () use ($staff, $site, $request, $scope, $year, $month, $commandKey, $targetStaffId) {
            $job = PayrollRecomputeJob::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'status' => PayrollRecomputeJobStatus::Pending,
                'scope' => $scope,
                'year' => $year,
                'month' => $month,
                'staff_id' => $targetStaffId,
                'command_key' => $commandKey,
                'requested_by_staff_id' => $staff->id,
            ]);

            $this->audit->record($request, $staff, $site, $job, 'payroll.recompute.job.created', [
                'scope' => $scope->value,
                'year' => $year,
                'month' => $month,
                'staffId' => $targetStaffId,
                'commandKey' => $commandKey,
            ]);

            return $job;
        });

        ProcessPayrollRecomputeJob::dispatch($job->id, $staff->id, $requestId);

        return $this->jobPayload($job->fresh(['requestedBy', 'staff']));
    }

    public function runRecomputeJob(int $payrollRecomputeJobId, JobActorContext $context): void
    {
        $job = PayrollRecomputeJob::query()->whereKey($payrollRecomputeJobId)->firstOrFail();
        $actor = Staff::query()->whereKey($context->staffId)->firstOrFail();
        $site = Site::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereKey($job->site_id)
            ->firstOrFail();

        if ($job->status === PayrollRecomputeJobStatus::Completed || $job->status === PayrollRecomputeJobStatus::Failed) {
            return;
        }

        $job->update(['status' => PayrollRecomputeJobStatus::Processing]);

        try {
            $snapshotCount = match ($job->scope) {
                PayrollRecomputeScope::Coach => $this->recomputeCoachScope($actor, $site, $job),
                PayrollRecomputeScope::Sales => $this->recomputeSalesScope($actor, $site, $job),
                PayrollRecomputeScope::Site => $this->recomputeSiteScope($actor, $site, $job),
            };

            $job->update([
                'status' => PayrollRecomputeJobStatus::Completed,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $this->audit->recordForActor(
                $actor,
                $site,
                $job,
                'payroll.recompute.job.completed',
                $actor->account_id,
                $context->requestId,
                ['snapshotCount' => $snapshotCount],
            );
        } catch (Throwable $exception) {
            $job->update([
                'status' => PayrollRecomputeJobStatus::Failed,
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $this->audit->recordForActor(
                $actor,
                $site,
                $job,
                'payroll.recompute.job.failed',
                $actor->account_id,
                $context->requestId,
                ['error' => $exception->getMessage()],
            );

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function listJobs(Staff $staff, Site $site, int $page, int $perPage): array
    {
        $paginator = PayrollRecomputeJob::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->with(['requestedBy', 'staff'])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())
                ->map(fn (PayrollRecomputeJob $job) => $this->jobPayload($job))
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    private function recomputeSiteScope(Staff $actor, Site $site, PayrollRecomputeJob $job): int
    {
        return $this->recomputeCoachScope($actor, $site, $job)
            + $this->recomputeSalesScope($actor, $site, $job);
    }

    private function recomputeCoachScope(Staff $actor, Site $site, PayrollRecomputeJob $job): int
    {
        $coaches = $this->resolveCoachTargets($actor, $site, $job);

        $count = 0;
        foreach ($coaches as $coach) {
            $coachReport = $this->engine->computeCoachReport($actor, $site, $coach, $job->year, $job->month);
            $this->persistSnapshot($job, PayrollReportType::Coach, $coach->id, $coachReport);

            $courseReport = $this->engine->computeCourseCommissionReport(
                $actor,
                $site,
                $coach,
                $job->year,
                $job->month,
            );
            $this->persistSnapshot($job, PayrollReportType::CoachCourseCommission, $coach->id, $courseReport);

            $count += 2;
        }

        return $count;
    }

    private function recomputeSalesScope(Staff $actor, Site $site, PayrollRecomputeJob $job): int
    {
        $salesStaff = $this->resolveSalesTargets($actor, $site, $job);

        $count = 0;
        foreach ($salesStaff as $person) {
            $report = $this->engine->computeSalesReport($actor, $site, $person, $job->year, $job->month);
            $this->persistSnapshot($job, PayrollReportType::Sales, $person->id, $report);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<Staff>
     */
    private function resolveCoachTargets(Staff $actor, Site $site, PayrollRecomputeJob $job): array
    {
        if ($job->staff_id !== null) {
            $coach = Staff::query()
                ->where('tenant_id', $actor->tenant_id)
                ->whereKey($job->staff_id)
                ->firstOrFail();

            abort_unless(
                $coach->sites()->whereKey($site->id)->where('site_staff.tenant_id', $actor->tenant_id)->exists(),
                404,
            );

            return [$coach];
        }

        $summaries = $this->engine->computeCoachReportSummaries($actor, $site, $job->year, $job->month);
        $staffIds = collect($summaries)->pluck('staffId')->all();

        if ($staffIds === []) {
            return Staff::query()
                ->where('tenant_id', $actor->tenant_id)
                ->whereHas('sites', fn ($query) => $query->whereKey($site->id))
                ->orderBy('name')
                ->get()
                ->all();
        }

        return Staff::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('id', $staffIds)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @return list<Staff>
     */
    private function resolveSalesTargets(Staff $actor, Site $site, PayrollRecomputeJob $job): array
    {
        if ($job->staff_id !== null) {
            $person = Staff::query()
                ->where('tenant_id', $actor->tenant_id)
                ->whereKey($job->staff_id)
                ->firstOrFail();

            abort_unless(
                $person->sites()->whereKey($site->id)->where('site_staff.tenant_id', $actor->tenant_id)->exists(),
                404,
            );

            return [$person];
        }

        $summaries = $this->engine->computeSalesReportSummaries($actor, $site, $job->year, $job->month);
        $staffIds = collect($summaries)->pluck('staffId')->all();

        if ($staffIds === []) {
            return [];
        }

        return Staff::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('id', $staffIds)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistSnapshot(
        PayrollRecomputeJob $job,
        PayrollReportType $type,
        int $staffId,
        array $payload,
    ): void {
        PayrollReportSnapshot::create([
            'tenant_id' => $job->tenant_id,
            'site_id' => $job->site_id,
            'year' => $job->year,
            'month' => $job->month,
            'report_type' => $type,
            'staff_id' => $staffId,
            'payload' => $payload,
            'recompute_job_id' => $job->id,
            'computed_at' => now(),
        ]);
    }

    private function validateScope(Staff $actor, Site $site, PayrollRecomputeScope $scope, ?int $staffId): void
    {
        if ($staffId === null) {
            return;
        }

        $target = Staff::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereKey($staffId)
            ->first();

        abort_unless($target !== null, 404);

        abort_unless(
            $target->sites()->whereKey($site->id)->where('site_staff.tenant_id', $actor->tenant_id)->exists(),
            404,
        );

        if ($scope === PayrollRecomputeScope::Coach && $staffId === null) {
            throw ValidationException::withMessages([
                'staffId' => ['教练范围重算必须指定 staffId'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(PayrollRecomputeJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status->value,
            'scope' => $job->scope->value,
            'year' => $job->year,
            'month' => $job->month,
            'staffId' => $job->staff_id,
            'staffName' => $job->staff?->name,
            'commandKey' => $job->command_key,
            'requestedByStaffId' => $job->requested_by_staff_id,
            'requestedByStaffName' => $job->requestedBy?->name,
            'errorMessage' => $job->error_message,
            'createdAt' => $job->created_at?->toISOString(),
            'completedAt' => $job->completed_at?->toISOString(),
        ];
    }
}
