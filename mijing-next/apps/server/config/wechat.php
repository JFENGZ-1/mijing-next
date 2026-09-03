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
];
