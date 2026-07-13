<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_test_code_is_rejected_before_wechat_exchange(): void
    {
        $response = $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'member',
            'code' => 'test',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_unconfigured_wechat_application_fails_closed(): void
    {
        config()->set('wechat.apps.member', ['appid' => null, 'secret' => null]);

        $response = $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'member',
            'code' => 'one-time-code',
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('code', 'WECHAT_NOT_CONFIGURED');
    }

    public function test_wechat_http_failure_is_returned_as_sanitized_unavailable_error(): void
    {
        config()->set('wechat.apps.member', ['appid' => 'member-appid', 'secret' => 'sensitive-secret']);
        Http::fake(['api.weixin.qq.com/*' => Http::response('upstream failed', 500)]);

        $response = $this->postJson('/api/v1/auth/wechat/login', [
            'appType' => 'member',
            'code' => 'one-time-sensitive-code',
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('code', 'WECHAT_UNAVAILABLE')
            ->assertJsonMissing(['secret' => 'sensitive-secret'])
            ->assertJsonMissing(['code' => 'one-time-sensitive-code']);
    }
}
