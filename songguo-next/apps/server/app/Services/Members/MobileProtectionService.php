<?php

namespace App\Services\Members;

use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class MobileProtectionService
{
    public function normalize(string $mobile): string
    {
        $normalized = preg_replace('/[^0-9]/', '', $mobile);
        if (! is_string($normalized) || strlen($normalized) < 7 || strlen($normalized) > 20) {
            throw new InvalidArgumentException('INVALID_MOBILE');
        }

        return $normalized;
    }

    public function hash(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    public function hashForTenant(string $normalized, int $tenantId): string
    {
        return hash_hmac('sha256', "tenant:{$tenantId}|{$normalized}", (string) config('app.key'));
    }

    public function encrypt(string $normalized): string
    {
        return Crypt::encryptString($normalized);
    }

    public function decrypt(string $ciphertext): string
    {
        return Crypt::decryptString($ciphertext);
    }
}
