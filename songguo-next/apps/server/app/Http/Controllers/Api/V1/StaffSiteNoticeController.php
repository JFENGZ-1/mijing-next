<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteNoticeRequest;
use App\Http\Requests\UpdateSiteNoticeRequest;
use App\Models\Staff;
use App\Services\Tenant\SiteNoticeAdminService;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffSiteNoticeController extends Controller
{
    public function index(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        SiteNoticeAdminService $notices,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertAnnouncementReadPermission($staff, $siteModel->id);

        return ApiResponse::success($notices->list($siteModel));
    }

    public function store(
        StoreSiteNoticeRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        SiteNoticeAdminService $notices,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertAnnouncementWritePermission($staff, $siteModel->id);

        return ApiResponse::success(
            $notices->create($siteModel, $request->validated()),
            201,
        );
    }

    public function update(
        UpdateSiteNoticeRequest $request,
        int $site,
        int $notice,
        TenantSettingsAccessService $access,
        SiteNoticeAdminService $notices,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertAnnouncementWritePermission($staff, $siteModel->id);

        $entry = $notices->findForSite($siteModel, $notice);

        return ApiResponse::success($notices->update($entry, $request->validated()));
    }

    public function archive(
        Request $request,
        int $site,
        int $notice,
        TenantSettingsAccessService $access,
        SiteNoticeAdminService $notices,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertAnnouncementWritePermission($staff, $siteModel->id);

        $entry = $notices->findForSite($siteModel, $notice);

        return ApiResponse::success($notices->archive($entry));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
