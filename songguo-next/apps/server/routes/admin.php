<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminMediaAssetController;
use App\Http\Controllers\Api\V1\Admin\AdminMemberController;
use App\Http\Controllers\Api\V1\Admin\AdminQueueController;
use App\Http\Controllers\Api\V1\Admin\AdminResourceController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\AdminWechatPaymentConfigController;
use App\Http\Controllers\Api\V1\Admin\SuperAdminAuthController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RecordSuperAdminAudit;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [SuperAdminAuthController::class, 'login'])
    ->middleware('throttle:admin-login');

Route::middleware(['auth:sanctum', EnsureSuperAdmin::class, RecordSuperAdminAudit::class])->group(function () {
    Route::get('/me', [SuperAdminAuthController::class, 'me']);
    Route::post('/auth/logout', [SuperAdminAuthController::class, 'logout']);
    Route::get('/dashboard', AdminDashboardController::class);
    Route::get('/tenants', [AdminTenantController::class, 'index']);
    Route::get('/members', [AdminMemberController::class, 'index']);
    Route::get('/resources/{resource}', [AdminResourceController::class, 'index'])
        ->whereIn('resource', ['staff', 'courses', 'schedules', 'appointments', 'cards', 'orders']);
    Route::get('/settings/payments/wechat', [AdminWechatPaymentConfigController::class, 'show']);
    Route::put('/settings/payments/wechat', [AdminWechatPaymentConfigController::class, 'update']);
    Route::get('/media-assets', [AdminMediaAssetController::class, 'index']);
    Route::post('/media-assets', [AdminMediaAssetController::class, 'store']);
    Route::put('/media-assets/{mediaAsset}', [AdminMediaAssetController::class, 'update']);
    Route::post('/media-assets/{mediaAsset}/publish', [AdminMediaAssetController::class, 'publish']);
    Route::post('/media-assets/{mediaAsset}/archive', [AdminMediaAssetController::class, 'archive']);
    Route::get('/media-assets/{mediaAsset}/content', [AdminMediaAssetController::class, 'content'])
        ->name('admin.media-assets.content');
    Route::get('/queues/overview', [AdminQueueController::class, 'overview']);
    Route::get('/queues/jobs', [AdminQueueController::class, 'jobs']);
    Route::get('/queues/failed', [AdminQueueController::class, 'failed']);
    Route::get('/queues/batches', [AdminQueueController::class, 'batches']);
    Route::post('/queues/failed/{uuid}/retry', [AdminQueueController::class, 'retry'])->whereUuid('uuid');
    Route::delete('/queues/failed/{uuid}', [AdminQueueController::class, 'forget'])->whereUuid('uuid');
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
});
