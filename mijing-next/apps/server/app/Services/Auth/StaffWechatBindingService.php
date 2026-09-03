<?php

namespace App\Services\Auth;

use App\Models\Member;
use App\Models\Staff;
use App\Models\WechatIdentity;
use App\Services\Wechat\WechatSession;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class StaffWechatBindingService
{
    public function bindSessionToStaff(WechatSession $session, Staff $staff): void
    {
        $this->bindOpenidToStaff(
            appid: $session->appid,
            openid: $session->openid,
            staff: $staff,
            unionid: $session->unionid,
            sessionKey: $session->sessionKey,
        );
    }

    public function bindSessionToMember(WechatSession $session, Member $member): void
    {
        $this->bindOpenidToMember(
            appid: $session->appid,
            openid: $session->openid,
            member: $member,
            unionid: $session->unionid,
            sessionKey: $session->sessionKey,
        );
    }

    public function bindOpenidToStaff(
        string $appid,
        string $openid,
        Staff $staff,
        ?string $unionid = null,
        ?string $sessionKey = null,
    ): void {
        $this->bindOpenidToAccount(
            appid: $appid,
            openid: $openid,
            accountId: $staff->account_id,
            unionid: $unionid,
            sessionKey: $sessionKey,
        );
    }

    public function bindOpenidToMember(
        string $appid,
        string $openid,
        Member $member,
        ?string $unionid = null,
        ?string $sessionKey = null,
    ): void {
        $this->bindOpenidToAccount(
            appid: $appid,
            openid: $openid,
            accountId: $member->account_id,
            unionid: $unionid,
            sessionKey: $sessionKey,
        );
    }

    public function bindOpenidToAccount(
        string $appid,
        string $openid,
        int $accountId,
        ?string $unionid = null,
        ?string $sessionKey = null,
    ): void {
        DB::transaction(function () use ($appid, $openid, $accountId, $unionid, $sessionKey) {
            $existing = WechatIdentity::query()
                ->where('appid', $appid)
                ->where('openid', $openid)
                ->first();
            $existing?->account?->tokens()->delete();

            WechatIdentity::updateOrCreate(
                ['appid' => $appid, 'openid' => $openid],
                [
                    'account_id' => $accountId,
                    'unionid' => $unionid,
                    'session_key_ciphertext' => Crypt::encryptString($sessionKey ?? 'local-dev-placeholder'),
                    'last_authenticated_at' => now(),
                ],
            );
        });
    }

    public function unbindIdentity(WechatIdentity $identity): void
    {
        DB::transaction(function () use ($identity) {
            $identity->account?->tokens()->delete();
            $identity->delete();
        });
    }
}
