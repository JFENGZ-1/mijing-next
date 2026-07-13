<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertStaffRoleRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Services\Staff\StaffRoleService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffRoleController extends Controller
{
    public function show(
        Request $request,
        int $role,
        StaffRoleService $roles,
    ) {
        $staff = $this->staff($request);
        abort_unless($staff->hasPermission('staff.directory.read'), 403, 'PERMISSION_DENIED');

        $roleModel = Role::query()
            ->whereKey($role)
            ->where('tenant_id', $staff->tenant_id)
            ->firstOrFail();

        return ApiResponse::success($roles->show($staff, $roleModel));
    }

    public function upsert(
        UpsertStaffRoleRequest $request,
        StaffRoleService $roles,
    ) {
        $staff = $this->staff($request);
        abort_unless($staff->hasPermission('staff.directory.write'), 403, 'PERMISSION_DENIED');

        return ApiResponse::success(
            $roles->upsert($staff, $request->validated()),
            $request->filled('id') ? 200 : 201,
        );
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
