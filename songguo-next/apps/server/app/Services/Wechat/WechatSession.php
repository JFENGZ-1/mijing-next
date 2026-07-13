<?php

namespace App\Services\Wechat;

final readonly class WechatSession
{
    public function __construct(
        public string $appid,
        public string $openid,
        public ?string $unionid,
        public string $sessionKey,
    ) {}
}
