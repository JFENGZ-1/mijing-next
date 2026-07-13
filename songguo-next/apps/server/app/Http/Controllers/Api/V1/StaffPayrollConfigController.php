<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePayrollCoachConfigRequest;
use App\Http\Requests\UpdatePayrollCoachRulesRequest;
use App\Http\Requests\UpdatePayrollSalesConfigRequest;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Payroll\PayrollCoachConfigService;
use App\Services\Payroll\PayrollCoachRuleService;
use App\Services\Payroll\PayrollSalesConfigService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffPayrollConfigController extends Controller
{
    public function coachConfig(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollCoachConfigService $payroll,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.config.read', $siteModel->id);

        return ApiResponse::success($payroll->read($staff, $siteModel));
    }

    public function updateCoachConfig(
        UpdatePayrollCoachConfigRequest $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollCoachConfigService $payroll,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.config.write', $siteModel->id);

        return ApiResponse::success($payroll->save($staff, $siteModel, $request->validated()));
    }

    public function coachRules(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollCoachRuleService $rules,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.config.read', $siteModel->id);

        $request->validate([
            'staffId' => ['required', 'integer', 'min:1'],
        ]);

        $coach = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($request->integer('staffId'))
            ->firstOrFail();

        return ApiResponse::success($rules->read($staff, $siteModel, $coach));
    }

    public function updateCoachRules(
        UpdatePayrollCoachRulesRequest $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollCoachRuleService $rules,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.config.write', $siteModel->id);

        $request->validate([
            'staffId' => ['required', 'integer', 'min:1'],
        ]);

        $coach = Staff::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereKey($request->integer('staffId'))
            ->firstOrFail();

        return ApiResponse::success($rules->save($staff, $siteModel, $coach, $request->validated()));
    }

    public function salesConfig(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollSalesConfigService $payroll,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.config.read', $siteModel->id);

        return ApiResponse::success($payroll->read($staff, $siteModel));
    }

    public function updateSalesConfig(
        UpdatePayrollSalesConfigRequest $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollSalesConfigService $payroll,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.config.write', $siteModel->id);

        return ApiResponse::success($payroll->save($staff, $siteModel, $request->validated()));
    }

    public function coaches(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        PayrollCoachConfigService $coachConfig,
        PayrollCoachRuleService $rules,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'payroll.config.read', $siteModel->id);

        return ApiResponse::success([
            'coachConfig' => $coachConfig->read($staff, $siteModel),
            'items' => $rules->coachList($staff, $siteModel),
        ]);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
