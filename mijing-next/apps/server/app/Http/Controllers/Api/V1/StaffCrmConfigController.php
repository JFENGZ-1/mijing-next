<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCrmMemberFieldPolicyRequest;
use App\Models\Staff;
use App\Services\Members\MemberCrmFieldPolicyService;
use App\Services\Members\StaffCrmMemberFilterPresetService;
use App\Services\Members\StaffCrmSalesStaffService;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffCrmConfigController extends Controller
{
    public function memberFieldPolicy(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCrmFieldPolicyService $policy,
    ) {
        $staff = $this->staff($request);
        $staff->loadMissing('tenant');
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.read', $siteModel->id);

        return ApiResponse::success([
            'fields' => $policy->fields($staff->tenant),
        ]);
    }

    public function updateMemberFieldPolicy(
        UpdateCrmMemberFieldPolicyRequest $request,
        int $site,
        TenantSettingsAccessService $settingsAccess,
        MemberCrmFieldPolicyService $policy,
    ) {
        $staff = $this->staff($request);
        $staff->loadMissing('tenant');
        $siteModel = $settingsAccess->site($staff, $site);
        $settingsAccess->assertFieldConfigWritePermission($staff, $siteModel->id);

        return ApiResponse::success([
            'fields' => $policy->updateFields($staff->tenant, $request->validated('fields')),
        ]);
    }

    public function memberFilterPresets(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        StaffCrmMemberFilterPresetService $presets,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.read', $siteModel->id);

        return ApiResponse::success($presets->presets());
    }

    public function salesStaff(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        StaffCrmSalesStaffService $salesStaff,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.read', $siteModel->id);

        return ApiResponse::success([
            'items' => $salesStaff->list($staff, $siteModel),
        ]);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
