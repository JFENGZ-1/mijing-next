<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Member;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

class StaffBookingAppointmentAccessService
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

    public function session(Staff $staff, Site $site, int $sessionId): ScheduleSession
    {
        return ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($sessionId)
            ->firstOrFail();
    }

    public function appointment(Staff $staff, Site $site, int $appointmentId): Appointment
    {
        return Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($appointmentId)
            ->firstOrFail();
    }

    public function member(Staff $staff, Site $site, int $memberId): Member
    {
        return Member::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($memberId)
            ->firstOrFail();
    }
}
