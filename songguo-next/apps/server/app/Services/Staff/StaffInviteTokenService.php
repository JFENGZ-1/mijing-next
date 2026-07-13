<?php

namespace App\Services\Staff;

use App\Models\Site;
use App\Models\Staff;

class StaffInviteTokenService
{
    public const TTL_DAYS = 7;

    /**
     * @return array{sign: string, expiresAt: string}
     */
    public function issue(Staff $staff, Site $site): array
    {
        $expiresAt = now()->addDays(self::TTL_DAYS);
        $payload = [
            't' => $staff->tenant_id,
            's' => $site->id,
            'st' => $staff->id,
            'exp' => $expiresAt->getTimestamp(),
        ];

        return [
            'sign' => $this->encode($payload),
            'expiresAt' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @return array{t: int, s: int, st: int, exp: int}
     */
    public function verify(string $sign): array
    {
        $parts = explode('.', $sign, 2);
        abort_unless(count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '', 404, 'STAFF_INVITE_SIGN_INVALID');

        $payloadJson = $this->base64UrlDecode($parts[0]);
        abort_unless($payloadJson !== false, 404, 'STAFF_INVITE_SIGN_INVALID');

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $parts[0], (string) config('app.key'), true),
        );
        abort_unless(hash_equals($expectedSignature, $parts[1]), 404, 'STAFF_INVITE_SIGN_INVALID');

        $payload = json_decode($payloadJson, true);
        abort_unless(
            is_array($payload)
            && isset($payload['t'], $payload['s'], $payload['st'], $payload['exp'])
            && is_numeric($payload['t'])
            && is_numeric($payload['s'])
            && is_numeric($payload['st'])
            && is_numeric($payload['exp']),
            404,
            'STAFF_INVITE_SIGN_INVALID',
        );

        $normalized = [
            't' => (int) $payload['t'],
            's' => (int) $payload['s'],
            'st' => (int) $payload['st'],
            'exp' => (int) $payload['exp'],
        ];

        abort_unless($normalized['exp'] >= now()->getTimestamp(), 410, 'STAFF_INVITE_SIGN_EXPIRED');

        return $normalized;
    }

    /**
     * @param  array{t: int, s: int, st: int, exp: int}  $payload
     */
    private function encode(array $payload): string
    {
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, (string) config('app.key'), true),
        );

        return $encodedPayload.'.'.$signature;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
