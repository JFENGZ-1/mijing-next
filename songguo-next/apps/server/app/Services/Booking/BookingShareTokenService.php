<?php

namespace App\Services\Booking;

use App\Models\ScheduleSession;

class BookingShareTokenService
{
    public const TTL_DAYS = 7;

    /**
     * @return array{token: string, expiresAt: string, publicUrl: string}
     */
    public function issue(ScheduleSession $session): array
    {
        $expiresAt = now()->addDays(self::TTL_DAYS);
        $payload = [
            't' => $session->tenant_id,
            's' => $session->site_id,
            'ss' => $session->id,
            'exp' => $expiresAt->getTimestamp(),
        ];

        $token = $this->encode($payload);

        return [
            'token' => $token,
            'expiresAt' => $expiresAt->toIso8601String(),
            'publicUrl' => url("/api/v1/public/booking/share/sessions/{$token}"),
        ];
    }

    /**
     * @return array{t: int, s: int, ss: int, exp: int}
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token, 2);
        abort_unless(count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '', 404, 'BOOKING_SHARE_TOKEN_INVALID');

        $payloadJson = $this->base64UrlDecode($parts[0]);
        abort_unless($payloadJson !== false, 404, 'BOOKING_SHARE_TOKEN_INVALID');

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $parts[0], (string) config('app.key'), true),
        );
        abort_unless(hash_equals($expectedSignature, $parts[1]), 404, 'BOOKING_SHARE_TOKEN_INVALID');

        $payload = json_decode($payloadJson, true);
        abort_unless(
            is_array($payload)
            && isset($payload['t'], $payload['s'], $payload['ss'], $payload['exp'])
            && is_numeric($payload['t'])
            && is_numeric($payload['s'])
            && is_numeric($payload['ss'])
            && is_numeric($payload['exp']),
            404,
            'BOOKING_SHARE_TOKEN_INVALID',
        );

        $normalized = [
            't' => (int) $payload['t'],
            's' => (int) $payload['s'],
            'ss' => (int) $payload['ss'],
            'exp' => (int) $payload['exp'],
        ];

        abort_unless($normalized['exp'] >= now()->getTimestamp(), 410, 'BOOKING_SHARE_TOKEN_EXPIRED');

        return $normalized;
    }

    /**
     * @param  array{t: int, s: int, ss: int, exp: int}  $payload
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
