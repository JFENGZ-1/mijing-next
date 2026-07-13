<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Platform\PlatformSubscriptionAccessService;
use App\Services\Platform\PlatformSubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffPlatformSubscriptionController extends Controller
{
    public function pricing(
        Request $request,
        PlatformSubscriptionAccessService $access,
        PlatformSubscriptionService $subscriptions,
    ) {
        $staff = $this->staff($request);
        $access->assertReadPermission($staff);

        return ApiResponse::success($subscriptions->pricing());
    }

    public function agreement(
        Request $request,
        PlatformSubscriptionAccessService $access,
        PlatformSubscriptionService $subscriptions,
    ) {
        $staff = $this->staff($request);
        $access->assertReadPermission($staff);

        return ApiResponse::success($subscriptions->agreement());
    }

    public function siteStatus(
        Request $request,
        int $site,
        PlatformSubscriptionAccessService $access,
        PlatformSubscriptionService $subscriptions,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertReadPermission($staff, $siteModel->id);

        return ApiResponse::success($subscriptions->siteStatus($siteModel));
    }

    public function orders(
        Request $request,
        PlatformSubscriptionAccessService $access,
        PlatformSubscriptionService $subscriptions,
    ) {
        $staff = $this->staff($request);
        $access->assertReadPermission($staff);

        return ApiResponse::success($subscriptions->orders($staff));
    }

    public function pay(
        Request $request,
        PlatformSubscriptionAccessService $access,
        PlatformSubscriptionService $subscriptions,
    ) {
        $staff = $this->staff($request);
        $access->assertReadPermission($staff);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'planId' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponse::success($subscriptions->pay($staff, $payload), 201);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
