<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSiteProfileRequest;
use App\Models\Staff;
use App\Services\Site\SiteProfileAccessService;
use App\Services\Site\SiteProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffSiteProfileController extends Controller
{
    public function show(
        Request $request,
        int $site,
        SiteProfileAccessService $access,
        SiteProfileService $profiles,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertReadPermission($staff, $siteModel->id);

        return ApiResponse::success($profiles->read($siteModel));
    }

    public function update(
        UpdateSiteProfileRequest $request,
        int $site,
        SiteProfileAccessService $access,
        SiteProfileService $profiles,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertWritePermission($staff, $siteModel->id);

        return ApiResponse::success(
            $profiles->update($siteModel, $request->validated()),
        );
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
