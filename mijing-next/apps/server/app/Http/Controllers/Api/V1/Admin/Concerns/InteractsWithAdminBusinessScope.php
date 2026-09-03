<?php

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

use App\Models\Member;
use App\Models\Site;
use App\Models\Tenant;
use BackedEnum;
use Illuminate\Pagination\LengthAwarePaginator;

trait InteractsWithAdminBusinessScope
{
    protected function scopedSite(Tenant $tenant, Site $site): Site
    {
        abort_unless($site->tenant_id === $tenant->id, 404);

        return $site;
    }

    protected function memberBelongsToSite(Member $member, Site $site): bool
    {
        if ($member->tenant_id !== $site->tenant_id) {
            return false;
        }

        if ($member->registration_site_id === $site->id || $member->home_site_id === $site->id) {
            return true;
        }

        return $member->sites()->whereKey($site->id)->exists();
    }

    protected function memberName(?Member $member): string
    {
        return $member?->crmProfile?->name
            ?? $member?->account?->display_name
            ?? $member?->member_no
            ?? '未知会员';
    }

    protected function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    protected function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
        ];
    }
}
