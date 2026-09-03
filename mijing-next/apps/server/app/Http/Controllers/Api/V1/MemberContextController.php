<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JoinMemberSiteRequest;
use App\Models\LegalDocument;
use App\Models\Site;
use App\Services\Members\MemberLinkService;
use App\Services\Members\MemberMembershipService;
use App\Services\Members\TenantMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberContextController extends Controller
{
    public function sites()
    {
        return ApiResponse::success($this->siteOptions(
            Site::query()
                ->where('status', 'active')
                ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'phone', 'address', 'timezone']),
        ));
    }

    public function memberships(Request $request)
    {
        $account = $request->user();

        return ApiResponse::success($this->siteOptions(
            Site::query()
                ->select('sites.id', 'sites.tenant_id', 'sites.name', 'sites.phone', 'sites.address', 'sites.timezone')
                ->join('member_sites', 'member_sites.site_id', '=', 'sites.id')
                ->join('members', 'members.id', '=', 'member_sites.member_id')
                ->where('members.account_id', $account->id)
                ->where('member_sites.status', 'active')
                ->where('members.app_access_status', 'allowed')
                ->whereNot('members.status', 'closed')
                ->where('sites.status', 'active')
                ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                ->orderBy('sites.name')
                ->distinct()
                ->get(),
        ));
    }

    public function join(
        JoinMemberSiteRequest $request,
        TenantMemberAccessService $access,
        MemberLinkService $links,
        MemberMembershipService $memberships,
    ) {
        $site = Site::query()
            ->whereKey($request->integer('siteId'))
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->firstOrFail();

        $account = $request->user();
        $access->memberForSite($account, $site);
        $linkRequest = $links->detectOrCreate($request, $account, $site);
        if ($linkRequest) {
            return ApiResponse::success($links->memberData($linkRequest), 202);
        }

        $member = DB::transaction(fn () => $memberships->ensure($account, $site));

        return ApiResponse::success($memberships->data($member), 201);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Site>  $sites
     */
    private function siteOptions($sites): array
    {
        return $sites
            ->map(fn (Site $site) => [
                'id' => $site->id,
                'tenantId' => $site->tenant_id,
                'name' => $site->name,
                'phone' => $site->phone,
                'address' => $site->address,
                'timezone' => $site->timezone,
            ])
            ->values()
            ->all();
    }

    public function legalDocuments()
    {
        return ApiResponse::success(
            LegalDocument::query()
                ->where('scope_key', 'global')->where('status', 'published')->where('is_required', true)
                ->orderBy('type')
                ->get()
                ->map(fn (LegalDocument $document) => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'version' => $document->version,
                    'title' => $document->title,
                    'content' => $document->content,
                    'contentHash' => $document->content_hash,
                    'publishedAt' => $document->published_at?->toISOString(),
                ]),
        );
    }
}
