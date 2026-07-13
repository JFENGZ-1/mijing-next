<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffVacationRequest;
use App\Http\Requests\UpdateStaffVacationRequest;
use App\Models\Staff;
use App\Services\Tenant\StaffVacationService;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffVacationController extends Controller
{
    public function index(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        StaffVacationService $vacations,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertVacationReadPermission($staff, $siteModel->id);

        return ApiResponse::success($vacations->rollup($siteModel));
    }

    public function staffIndex(
        Request $request,
        int $site,
        int $staffMember,
        TenantSettingsAccessService $access,
        StaffVacationService $vacations,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertVacationReadPermission($staff, $siteModel->id);

        $target = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($staffMember)
            ->firstOrFail();

        return ApiResponse::success($vacations->forStaff($siteModel, $target));
    }

    public function store(
        StoreStaffVacationRequest $request,
        int $site,
        int $staffMember,
        TenantSettingsAccessService $access,
        StaffVacationService $vacations,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertVacationWritePermission($staff, $siteModel->id);

        $target = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($staffMember)
            ->firstOrFail();

        return ApiResponse::success(
            $vacations->create($siteModel, $target, $staff, $request->validated()),
            201,
        );
    }

    public function update(
        UpdateStaffVacationRequest $request,
        int $site,
        int $staffMember,
        int $vacation,
        TenantSettingsAccessService $access,
        StaffVacationService $vacations,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertVacationWritePermission($staff, $siteModel->id);

        $target = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($staffMember)
            ->firstOrFail();

        $entry = $vacations->findForStaff($siteModel, $target, $vacation);

        return ApiResponse::success($vacations->update($entry, $request->validated()));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
