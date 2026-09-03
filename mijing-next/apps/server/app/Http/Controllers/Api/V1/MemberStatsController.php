<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\MemberStatsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberStatsController extends Controller
{
    public function year(
        Request $request,
        MemberBookingAccessService $access,
        MemberStatsService $stats,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        abort_unless($request->filled('year'), 422, 'YEAR_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));

        return ApiResponse::success($stats->year($member, $request->integer('year')));
    }

    public function month(
        Request $request,
        MemberBookingAccessService $access,
        MemberStatsService $stats,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        abort_unless($request->filled('year'), 422, 'YEAR_REQUIRED');
        abort_unless($request->filled('month'), 422, 'MONTH_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));

        return ApiResponse::success($stats->month(
            $member,
            $request->integer('year'),
            $request->integer('month'),
        ));
    }

    public function monthAppointments(
        Request $request,
        MemberBookingAccessService $access,
        MemberStatsService $stats,
    ) {
        $validated = $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'courseKind' => ['nullable', 'string', 'in:group,private,all'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $member = $access->member($request->user(), (int) $validated['tenantId']);

        return ApiResponse::success($stats->monthAppointments(
            $member,
            (int) $validated['year'],
            (int) $validated['month'],
            $validated['courseKind'] ?? 'all',
            (int) ($validated['page'] ?? 1),
            min(max((int) ($validated['perPage'] ?? 20), 1), 50),
        ));
    }
}
