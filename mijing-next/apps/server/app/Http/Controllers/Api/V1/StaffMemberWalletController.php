<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Wallet\MemberWalletService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;

class StaffMemberWalletController extends Controller
{
    public function show(
        Request $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MemberWalletService $wallets,
    ) {
        [$staff, $siteModel, $memberModel] = $this->context($request, $site, $member, $access, 'wallet.read');

        return ApiResponse::success($wallets->summary(
            $memberModel,
            min(max($request->integer('ledgerLimit', 20), 0), 100),
        ));
    }

    public function adjust(
        Request $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MemberWalletService $wallets,
    ) {
        [$staff, $siteModel, $memberModel] = $this->context($request, $site, $member, $access, 'wallet.adjust');
        $payload = $request->validate([
            'amountCents' => ['required', 'integer', 'not_in:0'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:4', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $result = $wallets->adjust(DomainActor::staff($staff), $siteModel, $memberModel, $payload);

        return ApiResponse::success([
            'wallet' => $wallets->summary($memberModel, 0),
            'entry' => $wallets->presentEntry($result['entry']),
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    private function context(
        Request $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        string $permission,
    ): array {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff_context');
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, $permission, $siteModel->id);

        return [$staff, $siteModel, $access->member($staff, $siteModel, $member)];
    }
}
