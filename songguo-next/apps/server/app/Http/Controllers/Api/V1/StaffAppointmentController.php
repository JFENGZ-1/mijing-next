<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Staff;
use App\Services\Booking\AppointmentFulfillmentService;
use App\Services\Booking\AppointmentPresenter;
use App\Services\Booking\AppointmentWriteService;
use App\Services\Booking\BookingPayableCardService;
use App\Services\Booking\StaffBookingAppointmentAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffAppointmentController extends Controller
{
    public function index(
        Request $request,
        int $site,
        int $session,
        StaffBookingAppointmentAccessService $access,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);

        $sessionModel = $access->session($staff, $siteModel, $session);
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $siteModel->id);

        $items = Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)
            ->where('session_id', $sessionModel->id)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Completed, AppointmentStatus::Absent, AppointmentStatus::Cancelled])
            ->with(['member.crmProfile', 'member.account', 'memberCard', 'ledgerEntry', 'createdByStaff:id,name'])
            ->orderBy('booked_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Appointment $appointment) => AppointmentPresenter::toStaffArray($appointment, $canReadMemberNames))
            ->values()
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(
        Request $request,
        int $site,
        int $session,
        StaffBookingAppointmentAccessService $access,
        BookingPayableCardService $payableCards,
        AppointmentWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.appointment.create', $siteModel->id);

        $payload = $request->validate([
            'memberId' => ['required', 'integer', 'min:1'],
            'memberCardId' => ['required', 'integer', 'min:1'],
            'commandKey' => ['required', 'uuid'],
        ]);

        $sessionModel = $access->session($staff, $siteModel, $session);
        $member = $access->member($staff, $siteModel, (int) $payload['memberId']);
        $card = $payableCards->resolvePayableCard($member, $sessionModel, (int) $payload['memberCardId']);

        $result = $writer->createForStaff(
            $staff,
            $member,
            $sessionModel,
            $card,
            $payload['commandKey'],
        );

        $status = $result['created'] ? 201 : 200;

        return ApiResponse::success(
            AppointmentPresenter::toArray($result['appointment']),
            $status,
        );
    }

    public function cancel(
        Request $request,
        int $site,
        int $appointment,
        StaffBookingAppointmentAccessService $access,
        AppointmentWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.appointment.cancel', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
        ]);

        $appointmentModel = $access->appointment($staff, $siteModel, $appointment);
        $result = $writer->cancelForStaff($staff, $appointmentModel, $payload['commandKey']);

        return ApiResponse::success(AppointmentPresenter::toArray($result['appointment']));
    }

    public function waitlist(
        Request $request,
        int $site,
        int $session,
        StaffBookingAppointmentAccessService $access,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);

        $sessionModel = $access->session($staff, $siteModel, $session);

        $canReadMemberNames = $staff->hasPermission('crm.member.read', $siteModel->id);

        $items = Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)
            ->where('session_id', $sessionModel->id)
            ->where('status', AppointmentStatus::Waitlisted)
            ->with(['member.crmProfile', 'member.account', 'memberCard', 'ledgerEntry', 'createdByStaff:id,name'])
            ->orderBy('booked_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Appointment $appointment) => AppointmentPresenter::toStaffArray($appointment, $canReadMemberNames))
            ->values()
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function promote(
        Request $request,
        int $site,
        int $appointment,
        StaffBookingAppointmentAccessService $access,
        AppointmentWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.waitlist.promote', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
        ]);

        $appointmentModel = $access->appointment($staff, $siteModel, $appointment);
        $result = $writer->promoteForStaff($staff, $appointmentModel, $payload['commandKey']);

        return ApiResponse::success(AppointmentPresenter::toArray($result['appointment']));
    }

    public function markAbsent(
        Request $request,
        int $site,
        int $appointment,
        StaffBookingAppointmentAccessService $access,
        AppointmentFulfillmentService $fulfillment,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.fulfillment.absent', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
        ]);

        $appointmentModel = $access->appointment($staff, $siteModel, $appointment);
        $result = $fulfillment->markAbsentForStaff($staff, $appointmentModel, $payload['commandKey']);

        return ApiResponse::success(AppointmentPresenter::toArray($result['appointment']));
    }

    public function updateStaffNotes(
        Request $request,
        int $site,
        int $appointment,
        StaffBookingAppointmentAccessService $access,
        AppointmentFulfillmentService $fulfillment,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.fulfillment.notes', $siteModel->id);

        $payload = $request->validate([
            'staffNotes' => ['required', 'string', 'max:2000'],
        ]);

        $appointmentModel = $access->appointment($staff, $siteModel, $appointment);
        $result = $fulfillment->updateStaffNotesForStaff($staff, $appointmentModel, $payload['staffNotes']);

        return ApiResponse::success(AppointmentPresenter::toArray($result['appointment']));
    }

    public function reschedule(
        Request $request,
        int $site,
        int $appointment,
        StaffBookingAppointmentAccessService $access,
        AppointmentFulfillmentService $fulfillment,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.appointment.reschedule', $siteModel->id);

        $payload = $request->validate([
            'sessionId' => ['required', 'integer', 'min:1'],
            'commandKey' => ['required', 'uuid'],
        ]);

        $appointmentModel = $access->appointment($staff, $siteModel, $appointment);
        $targetSession = $access->session($staff, $siteModel, (int) $payload['sessionId']);
        $result = $fulfillment->reschedulePrivateForStaff(
            $staff,
            $appointmentModel,
            $targetSession,
            $payload['commandKey'],
        );

        return ApiResponse::success(AppointmentPresenter::toArray($result['appointment']));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
