<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'requestId' => request()->attributes->get('request_id'),
        ], $status);
    }

    public static function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'requestId' => request()->attributes->get('request_id'),
        ], $status);
    }
}
