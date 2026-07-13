<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'demo'),

    'wechat' => [
        'merchant_id' => env('WECHAT_PAY_MERCHANT_ID'),
        'api_v3_key' => env('WECHAT_PAY_API_V3_KEY'),
        'webhook_secret' => env('WECHAT_PAY_WEBHOOK_SECRET'),
    ],
];
