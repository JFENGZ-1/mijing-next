<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Points\PointsConfigService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffPointsConfigController extends Controller
{
    public function show(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PointsConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'points.config.read', $siteModel->id);

        return ApiResponse::success($config->get($staff, $siteModel));
    }

    public function update(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PointsConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'points.config.write', $siteModel->id);

        $payload = $request->validate([
            'pointsEnabled' => ['sometimes', 'boolean'],
            'descriptionText' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'policy' => ['sometimes', 'array'],
            'policy.earnPerVisit' => ['sometimes', 'integer', 'min:0'],
            'policy.earnPerPurchase' => ['sometimes', 'integer', 'min:0'],
            'policy.debitEnabled' => ['sometimes', 'boolean'],
            'policy.descriptionText' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::success($config->save($staff, $siteModel, $payload));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
