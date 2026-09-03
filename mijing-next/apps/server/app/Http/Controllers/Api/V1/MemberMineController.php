<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\MemberDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberMineController extends Controller
{
    public function show(
        Request $request,
        MemberBookingAccessService $access,
        MemberDashboardService $dashboard,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));

        return ApiResponse::success($dashboard->mine($request->user(), $member));
    }
}
