<?php

namespace App\Services\Site;

use App\Models\Site;
use App\Models\Staff;

class SiteProfileAccessService
{
    public function assertReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('site.profile.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('site.profile.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function site(Staff $staff, int $siteId): Site
    {
        return Site::query()
            ->whereKey($siteId)
            ->where('tenant_id', $staff->tenant_id)
            ->whereHas('staff', fn ($query) => $query
                ->whereKey($staff->id)
                ->where('site_staff.tenant_id', $staff->tenant_id))
            ->firstOrFail();
    }
}
