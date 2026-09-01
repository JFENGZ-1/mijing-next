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

        $filters = $request->validate([
            'category' => ['sometimes', 'string', 'in:all,issue,holiday,freeze,archive,adjust'],
            'dateFrom' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'dateTo' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
            'actorStaffId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $category = (string) ($filters['category'] ?? 'all');
        $dateFrom = $filters['dateFrom'] ?? null;
        $dateTo = $filters['dateTo'] ?? null;
        $actorStaffId = isset($filters['actorStaffId']) ? (int) $filters['actorStaffId'] : null;
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['perPage'] ?? 20);

        return ApiResponse::success(
            $reports->list($staff, $siteModel, $category, $dateFrom, $dateTo, $actorStaffId, $page, $perPage),
        );
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
