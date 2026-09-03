<?php

namespace App\Services\Wechat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WechatPhoneService
{
    public function exchangeCode(string $code, string $appType = 'member'): string
    {
        $app = config("wechat.apps.{$appType}");
        if (! is_array($app) || empty($app['appid']) || empty($app['secret'])) {
            throw new RuntimeException('WECHAT_NOT_CONFIGURED');
        }

        $cacheKey = "wechat:{$appType}:access-token";
        $accessToken = Cache::remember($cacheKey, now()->addMinutes(100), function () use ($app) {
            try {
                $response = Http::timeout(8)->get('https://api.weixin.qq.com/cgi-bin/token', [
                    'grant_type' => 'client_credential',
                    'appid' => $app['appid'],
                    'secret' => $app['secret'],
                ]);
            } catch (ConnectionException) {
                throw new RuntimeException('WECHAT_PHONE_UNAVAILABLE');
            }
            $payload = $response->successful() ? $response->json() : null;
            if (! is_array($payload) || empty($payload['access_token'])) {
                throw new RuntimeException('WECHAT_PHONE_UNAVAILABLE');
            }
            return $payload['access_token'];
        });

        try {
            $response = Http::timeout(8)->post(
                'https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token='.urlencode($accessToken),
                ['code' => $code],
            );
        } catch (ConnectionException) {
            throw new RuntimeException('WECHAT_PHONE_UNAVAILABLE');
        }

        $payload = $response->successful() ? $response->json() : null;
        $mobile = is_array($payload) ? data_get($payload, 'phone_info.purePhoneNumber') : null;
        if (! is_string($mobile) || $mobile === '') {
            throw new RuntimeException('WECHAT_PHONE_CODE_INVALID');
        }
        return $mobile;
    }
}
