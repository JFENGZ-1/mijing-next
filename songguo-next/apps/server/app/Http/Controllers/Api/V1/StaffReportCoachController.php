<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportCoachService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffReportCoachController extends Controller
{
    public function appointmentDetail(
        Request $request,
        int $site,
        int $coachStaff,
        StaffMemberAccessService $access,
        ReportCoachService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.coach.read', $siteModel->id);

        $coach = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($coachStaff)
            ->with('account')
            ->firstOrFail();

        [$year, $month, $sessionKind, $page, $perPage] = $this->periodPaginationAndKind($request);

        return ApiResponse::success($reporting->appointmentDetail(
            $staff,
            $siteModel,
            $coach,
            $year,
            $month,
            $sessionKind,
            $page,
            $perPage,
        ));
    }

    public function monthlyRank(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportCoachService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.coach.read', $siteModel->id);

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'sortBy' => ['sometimes', 'string', Rule::in(['total', 'group', 'private'])],
        ]);

        return ApiResponse::success($reporting->monthlyRank(
            $staff,
            $siteModel,
            $request->integer('year'),
            $request->integer('month'),
            $request->string('sortBy', 'total')->toString(),
        ));
    }

    /**
     * @return array{0: int, 1: int, 2: string, 3: int, 4: int}
     */
    private function periodPaginationAndKind(Request $request): array
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'sessionKind' => ['sometimes', 'string', Rule::in(['group', 'private', 'all'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return [
            $request->integer('year'),
            $request->integer('month'),
            $request->string('sessionKind', 'all')->toString(),
            max($request->integer('page', 1), 1),
            min(max($request->integer('perPage', 20), 1), 50),
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
