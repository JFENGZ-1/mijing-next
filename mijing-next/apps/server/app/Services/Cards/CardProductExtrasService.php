<?php

namespace App\Services\Cards;

use App\Enums\EntitlementLedgerEntryType;
use App\Enums\ExportJobStatus;
use App\Enums\ExportJobType;
use App\Jobs\ProcessExportJob;
use App\Models\CardProduct;
use App\Models\EntitlementLedgerEntry;
use App\Models\ExportJob;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Exports\ExportAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardProductExtrasService
{
    public function __construct(
        private readonly ExportAuditService $audit,
    ) {}

    /**
     * @return array{items: list<array{key: string, label: string, usageCount: int}>}
     */
    public function groupHistory(Staff $staff, Site $site, CardProduct $product): array
    {
        $keys = EntitlementLedgerEntry::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('entry_type', EntitlementLedgerEntryType::CountAdjust->value)
            ->whereNotNull('count_group_key')
            ->select('count_group_key')
            ->selectRaw('COUNT(*) as usage_count')
            ->groupBy('count_group_key')
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get();

        return [
            'cardProductId' => $product->id,
            'items' => $keys->map(fn ($row) => [
                'key' => $row->count_group_key,
                'label' => $row->count_group_key,
                'usageCount' => (int) $row->usage_count,
            ])->all(),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function faceLibrary(Site $site): array
    {
        // 平台级图案库（config/card_faces.php，未来由平台 Web 后台统一管理）
        return [
            'items' => app(CardFaceLibraryService::class)->items(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createExportJob(Staff $staff, Site $site, Request $request): array
    {
        $requestId = $request->attributes->get('request_id');

        $job = DB::transaction(function () use ($staff, $site, $request) {
            $job = ExportJob::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'type' => ExportJobType::CardExport,
                'status' => ExportJobStatus::Pending,
                'requested_by_staff_id' => $staff->id,
                'filters' => [],
            ]);

            $this->audit->record($request, $staff, $site, $job, 'export.job.created', [
                'type' => ExportJobType::CardExport->value,
            ]);

            return $job;
        });

        ProcessExportJob::dispatch($job->id, $staff->id, $requestId, []);

        return [
            'id' => $job->id,
            'type' => $job->type->value,
            'status' => $job->status->value,
            'createdAt' => $job->created_at?->toIso8601String(),
        ];
    }
}
