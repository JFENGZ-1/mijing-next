<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Booking\MemberBookingBoardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberBookingCatalogController extends Controller
{
    public function index(
        Request $request,
        MemberBookingAccessService $access,
        MemberBookingBoardService $board,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'siteId' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $site = $access->site($member, $request->integer('siteId'));

        return ApiResponse::success($board->catalog($site, $request->string('date')->toString(), $member));
    }
}
