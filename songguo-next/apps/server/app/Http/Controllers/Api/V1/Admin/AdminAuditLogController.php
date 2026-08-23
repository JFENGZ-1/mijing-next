<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdminAuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'method' => ['sometimes', 'nullable', 'in:POST,PUT,PATCH,DELETE'],
            'status' => ['sometimes', 'nullable', 'in:success,failure'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = SuperAdminAuditLog::query()
            ->with('admin:id,username,name')
            ->when($validated['method'] ?? null, fn ($query, $method) => $query->where('method', $method))
            ->when(($validated['status'] ?? null) === 'success', fn ($query) => $query->whereBetween('status_code', [200, 399]))
            ->when(($validated['status'] ?? null) === 'failure', fn ($query) => $query->where('status_code', '>=', 400))
            ->when($validated['query'] ?? null, fn ($query, $keyword) => $query->where(
                fn ($nested) => $nested->where('action', 'like', "%{$keyword}%")
                    ->orWhere('path', 'like', "%{$keyword}%")
                    ->orWhere('request_id', 'like', "%{$keyword}%")
            ))
            ->latest('id')
            ->paginate($validated['perPage'] ?? 30);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (SuperAdminAuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->admin ? [
                    'id' => $log->admin->id,
                    'username' => $log->admin->username,
                    'name' => $log->admin->name,
                ] : null,
                'action' => $log->action,
                'method' => $log->method,
                'path' => $log->path,
                'subject' => $log->subject,
                'requestId' => $log->request_id,
                'statusCode' => $log->status_code,
                'ipAddress' => $log->ip_address,
                'occurredAt' => $log->occurred_at?->toISOString(),
            ])->values(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }
}
