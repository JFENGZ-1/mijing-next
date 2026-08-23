<?php

namespace App\Services\Admin;

use App\Models\PlatformPaymentConfig;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformPaymentConfigService
{
    /**
     * @return array{exists: bool, enabled: bool, values: array<string, string>}
     */
    public function wechatRuntimeConfig(): array
    {
        $config = PlatformPaymentConfig::query()->where('provider', 'wechat')->first();

        if (! $config) {
            return [
                'exists' => false,
                'enabled' => config('payment.driver') === 'wechat',
                'values' => $this->environmentValues(),
            ];
        }

        return [
            'exists' => true,
            'enabled' => $config->enabled,
            'values' => [
                'merchant_id' => (string) $config->merchant_id,
                'merchant_serial_no' => (string) $config->merchant_serial_no,
                'private_key' => (string) $config->private_key,
                'api_v3_key' => (string) $config->api_v3_key,
                'platform_public_key' => (string) $config->platform_public_key,
                'platform_public_key_id' => (string) $config->platform_public_key_id,
                'notify_url' => (string) $config->notify_url,
                'webhook_secret' => (string) $config->webhook_secret,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function safeWechatConfig(): array
    {
        $config = PlatformPaymentConfig::query()
            ->with('updatedBy:id,username,name')
            ->where('provider', 'wechat')
            ->first();

        if (! $config) {
            $environment = $this->environmentValues();

            return [
                'exists' => false,
                'source' => 'environment',
                'enabled' => config('payment.driver') === 'wechat',
                'merchantId' => $environment['merchant_id'],
                'merchantSerialNo' => $environment['merchant_serial_no'],
                'platformPublicKeyId' => $environment['platform_public_key_id'],
                'notifyUrl' => $environment['notify_url'],
                'hasPrivateKey' => $environment['private_key'] !== '',
                'hasApiV3Key' => $environment['api_v3_key'] !== '',
                'hasPlatformPublicKey' => $environment['platform_public_key'] !== '',
                'hasWebhookSecret' => $environment['webhook_secret'] !== '',
                'configured' => $this->isComplete($environment),
                'version' => 0,
                'updatedAt' => null,
                'updatedBy' => null,
            ];
        }

        $values = $this->wechatRuntimeConfig()['values'];

        return [
            'exists' => true,
            'source' => 'database',
            'enabled' => $config->enabled,
            'merchantId' => $config->merchant_id,
            'merchantSerialNo' => $config->merchant_serial_no,
            'platformPublicKeyId' => $config->platform_public_key_id,
            'notifyUrl' => $config->notify_url,
            'hasPrivateKey' => $values['private_key'] !== '',
            'hasApiV3Key' => $values['api_v3_key'] !== '',
            'hasPlatformPublicKey' => $values['platform_public_key'] !== '',
            'hasWebhookSecret' => $values['webhook_secret'] !== '',
            'configured' => $this->isComplete($values),
            'version' => $config->version,
            'updatedAt' => $config->updated_at?->toISOString(),
            'updatedBy' => $config->updatedBy ? [
                'id' => $config->updatedBy->id,
                'username' => $config->updatedBy->username,
                'name' => $config->updatedBy->name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateWechat(array $payload, SuperAdmin $admin): array
    {
        DB::transaction(function () use ($payload, $admin) {
            $config = PlatformPaymentConfig::query()
                ->where('provider', 'wechat')
                ->lockForUpdate()
                ->first();

            $expectedVersion = (int) $payload['version'];
            $actualVersion = $config?->version ?? 0;
            if ($expectedVersion !== $actualVersion) {
                throw ValidationException::withMessages([
                    'version' => ['配置已被其他管理员修改，请刷新后重试。'],
                ]);
            }

            $config ??= new PlatformPaymentConfig(['provider' => 'wechat']);
            $fieldMap = [
                'merchantId' => 'merchant_id',
                'merchantSerialNo' => 'merchant_serial_no',
                'platformPublicKeyId' => 'platform_public_key_id',
                'notifyUrl' => 'notify_url',
                'privateKey' => 'private_key',
                'apiV3Key' => 'api_v3_key',
                'platformPublicKey' => 'platform_public_key',
                'webhookSecret' => 'webhook_secret',
            ];
            foreach ($fieldMap as $requestField => $databaseField) {
                if (array_key_exists($requestField, $payload)) {
                    $config->{$databaseField} = $payload[$requestField] ?: null;
                }
            }
            foreach ($payload['clearSecrets'] ?? [] as $secretField) {
                $config->{$fieldMap[$secretField]} = null;
            }

            $config->enabled = (bool) $payload['enabled'];
            $config->version = $actualVersion + 1;
            $config->updated_by_super_admin_id = $admin->id;

            $values = [
                'merchant_id' => (string) $config->merchant_id,
                'merchant_serial_no' => (string) $config->merchant_serial_no,
                'private_key' => (string) $config->private_key,
                'api_v3_key' => (string) $config->api_v3_key,
                'platform_public_key' => (string) $config->platform_public_key,
                'platform_public_key_id' => (string) $config->platform_public_key_id,
                'notify_url' => (string) $config->notify_url,
                'webhook_secret' => (string) $config->webhook_secret,
            ];
            if ($config->enabled && ! $this->isComplete($values)) {
                throw ValidationException::withMessages([
                    'enabled' => ['启用微信支付前必须填写商户号、证书序列号、商户私钥、APIv3 密钥、平台公钥、公钥 ID 和 HTTPS 回调地址。'],
                ]);
            }

            $config->save();
        });

        return $this->safeWechatConfig();
    }

    /**
     * @param  array<string, string>  $values
     */
    private function isComplete(array $values): bool
    {
        return $values['merchant_id'] !== ''
            && $values['merchant_serial_no'] !== ''
            && $values['private_key'] !== ''
            && strlen($values['api_v3_key']) === 32
            && $values['platform_public_key'] !== ''
            && $values['platform_public_key_id'] !== ''
            && str_starts_with($values['notify_url'], 'https://');
    }

    /**
     * @return array<string, string>
     */
    private function environmentValues(): array
    {
        return [
            'merchant_id' => (string) config('payment.wechat.merchant_id'),
            'merchant_serial_no' => (string) config('payment.wechat.merchant_serial_no'),
            'private_key' => (string) config('payment.wechat.private_key'),
            'api_v3_key' => (string) config('payment.wechat.api_v3_key'),
            'platform_public_key' => (string) config('payment.wechat.platform_public_key'),
            'platform_public_key_id' => (string) config('payment.wechat.platform_public_key_id'),
            'notify_url' => (string) config('payment.wechat.notify_url'),
            'webhook_secret' => (string) config('payment.wechat.webhook_secret'),
        ];
    }
}
