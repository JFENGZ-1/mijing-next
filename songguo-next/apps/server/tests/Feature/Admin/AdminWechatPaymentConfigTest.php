<?php

namespace Tests\Feature\Admin;

use App\Contracts\Payments\PaymentGateway;
use App\Models\PlatformPaymentConfig;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWechatPaymentConfigTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = SuperAdmin::query()->create([
            'username' => 'payment.admin',
            'name' => '支付管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($this->admin, ['api', 'client:admin', 'admin:platform']);
    }

    public function test_super_admin_can_store_encrypted_payment_config_without_reading_secrets_back(): void
    {
        $response = $this->putJson('/api/v1/admin/settings/payments/wechat', [
            'version' => 0,
            'enabled' => false,
            'merchantId' => '1900000109',
            'merchantSerialNo' => 'SERIAL-001',
            'privateKey' => '-----BEGIN PRIVATE KEY-----\nsecret\n-----END PRIVATE KEY-----',
            'apiV3Key' => str_repeat('a', 32),
            'platformPublicKey' => '-----BEGIN PUBLIC KEY-----\npublic\n-----END PUBLIC KEY-----',
            'platformPublicKeyId' => 'PUB_KEY_ID_001',
            'notifyUrl' => 'https://example.com/webhooks/wechat-pay',
            'webhookSecret' => str_repeat('b', 32),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.source', 'database')
            ->assertJsonPath('data.hasPrivateKey', true)
            ->assertJsonPath('data.hasApiV3Key', true)
            ->assertJsonMissing(['privateKey'])
            ->assertJsonMissing(['apiV3Key']);

        $raw = DB::table('platform_payment_configs')->first();
        $this->assertNotSame(str_repeat('a', 32), $raw->api_v3_key);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $raw->private_key);
        $this->assertSame(str_repeat('a', 32), PlatformPaymentConfig::query()->firstOrFail()->api_v3_key);
    }

    public function test_payment_config_requires_complete_values_before_enabling_and_uses_version_lock(): void
    {
        $this->putJson('/api/v1/admin/settings/payments/wechat', [
            'version' => 0,
            'enabled' => true,
            'merchantId' => '1900000109',
        ])->assertUnprocessable()->assertJsonValidationErrors('enabled');

        $this->putJson('/api/v1/admin/settings/payments/wechat', [
            'version' => 0,
            'enabled' => false,
            'merchantId' => '1900000109',
        ])->assertOk()->assertJsonPath('data.version', 1);

        $this->putJson('/api/v1/admin/settings/payments/wechat', [
            'version' => 0,
            'enabled' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('version');
    }

    public function test_managed_gateway_uses_database_enable_switch(): void
    {
        config(['payment.driver' => 'demo']);
        $this->assertSame('demo', app(PaymentGateway::class)->driver());

        PlatformPaymentConfig::query()->create([
            'provider' => 'wechat',
            'enabled' => true,
            'merchant_id' => '1900000109',
            'merchant_serial_no' => 'SERIAL-001',
            'private_key' => 'placeholder',
            'api_v3_key' => str_repeat('a', 32),
            'platform_public_key' => 'placeholder',
            'platform_public_key_id' => 'PUB_KEY_ID_001',
            'notify_url' => 'https://example.com/webhooks/wechat-pay',
        ]);

        $this->assertSame('wechat', app(PaymentGateway::class)->driver());
    }
}
