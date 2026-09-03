<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\Member;
use App\Models\Site;

class TenantMemberAccessService
{
    public function member(Account $account, int $tenantId): ?Member
    {
        return Member::query()
            ->where('tenant_id', $tenantId)
            ->where('account_id', $account->id)
            ->first();
    }

    public function assertAppAccess(Member $member): void
    {
        abort_if($member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');
        abort_if($member->status === 'closed', 403, 'MEMBER_RELATIONSHIP_CLOSED');
    }

    public function memberForSite(Account $account, Site $site): ?Member
    {
        $member = $this->member($account, $site->tenant_id);
        if ($member) {
            $this->assertAppAccess($member);
        }

        return $member;
    }
}
