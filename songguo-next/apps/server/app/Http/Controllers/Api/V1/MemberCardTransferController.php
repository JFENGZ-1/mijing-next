<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Cards\CardTransferService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberCardTransferController extends Controller
{
    public function show(string $token, CardTransferService $transfers)
    {
        return ApiResponse::success($transfers->preview($token));
    }

    public function claim(Request $request, string $token, CardTransferService $transfers)
    {
        $validated = $request->validate([
            'commandKey' => ['required', 'string', 'max:64'],
        ]);

        $result = $transfers->claim($request->user(), $token, $validated['commandKey']);

        return ApiResponse::success([
            'memberCardId' => $result['memberCard']->id,
            'status' => $result['memberCard']->status->value,
        ], $result['created'] ? 201 : 200);
    }
}
