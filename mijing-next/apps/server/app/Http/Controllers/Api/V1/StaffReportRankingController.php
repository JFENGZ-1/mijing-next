<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportRankingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffReportRankingController extends Controller
{
    public function orders(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        [$year, $month, $page, $perPage] = $this->periodAndPagination($request);

        return ApiResponse::success($ranking->orderRank($staff, $siteModel, $year, $month, $page, $perPage));
    }

    public function courseAttendance(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        [$year, $month, $page, $perPage] = $this->periodAndPagination($request);

        return ApiResponse::success($ranking->courseAttendanceRank($staff, $siteModel, $year, $month, $page, $perPage));
    }

    public function points(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site)->load('tenant');
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        [$year, $month, $page, $perPage] = $this->periodAndPagination($request);

        return ApiResponse::success($ranking->pointsRank($staff, $siteModel, $year, $month, $page, $perPage));
    }

    public function salesStaff(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        [$year, $month, $page, $perPage] = $this->periodAndPagination($request);

        return ApiResponse::success($ranking->salesStaffRank($staff, $siteModel, $year, $month, $page, $perPage));
    }

    public function salesStaffDetail(
        Request $request,
        int $site,
        int $salesStaff,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        [$year, $month, $page, $perPage] = $this->periodAndPagination($request);

        $salesperson = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($salesStaff)
            ->firstOrFail();

        return ApiResponse::success(
            $ranking->salesStaffDetail($staff, $siteModel, $salesperson, $year, $month, $page, $perPage),
        );
    }

    public function memberCardConsumption(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        [$year, $month, $page, $perPage] = $this->periodAndPagination($request);

        return ApiResponse::success($ranking->memberCardConsumptionRank($staff, $siteModel, $year, $month, $page, $perPage));
    }

    public function cardProductSales(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        [$year, $month, $page, $perPage] = $this->periodAndPagination($request);

        return ApiResponse::success($ranking->cardProductSalesRank($staff, $siteModel, $year, $month, $page, $perPage));
    }

    public function cardProductAnalytics(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportRankingService $ranking,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.rankings.read', $siteModel->id);

        return ApiResponse::success($ranking->cardProductAnalytics($staff, $siteModel));
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function periodAndPagination(Request $request): array
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return [
            $request->integer('year'),
            $request->integer('month'),
            max($request->integer('page', 1), 1),
            min(max($request->integer('perPage', 20), 1), 50),
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
