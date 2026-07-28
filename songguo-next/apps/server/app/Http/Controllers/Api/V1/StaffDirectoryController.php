<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffDirectoryRequest;
use App\Http\Requests\UpdateStaffDirectoryRequest;
use App\Models\Staff;
use App\Services\Staff\StaffDirectoryAccessService;
use App\Services\Staff\StaffDirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffDirectoryController extends Controller
{
    public function index(
        Request $request,
        int $site,
        StaffDirectoryAccessService $access,
        StaffDirectoryService $directory,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertReadPermission($staff, $siteModel->id);

        return ApiResponse::success($directory->list($staff, $siteModel));
    }

    public function show(
        Request $request,
        int $site,
        int $staffMember,
        StaffDirectoryAccessService $access,
        StaffDirectoryService $directory,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertReadPermission($staff, $siteModel->id);
        $member = $access->directoryStaff($staff, $siteModel, $staffMember);

        return ApiResponse::success($directory->show($staff, $siteModel, $member));
    }

    public function store(
        StoreStaffDirectoryRequest $request,
        int $site,
        StaffDirectoryAccessService $access,
        StaffDirectoryService $directory,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertWritePermission($staff, $siteModel->id);

        return ApiResponse::success(
            $directory->create($staff, $siteModel, $request->validated()),
            201,
        );
    }

    public function update(
        UpdateStaffDirectoryRequest $request,
        int $site,
        int $staffMember,
        StaffDirectoryAccessService $access,
        StaffDirectoryService $directory,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertWritePermission($staff, $siteModel->id);
        $member = $access->directoryStaff($staff, $siteModel, $staffMember);

        return ApiResponse::success(
            $directory->update($staff, $siteModel, $member, $request->validated()),
        );
    }

    public function departure(
        Request $request,
        int $site,
        int $staffMember,
        StaffDirectoryAccessService $access,
        StaffDirectoryService $directory,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertDeparturePermission($staff, $siteModel->id);
        $member = $access->directoryStaff($staff, $siteModel, $staffMember);

        return ApiResponse::success(
            $directory->softDeparture($staff, $siteModel, $member, $request->boolean('force')),
        );
    }

    public function transferOwnership(
        Request $request,
        int $site,
        int $staffMember,
        StaffDirectoryAccessService $access,
        StaffDirectoryService $directory,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertTransferOwnershipPermission($staff, $siteModel->id);
        $member = $access->directoryStaff($staff, $siteModel, $staffMember);

        return ApiResponse::success(
            $directory->transferOwnership($staff, $siteModel, $member),
        );
    }

    public function roles(Request $request, StaffDirectoryAccessService $access, StaffDirectoryService $directory)
    {
        $staff = $this->staff($request);
        abort_unless(
            $staff->hasPermission('staff.directory.read'),
            403,
            'PERMISSION_DENIED',
        );

        return ApiResponse::success([
            'items' => $directory->roleOptions($staff),
        ]);
    }

    public function permissionCatalog(Request $request, StaffDirectoryService $directory)
    {
        $staff = $this->staff($request);
        abort_unless(
            $staff->hasPermission('staff.directory.write'),
            403,
            'PERMISSION_DENIED',
        );

        return ApiResponse::success([
            'modules' => $directory->permissionCatalog(),
        ]);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
