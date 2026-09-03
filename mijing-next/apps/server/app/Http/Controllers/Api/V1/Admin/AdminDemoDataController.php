<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDemoDataService;
use App\Support\ApiResponse;

class AdminDemoDataController extends Controller
{
    public function __invoke(AdminDemoDataService $demoData)
    {
        return ApiResponse::success($demoData->generate());
    }
}
