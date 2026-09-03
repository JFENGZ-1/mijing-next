<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Staff;
use App\Services\Booking\AppointmentFulfillmentService;
use App\Services\Booking\AppointmentPresenter;
use App\Services\Booking\StaffBookingAppointmentAccessService;
use App\Services\Booking\StaffCheckInService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffCheckInController extends Controller
{
    public function resolve(
        Request $request,
        int $site,
        StaffBookingAppointmentAccessService $access,
        StaffCheckInService $checkIn,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.fulfillment.check-in', $siteModel->id);

        $payload = $request->validate([
            'code' => ['required', 'string', 'max:120'],
        ]);

        return ApiResponse::success($checkIn->resolve($staff, $siteModel, $payload['code']));
    }

    public function markCheckIn(
        Request $request,
        int $site,
        int $appointment,
        StaffBookingAppointmentAccessService $access,
        AppointmentFulfillmentService $fulfillment,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.fulfillment.check-in', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
        ]);

        $appointmentModel = Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)
            ->whereKey($appointment)
            ->firstOrFail();

        $result = $fulfillment->markCheckInForStaff($staff, $appointmentModel, $payload['commandKey']);
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $siteModel->id);

        return ApiResponse::success(
            AppointmentPresenter::toStaffArray($result['appointment'], $canReadMemberNames),
            $result['created'] ? 201 : 200,
        );
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
