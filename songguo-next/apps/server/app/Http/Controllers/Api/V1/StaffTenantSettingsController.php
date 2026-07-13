<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Tenant\TenantSettingsAccessService;
use App\Services\Tenant\TenantSettingsHubService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffTenantSettingsController extends Controller
{
    public function hub(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
        TenantSettingsHubService $hub,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertReadPermission($staff, $siteModel->id);

        return ApiResponse::success($hub->hub($staff, $siteModel));
    }

    public function paymentMarketing(
        Request $request,
        int $site,
        TenantSettingsAccessService $access,
    ) {
        $staff = $this->staff($request);
        $access->site($staff, $site);
        $access->assertReadPermission($staff, $site);

        return ApiResponse::success([
            'cards' => [
                [
                    'key' => 'wechat-pay',
                    'title' => '微信支付',
                    'description' => '会员在线购卡、约课均通过微信支付完成收款。',
                    'contactLabel' => '联系客服开通',
                ],
                [
                    'key' => 'offline-transfer',
                    'title' => '线下收款',
                    'description' => '支持现金、转账等线下收款方式，由员工在后台录单。',
                    'contactLabel' => '联系客服了解',
                ],
            ],
            'supportHint' => '收款账户配置需联系松果客服协助开通，本页仅作说明。',
        ]);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
