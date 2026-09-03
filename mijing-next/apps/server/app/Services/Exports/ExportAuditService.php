<?php

namespace App\Services\Exports;

use App\Models\AuditEvent;
use App\Models\ExportJob;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Http\Request;

class ExportAuditService
{
    public function record(
        Request $request,
        Staff $staff,
        Site $site,
        ExportJob $job,
        string $action,
        array $metadata = [],
    ): void {
        $this->recordForActor(
            $staff,
            $site,
            $job,
            $action,
            $request->user()->id,
            $request->attributes->get('request_id'),
            $metadata,
        );
    }

    public function recordForActor(
        Staff $staff,
        Site $site,
        ExportJob $job,
        string $action,
        ?int $actorAccountId,
        ?string $requestId,
        array $metadata = [],
    ): void {
        AuditEvent::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'actor_account_id' => $actorAccountId,
            'actor_staff_id' => $staff->id,
            'action' => $action,
            'subject_type' => 'export_job',
            'subject_id' => $job->id,
            'request_id' => $requestId,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
