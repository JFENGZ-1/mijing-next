<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberLinkDecisionRequest;
use App\Models\MemberLinkRequest;
use App\Services\Members\MemberLinkService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberLinkController extends Controller
{
    public function index(Request $request, MemberLinkService $links)
    {
        $query = MemberLinkRequest::query()
            ->where('account_id', $request->user()->id)
            ->whereNotNull('active_key')
            ->latest();
        if ($request->filled('siteId')) {
            $request->validate(['siteId' => ['integer', 'min:1']]);
            $query->where('site_id', $request->integer('siteId'));
        }

        return ApiResponse::success($query->get()->map(
            fn (MemberLinkRequest $linkRequest) => $links->memberData($linkRequest),
        ));
    }

    public function decide(
        MemberLinkDecisionRequest $request,
        string $linkRequest,
        MemberLinkService $links,
    ) {
        $updated = $links->memberDecision(
            $request,
            $request->user(),
            $linkRequest,
            $request->string('decision')->toString(),
            $request->integer('version'),
        );

        return ApiResponse::success($links->memberData($updated));
    }
}
