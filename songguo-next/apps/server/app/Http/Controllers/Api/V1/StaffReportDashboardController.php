<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffReportDashboardController extends Controller
{
    public function summary(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportDashboardService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.dashboard.read', $siteModel->id);

        return ApiResponse::success($reporting->summary($staff, $siteModel));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
