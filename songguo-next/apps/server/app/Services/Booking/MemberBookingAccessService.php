<?php

namespace App\Services\Booking;

use App\Models\Account;
use App\Models\Member;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Services\Members\TenantMemberAccessService;

class MemberBookingAccessService
{
    public function __construct(private TenantMemberAccessService $members) {}

    public function member(Account $account, int $tenantId): Member
    {
        $member = $this->members->member($account, $tenantId);
        abort_unless($member, 404);
        $this->members->assertAppAccess($member);

        return $member;
    }

    public function site(Member $member, int $siteId): Site
    {
        return Site::query()
            ->whereKey($siteId)
            ->where('tenant_id', $member->tenant_id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    public function session(Member $member, int $sessionId): ScheduleSession
    {
        return ScheduleSession::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereKey($sessionId)
            ->firstOrFail();
    }
}
