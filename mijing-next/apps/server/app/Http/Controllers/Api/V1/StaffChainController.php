<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Chain\ChainAccessService;
use App\Services\Chain\ChainBrandService;
use App\Services\Chain\ChainReportService;
use App\Services\Chain\ChainSiteService;
use App\Services\Chain\ChainStaffService;
use App\Services\Chain\ChainStoreCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffChainController extends Controller
{
    public function sites(
        Request $request,
        ChainAccessService $access,
        ChainSiteService $sites,
    ) {
        $staff = $this->staff($request);
        $access->assertChainManagementRead($staff);

        return ApiResponse::success($sites->list($staff));
    }

    public function brand(
        Request $request,
        ChainAccessService $access,
        ChainBrandService $brand,
        ChainSiteService $sites,
    ) {
        $staff = $this->staff($request);
        $access->assertChainManagementRead($staff);

        $siteCount = count($sites->list($staff)['sites']);

        return ApiResponse::success($brand->show($staff, $siteCount));
    }

    public function updateBrand(
        Request $request,
        ChainAccessService $access,
        ChainBrandService $brand,
        ChainSiteService $sites,
    ) {
        $staff = $this->staff($request);
        $access->assertChainManagementRead($staff);
        abort_unless($staff->hasPermission('organization.site.manage'), 403, 'PERMISSION_DENIED');

        $payload = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'logoUrl' => ['sometimes', 'nullable', 'string', 'max:512'],
        ]);

        $siteCount = count($sites->list($staff)['sites']);

        return ApiResponse::success($brand->upsert($staff, $payload, $siteCount));
    }

    public function storeCourses(
        Request $request,
        ChainAccessService $access,
        ChainStoreCourseService $storeCourses,
    ) {
        $staff = $this->staff($request);
        $access->assertChainManagementRead($staff);
        abort_unless($staff->hasPermission('card-product.editor.write'), 403, 'PERMISSION_DENIED');

        return ApiResponse::success($storeCourses->list($staff));
    }

    public function staffDirectory(
        Request $request,
        ChainAccessService $access,
        ChainStaffService $chainStaff,
    ) {
        $staff = $this->staff($request);
        $access->assertChainManagementRead($staff);
        abort_unless($staff->hasPermission('staff.directory.read'), 403, 'PERMISSION_DENIED');

        return ApiResponse::success($chainStaff->list($staff));
    }

    public function financeSummary(
        Request $request,
        ChainAccessService $access,
        ChainReportService $reports,
    ) {
        $staff = $this->staff($request);
        $access->assertChainRead($staff);

        $siteModels = $access->resolveReportSites($staff, $request->query('siteIds'));

        return ApiResponse::success($reports->financeSummary($staff, $siteModels));
    }

    public function courseSummary(
        Request $request,
        ChainAccessService $access,
        ChainReportService $reports,
    ) {
        $staff = $this->staff($request);
        $access->assertChainRead($staff);

        $siteModels = $access->resolveReportSites($staff, $request->query('siteIds'));

        return ApiResponse::success($reports->courseSummary($staff, $siteModels));
    }

    public function membersSummary(
        Request $request,
        ChainAccessService $access,
        ChainReportService $reports,
    ) {
        $staff = $this->staff($request);
        $access->assertChainRead($staff);

        $siteModels = $access->resolveReportSites($staff, $request->query('siteIds'));

        return ApiResponse::success($reports->membersSummary($staff, $siteModels));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
