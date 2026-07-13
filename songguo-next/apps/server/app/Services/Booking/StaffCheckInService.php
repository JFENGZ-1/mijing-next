<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Carbon\Carbon;

class StaffCheckInService
{
    /**
     * Resolve a scanned or manually entered member code to today's check-in candidates.
     *
     * @return array{
     *     member: array<string, mixed>,
     *     appointments: list<array<string, mixed>>
     * }
     */
    public function resolve(Staff $staff, Site $site, string $code): array
    {
        $member = $this->resolveMember($staff, $site, $code);
        abort_unless($member !== null, 404, 'MEMBER_NOT_FOUND');

        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);
        $today = Carbon::now($site->timezone ?: config('app.timezone'))->toDateString();

        $appointments = Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_id', $member->id)
            ->where('status', AppointmentStatus::Confirmed)
            ->whereHas('session', fn ($query) => $query
                ->whereDate('starts_at', $today)
                ->where('status', '!=', 'cancelled'))
            ->with(['session.course', 'member.crmProfile', 'member.account'])
            ->orderBy(
                ScheduleSession::query()
                    ->select('starts_at')
                    ->whereColumn('schedule_sessions.id', 'appointments.session_id')
                    ->limit(1),
            )
            ->get()
            ->map(fn (Appointment $appointment) => array_merge(
                AppointmentPresenter::toStaffArray($appointment, $canReadMemberNames),
                [
                    'sessionStartsAt' => $appointment->session?->starts_at?->toIso8601String(),
                    'courseName' => $appointment->session?->course?->name,
                ],
            ))
            ->values()
            ->all();

        return [
            'member' => [
                'id' => $member->id,
                'memberNo' => $member->member_no,
                'displayName' => $canReadMemberNames
                    ? ($member->crmProfile?->display_name ?: $member->account?->display_name ?: '会员')
                    : '会员',
            ],
            'appointments' => $appointments,
        ];
    }

    private function resolveMember(Staff $staff, Site $site, string $code): ?Member
    {
        $normalized = trim($code);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^member:(\d+)$/i', $normalized, $matches) === 1) {
            return $this->memberQuery($staff, $site)->whereKey((int) $matches[1])->first();
        }

        if (preg_match('/^memberNo:(.+)$/i', $normalized, $matches) === 1) {
            return $this->memberQuery($staff, $site)->where('member_no', trim($matches[1]))->first();
        }

        if (ctype_digit($normalized)) {
            $memberId = (int) $normalized;

            return $this->memberQuery($staff, $site)
                ->where(function ($query) use ($normalized, $memberId) {
                    $query->where('member_no', $normalized)->orWhereKey($memberId);
                })
                ->first();
        }

        return $this->memberQuery($staff, $site)->where('member_no', $normalized)->first();
    }

    private function memberQuery(Staff $staff, Site $site)
    {
        return Member::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereHas('sites', fn ($sites) => $sites
                ->whereKey($site->id)
                ->where('member_sites.tenant_id', $staff->tenant_id));
    }
}
