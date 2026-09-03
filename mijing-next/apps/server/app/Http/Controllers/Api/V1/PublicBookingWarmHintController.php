<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\PublicBookingWarmHintService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PublicBookingWarmHintController extends Controller
{
    public function show(
        Request $request,
        int $site,
        PublicBookingWarmHintService $hints,
    ) {
        $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'courseType' => ['sometimes', 'integer'],
        ]);

        $siteModel = \App\Models\Site::query()
            ->where('tenant_id', $request->integer('tenantId'))
            ->whereKey($site)
            ->firstOrFail();

        return ApiResponse::success($hints->forSite(
            $siteModel,
            $request->filled('courseType') ? $request->integer('courseType') : null,
        ));
    }
}
