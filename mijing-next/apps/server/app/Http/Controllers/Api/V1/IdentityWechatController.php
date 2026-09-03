<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveWechatUnionIdRequest;
use App\Models\WechatIdentity;
use App\Services\Wechat\WechatAuthService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class IdentityWechatController extends Controller
{
    public function resolveUnionId(
        ResolveWechatUnionIdRequest $request,
        WechatAuthService $wechat,
    ) {
        try {
            $session = $wechat->exchangeCode('staff', $request->string('code')->toString());
        } catch (RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'WECHAT_NOT_CONFIGURED' => ApiResponse::error('WECHAT_NOT_CONFIGURED', '微信应用尚未配置', 503),
                'WECHAT_UNAVAILABLE' => ApiResponse::error('WECHAT_UNAVAILABLE', '微信服务暂时不可用', 503),
                default => ApiResponse::error('WECHAT_CODE_INVALID', '微信登录凭证无效或已过期', 401),
            };
        }

        $account = $request->user();
        WechatIdentity::updateOrCreate(
            ['appid' => $session->appid, 'openid' => $session->openid],
            [
                'account_id' => $account->id,
                'unionid' => $session->unionid,
                'session_key_ciphertext' => Crypt::encryptString($session->sessionKey),
                'last_authenticated_at' => now(),
            ],
        );

        return ApiResponse::success([
            'openid' => $session->openid,
            'unionid' => $session->unionid,
        ]);
    }
}
