<?php

return [
    'apps' => [
        'member' => [
            'appid' => env('WECHAT_MEMBER_APPID'),
            'secret' => env('WECHAT_MEMBER_SECRET'),
        ],
        'staff' => [
            'appid' => env('WECHAT_STAFF_APPID'),
            'secret' => env('WECHAT_STAFF_SECRET'),
        ],
    ],
    'dev_staff_openid' => env('WECHAT_DEV_STAFF_OPENID'),

    'staff_demo' => [
        'auto_provision' => (bool) env('WECHAT_STAFF_DEMO_AUTO_PROVISION', false),
        'tenant_code' => env('WECHAT_STAFF_DEMO_TENANT_CODE', 'mijing'),
        'site_code' => env('WECHAT_STAFF_DEMO_SITE_CODE', 'main'),
    ],
];
