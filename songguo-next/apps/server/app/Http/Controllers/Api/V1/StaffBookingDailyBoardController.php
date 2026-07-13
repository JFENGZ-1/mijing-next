<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Booking\StaffBookingDailyBoardAccessService;
use App\Services\Booking\StaffBookingDailyBoardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffBookingDailyBoardController extends Controller
{
    public function index(
        Request $request,
        int $site,
        StaffBookingDailyBoardAccessService $access,
        StaffBookingDailyBoardService $board,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertReadPermission($staff, $siteModel->id);

        $request->validate([
            'date' => ['required', 'date'],
        ]);

        return ApiResponse::success($board->board($siteModel, $request->string('date')->toString()));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
