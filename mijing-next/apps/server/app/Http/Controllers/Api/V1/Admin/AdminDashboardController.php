<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MemberCardOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Support\ApiResponse;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        $recentTenants = Tenant::query()
            ->withCount('sites')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'status' => $tenant->status,
                'siteCount' => $tenant->sites_count,
                'createdAt' => $tenant->created_at?->toISOString(),
            ]);

        return ApiResponse::success([
            'totals' => [
                'tenants' => Tenant::query()->count(),
                'sites' => Site::query()->count(),
                'accounts' => Account::query()->count(),
                'members' => Member::query()->count(),
                'staff' => Staff::query()->count(),
                'paidRevenue' => (string) MemberCardOrder::query()
                    ->where('status', MemberCardOrderStatus::Paid->value)
                    ->sum('amount'),
            ],
            'tenantStatus' => Tenant::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'recentTenants' => $recentTenants,
            'generatedAt' => now()->toISOString(),
        ]);
    }
}
