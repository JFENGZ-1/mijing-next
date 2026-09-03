<?php

namespace App\Services\Wechat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WechatAuthService
{
    public function exchangeCode(string $appType, string $code): WechatSession
    {
        $app = config("wechat.apps.{$appType}");
        if (! is_array($app) || empty($app['appid']) || empty($app['secret'])) {
            throw new RuntimeException('WECHAT_NOT_CONFIGURED');
        }

        try {
            $httpResponse = Http::timeout(8)->get('https://api.weixin.qq.com/sns/jscode2session', [
                'appid' => $app['appid'],
                'secret' => $app['secret'],
                'js_code' => $code,
                'grant_type' => 'authorization_code',
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('WECHAT_UNAVAILABLE');
        }

        if (! $httpResponse->successful()) {
            throw new RuntimeException('WECHAT_UNAVAILABLE');
        }

        $response = $httpResponse->json();
        if (! is_array($response) || ! empty($response['errcode']) || empty($response['openid']) || empty($response['session_key'])) {
            throw new RuntimeException('WECHAT_CODE_INVALID');
        }

        return new WechatSession(
            appid: $app['appid'],
            openid: $response['openid'],
            unionid: $response['unionid'] ?? null,
            sessionKey: $response['session_key'],
        );
    }
}
