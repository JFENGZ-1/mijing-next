<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMemberCarouselRequest;
use App\Http\Requests\UpdateMemberMiniappLayoutRequest;
use App\Http\Requests\UpdateMemberOnboardingHelpRequest;
use App\Http\Requests\UpdateMemberWarmHintRequest;
use App\Models\Staff;
use App\Services\Tenant\MemberExperienceConfigService;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffMemberExperienceController extends Controller
{
    public function warmHint(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberExperienceReadPermission($staff, $siteModel->id);

        return ApiResponse::success($config->warmHints($siteModel));
    }

    public function updateWarmHint(
        UpdateMemberWarmHintRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberExperienceWritePermission($staff, $siteModel->id);

        return ApiResponse::success($config->updateWarmHint($siteModel, $request->validated()));
    }

    public function carousel(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberExperienceReadPermission($staff, $siteModel->id);

        return ApiResponse::success($config->carousel($siteModel));
    }

    public function updateCarousel(
        UpdateMemberCarouselRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberExperienceWritePermission($staff, $siteModel->id);

        return ApiResponse::success($config->updateCarousel($siteModel, $request->validated()));
    }

    public function miniappLayout(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site)->loadMissing('tenant');
        $access->assertMemberExperienceReadPermission($staff, $siteModel->id);

        return ApiResponse::success($config->miniappLayout($siteModel));
    }

    public function updateMiniappLayout(
        UpdateMemberMiniappLayoutRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site)->loadMissing('tenant');
        $access->assertMemberExperienceWritePermission($staff, $siteModel->id);

        return ApiResponse::success($config->updateMiniappLayout($siteModel, $request->validated()));
    }

    public function onboardingHelp(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberExperienceReadPermission($staff, $siteModel->id);

        return ApiResponse::success($config->onboardingHelp($siteModel));
    }

    public function updateOnboardingHelp(
        UpdateMemberOnboardingHelpRequest $request,
        int $site,
        TenantSettingsAccessService $access,
        MemberExperienceConfigService $config,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberExperienceWritePermission($staff, $siteModel->id);

        return ApiResponse::success($config->updateOnboardingHelp($siteModel, $request->validated()));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
