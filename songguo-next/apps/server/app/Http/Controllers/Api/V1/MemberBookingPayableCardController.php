<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\BookingPayableCardService;
use App\Services\Booking\MemberBookingAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberBookingPayableCardController extends Controller
{
    public function index(
        Request $request,
        int $session,
        MemberBookingAccessService $access,
        BookingPayableCardService $payableCards,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $sessionModel = $access->session($member, $session);

        return ApiResponse::success([
            'items' => $payableCards->payableCardsForSession($member, $sessionModel),
        ]);
    }
}
