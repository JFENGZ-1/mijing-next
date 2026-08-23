<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuperAdminLoginRequest;
use App\Models\SuperAdmin;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminAuthController extends Controller
{
    public function login(SuperAdminLoginRequest $request)
    {
        $login = Str::lower(trim($request->string('login')->toString()));
        $admin = SuperAdmin::query()
            ->whereRaw('LOWER(username) = ?', [$login])
            ->orWhereRaw('LOWER(email) = ?', [$login])
            ->first();

        $passwordMatches = $admin
            ? Hash::check($request->string('password')->toString(), $admin->password)
            : Hash::check($request->string('password')->toString(), Hash::make(Str::random(40)));

        if (! $admin || ! $passwordMatches) {
            return ApiResponse::error('SUPER_ADMIN_CREDENTIALS_INVALID', '账号或密码错误', 401);
        }

        if ($admin->status !== 'active') {
            return ApiResponse::error('SUPER_ADMIN_DISABLED', '超级管理员账号已停用', 403);
        }

        $expiresAt = now()->addHours((int) config('admin.session_hours', 8));
        $token = $admin->createToken(
            $request->string('deviceName', 'admin-web')->toString(),
            ['api', 'client:admin', 'admin:platform'],
            $expiresAt,
        );

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return ApiResponse::success([
            'accessToken' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->toISOString(),
            'admin' => $this->adminData($admin),
        ]);
    }

    public function me(Request $request)
    {
        return ApiResponse::success($this->adminData($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(['loggedOut' => true]);
    }

    private function adminData(SuperAdmin $admin): array
    {
        return [
            'id' => $admin->id,
            'username' => $admin->username,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'platform_super_admin',
            'permissions' => ['platform:*'],
            'lastLoginAt' => $admin->last_login_at?->toISOString(),
        ];
    }
}
