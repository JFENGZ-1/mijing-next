<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminCardConsumptionOptionsController;
use App\Http\Controllers\Api\V1\Admin\AdminCardCourseRuleController;
use App\Http\Controllers\Api\V1\Admin\AdminCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminCompensationRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminConsumptionReportController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminMediaAssetController;
use App\Http\Controllers\Api\V1\Admin\AdminMemberController;
use App\Http\Controllers\Api\V1\Admin\AdminMemberWalletController;
use App\Http\Controllers\Api\V1\Admin\AdminQueueController;
use App\Http\Controllers\Api\V1\Admin\AdminResourceController;
use App\Http\Controllers\Api\V1\Admin\AdminSessionDeliveryAssignmentController;
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
    Route::get('/tenants/{tenant}/sites', [AdminTenantController::class, 'sites']);
    Route::prefix('/tenants/{tenant}/sites/{site}')->group(function () {
        Route::get('/card-consumption/options', AdminCardConsumptionOptionsController::class)
            ->name('admin.card-consumption.options');

        Route::get('/compensation-roles', [AdminCompensationRoleController::class, 'index'])
            ->name('admin.compensation-roles.index');
        Route::post('/compensation-roles', [AdminCompensationRoleController::class, 'store'])
            ->name('admin.compensation-roles.store');
        Route::put('/compensation-roles/{compensationRole}', [AdminCompensationRoleController::class, 'update'])
            ->name('admin.compensation-roles.update');
        Route::post('/compensation-roles/{compensationRole}/archive', [AdminCompensationRoleController::class, 'archive'])
            ->name('admin.compensation-roles.archive');
        Route::get('/compensation-role-assignments', [AdminCompensationRoleController::class, 'assignments'])
            ->name('admin.compensation-role-assignments.index');
        Route::post('/compensation-role-assignments', [AdminCompensationRoleController::class, 'storeAssignment'])
            ->name('admin.compensation-role-assignments.store');
        Route::post('/compensation-role-assignments/{assignment}/end', [AdminCompensationRoleController::class, 'endAssignment'])
            ->name('admin.compensation-role-assignments.end');
        Route::get('/member-card-share-assignments', [AdminCompensationRoleController::class, 'shareAssignments'])
            ->name('admin.member-card-share-assignments.index');
        Route::post('/member-card-share-assignments', [AdminCompensationRoleController::class, 'storeShareAssignment'])
            ->name('admin.member-card-share-assignments.store');
        Route::post('/member-card-share-assignments/{assignment}/end', [AdminCompensationRoleController::class, 'endShareAssignment'])
            ->name('admin.member-card-share-assignments.end');
        Route::put('/member-cards/{memberCard}/share-assignments', [AdminCompensationRoleController::class, 'replaceShareAssignments'])
            ->name('admin.member-card-share-assignments.replace');
        Route::get('/delivery-assignments', [AdminSessionDeliveryAssignmentController::class, 'index'])
            ->name('admin.delivery-assignments.index');
        Route::put('/sessions/{session}/delivery-assignments', [AdminSessionDeliveryAssignmentController::class, 'replace'])
            ->name('admin.delivery-assignments.replace');

        Route::get('/card-products', [AdminCatalogController::class, 'cardProducts'])
            ->name('admin.card-products.index');
        Route::post('/card-products', [AdminCatalogController::class, 'storeCardProduct'])
            ->name('admin.card-products.store');
        Route::put('/card-products/{cardProduct}', [AdminCatalogController::class, 'updateCardProduct'])
            ->name('admin.card-products.update');
        Route::post('/card-products/{cardProduct}/archive', [AdminCatalogController::class, 'archiveCardProduct'])
            ->name('admin.card-products.archive');
        Route::post('/card-products/{cardProduct}/restore', [AdminCatalogController::class, 'restoreCardProduct'])
            ->name('admin.card-products.restore');
        Route::get('/courses', [AdminCatalogController::class, 'courses'])
            ->name('admin.courses.index');
        Route::post('/courses', [AdminCatalogController::class, 'storeCourse'])
            ->name('admin.courses.store');
        Route::put('/courses/{course}', [AdminCatalogController::class, 'updateCourse'])
            ->name('admin.courses.update');
        Route::post('/courses/{course}/archive', [AdminCatalogController::class, 'archiveCourse'])
            ->name('admin.courses.archive');
        Route::post('/courses/{course}/restore', [AdminCatalogController::class, 'restoreCourse'])
            ->name('admin.courses.restore');

        Route::get('/card-product-payment-methods', [AdminCardCourseRuleController::class, 'paymentMethods'])
            ->name('admin.card-product-payment-methods.index');
        Route::put('/card-products/{cardProduct}/payment-methods', [AdminCardCourseRuleController::class, 'updatePaymentMethods'])
            ->name('admin.card-product-payment-methods.update');
        Route::get('/card-course-rules', [AdminCardCourseRuleController::class, 'cardCourseRules'])
            ->name('admin.card-course-rules.index');
        Route::put('/card-products/{cardProduct}/course-rules', [AdminCardCourseRuleController::class, 'replaceCardCourseRules'])
            ->name('admin.card-course-rules.replace');
        Route::get('/course-compensation-rules', [AdminCardCourseRuleController::class, 'courseCompensationRules'])
            ->name('admin.course-compensation-rules.index');
        Route::put('/course-compensation-rules', [AdminCardCourseRuleController::class, 'saveCourseCompensationRule'])
            ->name('admin.course-compensation-rules.save-version');

        Route::get('/member-wallets', [AdminMemberWalletController::class, 'index'])
            ->name('admin.member-wallets.index');
        Route::get('/member-wallets/{member}/ledger', [AdminMemberWalletController::class, 'ledger'])
            ->name('admin.member-wallets.ledger');
        Route::post('/member-wallets/{member}/adjustments', [AdminMemberWalletController::class, 'adjust'])
            ->name('admin.member-wallets.adjust');

        Route::get('/consumption-reports', [AdminConsumptionReportController::class, 'reports'])
            ->name('admin.consumption-reports.index');
        Route::get('/consumption-events', [AdminConsumptionReportController::class, 'events'])
            ->name('admin.consumption-events.index');
        Route::post('/consumption-events/{consumptionEvent}/reverse', [AdminConsumptionReportController::class, 'reverseEvent'])
            ->name('admin.consumption-events.reverse');
        Route::get('/period-settlement-days', [AdminConsumptionReportController::class, 'periodDays'])
            ->name('admin.period-settlement-days.index');
        Route::get('/payroll-periods', [AdminConsumptionReportController::class, 'payrollPeriods'])
            ->name('admin.payroll-periods.index');
        Route::post('/payroll-periods', [AdminConsumptionReportController::class, 'storePayrollPeriod'])
            ->name('admin.payroll-periods.store');
        Route::post('/payroll-periods/{payrollPeriod}/close', [AdminConsumptionReportController::class, 'closePayrollPeriod'])
            ->name('admin.payroll-periods.close');
    });
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
