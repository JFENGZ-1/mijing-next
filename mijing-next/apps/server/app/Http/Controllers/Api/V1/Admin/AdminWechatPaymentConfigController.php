<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWechatPaymentConfigRequest;
use App\Models\SuperAdmin;
use App\Services\Admin\PlatformPaymentConfigService;
use App\Support\ApiResponse;

class AdminWechatPaymentConfigController extends Controller
{
    public function show(PlatformPaymentConfigService $service)
    {
        return ApiResponse::success($service->safeWechatConfig());
    }

    public function update(
        UpdateWechatPaymentConfigRequest $request,
        PlatformPaymentConfigService $service,
    ) {
        /** @var SuperAdmin $admin */
        $admin = $request->user();

        return ApiResponse::success($service->updateWechat($request->validated(), $admin));
    }
}
