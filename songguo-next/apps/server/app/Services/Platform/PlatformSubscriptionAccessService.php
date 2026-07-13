<?php

namespace App\Services\Platform;

use App\Models\Site;
use App\Models\Staff;

class PlatformSubscriptionAccessService
{
    public function assertReadPermission(Staff $staff, ?int $siteId = null): void
    {
        abort_unless(
            $staff->hasPermission('platform.subscription.read', $siteId),
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
}
