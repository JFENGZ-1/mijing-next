<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffMemberLinkDecisionRequest;
use App\Models\MemberLinkRequest;
use App\Models\Staff;
use App\Services\Members\MemberLinkService;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffMemberLinkController extends Controller
{
    public function index(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberLinkService $links,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.link.review', $siteModel->id);
        $status = $request->string('status', 'pending_staff_review')->toString();
        abort_unless(in_array($status, ['pending_staff_review', 'linked', 'separate_approved', 'rejected', 'conflict'], true), 422, 'INVALID_FILTER');

        return ApiResponse::success(MemberLinkRequest::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)
            ->where('status', $status)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (MemberLinkRequest $linkRequest) => $links->staffData($linkRequest)));
    }

    public function decide(
        StaffMemberLinkDecisionRequest $request,
        int $site,
        string $linkRequest,
        StaffMemberAccessService $access,
        MemberLinkService $links,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.link.review', $siteModel->id);
        $updated = $links->staffDecision(
            $request,
            $staff,
            $siteModel,
            $linkRequest,
            $request->string('decision')->toString(),
            $request->string('reason')->toString(),
            $request->integer('version'),
        );

        return ApiResponse::success($links->staffData($updated));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
