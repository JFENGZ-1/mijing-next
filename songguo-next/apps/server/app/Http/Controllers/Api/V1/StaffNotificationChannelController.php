<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationChannelConfigRequest;
use App\Models\Staff;
use App\Services\Tenant\NotificationChannelConfigService;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffNotificationChannelController extends Controller
{
    public function show(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        NotificationChannelConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertNotificationChannelReadPermission($staff, $siteModel->id);

        return ApiResponse::success($config->config($siteModel));
    }

    public function update(
        UpdateNotificationChannelConfigRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        NotificationChannelConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertNotificationChannelWritePermission($staff, $siteModel->id);

        return ApiResponse::success($config->update($siteModel, $request->validated()));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
