<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitMemberCardPurchaseRequest;
use App\Models\CardProduct;
use App\Models\Site;
use App\Services\Cards\MemberCardPurchaseService;
use App\Services\Members\TenantMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberCardProductController extends Controller
{
    public function index(
        Request $request,
        TenantMemberAccessService $access,
        MemberCardPurchaseService $purchases,
    ) {
        abort_unless($request->filled('tenantId') && $request->filled('siteId'), 422, 'TENANT_SITE_REQUIRED');
        $account = $request->user();
        $member = $access->member($account, $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);

        $site = Site::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereKey($request->integer('siteId'))
            ->firstOrFail();

        $paginator = $purchases->sellableProductsQuery($site)
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(
                fn (CardProduct $product) => $purchases->catalogItem($product),
            ),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function submit(
        SubmitMemberCardPurchaseRequest $request,
        TenantMemberAccessService $access,
        MemberCardPurchaseService $purchases,
    ) {
        abort_unless($request->filled('tenantId') && $request->filled('siteId'), 422, 'TENANT_SITE_REQUIRED');
        $account = $request->user();
        $member = $access->member($account, $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);

        $site = Site::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereKey($request->integer('siteId'))
            ->firstOrFail();

        $result = $purchases->submit($account, $member, $site, $request->validated());

        return ApiResponse::success(
            $purchases->submitResponse($result['order'], $result['memberCard'], $result['payment']),
            $result['created'] ? 201 : 200,
        );
    }
}
