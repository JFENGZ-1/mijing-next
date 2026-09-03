<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Booking\MemberBookingBoardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberBookingSessionController extends Controller
{
    public function show(
        Request $request,
        int $session,
        MemberBookingAccessService $access,
        MemberBookingBoardService $board,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $sessionModel = $access->session($member, $session);
        $site = $access->site($member, $sessionModel->site_id);

        return ApiResponse::success($board->sessionDetail($site, $sessionModel, $member));
    }
}
