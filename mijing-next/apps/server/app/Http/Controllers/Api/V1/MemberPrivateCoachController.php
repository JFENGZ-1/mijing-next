<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Booking\MemberPrivateCoachBookingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberPrivateCoachController extends Controller
{
    public function profile(
        Request $request,
        MemberBookingAccessService $access,
        MemberPrivateCoachBookingService $booking,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'siteId' => ['required', 'integer', 'min:1'],
            'coachStaffId' => ['required', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $site = $access->site($member, $request->integer('siteId'));

        return ApiResponse::success($booking->profile($site, $request->integer('coachStaffId')));
    }

    public function timeSlots(
        Request $request,
        MemberBookingAccessService $access,
        MemberPrivateCoachBookingService $booking,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'siteId' => ['required', 'integer', 'min:1'],
            'coachStaffId' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'courseId' => ['sometimes', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $site = $access->site($member, $request->integer('siteId'));

        return ApiResponse::success($booking->timeSlots(
            $site,
            $request->integer('coachStaffId'),
            $request->string('date')->toString(),
            $request->integer('courseId'),
        ));
    }

    public function payableCards(
        Request $request,
        MemberBookingAccessService $access,
        MemberPrivateCoachBookingService $booking,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'siteId' => ['required', 'integer', 'min:1'],
            'coachStaffId' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'courseId' => ['sometimes', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $site = $access->site($member, $request->integer('siteId'));

        return ApiResponse::success([
            'items' => $booking->payableCards(
                $site,
                $member,
                $request->integer('coachStaffId'),
                $request->string('date')->toString(),
                $request->string('start')->toString(),
                $request->integer('courseId'),
            ),
        ]);
    }

    public function book(
        Request $request,
        MemberBookingAccessService $access,
        MemberPrivateCoachBookingService $booking,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $payload = $request->validate([
            'siteId' => ['required', 'integer', 'min:1'],
            'coachStaffId' => ['required', 'integer', 'min:1'],
            'memberCardId' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'courseId' => ['sometimes', 'integer', 'min:1'],
            'remark' => ['sometimes', 'nullable', 'string', 'max:150'],
            'commandKey' => ['required', 'uuid'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $site = $access->site($member, (int) $payload['siteId']);

        $result = $booking->book(
            $site,
            $member,
            (int) $request->user()->id,
            (int) $payload['coachStaffId'],
            (int) $payload['memberCardId'],
            $payload['date'],
            $payload['start'],
            $payload['commandKey'],
            (int) ($payload['courseId'] ?? 0),
            isset($payload['remark']) ? trim((string) $payload['remark']) : null,
        );

        return ApiResponse::success($result, 201);
    }
}
