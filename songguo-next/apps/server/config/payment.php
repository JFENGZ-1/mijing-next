<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'demo'),

    'wechat' => [
        // 商户号
        'merchant_id' => env('WECHAT_PAY_MERCHANT_ID'),
        // 商户 API 证书序列号
        'merchant_serial_no' => env('WECHAT_PAY_MERCHANT_SERIAL_NO'),
        // 商户 API 私钥：支持直接填 PEM 内容，或填文件路径（apiclient_key.pem）
        'private_key' => env('WECHAT_PAY_PRIVATE_KEY'),
        // APIv3 密钥（回调报文 AES-256-GCM 解密用）
        'api_v3_key' => env('WECHAT_PAY_API_V3_KEY'),
        // 微信支付平台公钥（或平台证书公钥）：支持 PEM 内容或文件路径
        'platform_public_key' => env('WECHAT_PAY_PLATFORM_PUBLIC_KEY'),
        // 微信支付公钥 ID（PUB_KEY_ID_xxx）或平台证书序列号
        'platform_public_key_id' => env('WECHAT_PAY_PLATFORM_PUBLIC_KEY_ID'),
        // 支付结果回调地址（https，指向 /webhooks/wechat-pay）
        'notify_url' => env('WECHAT_PAY_NOTIFY_URL'),
        // 本地/联调环境的 HMAC 回调密钥（未接入真实平台验签时使用）
        'webhook_secret' => env('WECHAT_PAY_WEBHOOK_SECRET'),
    ],
];
