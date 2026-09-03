<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Payroll\PayrollRecomputeJobService;
use App\Services\Payroll\PayrollReportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffPayrollReportController extends Controller
{
    public function coachReports(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollReportService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.report.read', $siteModel->id);

        [$year, $month] = $this->period($request);

        return ApiResponse::success($reports->coachReports($staff, $siteModel, $year, $month));
    }

    public function coachReportDetail(
        Request $request,
        int $site,
        int $coachStaff,
        StaffMemberAccessService $access,
        PayrollReportService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.report.read', $siteModel->id);

        $coach = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($coachStaff)
            ->firstOrFail();

        [$year, $month] = $this->period($request);

        return ApiResponse::success($reports->coachReportDetail($staff, $siteModel, $coach, $year, $month));
    }

    public function salesReports(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollReportService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.report.read', $siteModel->id);

        [$year, $month] = $this->period($request);

        return ApiResponse::success($reports->salesReports($staff, $siteModel, $year, $month));
    }

    public function salesReportDetail(
        Request $request,
        int $site,
        int $salesStaff,
        StaffMemberAccessService $access,
        PayrollReportService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.report.read', $siteModel->id);

        $person = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($salesStaff)
            ->firstOrFail();

        [$year, $month] = $this->period($request);

        return ApiResponse::success($reports->salesReportDetail($staff, $siteModel, $person, $year, $month));
    }

    public function courseCommission(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollReportService $reports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.report.read', $siteModel->id);

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'staffId' => ['required', 'integer', 'min:1'],
        ]);

        $coach = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($request->integer('staffId'))
            ->firstOrFail();

        return ApiResponse::success($reports->courseCommissionReport(
            $staff,
            $siteModel,
            $coach,
            $request->integer('year'),
            $request->integer('month'),
        ));
    }

    public function createRecomputeJob(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollRecomputeJobService $jobs,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.recompute.execute', $siteModel->id);

        $payload = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'scope' => ['required', 'string', Rule::in(['site', 'coach', 'sales'])],
            'staffId' => ['nullable', 'integer', 'min:1'],
            'commandKey' => ['required', 'string', 'max:120'],
        ]);

        return ApiResponse::success($jobs->createJob($staff, $siteModel, $request, $payload), 201);
    }

    public function listRecomputeJobs(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollRecomputeJobService $jobs,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.recompute.execute', $siteModel->id);

        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $page = max($request->integer('page', 1), 1);
        $perPage = min(max($request->integer('perPage', 20), 1), 50);

        return ApiResponse::success($jobs->listJobs($staff, $siteModel, $page, $perPage));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return [$request->integer('year'), $request->integer('month')];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
