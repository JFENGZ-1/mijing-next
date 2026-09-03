<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\MonthlyRankingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberMonthlyRankingController extends Controller
{
    public function index(
        Request $request,
        MemberBookingAccessService $access,
        MonthlyRankingService $ranking,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $member->loadMissing('tenant');
        $ranking->assertEnabled($member->tenant);

        $now = now();
        $year = $request->integer('year', (int) $now->year);
        $month = $request->integer('month', (int) $now->month);

        return ApiResponse::success($ranking->list($member, $year, $month));
    }
}
