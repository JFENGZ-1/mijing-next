<?php

use App\Http\Controllers\Dev\DevWechatBindingController;
use App\Http\Controllers\WechatPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/wechat-pay', WechatPayWebhookController::class);

Route::middleware('dev.tools')->prefix('dev')->group(function () {
    Route::get('/wechat-bindings', [DevWechatBindingController::class, 'index'])->name('dev.wechat-bindings.index');
    Route::post('/wechat-bindings/staff', [DevWechatBindingController::class, 'bindStaff'])->name('dev.wechat-bindings.staff');
    Route::post('/wechat-bindings/member', [DevWechatBindingController::class, 'bindMember'])->name('dev.wechat-bindings.member');
    Route::post('/wechat-bindings/unbind', [DevWechatBindingController::class, 'unbind'])->name('dev.wechat-bindings.unbind');
});
