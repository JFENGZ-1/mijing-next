<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptStaffInviteRequest;
use App\Services\Staff\StaffInviteService;
use App\Services\Wechat\WechatAuthService;
use App\Services\Wechat\WechatPhoneService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StaffInviteController extends Controller
{
    public function preview(
        Request $request,
        string $sign,
        StaffInviteService $invites,
        WechatAuthService $wechat,
    ) {
        $wechatSession = null;
        if ($request->filled('code')) {
            try {
                $wechatSession = $wechat->exchangeCode('staff', $request->string('code')->toString());
            } catch (RuntimeException) {
                return ApiResponse::error('WECHAT_CODE_INVALID', '微信登录凭证无效或已过期', 401);
            }
        }

        return ApiResponse::success($invites->preview($sign, $wechatSession));
    }

    public function accept(
        AcceptStaffInviteRequest $request,
        string $sign,
        StaffInviteService $invites,
        WechatAuthService $wechat,
        WechatPhoneService $wechatPhone,
    ) {
        try {
            $wechatSession = $wechat->exchangeCode('staff', $request->string('code')->toString());
            $mobile = $wechatPhone->exchangeCode($request->string('phoneCode')->toString(), 'staff');
        } catch (RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'WECHAT_NOT_CONFIGURED' => ApiResponse::error('WECHAT_NOT_CONFIGURED', '微信应用尚未配置', 503),
                'WECHAT_UNAVAILABLE', 'WECHAT_PHONE_UNAVAILABLE' => ApiResponse::error('WECHAT_UNAVAILABLE', '微信服务暂时不可用', 503),
                'WECHAT_PHONE_CODE_INVALID' => ApiResponse::error('WECHAT_PHONE_CODE_INVALID', '手机号凭证无效或已过期', 422),
                default => ApiResponse::error('WECHAT_CODE_INVALID', '微信登录凭证无效或已过期', 401),
            };
        }

        return ApiResponse::success(
            $invites->accept($request->user(), $sign, $mobile, $wechatSession),
        );
    }
}
