<?php

namespace App\Services\Members;

use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class NationalIdProtectionService
{
    public function normalize(string $nationalId): string
    {
        $normalized = strtoupper((string) preg_replace('/[\s-]+/', '', trim($nationalId)));
        if (! preg_match('/^[0-9A-Z]{6,32}$/', $normalized)) {
            throw new InvalidArgumentException('INVALID_NATIONAL_ID');
        }

        return $normalized;
    }

    public function hashForTenant(string $normalized, int $tenantId): string
    {
        return hash_hmac('sha256', "tenant:{$tenantId}|national-id|{$normalized}", (string) config('app.key'));
    }

    public function encrypt(string $normalized): string
    {
        return Crypt::encryptString($normalized);
    }
}
