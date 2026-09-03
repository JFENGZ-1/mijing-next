<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\CalendarMonthOptionsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffReportCalendarController extends Controller
{
    public function monthOptions(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        CalendarMonthOptionsService $calendar,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        return ApiResponse::success($calendar->monthOptions($staff, $siteModel));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
