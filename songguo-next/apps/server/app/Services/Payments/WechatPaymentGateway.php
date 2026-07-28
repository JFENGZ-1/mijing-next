<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WechatPaymentGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.mch.weixin.qq.com';

    public function driver(): string
    {
        return 'wechat';
    }

    /**
     * 商户参数是否配置齐全（未配置时降级为本地占位模式，便于开发联调）。
     */
    public function configured(): bool
    {
        return (string) config('payment.wechat.merchant_id') !== ''
            && (string) config('payment.wechat.merchant_serial_no') !== ''
            && $this->privateKey() !== null
            && (string) config('payment.wechat.api_v3_key') !== ''
            && (string) config('payment.wechat.notify_url') !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function createMemberCardCheckout(
        MemberCardOrder $order,
        Account $account,
        Member $member,
        Site $site,
        CardProduct $product,
    ): array {
        if (! $this->configured()) {
            return $this->placeholderCheckout($order);
        }

        $appid = (string) config('wechat.apps.member.appid');
        abort_if($appid === '', 503, 'WECHAT_PAY_NOT_CONFIGURED');

        $openid = $account->wechatIdentities()
            ->where('appid', $appid)
            ->orderByDesc('last_authenticated_at')
            ->value('openid');
        abort_if(! $openid, 422, 'WECHAT_OPENID_MISSING');

        $body = json_encode([
            'appid' => $appid,
            'mchid' => (string) config('payment.wechat.merchant_id'),
            'description' => mb_substr('会员卡-'.$product->name, 0, 127),
            'out_trade_no' => $order->order_no,
            'notify_url' => (string) config('payment.wechat.notify_url'),
            'amount' => [
                'total' => (int) round(((float) $order->amount) * 100),
                'currency' => 'CNY',
            ],
            'payer' => ['openid' => $openid],
            'attach' => json_encode(['orderId' => $order->id, 'tenantId' => $order->tenant_id]),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $path = '/v3/pay/transactions/jsapi';
        $response = Http::withHeaders([
            'Authorization' => $this->authorizationHeader('POST', $path, $body),
            'Accept' => 'application/json',
            'User-Agent' => 'songguo-next-server',
        ])->withBody($body, 'application/json')->post(self::API_BASE.$path);

        if (! $response->successful() || ! is_string($response->json('prepay_id'))) {
            Log::error('wechat-pay jsapi checkout failed', [
                'orderNo' => $order->order_no,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            abort(502, 'WECHAT_PAY_CHECKOUT_FAILED');
        }

        $prepayId = (string) $response->json('prepay_id');

        return [
            'driver' => $this->driver(),
            'autoPaid' => false,
            'configured' => true,
            'orderNo' => $order->order_no,
            'prepayId' => $prepayId,
            'paymentParams' => $this->jsapiPaymentParams($appid, $prepayId),
        ];
    }

    public function validateWebhookSignature(Request $request): bool
    {
        // 微信官方回调：Wechatpay-Signature 平台公钥验签
        if ($request->hasHeader('Wechatpay-Signature')) {
            return $this->validateOfficialSignature($request);
        }

        // 本地/联调回调：HMAC 共享密钥验签
        $secret = (string) config('payment.wechat.webhook_secret');
        if ($secret === '') {
            return false;
        }

        $provided = (string) $request->header('X-Wechat-Signature', '');
        if ($provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return null;
        }

        // 微信官方回调：resource 为 AES-256-GCM 密文
        $resource = $payload['resource'] ?? null;
        if (is_array($resource) && isset($resource['ciphertext'])) {
            $transaction = $this->decryptResource($resource);
            if ($transaction === null) {
                return null;
            }

            $orderNo = $transaction['out_trade_no'] ?? null;
            if (! is_string($orderNo) || $orderNo === '') {
                return null;
            }

            $eventType = $payload['event_type'] ?? null;
            $tradeState = $transaction['trade_state'] ?? null;
            if (! is_string($eventType) || $eventType === '') {
                $eventType = $tradeState === 'SUCCESS' ? 'TRANSACTION.SUCCESS' : 'TRANSACTION.UNKNOWN';
            }

            return [
                'orderNo' => $orderNo,
                'eventType' => $eventType,
                'transactionId' => $transaction['transaction_id'] ?? null,
            ];
        }

        // 本地/联调回调：明文报文
        $orderNo = $payload['out_trade_no'] ?? $payload['orderNo'] ?? null;
        $eventType = $payload['event_type'] ?? $payload['eventType'] ?? null;

        if (! is_string($orderNo) || $orderNo === '') {
            return null;
        }

        return [
            'orderNo' => $orderNo,
            'eventType' => is_string($eventType) ? $eventType : 'TRANSACTION.SUCCESS',
            'transactionId' => $payload['transaction_id'] ?? $payload['transactionId'] ?? null,
        ];
    }

    /**
     * 商户主动查单：支付成功返回交易信息，否则返回 null。
     *
     * @return array<string, mixed>|null
     */
    public function queryOrderPaid(string $orderNo): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $mchid = (string) config('payment.wechat.merchant_id');
        $path = "/v3/pay/transactions/out-trade-no/{$orderNo}?mchid={$mchid}";

        $response = Http::withHeaders([
            'Authorization' => $this->authorizationHeader('GET', $path, ''),
            'Accept' => 'application/json',
            'User-Agent' => 'songguo-next-server',
        ])->get(self::API_BASE.$path);

        if (! $response->successful()) {
            Log::warning('wechat-pay order query failed', [
                'orderNo' => $orderNo,
                'status' => $response->status(),
            ]);

            return null;
        }

        if ($response->json('trade_state') !== 'SUCCESS') {
            return null;
        }

        return [
            'orderNo' => $orderNo,
            'eventType' => 'TRANSACTION.SUCCESS',
            'transactionId' => $response->json('transaction_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderCheckout(MemberCardOrder $order): array
    {
        $prepayId = 'wx_prepay_'.Str::lower((string) Str::ulid());

        return [
            'driver' => $this->driver(),
            'autoPaid' => false,
            'configured' => false,
            'orderNo' => $order->order_no,
            'prepayId' => $prepayId,
            'paymentParams' => [
                'timeStamp' => (string) now()->timestamp,
                'nonceStr' => Str::random(16),
                'package' => "prepay_id={$prepayId}",
                'signType' => 'RSA',
                'paySign' => 'PLACEHOLDER_PAY_SIGN',
            ],
        ];
    }

    /**
     * @return array{appId: string, timeStamp: string, nonceStr: string, package: string, signType: string, paySign: string}
     */
    private function jsapiPaymentParams(string $appid, string $prepayId): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(32);
        $package = "prepay_id={$prepayId}";
        $message = "{$appid}\n{$timestamp}\n{$nonce}\n{$package}\n";

        return [
            'appId' => $appid,
            'timeStamp' => $timestamp,
            'nonceStr' => $nonce,
            'package' => $package,
            'signType' => 'RSA',
            'paySign' => $this->sign($message),
        ];
    }

    private function authorizationHeader(string $method, string $pathWithQuery, string $body): string
    {
        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(32);
        $message = "{$method}\n{$pathWithQuery}\n{$timestamp}\n{$nonce}\n{$body}\n";
        $signature = $this->sign($message);
        $mchid = (string) config('payment.wechat.merchant_id');
        $serial = (string) config('payment.wechat.merchant_serial_no');

        return 'WECHATPAY2-SHA256-RSA2048 '
            ."mchid=\"{$mchid}\",nonce_str=\"{$nonce}\",signature=\"{$signature}\",timestamp=\"{$timestamp}\",serial_no=\"{$serial}\"";
    }

    private function sign(string $message): string
    {
        $key = $this->privateKey();
        abort_if($key === null, 503, 'WECHAT_PAY_NOT_CONFIGURED');

        $success = openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);
        abort_unless($success, 500, 'WECHAT_PAY_SIGN_FAILED');

        return base64_encode($signature);
    }

    private function validateOfficialSignature(Request $request): bool
    {
        $publicKey = $this->platformPublicKey();
        if ($publicKey === null) {
            Log::warning('wechat-pay webhook received but platform public key not configured');

            return false;
        }

        $expectedSerial = (string) config('payment.wechat.platform_public_key_id');
        $serial = (string) $request->header('Wechatpay-Serial', '');
        if ($expectedSerial !== '' && $serial !== '' && ! hash_equals($expectedSerial, $serial)) {
            return false;
        }

        $timestamp = (string) $request->header('Wechatpay-Timestamp', '');
        $nonce = (string) $request->header('Wechatpay-Nonce', '');
        $signature = base64_decode((string) $request->header('Wechatpay-Signature', ''), true);
        if ($timestamp === '' || $nonce === '' || $signature === false) {
            return false;
        }

        // 防重放：时间偏差超过 5 分钟拒绝
        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        $message = "{$timestamp}\n{$nonce}\n{$request->getContent()}\n";

        return openssl_verify($message, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>|null
     */
    private function decryptResource(array $resource): ?array
    {
        $key = (string) config('payment.wechat.api_v3_key');
        if ($key === '') {
            return null;
        }

        $ciphertext = base64_decode((string) ($resource['ciphertext'] ?? ''), true);
        $nonce = (string) ($resource['nonce'] ?? '');
        $aad = (string) ($resource['associated_data'] ?? '');
        if ($ciphertext === false || strlen($ciphertext) <= 16 || $nonce === '') {
            return null;
        }

        $tag = substr($ciphertext, -16);
        $data = substr($ciphertext, 0, -16);

        $plaintext = openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
        if ($plaintext === false) {
            Log::error('wechat-pay webhook resource decrypt failed');

            return null;
        }

        $decoded = json_decode($plaintext, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return \OpenSSLAsymmetricKey|null
     */
    private function privateKey()
    {
        $configured = (string) config('payment.wechat.private_key');

        return $this->loadKey($configured, fn (string $pem) => openssl_pkey_get_private($pem));
    }

    /**
     * @return \OpenSSLAsymmetricKey|null
     */
    private function platformPublicKey()
    {
        $configured = (string) config('payment.wechat.platform_public_key');

        return $this->loadKey($configured, function (string $pem) {
            // 支持直接配置公钥或平台证书（从证书中提取公钥）
            if (str_contains($pem, 'BEGIN CERTIFICATE')) {
                $cert = openssl_x509_read($pem);

                return $cert === false ? false : openssl_pkey_get_public($cert);
            }

            return openssl_pkey_get_public($pem);
        });
    }

    /**
     * @param  callable(string): (\OpenSSLAsymmetricKey|false)  $loader
     * @return \OpenSSLAsymmetricKey|null
     */
    private function loadKey(string $configured, callable $loader)
    {
        if ($configured === '') {
            return null;
        }

        $pem = str_contains($configured, '-----BEGIN')
            ? $configured
            : (is_file($configured) ? (string) file_get_contents($configured) : '');
        if ($pem === '') {
            return null;
        }

        $key = $loader($pem);

        return $key === false ? null : $key;
    }
}
