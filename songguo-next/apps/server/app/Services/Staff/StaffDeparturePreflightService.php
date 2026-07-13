<?php

namespace App\Services\Staff;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionStatus;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;

class StaffDeparturePreflightService
{
    public function hasFutureBookings(Staff $member, Site $site): bool
    {
        return ScheduleSession::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->where('coach_staff_id', $member->id)
            ->where('starts_at', '>=', now())
            ->where('status', ScheduleSessionStatus::Scheduled)
            ->where(function ($query) {
                $query->where('booked_count', '>', 0)
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('appointments')
                            ->whereColumn('appointments.session_id', 'schedule_sessions.id')
                            ->whereIn('appointments.status', [
                                AppointmentStatus::Confirmed->value,
                                AppointmentStatus::Waitlisted->value,
                            ]);
                    });
            })
            ->exists();
    }
}
