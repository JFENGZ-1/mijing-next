<?php

namespace App\Services\Exports;

use App\Enums\ExportJobStatus;
use App\Enums\ExportJobType;
use App\Jobs\ProcessExportJob;
use App\Models\ExportJob;
use App\Models\Site;
use App\Models\Staff;
use App\Support\JobActorContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExportJobService
{
    public function __construct(
        private readonly MemberExportGenerator $memberExport,
        private readonly ExportAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createMemberExport(Staff $staff, Site $site, Request $request): array
    {
        $filters = $this->extractFilters($request);
        $requestId = $request->attributes->get('request_id');

        $job = DB::transaction(function () use ($staff, $site, $request, $filters) {
            $job = ExportJob::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'type' => ExportJobType::MemberExport,
                'status' => ExportJobStatus::Pending,
                'requested_by_staff_id' => $staff->id,
                'filters' => $filters,
            ]);

            $this->audit->record($request, $staff, $site, $job, 'export.job.created', [
                'type' => ExportJobType::MemberExport->value,
                'filters' => $filters,
            ]);

            return $job;
        });

        ProcessExportJob::dispatch(
            $job->id,
            $staff->id,
            $requestId,
            $filters,
        );

        return $this->jobPayload($job->fresh(['requestedBy']));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function runExportJob(
        int $exportJobId,
        JobActorContext $context,
        array $filters = [],
        int $attempt = 1,
        int $maxAttempts = 1,
    ): void {
        $job = ExportJob::query()->whereKey($exportJobId)->firstOrFail();
        $staff = Staff::query()->whereKey($context->staffId)->firstOrFail();
        $site = Site::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($job->site_id)
            ->firstOrFail();

        if ($job->status === ExportJobStatus::Completed || $job->status === ExportJobStatus::Failed) {
            return;
        }

        $job->update([
            'status' => ExportJobStatus::Processing,
            'completed_at' => null,
        ]);

        try {
            $path = match ($job->type) {
                ExportJobType::CardExport => $this->generateCardExport($staff, $site, $job),
                default => $this->memberExport->generate($staff, $site, $job),
            };
            $job->update([
                'status' => ExportJobStatus::Completed,
                'file_path' => $path,
                'completed_at' => now(),
            ]);
            $this->audit->recordForActor(
                $staff,
                $site,
                $job,
                'export.job.completed',
                $staff->account_id,
                $context->requestId,
                ['rowCount' => null],
            );
        } catch (Throwable $exception) {
            $isFinalAttempt = max(1, $attempt) >= max(1, $maxAttempts);
            $job->update([
                'status' => $isFinalAttempt ? ExportJobStatus::Failed : ExportJobStatus::Pending,
                'completed_at' => $isFinalAttempt ? now() : null,
            ]);
            if ($isFinalAttempt) {
                $this->audit->recordForActor(
                    $staff,
                    $site,
                    $job,
                    'export.job.failed',
                    $staff->account_id,
                    $context->requestId,
                    [
                        'attempt' => max(1, $attempt),
                        'error' => $exception->getMessage(),
                    ],
                );
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function listJobs(Staff $staff, Site $site, int $page, int $perPage): array
    {
        $paginator = ExportJob::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->with('requestedBy')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())->map(fn (ExportJob $job) => $this->jobPayload($job))->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    public function download(
        Staff $staff,
        Site $site,
        ExportJob $job,
        Request $request,
    ): StreamedResponse {
        $this->assertCanReadJob($staff, $site, $job);
        abort_unless($job->status === ExportJobStatus::Completed, 409, 'EXPORT_JOB_NOT_READY');
        abort_if(blank($job->file_path), 404, 'EXPORT_FILE_MISSING');

        $this->audit->record($request, $staff, $site, $job, 'export.job.downloaded');

        $filename = sprintf('member-export-%d.csv', $job->id);

        return response()->streamDownload(function () use ($job) {
            $stream = Storage::disk('local')->readStream($job->file_path);
            if ($stream === null) {
                abort(404, 'EXPORT_FILE_MISSING');
            }
            fpassthru($stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * A creator may follow the status of their own asynchronous export even when
     * they do not have permission to browse every export created for the site.
     *
     * @return array<string, mixed>
     */
    public function showJob(Staff $staff, Site $site, ExportJob $job): array
    {
        $this->assertCanReadJob($staff, $site, $job);

        return $this->jobPayload($job->loadMissing('requestedBy'));
    }

    public function findJob(Staff $staff, Site $site, int $jobId): ExportJob
    {
        return ExportJob::query()
            ->whereKey($jobId)
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->firstOrFail();
    }

    private function assertCanReadJob(Staff $staff, Site $site, ExportJob $job): void
    {
        abort_unless($job->tenant_id === $staff->tenant_id && $job->site_id === $site->id, 404);
        abort_unless(
            $job->requested_by_staff_id === $staff->id || $staff->hasPermission('export.job.read', $site->id),
            403,
            'PERMISSION_DENIED',
        );
    }

    private function generateCardExport(Staff $staff, Site $site, ExportJob $job): string
    {
        $products = \App\Models\CardProduct::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'card_type', 'price', 'sale_status']);

        $lines = ['id,name,cardType,price,saleStatus'];
        foreach ($products as $product) {
            $lines[] = implode(',', [
                $product->id,
                '"'.str_replace('"', '""', $product->name).'"',
                $product->card_type->value,
                $product->price,
                $product->sale_status->value,
            ]);
        }

        $path = 'exports/'.$site->tenant_id.'/'.$job->id.'/card-products.csv';
        Storage::disk('local')->put($path, implode("\n", $lines));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        $filters = $request->only([
            'status',
            'q',
            'includeVisitors',
            'tagIds',
            'flag',
            'sumMode',
            'pinyinInitial',
            'runOff',
            'columns',
        ]);

        if ($request->has('columns') && is_array($request->input('columns'))) {
            $filters['columns'] = array_values(array_map('strval', $request->input('columns')));
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(ExportJob $job): array
    {
        return [
            'id' => $job->id,
            'type' => $job->type->value,
            'status' => $job->status->value,
            'requestedByStaffId' => $job->requested_by_staff_id,
            'requestedByStaffName' => $job->requestedBy?->name,
            'filters' => $job->filters ?? [],
            'createdAt' => $job->created_at?->toISOString(),
            'completedAt' => $job->completed_at?->toISOString(),
            'downloadAvailable' => $job->status === ExportJobStatus::Completed && filled($job->file_path),
        ];
    }
}
