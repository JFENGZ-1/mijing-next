<?php

namespace App\Services\Members;

use App\Models\AuditEvent;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberSelfAuditService
{
    public function record(Request $request, Member $member, string $action, array $metadata = []): void
    {
        AuditEvent::create([
            'tenant_id' => $member->tenant_id,
            'site_id' => $member->registration_site_id,
            'actor_account_id' => $request->user()->id,
            'actor_staff_id' => null,
            'action' => $action,
            'subject_type' => 'member',
            'subject_id' => $member->id,
            'request_id' => $request->attributes->get('request_id'),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
