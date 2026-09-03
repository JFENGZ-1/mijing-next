<?php

namespace App\Services\Payroll;

use App\Models\AuditEvent;
use App\Models\PayrollRecomputeJob;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Http\Request;

class PayrollAuditService
{
    public function record(
        Request $request,
        Staff $staff,
        Site $site,
        PayrollRecomputeJob $job,
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
        PayrollRecomputeJob $job,
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
            'subject_type' => 'payroll_recompute_job',
            'subject_id' => $job->id,
            'request_id' => $requestId,
            'metadata' => array_merge($metadata, [
                'scope' => $job->scope->value,
                'year' => $job->year,
                'month' => $job->month,
                'commandKey' => $job->command_key,
            ]),
            'occurred_at' => now(),
        ]);
    }
}
