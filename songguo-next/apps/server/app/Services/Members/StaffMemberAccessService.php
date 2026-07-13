<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

class StaffMemberAccessService
{
    public function assertPermission(Staff $staff, string $permission, int $siteId): void
    {
        abort_unless($staff->hasPermission($permission, $siteId), 403, 'PERMISSION_DENIED');
    }

    public function assertMemberCardRead(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('member-card.read', $siteId)
            || $staff->hasPermission('crm.member.card.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function site(Staff $staff, int $siteId): Site
    {
        return Site::query()
            ->whereKey($siteId)
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->whereHas('staff', fn ($query) => $query
                ->whereKey($staff->id)
                ->where('site_staff.tenant_id', $staff->tenant_id))
            ->firstOrFail();
    }

    public function query(Staff $staff, Site $site): Builder
    {
        return Member::query()
            ->where('members.tenant_id', $staff->tenant_id)
            ->whereHas('sites', fn ($query) => $query
                ->whereKey($site->id)
                ->where('member_sites.tenant_id', $staff->tenant_id)
                ->where('member_sites.status', 'active'));
    }

    public function member(Staff $staff, Site $site, int $memberId): Member
    {
        return $this->query($staff, $site)->whereKey($memberId)->firstOrFail();
    }
}
