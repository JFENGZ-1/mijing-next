<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof SuperAdmin || ! $admin->tokenCan('client:admin')) {
            return ApiResponse::error('SUPER_ADMIN_ACCESS_DENIED', '当前会话不能访问超级管理后台', 403);
        }

        if ($admin->status !== 'active') {
            $admin->currentAccessToken()?->delete();

            return ApiResponse::error('SUPER_ADMIN_DISABLED', '超级管理员账号已停用', 403);
        }

        return $next($request);
    }
}
