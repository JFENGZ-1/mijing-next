<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Staff;
use App\Models\Tenant;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:24'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Tenant::query()
            ->withCount('sites')
            ->addSelect([
                'member_count' => Member::query()->selectRaw('COUNT(*)')->whereColumn('tenant_id', 'tenants.id'),
                'staff_count' => Staff::query()->selectRaw('COUNT(*)')->whereColumn('tenant_id', 'tenants.id'),
            ])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['query'] ?? null, function ($query, $keyword) {
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%"));
            })
            ->latest('id')
            ->paginate($validated['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'status' => $tenant->status,
                'timezone' => $tenant->timezone,
                'siteCount' => (int) $tenant->sites_count,
                'memberCount' => (int) $tenant->member_count,
                'staffCount' => (int) $tenant->staff_count,
                'createdAt' => $tenant->created_at?->toISOString(),
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
