<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Members\TenantMemberAccessService;
use App\Services\Wallet\MemberWalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberWalletController extends Controller
{
    public function show(
        Request $request,
        TenantMemberAccessService $access,
        MemberWalletService $wallets,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        $member = $access->member($request->user(), $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);

        return ApiResponse::success($wallets->summary(
            $member,
            min(max($request->integer('ledgerLimit', 20), 0), 100),
        ));
    }
}
