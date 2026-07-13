<?php

namespace App\Services\Dashboard;

use App\Models\Site;
use App\Models\Staff;

class StaffDashboardAccessService
{
    public function assertSummaryPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('staff.dashboard.read', $siteId)
            || $staff->hasPermission('crm.member.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertSalesFeedPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('staff.dashboard.read', $siteId)
            || $staff->hasPermission('order.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertAppointmentFeedPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('staff.dashboard.read', $siteId),
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
