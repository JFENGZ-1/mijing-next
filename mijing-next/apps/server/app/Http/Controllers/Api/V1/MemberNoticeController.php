<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\MemberDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberNoticeController extends Controller
{
    public function index(
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

        return ApiResponse::success(['items' => $dashboard->noticeList($member, $site)]);
    }

    public function show(
        Request $request,
        int $notice,
        MemberBookingAccessService $access,
        MemberDashboardService $dashboard,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));

        return ApiResponse::success($dashboard->noticeDetail($member, $notice));
    }
}
