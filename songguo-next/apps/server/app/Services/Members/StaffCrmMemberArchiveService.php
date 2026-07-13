<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffCrmMemberArchiveService
{
    public function __construct(
        private readonly StaffMemberAccessService $access,
        private readonly MemberAuditService $audit,
        private readonly MobileProtectionService $mobile,
    ) {}

    public function archivedQuery(Staff $staff, Site $site): Builder
    {
        return Member::query()
            ->where('members.tenant_id', $staff->tenant_id)
            ->whereNotNull('members.archived_at')
            ->where(function (Builder $query) use ($staff, $site) {
                $query->whereHas('sites', fn (Builder $sites) => $sites
                    ->whereKey($site->id)
                    ->where('member_sites.tenant_id', $staff->tenant_id))
                    ->orWhere('members.registration_site_id', $site->id);
            });
    }

    public function archivedMember(Staff $staff, Site $site, int $memberId): Member
    {
        return $this->archivedQuery($staff, $site)->whereKey($memberId)->firstOrFail();
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function listDeleted(Staff $staff, Site $site, int $page, int $perPage): array
    {
        $paginator = $this->archivedQuery($staff, $site)
            ->with(['crmProfile', 'owner'])
            ->orderByDesc('members.archived_at')
            ->paginate($perPage, ['members.*'], 'page', $page);

        return [
            'items' => collect($paginator->items())->map(fn (Member $member) => $this->deletedMemberData($member))->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    public function restore(Request $request, Staff $staff, Site $site, Member $member): Member
    {
        abort_if($member->archived_at === null, 409, 'MEMBER_NOT_ARCHIVED');

        $profile = $member->crmProfile;
        $mobileHash = $profile?->mobile_hash;
        if (! $mobileHash && filled($profile?->mobile_ciphertext)) {
            $mobileHash = $this->mobile->hashForTenant(
                $this->mobile->normalize($this->mobile->decrypt($profile->mobile_ciphertext)),
                $staff->tenant_id,
            );
        }
        if ($mobileHash) {
            $conflict = MemberCrmProfile::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('mobile_hash', $mobileHash)
                ->whereHas('member', fn (Builder $active) => $active
                    ->whereNull('archived_at')
                    ->whereKeyNot($member->id))
                ->exists();
            abort_if($conflict, 409, 'MEMBER_RESTORE_BLOCKED');
        }

        DB::transaction(function () use ($request, $staff, $site, $member, $profile, $mobileHash) {
            $updated = Member::whereKey($member->id)
                ->whereNotNull('archived_at')
                ->update([
                    'archived_at' => null,
                    'version' => DB::raw('version + 1'),
                ]);
            abort_if($updated !== 1, 409, 'MEMBER_RESTORE_INVALID');

            if ($profile && ! $profile->mobile_hash && $mobileHash) {
                $profile->update(['mobile_hash' => $mobileHash]);
            }

            $this->audit->record($request, $staff, $site, $member, 'crm.member.restored');
        });

        return $member->fresh(['crmProfile', 'owner', 'tags']);
    }

    /**
     * @return array<string, mixed>
     */
    private function deletedMemberData(Member $member): array
    {
        return [
            'id' => $member->id,
            'memberNo' => $member->member_no,
            'name' => $member->crmProfile?->name,
            'mobileMasked' => $member->crmProfile?->mobile_last4 ? "*******{$member->crmProfile->mobile_last4}" : null,
            'status' => $member->status,
            'owner' => $member->owner ? ['id' => $member->owner->id, 'name' => $member->owner->name] : null,
            'archivedAt' => $member->archived_at?->toISOString(),
            'version' => $member->version,
        ];
    }
}
