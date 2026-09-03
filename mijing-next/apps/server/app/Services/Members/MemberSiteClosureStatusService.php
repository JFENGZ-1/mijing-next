<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\Site;
use App\Models\SiteClosurePeriod;
use Carbon\Carbon;

class MemberSiteClosureStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function status(Member $member, Site $site): array
    {
        abort_unless($site->tenant_id === $member->tenant_id, 404);

        $today = Carbon::now()->startOfDay();
        $active = SiteClosurePeriod::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('begin_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('begin_date')
            ->first();

        return [
            'siteId' => $site->id,
            'isClosed' => $active !== null,
            'closure' => $active ? [
                'id' => $active->id,
                'reason' => $active->reason,
                'beginDate' => $active->begin_date?->toDateString(),
                'endDate' => $active->end_date?->toDateString(),
            ] : null,
        ];
    }
}
