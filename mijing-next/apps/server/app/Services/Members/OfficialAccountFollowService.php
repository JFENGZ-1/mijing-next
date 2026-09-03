<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\Site;
use App\Models\SiteOfficialAccountFollow;

class OfficialAccountFollowService
{
    /**
     * @return array<string, mixed>
     */
    public function content(Member $member, ?Site $site): array
    {
        $site ??= Site::query()
            ->whereKey($member->home_site_id)
            ->where('tenant_id', $member->tenant_id)
            ->where('status', 'active')
            ->firstOrFail();

        $content = SiteOfficialAccountFollow::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->first();

        abort_unless($content, 404);

        return [
            'tenantId' => $member->tenant_id,
            'siteId' => $site->id,
            'imageUrl' => $content->image_url,
            'instructionsText' => $content->instructions_text,
        ];
    }
}
