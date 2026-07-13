<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportFinanceProfitService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffReportFinanceController extends Controller
{
    public function profitSummary(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportFinanceProfitService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.finance.read', $siteModel->id);

        return ApiResponse::success($reporting->summary($staff, $siteModel));
    }

    public function profitCalendar(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportFinanceProfitService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.finance.read', $siteModel->id);

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        return ApiResponse::success($reporting->calendar($staff, $siteModel, $request->integer('year')));
    }

    public function profitDaily(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportFinanceProfitService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.finance.read', $siteModel->id);

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return ApiResponse::success($reporting->daily(
            $staff,
            $siteModel,
            $request->integer('year'),
            $request->integer('month'),
        ));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
