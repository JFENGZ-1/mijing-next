<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\MemberDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberHomeController extends Controller
{
    public function show(
        Request $request,
        MemberBookingAccessService $access,
        MemberDashboardService $dashboard,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'siteId' => ['required', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $site = $access->site($member, $request->integer('siteId'));

        return ApiResponse::success($dashboard->home($request->user(), $member, $site));
    }
}
