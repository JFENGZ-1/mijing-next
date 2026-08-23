<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AdminMemberController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tenantId' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:24'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Member::query()
            ->with([
                'tenant:id,name,code',
                'homeSite:id,name',
                'account:id,display_name,mobile',
                'crmProfile:id,member_id,name,mobile_last4',
            ])
            ->when($validated['tenantId'] ?? null, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['query'] ?? null, function ($query, $keyword) {
                $query->where(function ($nested) use ($keyword) {
                    $nested->where('member_no', 'like', "%{$keyword}%")
                        ->orWhereHas('crmProfile', fn ($profile) => $profile
                            ->where('name', 'like', "%{$keyword}%")
                            ->orWhere('mobile_last4', $keyword));
                });
            })
            ->latest('id')
            ->paginate($validated['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (Member $member) => [
                'id' => $member->id,
                'memberNo' => $member->member_no,
                'name' => $member->crmProfile?->name ?? $member->account?->display_name ?? '未命名会员',
                'mobile' => $this->maskedMobile($member->account?->mobile, $member->crmProfile?->mobile_last4),
                'tenant' => $member->tenant ? [
                    'id' => $member->tenant->id,
                    'name' => $member->tenant->name,
                    'code' => $member->tenant->code,
                ] : null,
                'homeSite' => $member->homeSite ? [
                    'id' => $member->homeSite->id,
                    'name' => $member->homeSite->name,
                ] : null,
                'status' => $member->status,
                'joinedAt' => $member->joined_at?->toISOString(),
            ])->values(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    private function maskedMobile(?string $mobile, ?string $lastFour): ?string
    {
        if ($mobile && strlen($mobile) >= 7) {
            return substr($mobile, 0, 3).'****'.substr($mobile, -4);
        }

        return $lastFour ? '*******'.$lastFour : null;
    }
}
