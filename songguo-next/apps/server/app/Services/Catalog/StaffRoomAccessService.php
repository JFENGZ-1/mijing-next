<?php

namespace App\Services\Catalog;

use App\Enums\CourseCatalogStatus;
use App\Models\Room;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

class StaffRoomAccessService
{
    public function assertPermission(Staff $staff, string $permission, int $siteId): void
    {
        abort_unless($staff->hasPermission($permission, $siteId), 403, 'PERMISSION_DENIED');
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

    public function roomQuery(Staff $staff, Site $site): Builder
    {
        return Room::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('catalog_status', CourseCatalogStatus::Active);
    }

    public function room(Staff $staff, Site $site, int $roomId): Room
    {
        return Room::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($roomId)
            ->firstOrFail();
    }
}
