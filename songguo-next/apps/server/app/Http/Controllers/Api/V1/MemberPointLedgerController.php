<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Points\PointLedgerReadService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberPointLedgerController extends Controller
{
    public function index(
        Request $request,
        MemberBookingAccessService $access,
        PointLedgerReadService $reader,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $member->loadMissing('tenant');
        $reader->assertEnabled($member->tenant);

        $paginator = $reader->ledgerQuery($member)
            ->paginate(min(max($request->integer('perPage', 30), 1), 50));

        return ApiResponse::success([
            ...$reader->memberLedger($request->user(), $member),
            'items' => $reader->ledgerEntries($paginator),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }
}
