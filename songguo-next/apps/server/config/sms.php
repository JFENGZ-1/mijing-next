<?php

return [
    'driver' => env('SMS_DRIVER', 'log'),

    'log' => [
        'channel' => env('SMS_LOG_CHANNEL', 'stack'),
    ],
];
