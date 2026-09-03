<?php

namespace App\Services\Cards;

use App\Models\MemberCard;

class CardTransferShareTokenService
{
    public const TTL_DAYS = 7;

    /**
     * @return array{token: string, expiresAt: string}
     */
    public function issue(MemberCard $card): array
    {
        $expiresAt = now()->addDays(self::TTL_DAYS);
        $payload = [
            't' => $card->tenant_id,
            'mc' => $card->id,
            'fm' => $card->member_id,
            'exp' => $expiresAt->getTimestamp(),
        ];

        return [
            'token' => $this->encode($payload),
            'expiresAt' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @return array{t: int, mc: int, fm: int, exp: int}
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token, 2);
        abort_unless(count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '', 404, 'CARD_TRANSFER_TOKEN_INVALID');

        $payloadJson = $this->base64UrlDecode($parts[0]);
        abort_unless($payloadJson !== false, 404, 'CARD_TRANSFER_TOKEN_INVALID');

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $parts[0], (string) config('app.key'), true),
        );
        abort_unless(hash_equals($expectedSignature, $parts[1]), 404, 'CARD_TRANSFER_TOKEN_INVALID');

        $payload = json_decode($payloadJson, true);
        abort_unless(
            is_array($payload)
            && isset($payload['t'], $payload['mc'], $payload['fm'], $payload['exp'])
            && is_numeric($payload['t'])
            && is_numeric($payload['mc'])
            && is_numeric($payload['fm'])
            && is_numeric($payload['exp']),
            404,
            'CARD_TRANSFER_TOKEN_INVALID',
        );

        $normalized = [
            't' => (int) $payload['t'],
            'mc' => (int) $payload['mc'],
            'fm' => (int) $payload['fm'],
            'exp' => (int) $payload['exp'],
        ];

        abort_unless($normalized['exp'] >= now()->getTimestamp(), 410, 'CARD_TRANSFER_TOKEN_EXPIRED');

        return $normalized;
    }

    /**
     * @param  array{t: int, mc: int, fm: int, exp: int}  $payload
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
