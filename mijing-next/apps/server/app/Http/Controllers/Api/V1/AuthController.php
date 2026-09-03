<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WechatLoginRequest;
use App\Models\Account;
use App\Models\Staff;
use App\Models\WechatIdentity;
use App\Services\Auth\DemoStaffProvisioningService;
use App\Services\Auth\StaffSessionDataService;
use App\Services\Members\MemberRegistrationService;
use App\Services\Staff\StaffInviteTokenService;
use App\Services\Wechat\WechatAuthService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuthController extends Controller
{
    public function login(WechatLoginRequest $request, WechatAuthService $wechat, MemberRegistrationService $memberRegistration, StaffSessionDataService $staffSession, StaffInviteTokenService $inviteTokens, DemoStaffProvisioningService $demoStaffProvisioning)
    {
        try {
            $session = $wechat->exchangeCode($request->string('appType'), $request->string('code'));
        } catch (RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'WECHAT_NOT_CONFIGURED' => ApiResponse::error('WECHAT_NOT_CONFIGURED', '微信应用尚未配置', 503),
                'WECHAT_UNAVAILABLE' => ApiResponse::error('WECHAT_UNAVAILABLE', '微信服务暂时不可用', 503),
                default => ApiResponse::error('WECHAT_CODE_INVALID', '微信登录凭证无效或已过期', 401),
            };
        }

        $lockKey = 'wechat-login:'.hash('sha256', $session->appid.'|'.$session->openid);
        try {
            $result = Cache::lock($lockKey, 10)->block(5, function () use ($request, $session, $memberRegistration, $staffSession, $inviteTokens, $demoStaffProvisioning) {
                return DB::transaction(function () use ($request, $session, $memberRegistration, $staffSession, $inviteTokens, $demoStaffProvisioning) {
                    $inviteAccount = $this->resolveInviteAccount($request, $inviteTokens);
                    $identity = WechatIdentity::query()->where('appid', $session->appid)->where('openid', $session->openid)->first();

                    if ($inviteAccount !== null) {
                        $conflict = WechatIdentity::query()
                            ->where('appid', $session->appid)
                            ->where('openid', $session->openid)
                            ->where('account_id', '!=', $inviteAccount->id)
                            ->exists();
                        abort_if($conflict, 409, 'STAFF_INVITE_WECHAT_CONFLICT');
                        $account = $inviteAccount;
                    } else {
                        $account = $identity?->account ?? Account::create(['status' => 'active']);
                    }

                    $identity = WechatIdentity::updateOrCreate(
                        ['appid' => $session->appid, 'openid' => $session->openid],
                        [
                            'account_id' => $account->id,
                            'unionid' => $session->unionid,
                            'session_key_ciphertext' => Crypt::encryptString($session->sessionKey),
                            'last_authenticated_at' => now(),
                        ],
                    );

                    if ($account->status !== 'active') {
                        abort(403, 'ACCOUNT_DISABLED');
                    }

                    $staff = null;
                    $inviteBootstrap = $inviteAccount !== null;
                    if ($request->string('appType')->toString() === 'staff' && ! $inviteBootstrap) {
                        $staffProfiles = Staff::query()->with(['sites', 'roles.permissions'])
                            ->where('account_id', $account->id)
                            ->where('status', 'active')
                            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                            ->whereHas('sites')
                            ->when($request->filled('tenantId'), fn ($query) => $query->where('tenant_id', $request->integer('tenantId')))
                            ->get();
                        if ($staffProfiles->isEmpty()) {
                            $demoStaff = $demoStaffProvisioning->provision($account, $session->openid);
                            if ($demoStaff !== null) {
                                $staffProfiles = collect([$demoStaff]);
                            }
                        }
                        abort_if($staffProfiles->isEmpty(), 403, 'STAFF_ACCESS_DENIED');
                        abort_if($staffProfiles->count() > 1, 409, 'STAFF_CONTEXT_REQUIRED');
                        $staff = $staffProfiles->first();
                    } elseif ($inviteBootstrap) {
                        $staff = Staff::query()
                            ->with(['sites', 'roles.permissions'])
                            ->where('account_id', $account->id)
                            ->where('status', 'active')
                            ->first();
                    }

                    $account->forceFill(['last_login_at' => now()])->save();
                    $abilities = $staff
                        ? ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]
                        : ['api', 'client:member'];
                    $token = $account->createToken($request->string('deviceName', 'weixin')->toString(), $abilities, now()->addDays(7));
                    $registration = $request->string('appType')->toString() === 'member'
                        ? $memberRegistration->status($account)
                        : null;

                    return [
                        'accessToken' => $token->plainTextToken,
                        'tokenType' => 'Bearer',
                        'expiresAt' => now()->addDays(7)->toISOString(),
                        'registrationRequired' => $registration['registrationRequired'] ?? false,
                        'registrationState' => $registration['state'] ?? null,
                        'account' => ['id' => $account->id, 'displayName' => $account->display_name],
                        'staff' => $staff ? $staffSession->data($staff) : null,
                        'inviteBootstrap' => $inviteBootstrap,
                    ];
                });
            });
        } catch (LockTimeoutException) {
            return ApiResponse::error('LOGIN_BUSY', '登录请求正在处理中，请稍后重试', 503);
        }

        return ApiResponse::success($result);
    }

    private function resolveInviteAccount(WechatLoginRequest $request, StaffInviteTokenService $inviteTokens): ?Account
    {
        if ($request->string('appType')->toString() !== 'staff' || ! $request->filled('inviteSign')) {
            return null;
        }

        $payload = $inviteTokens->verify($request->string('inviteSign')->toString());
        $staff = Staff::query()
            ->with('account')
            ->where('tenant_id', $payload['t'])
            ->whereKey($payload['st'])
            ->firstOrFail();

        abort_if($staff->status !== 'active', 422, 'STAFF_INVITE_INACTIVE');
        abort_if(
            ($staff->account?->wechatIdentities()->exists() ?? false) && filled($staff->account?->mobile),
            422,
            'STAFF_INVITE_ALREADY_ACCEPTED',
        );

        return $staff->account;
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(['loggedOut' => true]);
    }
}
