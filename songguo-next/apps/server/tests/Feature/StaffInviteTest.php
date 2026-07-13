<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\WechatIdentity;
use App\Services\Staff\StaffInviteTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_preview_and_accept_happy_path(): void
    {
        [$invitee, $site, $sign] = $this->seedPendingInvitee();
        $this->fakeWechat('staff-openid', 'staff-union');

        $this->getJson("/api/v1/staff/invites/{$sign}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.site.id', $site->id)
            ->assertJsonPath('data.invitee.displayName', 'Pending Coach');

        $login = $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'staff',
            'code' => 'invite-login-code',
            'inviteSign' => $sign,
            'deviceName' => 'staff-miniapp',
        ])->assertOk()
            ->assertJsonPath('data.inviteBootstrap', true);

        $token = $login->json('data.accessToken');

        $this->withToken($token)
            ->postJson('/api/v1/identity/wechat/unionid', ['code' => 'unionid-code'])
            ->assertOk()
            ->assertJsonPath('data.openid', 'staff-openid')
            ->assertJsonPath('data.unionid', 'staff-union');

        $this->withToken($token)
            ->postJson("/api/v1/staff/invites/{$sign}/accept", [
                'code' => 'accept-login-code',
                'phoneCode' => 'phone-code',
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.siteId', $site->id)
            ->assertJsonPath('data.staff.name', 'Pending Coach');

        $invitee->refresh();
        $this->assertTrue($invitee->account->wechatIdentities()->exists());
        $this->assertSame('13800138000', $invitee->account->mobile);
    }

    public function test_expired_or_invalid_sign_is_rejected(): void
    {
        [$invitee, $site, $sign] = $this->seedPendingInvitee();

        $this->getJson('/api/v1/staff/invites/not-valid.sign')
            ->assertNotFound()
            ->assertJsonPath('code', 'STAFF_INVITE_SIGN_INVALID');

        $this->travel(8)->days();

        $this->getJson("/api/v1/staff/invites/{$sign}")
            ->assertStatus(410)
            ->assertJsonPath('code', 'STAFF_INVITE_SIGN_EXPIRED');
    }

    public function test_invite_sign_does_not_authenticate_staff_endpoints(): void
    {
        [, , $sign] = $this->seedPendingInvitee();

        $this->getJson("/api/v1/staff/profile?sign={$sign}")
            ->assertUnauthorized();

        $this->withHeader('Authorization', "Bearer {$sign}")
            ->getJson('/api/v1/staff/profile')
            ->assertUnauthorized();
    }

    public function test_accepted_invite_cannot_be_reused(): void
    {
        [$invitee, $site, $sign] = $this->seedPendingInvitee();
        $this->fakeWechat('staff-openid', 'staff-union');

        $login = $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'staff',
            'code' => 'invite-login-code',
            'inviteSign' => $sign,
        ])->assertOk();
        $token = $login->json('data.accessToken');

        $this->withToken($token)
            ->postJson("/api/v1/staff/invites/{$sign}/accept", [
                'code' => 'accept-login-code',
                'phoneCode' => 'phone-code',
            ])
            ->assertOk();

        $this->getJson("/api/v1/staff/invites/{$sign}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'STAFF_INVITE_ALREADY_ACCEPTED');
    }

    /**
     * @return array{0: Staff, 1: Site, 2: string}
     */
    private function seedPendingInvitee(): array
    {
        config()->set('wechat.apps.staff', ['appid' => 'staff-appid', 'secret' => 'staff-secret']);

        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $site = Site::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Studio',
            'code' => 'main',
            'status' => 'active',
        ]);
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Coach Role',
            'code' => 'coach-role',
            'status' => 'active',
        ]);
        $account = Account::create(['display_name' => 'Pending Coach', 'status' => 'active']);
        $invitee = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Pending Coach',
            'status' => 'active',
        ]);
        $invitee->sites()->attach($site->id, [
            'tenant_id' => $tenant->id,
            'is_primary' => true,
            'capabilities' => json_encode(['coach'], JSON_THROW_ON_ERROR),
        ]);
        $invitee->roles()->attach($role->id, [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
        ]);

        $sign = app(StaffInviteTokenService::class)->issue($invitee, $site)['sign'];

        return [$invitee, $site, $sign];
    }

    private function fakeWechat(string $openid, string $unionid): void
    {
        Http::fake([
            'https://api.weixin.qq.com/sns/jscode2session*' => Http::response([
                'openid' => $openid,
                'unionid' => $unionid,
                'session_key' => 'session-key',
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'phone-access-token',
            ], 200),
            'https://api.weixin.qq.com/wxa/business/getuserphonenumber*' => Http::response([
                'phone_info' => ['purePhoneNumber' => '13800138000'],
            ], 200),
        ]);
    }
}
