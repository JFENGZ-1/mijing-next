<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteClosureRequest;
use App\Http\Requests\UpdateSiteClosureRequest;
use App\Models\Staff;
use App\Services\Tenant\SiteClosureService;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffSiteClosureController extends Controller
{
    public function index(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        SiteClosureService $closures,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertClosureReadPermission($staff, $siteModel->id);

        return ApiResponse::success($closures->list($siteModel));
    }

    public function store(
        StoreSiteClosureRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        SiteClosureService $closures,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertClosureWritePermission($staff, $siteModel->id);

        return ApiResponse::success(
            $closures->create($siteModel, $staff, $request->validated()),
            201,
        );
    }

    public function update(
        UpdateSiteClosureRequest $request,
        int $site,
        int $closure,
        TenantSettingsAccessService $access,
        SiteClosureService $closures,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertClosureWritePermission($staff, $siteModel->id);

        $period = $closures->findForSite($siteModel, $closure);

        return ApiResponse::success($closures->update($period, $request->validated()));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
