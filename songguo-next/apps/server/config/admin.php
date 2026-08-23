<?php

return [
    'session_hours' => (int) env('SUPER_ADMIN_SESSION_HOURS', 8),
    'media_disk' => env('ADMIN_MEDIA_DISK', 'public'),
    'media_max_kilobytes' => (int) env('ADMIN_MEDIA_MAX_KILOBYTES', 102400),
];
