<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Services\Booking\BookingPolicyService;
use App\Services\Booking\BookingShareTokenService;
use App\Services\Booking\PublicBookingSharePresenter;
use App\Support\ApiResponse;

class PublicBookingShareController extends Controller
{
    public function show(
        string $token,
        BookingShareTokenService $tokens,
        BookingPolicyService $policies,
    ) {
        $payload = $tokens->verify($token);

        $session = ScheduleSession::query()
            ->where('tenant_id', $payload['t'])
            ->where('site_id', $payload['s'])
            ->whereKey($payload['ss'])
            ->firstOrFail();

        $site = Site::query()
            ->where('tenant_id', $payload['t'])
            ->whereKey($payload['s'])
            ->firstOrFail();

        $policy = $policies->policyForTenantSite($site->tenant_id, $site->id);
        $expiresAt = \Carbon\Carbon::createFromTimestamp($payload['exp'])->toIso8601String();

        return ApiResponse::success(
            PublicBookingSharePresenter::toArray($site, $session, $policy, $expiresAt),
        );
    }
}
