<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffCrmMemberListService;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffCrmDashboardController extends Controller
{
    public function summary(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        StaffCrmMemberListService $crmList,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.read', $siteModel->id);

        return ApiResponse::success($crmList->dashboardSummary($staff, $siteModel));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
