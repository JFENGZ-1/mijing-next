<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportChangeLogService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffReportChangeLogController extends Controller
{
    public function index(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportChangeLogService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.read', $siteModel->id);

        $category = (string) $request->query('category', 'all');
        $dateFrom = $request->filled('dateFrom') ? (string) $request->query('dateFrom') : null;
        $dateTo = $request->filled('dateTo') ? (string) $request->query('dateTo') : null;
        $actorStaffId = $request->filled('actorStaffId') ? $request->integer('actorStaffId') : null;
        $page = max(1, $request->integer('page', 1));
        $perPage = min(50, max(1, $request->integer('perPage', 20)));

        return ApiResponse::success(
            $reports->list($staff, $siteModel, $category, $dateFrom, $dateTo, $actorStaffId, $page, $perPage),
        );
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
