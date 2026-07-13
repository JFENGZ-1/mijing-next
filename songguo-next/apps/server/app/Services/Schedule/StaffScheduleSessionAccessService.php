<?php

namespace App\Services\Schedule;

use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

class StaffScheduleSessionAccessService
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

    public function sessionQuery(Staff $staff, Site $site): Builder
    {
        return ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id);
    }

    public function session(Staff $staff, Site $site, int $sessionId): ScheduleSession
    {
        return ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($sessionId)
            ->firstOrFail();
    }
}
