<?php

namespace App\Services\Staff;

use App\Models\Account;
use App\Models\Site;
use App\Models\Staff;
use App\Models\WechatIdentity;
use App\Services\Auth\StaffSessionDataService;
use App\Services\Wechat\WechatSession;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class StaffInviteService
{
    public function __construct(
        private readonly StaffInviteTokenService $tokens,
        private readonly StaffSessionDataService $staffSession,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $sign, ?WechatSession $wechatSession = null): array
    {
        $payload = $this->tokens->verify($sign);
        [$staff, $site] = $this->resolveInviteTargets($payload);

        abort_if($staff->status !== 'active', 422, 'STAFF_INVITE_INACTIVE');
        abort_if($this->isInviteAccepted($staff), 422, 'STAFF_INVITE_ALREADY_ACCEPTED');

        $role = $staff->roles
            ->first(fn ($role) => (int) $role->pivot->site_id === $site->id)
            ?? $staff->roles->first(fn ($role) => $role->pivot->site_id === null);

        $preview = [
            'sign' => $sign,
            'expiresAt' => \Carbon\Carbon::createFromTimestamp($payload['exp'])->toIso8601String(),
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
            'invitee' => [
                'id' => $staff->id,
                'displayName' => $staff->name,
                'role' => $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                ] : null,
                'capabilities' => $this->normalizeCapabilities(
                    $staff->sites->firstWhere('id', $site->id)?->pivot?->capabilities,
                ),
            ],
            'status' => 'pending',
        ];

        if ($wechatSession !== null) {
            $preview['wechatBinding'] = [
                'openid' => $wechatSession->openid,
                'unionid' => $wechatSession->unionid,
                'alreadyBoundToInvitee' => WechatIdentity::query()
                    ->where('appid', $wechatSession->appid)
                    ->where('openid', $wechatSession->openid)
                    ->where('account_id', $staff->account_id)
                    ->exists(),
            ];
        }

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(Account $account, string $sign, string $mobile, WechatSession $wechatSession): array
    {
        $payload = $this->tokens->verify($sign);
        [$staff, $site] = $this->resolveInviteTargets($payload);

        abort_if($staff->status !== 'active', 422, 'STAFF_INVITE_INACTIVE');
        abort_if($this->isInviteAccepted($staff), 422, 'STAFF_INVITE_ALREADY_ACCEPTED');

        $conflictingIdentity = WechatIdentity::query()
            ->where('appid', $wechatSession->appid)
            ->where('openid', $wechatSession->openid)
            ->where('account_id', '!=', $staff->account_id)
            ->exists();
        abort_if($conflictingIdentity, 409, 'STAFF_INVITE_WECHAT_CONFLICT');

        return DB::transaction(function () use ($account, $staff, $site, $mobile, $wechatSession) {
            $staffAccount = $staff->account;
            abort_unless($staffAccount !== null, 422, 'STAFF_INVITE_INACTIVE');

            if ($account->id !== $staffAccount->id) {
                abort(409, 'STAFF_INVITE_ACCOUNT_MISMATCH');
            }

            WechatIdentity::updateOrCreate(
                ['appid' => $wechatSession->appid, 'openid' => $wechatSession->openid],
                [
                    'account_id' => $staffAccount->id,
                    'unionid' => $wechatSession->unionid,
                    'session_key_ciphertext' => Crypt::encryptString($wechatSession->sessionKey),
                    'last_authenticated_at' => now(),
                ],
            );

            $staffAccount->update([
                'mobile' => $mobile,
                'display_name' => $staffAccount->display_name ?: $staff->name,
            ]);

            $staff->refresh()->load(['sites', 'roles.permissions']);

            return [
                'accepted' => true,
                'siteId' => $site->id,
                'staff' => $this->staffSession->data($staff),
            ];
        });
    }

    /**
     * @param  array{t: int, s: int, st: int, exp: int}  $payload
     * @return array{0: Staff, 1: Site}
     */
    private function resolveInviteTargets(array $payload): array
    {
        $staff = Staff::query()
            ->with(['account.wechatIdentities', 'roles', 'sites'])
            ->where('tenant_id', $payload['t'])
            ->whereKey($payload['st'])
            ->firstOrFail();

        $site = Site::query()
            ->where('tenant_id', $payload['t'])
            ->whereKey($payload['s'])
            ->firstOrFail();

        abort_unless(
            $staff->sites()->whereKey($site->id)->where('site_staff.tenant_id', $payload['t'])->exists(),
            404,
            'STAFF_INVITE_SIGN_INVALID',
        );

        return [$staff, $site];
    }

    /**
     * @return list<string>
     */
    private function normalizeCapabilities(mixed $capabilities): array
    {
        if (is_array($capabilities)) {
            return array_values($capabilities);
        }
        if (is_string($capabilities) && $capabilities !== '') {
            return array_values(json_decode($capabilities, true) ?: []);
        }

        return [];
    }

    private function isInviteAccepted(Staff $staff): bool
    {
        $account = $staff->account;

        return ($account?->wechatIdentities()->exists() ?? false)
            && filled($account?->mobile);
    }
}
