<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Dashboard\StaffDashboardAccessService;
use App\Services\Dashboard\StaffDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function summary(
        Request $request,
        int $site,
        StaffDashboardAccessService $access,
        StaffDashboardService $dashboard,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertSummaryPermission($staff, $siteModel->id);

        return ApiResponse::success($dashboard->summary($staff, $siteModel));
    }

    public function salesFeed(
        Request $request,
        int $site,
        StaffDashboardAccessService $access,
        StaffDashboardService $dashboard,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertSalesFeedPermission($staff, $siteModel->id);

        $page = min(max($request->integer('page', 1), 1), 1000);
        $perPage = min(max($request->integer('perPage', 20), 1), 50);

        return ApiResponse::success($dashboard->salesFeed($staff, $siteModel, $page, $perPage));
    }

    public function appointmentFeed(
        Request $request,
        int $site,
        StaffDashboardAccessService $access,
        StaffDashboardService $dashboard,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertAppointmentFeedPermission($staff, $siteModel->id);

        $page = min(max($request->integer('page', 1), 1), 1000);
        $perPage = min(max($request->integer('perPage', 20), 1), 50);

        return ApiResponse::success($dashboard->appointmentFeed($staff, $siteModel, $page, $perPage));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
