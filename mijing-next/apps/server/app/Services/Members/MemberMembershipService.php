<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberMembershipService
{
    public function ensure(Account $account, Site $site): Member
    {
        $member = Member::firstOrCreate(
            ['tenant_id' => $site->tenant_id, 'account_id' => $account->id],
            [
                'member_no' => 'M'.strtoupper((string) Str::ulid()),
                'status' => 'lead',
                'source' => 'member-miniapp',
                'registration_site_id' => $site->id,
                'home_site_id' => $site->id,
                'joined_at' => now(),
            ],
        );

        $profile = $account->memberProfile;
        MemberCrmProfile::firstOrCreate(
            ['member_id' => $member->id],
            [
                'tenant_id' => $member->tenant_id,
                'name' => $profile?->display_name ?: $account->display_name ?: "会员{$member->id}",
                'gender' => $profile?->gender,
                'birth_date' => $profile?->birth_date,
            ],
        );

        DB::table('member_sites')->insertOrIgnore([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => $member->home_site_id === $site->id ? 'home' : 'served',
            'status' => 'active',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('member_sites')->where([
            'member_id' => $member->id,
            'site_id' => $site->id,
        ])->update([
            'relationship_type' => $member->home_site_id === $site->id ? 'home' : 'served',
            'status' => 'active',
            'last_seen_at' => now(),
            'updated_at' => now(),
        ]);

        return $member->fresh('sites');
    }

    public function data(Member $member): array
    {
        return [
            'state' => 'joined',
            'id' => $member->id,
            'tenantId' => $member->tenant_id,
            'memberNo' => $member->member_no,
            'status' => $member->status,
            'sites' => $member->sites->map->only(['id', 'name', 'status']),
        ];
    }
}
