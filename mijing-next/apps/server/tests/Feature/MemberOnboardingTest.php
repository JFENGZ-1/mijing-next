<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\WechatIdentity;
use App\Services\Members\MobileProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_member_reports_configuration_and_profile_requirements(): void
    {
        $account = $this->actAsMember();

        $this->getJson('/api/v1/member/onboarding')
            ->assertOk()
            ->assertJsonPath('data.state', 'configuration_required')
            ->assertJsonPath('data.registrationRequired', true)
            ->assertJsonPath('data.missingFields.0', 'displayName');

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonPath('data.memberRegistration.state', 'configuration_required');
    }

    public function test_profile_update_records_versioned_consent_and_completes_without_mobile(): void
    {
        $this->actAsMember();
        $document = $this->publishPrivacyDocument();

        $this->putJson('/api/v1/member/profile', [
            'displayName' => '新会员',
            'acceptedDocumentIds' => [$document->id],
        ])->assertOk()
            ->assertJsonPath('data.state', 'complete')
            ->assertJsonPath('data.registrationRequired', false)
            ->assertJsonPath('data.profile.mobileVerified', false)
            ->assertJsonPath('data.profile.displayName', '新会员')
            ->assertJsonPath('data.profile.version', 1);

        $this->assertDatabaseHas('legal_consents', [
            'legal_document_id' => $document->id,
            'action' => 'accepted',
        ]);
    }

    public function test_profile_update_requires_current_version(): void
    {
        $account = $this->actAsMember();
        $document = $this->publishPrivacyDocument();
        MemberProfile::create(['account_id' => $account->id, 'display_name' => 'Old']);

        $this->putJson('/api/v1/member/profile', [
            'displayName' => 'Changed',
            'version' => 999,
            'acceptedDocumentIds' => [$document->id],
        ])->assertStatus(409)
            ->assertJsonPath('code', 'PROFILE_VERSION_CONFLICT');
    }

    public function test_verified_wechat_mobile_completes_registration_without_exposing_full_number(): void
    {
        $account = $this->actAsMember();
        $document = $this->publishPrivacyDocument();
        MemberProfile::create(['account_id' => $account->id, 'display_name' => 'Member']);
        $this->putJson('/api/v1/member/profile', [
            'displayName' => 'Member',
            'version' => 1,
            'acceptedDocumentIds' => [$document->id],
        ])->assertOk();
        $this->fakeWechatPhone('13800138000');

        $this->postJson('/api/v1/member/profile/verify-mobile', [
            'code' => 'one-time-phone-code',
            'version' => 2,
        ])->assertOk()
            ->assertJsonPath('data.state', 'complete')
            ->assertJsonPath('data.registrationRequired', false)
            ->assertJsonPath('data.profile.mobileMasked', '*******8000')
            ->assertJsonMissing(['mobile' => '13800138000']);

        $profile = MemberProfile::where('account_id', $account->id)->firstOrFail();
        $this->assertSame('13800138000', Crypt::decryptString($profile->mobile_ciphertext));
    }

    public function test_verified_mobile_collision_never_merges_accounts(): void
    {
        $first = Account::create(['status' => 'active']);
        $second = $this->actAsMember();
        $protection = app(MobileProtectionService::class);
        $normalized = $protection->normalize('13800138000');
        MemberProfile::create([
            'account_id' => $first->id,
            'mobile_ciphertext' => $protection->encrypt($normalized),
            'mobile_hash' => $protection->hash($normalized),
            'mobile_last4' => '8000',
            'mobile_verified_at' => now(),
        ]);
        $this->fakeWechatPhone('13800138000');

        $this->postJson('/api/v1/member/profile/verify-mobile', ['code' => 'another-one-time-code'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MOBILE_ALREADY_BOUND');

        $this->assertDatabaseMissing('member_profiles', ['account_id' => $second->id]);
        $this->assertDatabaseCount('accounts', 2);
    }

    public function test_existing_profile_mobile_verification_requires_current_version(): void
    {
        $account = $this->actAsMember();
        MemberProfile::create(['account_id' => $account->id, 'display_name' => 'Member']);
        $this->fakeWechatPhone('13800138000');

        $this->postJson('/api/v1/member/profile/verify-mobile', ['code' => 'missing-version'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PROFILE_VERSION_REQUIRED');

        $this->postJson('/api/v1/member/profile/verify-mobile', ['code' => 'stale-version', 'version' => 999])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PROFILE_VERSION_CONFLICT');
    }

    public function test_joining_site_is_idempotent_and_tenant_is_derived_from_site(): void
    {
        $account = $this->actAsMember();
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => 'Member Created Profile',
            'gender' => 'female',
            'birth_date' => '1995-06-08',
        ]);
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'tenant']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);

        $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertCreated();
        $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertCreated();

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('member_sites', 1);
        $this->assertDatabaseHas('members', ['tenant_id' => $tenant->id, 'account_id' => $account->id]);
        $this->assertDatabaseHas('member_crm_profiles', [
            'tenant_id' => $tenant->id,
            'name' => 'Member Created Profile',
            'gender' => 'female',
            'birth_date' => '1995-06-08',
        ]);
    }

    public function test_memberships_index_lists_only_joined_sites(): void
    {
        $account = $this->actAsMember();
        $joinedTenant = Tenant::create(['name' => 'Joined Tenant', 'code' => 'joined-tenant']);
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'code' => 'other-tenant']);
        $joinedSite = Site::create([
            'tenant_id' => $joinedTenant->id,
            'name' => 'Main Studio',
            'code' => 'main-studio',
            'status' => 'active',
        ]);
        Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => '示范场馆',
            'code' => 'demo-site',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/member/memberships', ['siteId' => $joinedSite->id])->assertCreated();

        $this->getJson('/api/v1/member/memberships')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $joinedSite->id)
            ->assertJsonPath('data.0.tenantId', $joinedTenant->id)
            ->assertJsonPath('data.0.name', 'Main Studio');

        $this->getJson('/api/v1/member/sites')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_blocked_member_cannot_rejoin_the_tenant(): void
    {
        $account = $this->actAsMember();
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'tenant']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);

        $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertCreated();
        Member::where('tenant_id', $tenant->id)->where('account_id', $account->id)
            ->update(['app_access_status' => 'blocked']);

        $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'MEMBER_APP_ACCESS_BLOCKED');

        $this->getJson('/api/v1/member/onboarding')->assertOk();
        $otherTenant = Tenant::create(['name' => 'Other tenant', 'code' => 'other-tenant']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other site', 'code' => 'other-site', 'status' => 'active',
        ]);
        $this->postJson('/api/v1/member/memberships', ['siteId' => $otherSite->id])
            ->assertCreated()
            ->assertJsonPath('data.tenantId', $otherTenant->id);
    }

    public function test_staff_session_cannot_use_member_self_service_api(): void
    {
        $account = Account::create(['status' => 'active']);
        Sanctum::actingAs($account, ['api', 'client:staff']);

        $this->getJson('/api/v1/member/onboarding')
            ->assertForbidden()
            ->assertJsonPath('code', 'TOKEN_AUDIENCE_INVALID');
    }

    public function test_member_login_uses_persisted_registration_facts(): void
    {
        $account = Account::create(['status' => 'active']);
        $document = $this->publishPrivacyDocument();
        $protection = app(MobileProtectionService::class);
        $mobile = $protection->normalize('13800138000');
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => 'Complete Member',
            'mobile_ciphertext' => $protection->encrypt($mobile),
            'mobile_hash' => $protection->hash($mobile),
            'mobile_last4' => '8000',
            'mobile_verified_at' => now(),
        ]);
        LegalConsent::create([
            'account_id' => $account->id,
            'legal_document_id' => $document->id,
            'action' => 'accepted',
            'source' => 'test',
            'occurred_at' => now(),
        ]);
        WechatIdentity::create([
            'account_id' => $account->id,
            'appid' => 'member-appid',
            'openid' => 'member-openid',
            'session_key_ciphertext' => Crypt::encryptString('old-session'),
        ]);
        config()->set('wechat.apps.member', ['appid' => 'member-appid', 'secret' => 'member-secret']);
        Http::fake(['https://api.weixin.qq.com/sns/jscode2session*' => Http::response([
            'openid' => 'member-openid',
            'session_key' => 'new-session',
        ], 200)]);

        $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'member',
            'code' => 'fresh-login-code',
        ])->assertOk()
            ->assertJsonPath('data.registrationRequired', false)
            ->assertJsonPath('data.registrationState', 'complete');
    }

    public function test_local_mobile_override_requires_confirmation_and_records_its_method(): void
    {
        $account = Account::create(['status' => 'active']);

        $this->assertSame(1, Artisan::call('member:set-test-mobile', [
            'mobile' => '13800138000',
            '--account-id' => $account->id,
        ]));
        $this->assertSame(0, Artisan::call('member:set-test-mobile', [
            'mobile' => '13800138000',
            '--account-id' => $account->id,
            '--confirm' => true,
        ]));
        $this->assertStringNotContainsString('13800138000', Artisan::output());

        $this->assertDatabaseHas('member_profiles', [
            'account_id' => $account->id,
            'mobile_last4' => '8000',
            'mobile_verification_method' => 'local_test_override',
        ]);

        config()->set('app.env', 'production');
        $other = Account::create(['status' => 'active']);
        $this->assertSame(1, Artisan::call('member:set-test-mobile', [
            'mobile' => '13900139000',
            '--account-id' => $other->id,
            '--confirm' => true,
        ]));
        $this->assertDatabaseMissing('member_profiles', ['account_id' => $other->id]);
    }

    private function actAsMember(): Account
    {
        $account = Account::create(['status' => 'active']);
        Sanctum::actingAs($account, ['api', 'client:member']);

        return $account;
    }

    private function publishPrivacyDocument(): LegalDocument
    {
        return LegalDocument::create([
            'scope_key' => 'global',
            'type' => 'privacy',
            'version' => 'test-v1',
            'title' => 'Privacy',
            'content' => 'Test privacy content',
            'content_hash' => hash('sha256', 'Test privacy content'),
            'status' => 'published',
            'is_required' => true,
            'published_at' => now(),
        ]);
    }

    private function fakeWechatPhone(string $mobile): void
    {
        Cache::forget('wechat:member:access-token');
        config()->set('wechat.apps.member', ['appid' => 'member-appid', 'secret' => 'member-secret']);
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response(['access_token' => 'provider-token'], 200),
            'https://api.weixin.qq.com/wxa/business/getuserphonenumber*' => Http::response([
                'errcode' => 0,
                'phone_info' => ['purePhoneNumber' => $mobile],
            ], 200),
        ]);
    }
}
