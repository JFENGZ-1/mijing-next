<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Auth\StaffSessionDataService;
use App\Services\Members\MemberRegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request, MemberRegistrationService $memberRegistration, StaffSessionDataService $staffSession)
    {
        $account = $request->user();
        abort_unless($account->status === 'active', 403, 'ACCOUNT_DISABLED');

        $staffProfiles = Staff::query()->with(['sites', 'roles.permissions'])
            ->where('account_id', $account->id)
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->whereHas('sites')
            ->get()
            ->filter(fn (Staff $staff) => $account->tokenCan('client:staff') && $account->tokenCan("staff:{$staff->id}"))
            ->values();

        return ApiResponse::success([
            'account' => [
                'id' => $account->id,
                'displayName' => $account->display_name,
                'mobile' => $account->mobile,
                'avatarUrl' => $account->avatar_url,
            ],
            'staffProfiles' => $staffProfiles->map(fn (Staff $staff) => $staffSession->data($staff)),
            'memberRegistration' => $account->tokenCan('client:member')
                ? $memberRegistration->status($account)
                : null,
        ]);
    }
}
