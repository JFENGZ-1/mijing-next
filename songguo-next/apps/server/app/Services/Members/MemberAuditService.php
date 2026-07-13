<?php

namespace App\Services\Members;

use App\Models\AuditEvent;
use App\Models\Member;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Http\Request;

class MemberAuditService
{
    public function record(Request $request, Staff $staff, Site $site, Member $member, string $action, array $metadata = []): void
    {
        AuditEvent::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'actor_account_id' => $request->user()->id,
            'actor_staff_id' => $staff->id,
            'action' => $action,
            'subject_type' => 'member',
            'subject_id' => $member->id,
            'request_id' => $request->attributes->get('request_id'),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
