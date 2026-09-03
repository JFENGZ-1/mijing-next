<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\OfficialAccountFollowService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberOfficialAccountFollowController extends Controller
{
    public function show(
        Request $request,
        MemberBookingAccessService $access,
        OfficialAccountFollowService $follow,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'siteId' => ['nullable', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $site = $request->filled('siteId')
            ? $access->site($member, $request->integer('siteId'))
            : null;

        return ApiResponse::success($follow->content($member, $site));
    }
}
