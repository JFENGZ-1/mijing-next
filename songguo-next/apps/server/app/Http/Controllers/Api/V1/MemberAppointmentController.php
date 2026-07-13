<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Booking\AppointmentPresenter;
use App\Services\Booking\AppointmentWriteService;
use App\Services\Booking\BookingPayableCardService;
use App\Services\Booking\MemberAppointmentPresenter;
use App\Services\Booking\MemberBookingAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberAppointmentController extends Controller
{
    public function index(
        Request $request,
        MemberBookingAccessService $access,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $scope = $request->query('scope', 'upcoming');

        $query = Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->orderByDesc('booked_at')
            ->orderByDesc('id');

        if ($scope === 'past') {
            $query->whereIn('status', [
                AppointmentStatus::Cancelled,
                AppointmentStatus::Absent,
                AppointmentStatus::Completed,
            ]);
        } else {
            $query->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Waitlisted,
            ]);
        }

        $items = $query
            ->with(['session.course', 'session.coach'])
            ->limit(50)
            ->get()
            ->map(fn (Appointment $appointment) => MemberAppointmentPresenter::toArray($appointment))
            ->values()
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(
        Request $request,
        MemberBookingAccessService $access,
        BookingPayableCardService $payableCards,
        AppointmentWriteService $writer,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $payload = $request->validate([
            'sessionId' => ['required', 'integer', 'min:1'],
            'memberCardId' => ['required', 'integer', 'min:1'],
            'commandKey' => ['required', 'uuid'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $session = $access->session($member, (int) $payload['sessionId']);
        $card = $payableCards->resolvePayableCard($member, $session, (int) $payload['memberCardId']);

        $result = $writer->createForMember(
            $member,
            $session,
            $card,
            $payload['commandKey'],
            $request->user()->id,
        );

        $status = $result['created'] ? 201 : 200;

        return ApiResponse::success(
            AppointmentPresenter::toArray($result['appointment']),
            $status,
        );
    }

    public function cancel(
        Request $request,
        int $appointment,
        MemberBookingAccessService $access,
        AppointmentWriteService $writer,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $appointmentModel = Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->whereKey($appointment)
            ->firstOrFail();

        $result = $writer->cancelForMember(
            $member,
            $appointmentModel,
            $payload['commandKey'],
            $request->user()->id,
        );

        return ApiResponse::success(AppointmentPresenter::toArray($result['appointment']));
    }

    public function promote(
        Request $request,
        int $appointment,
        MemberBookingAccessService $access,
        AppointmentWriteService $writer,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $appointmentModel = Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->whereKey($appointment)
            ->firstOrFail();

        $result = $writer->promoteForMember(
            $member,
            $appointmentModel,
            $payload['commandKey'],
            $request->user()->id,
        );

        return ApiResponse::success(
            AppointmentPresenter::toArray($result['appointment']),
            $result['created'] ? 201 : 200,
        );
    }
}
