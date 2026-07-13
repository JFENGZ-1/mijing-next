<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Schedule\SchedulePreflightService;
use App\Services\Schedule\ScheduleRecurringTemplateService;
use App\Services\Schedule\ScheduleExportImageService;
use App\Services\Schedule\ScheduleSessionColorService;
use App\Services\Schedule\StaffScheduleSessionAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffScheduleExtrasController extends Controller
{
    public function copyPreflight(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        SchedulePreflightService $preflight,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.batch.copy', $siteModel->id);

        $payload = $request->validate([
            'sourceSessionIds' => ['sometimes', 'array', 'min:1'],
            'sourceSessionIds.*' => ['integer', 'min:1'],
            'sourceFrom' => ['sometimes', 'date_format:Y-m-d'],
            'sourceTo' => ['required_with:sourceFrom', 'date_format:Y-m-d', 'after:sourceFrom'],
            'targetFrom' => ['required_with:sourceFrom', 'date_format:Y-m-d'],
            'dayOffset' => ['sometimes', 'integer'],
        ]);

        return ApiResponse::success($preflight->copyPreflight($staff, $siteModel, $payload));
    }

    public function changeCoursePreflight(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        SchedulePreflightService $preflight,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);

        $payload = $request->validate([
            'sessionIds' => ['required', 'array', 'min:1'],
            'sessionIds.*' => ['integer', 'min:1'],
            'targetCourseId' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponse::success($preflight->changeCoursePreflight($staff, $siteModel, $payload));
    }

    public function sessionColors(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionColorService $colors,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);

        return ApiResponse::success($colors->forSite($staff, $siteModel));
    }

    public function updateSessionColors(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionColorService $colors,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);

        $payload = $request->validate([
            'palette' => ['required', 'array', 'min:1', 'max:20'],
            'palette.*.key' => ['required', 'string', 'max:40'],
            'palette.*.label' => ['required', 'string', 'max:40'],
            'palette.*.color' => ['nullable', 'string', 'max:20'],
        ]);

        return ApiResponse::success($colors->save($staff, $siteModel, $payload));
    }

    public function recurringTemplate(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleRecurringTemplateService $templates,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);

        $payload = $request->validate([
            'courseId' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponse::success($templates->forCourse($staff, $siteModel, (int) $payload['courseId']));
    }

    public function exportImage(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleExportImageService $exporter,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);

        $payload = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return ApiResponse::success($exporter->export($staff, $siteModel, $payload));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
