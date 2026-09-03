<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Services\Tenant\TenantSupportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffSupportController extends Controller
{
    public function contact(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        TenantSupportService $support,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertSupportReadPermission($staff, $siteModel->id);

        return ApiResponse::success($support->contact($staff, $siteModel));
    }

    public function videoHelp(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        TenantSupportService $support,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertSupportReadPermission($staff, $siteModel->id);

        return ApiResponse::success($support->videoHelp($staff));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
