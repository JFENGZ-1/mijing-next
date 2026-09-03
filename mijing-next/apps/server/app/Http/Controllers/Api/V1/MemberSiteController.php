<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\MemberSitePublicService;
use App\Services\Members\MemberSiteClosureStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberSiteController extends Controller
{
    public function show(
        Request $request,
        int $site,
        MemberBookingAccessService $access,
        MemberSitePublicService $sites,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $siteModel = $access->site($member, $site);

        return ApiResponse::success($sites->detail($siteModel));
    }

    public function closureStatus(
        Request $request,
        int $site,
        MemberBookingAccessService $access,
        MemberSiteClosureStatusService $closures,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $siteModel = $access->site($member, $site);

        return ApiResponse::success($closures->status($member, $siteModel));
    }
}
