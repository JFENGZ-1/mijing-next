<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\QueueMonitorService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AdminQueueController extends Controller
{
    public function overview(QueueMonitorService $service)
    {
        return ApiResponse::success($service->overview());
    }

    public function jobs(Request $request, QueueMonitorService $service)
    {
        $validated = $request->validate([
            'queue' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:ready,reserved,delayed'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success($service->jobs($validated));
    }

    public function failed(Request $request, QueueMonitorService $service)
    {
        $validated = $request->validate([
            'queue' => ['sometimes', 'nullable', 'string', 'max:120'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success($service->failed($validated));
    }

    public function batches(Request $request, QueueMonitorService $service)
    {
        $validated = $request->validate([
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success($service->batches($validated['perPage'] ?? 30));
    }

    public function retry(string $uuid, QueueMonitorService $service)
    {
        if (! $service->retry($uuid)) {
            return ApiResponse::error('FAILED_JOB_NOT_FOUND', '失败任务不存在或重试命令执行失败', 404);
        }

        return ApiResponse::success(['uuid' => $uuid, 'retried' => true]);
    }

    public function forget(string $uuid, QueueMonitorService $service)
    {
        if (! $service->forget($uuid)) {
            return ApiResponse::error('FAILED_JOB_NOT_FOUND', '失败任务不存在', 404);
        }

        return ApiResponse::success(['uuid' => $uuid, 'forgotten' => true]);
    }
}
