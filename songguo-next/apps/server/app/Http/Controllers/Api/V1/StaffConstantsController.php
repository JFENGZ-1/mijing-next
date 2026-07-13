<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformConstantsService;
use App\Services\Platform\CommonDataService;
use App\Support\ApiResponse;

class StaffConstantsController extends Controller
{
    public function regions(PlatformConstantsService $constants)
    {
        return ApiResponse::success($constants->regions());
    }

    public function commonData(CommonDataService $common)
    {
        return ApiResponse::success($common->commonData());
    }
}
