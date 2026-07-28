<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// 「购卡X天后自动开卡」：每小时扫描到期卡自动激活（另有用卡时懒激活兜底）
Schedule::command('cards:activate-due')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// 自动签到（对标原版「下课后，将由系统5分钟内自动签到」）：
// 每5分钟把已下课且仍 confirmed 的预约批量转 completed（停课/取消的课不签）
Schedule::command('appointments:auto-check-in')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('schedule:auto-cancel-under-min')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
