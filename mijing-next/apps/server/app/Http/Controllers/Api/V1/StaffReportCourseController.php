<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reporting\ReportCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffReportCourseController extends Controller
{
    public function summary(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportCourseService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.course.read', $siteModel->id);

        return ApiResponse::success($reporting->summary($staff, $siteModel));
    }

    public function calendar(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportCourseService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.course.read', $siteModel->id);

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        return ApiResponse::success($reporting->calendar($staff, $siteModel, $request->integer('year')));
    }

    public function daily(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ReportCourseService $reporting,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'report.course.read', $siteModel->id);

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'courseKind' => ['sometimes', 'string', Rule::in(['group', 'private', 'all'])],
        ]);

        return ApiResponse::success($reporting->daily(
            $staff,
            $siteModel,
            $request->integer('year'),
            $request->integer('month'),
            $request->string('courseKind', 'all')->toString(),
        ));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
