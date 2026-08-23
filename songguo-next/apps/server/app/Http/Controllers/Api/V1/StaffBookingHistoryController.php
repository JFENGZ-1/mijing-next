<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ScheduleSession;
use App\Models\Staff;
use App\Services\Booking\BookingShareTokenService;
use App\Services\Booking\StaffBookingAppointmentAccessService;
use App\Services\Booking\StaffBookingUpcomingPresenter;
use App\Services\Booking\StaffMemberBookingHistoryPresenter;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffBookingHistoryController extends Controller
{
    public function memberHistory(
        Request $request,
        int $site,
        int $member,
        StaffBookingAppointmentAccessService $access,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.member-history.list', $siteModel->id);

        $memberModel = $access->member($staff, $siteModel, $member);
        $scope = $request->query('scope', 'upcoming');

        $query = Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)
            ->where('member_id', $memberModel->id)
            ->with(['session.course', 'session.room', 'session.coach'])
            ->orderByDesc('booked_at')
            ->orderByDesc('id');

        if ($scope === 'past') {
            // 含已结束仍为 confirmed 的预约（对标管理端上课统计，不只看终态）
            $query->where(function ($builder) {
                $builder->whereIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::Absent,
                    AppointmentStatus::Completed,
                ])->orWhere(function ($inner) {
                    $inner->where('status', AppointmentStatus::Confirmed)
                        ->whereHas('session', fn ($session) => $session->where('starts_at', '<', now()));
                });
            });
        } else {
            $query->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Waitlisted,
            ])->where(function ($builder) {
                $builder->whereDoesntHave('session')
                    ->orWhereHas('session', fn ($session) => $session->where('starts_at', '>=', now()));
            });
        }

        $items = $query
            ->limit(200)
            ->get()
            ->map(fn (Appointment $appointment) => StaffMemberBookingHistoryPresenter::toArray($appointment))
            ->values()
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function staffUpcoming(
        Request $request,
        int $site,
        StaffBookingAppointmentAccessService $access,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.staff-upcoming.read', $siteModel->id);

        $items = ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)
            ->where('coach_staff_id', $staff->id)
            ->where('starts_at', '>=', now())
            ->where('status', ScheduleSessionStatus::Scheduled)
            ->with(['course', 'room'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (ScheduleSession $session) => StaffBookingUpcomingPresenter::toArray($session))
            ->values()
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function createShareLink(
        Request $request,
        int $site,
        int $session,
        StaffBookingAppointmentAccessService $access,
        BookingShareTokenService $tokens,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.share.create', $siteModel->id);

        $sessionModel = $access->session($staff, $siteModel, $session);
        $link = $tokens->issue($sessionModel);

        return ApiResponse::success([
            ...$link,
            'sessionId' => $sessionModel->id,
            'shareNotice' => 'Share links and legacy sign query parameters are not authentication. Booking actions require a member session.',
        ]);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
