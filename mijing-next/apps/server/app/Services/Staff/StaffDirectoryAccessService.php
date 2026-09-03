<?php

namespace App\Services\Staff;

use App\Models\Site;
use App\Models\Staff;

class StaffDirectoryAccessService
{
    public function assertReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('staff.directory.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('staff.directory.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertDeparturePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('staff.departure.soft', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertTransferOwnershipPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('staff.directory.transfer-ownership', $siteId),
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

    public function directoryStaff(Staff $actor, Site $site, int $staffId): Staff
    {
        return Staff::query()
            ->whereKey($staffId)
            ->where('tenant_id', $actor->tenant_id)
            ->whereHas('sites', fn ($query) => $query
                ->whereKey($site->id)
                ->where('site_staff.tenant_id', $actor->tenant_id))
            ->firstOrFail();
    }
}
