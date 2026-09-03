<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMembershipAgreementRequest;
use App\Models\Staff;
use App\Services\Tenant\TenantLegalConfigService;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffLegalConfigController extends Controller
{
    public function membershipAgreement(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        TenantLegalConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertLegalReadPermission($staff, $siteModel->id);

        return ApiResponse::success($config->membershipAgreement($siteModel));
    }

    public function updateMembershipAgreement(
        UpdateMembershipAgreementRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        TenantLegalConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertLegalWritePermission($staff, $siteModel->id);

        return ApiResponse::success($config->updateMembershipAgreement($siteModel, $request->validated()));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
