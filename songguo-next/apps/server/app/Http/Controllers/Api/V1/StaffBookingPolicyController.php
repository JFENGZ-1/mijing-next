<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertBookingPolicyRequest;
use App\Models\Staff;
use App\Services\Booking\BookingPolicyService;
use App\Services\Booking\StaffBookingPolicyAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffBookingPolicyController extends Controller
{
    public function show(
        Request $request,
        int $site,
        StaffBookingPolicyAccessService $access,
        BookingPolicyService $policies,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.policy.read', $siteModel->id);

        return ApiResponse::success($policies->policyForSite($staff, $siteModel));
    }

    public function update(
        UpsertBookingPolicyRequest $request,
        int $site,
        StaffBookingPolicyAccessService $access,
        BookingPolicyService $policies,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'booking.policy.write', $siteModel->id);

        return ApiResponse::success(
            $policies->savePolicy($staff, $siteModel, $request->validated()),
        );
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
