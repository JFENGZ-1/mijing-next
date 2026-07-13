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

    $year = max(2000, $request->integer('year', (int) now()->format('Y')));
    $month = min(12, max(1, $request->integer('month', (int) now()->format('n'))));

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

    $year = max(2000, $request->integer('year', (int) now()->format('Y')));
    $month = min(12, max(1, $request->integer('month', (int) now()->format('n'))));
    $page = max(1, $request->integer('page', 1));
    $perPage = min(50, max(1, $request->integer('perPage', 20)));
    $cardProductId = $request->filled('cardProductId') ? $request->integer('cardProductId') : null;

    return ApiResponse::success(
      $reports->detail($staff, $siteModel, $year, $month, $cardProductId, $page, $perPage),
    );
  }

  private function staff(Request $request): Staff
  {
    return $request->attributes->get('staff_context');
  }
}
