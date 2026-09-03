<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportCardSalesService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffReportCardSalesController extends Controller
{
    public function summary(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportCardSalesService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.read', $siteModel->id);

        $filters = $this->filters($request);
        $year = (int) ($filters['year'] ?? now()->format('Y'));
        $month = (int) ($filters['month'] ?? now()->format('n'));

        return ApiResponse::success($reports->summary($staff, $siteModel, $year, $month));
    }

    public function detail(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportCardSalesService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.read', $siteModel->id);

        $filters = $this->filters($request, withPagination: true);
        $year = (int) ($filters['year'] ?? now()->format('Y'));
        $month = (int) ($filters['month'] ?? now()->format('n'));
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['perPage'] ?? 20);
        $cardProductId = isset($filters['cardProductId']) ? (int) $filters['cardProductId'] : null;

        return ApiResponse::success(
            $reports->detail($staff, $siteModel, $year, $month, $cardProductId, $page, $perPage),
        );
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, bool $withPagination = false): array
    {
        $rules = [
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
        if ($withPagination) {
            $rules += [
                'cardProductId' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'page' => ['sometimes', 'integer', 'min:1'],
                'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ];
        }

        return $request->validate($rules);
    }
}
