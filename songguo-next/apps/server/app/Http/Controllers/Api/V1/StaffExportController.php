<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Exports\ExportJobService;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffExportController extends Controller
{
    public function createMemberExport(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ExportJobService $exports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'export.member.create', $siteModel->id);

        $request->validate([
            'status' => ['sometimes', 'string', 'in:lead,active,frozen,closed'],
            'q' => ['sometimes', 'string', 'max:120'],
            'includeVisitors' => ['sometimes', 'boolean'],
            'tagIds' => ['sometimes', 'string', 'max:200'],
            'flag' => ['sometimes', 'string', 'max:200'],
            'sumMode' => ['sometimes', 'string', 'in:valid,invalid,noCard,blocked,monthNew,all'],
            'pinyinInitial' => ['sometimes', 'string', 'max:120'],
            'runOff' => ['sometimes', 'integer', 'in:1'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:40'],
        ]);

        return ApiResponse::success($exports->createMemberExport($staff, $siteModel, $request), 201);
    }

    public function listJobs(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        ExportJobService $exports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'export.job.read', $siteModel->id);

        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $page = max($request->integer('page', 1), 1);
        $perPage = min(max($request->integer('perPage', 20), 1), 50);

        return ApiResponse::success($exports->listJobs($staff, $siteModel, $page, $perPage));
    }

    public function downloadJob(
        Request $request,
        int $site,
        int $job,
        StaffMemberAccessService $access,
        ExportJobService $exports,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $jobModel = $exports->findJob($staff, $siteModel, $job);

        return $exports->download($staff, $siteModel, $jobModel, $request);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
