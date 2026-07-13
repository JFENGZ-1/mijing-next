<?php

namespace App\Contracts\Sms;

interface SmsGateway
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function sendVerificationCode(string $phoneE164, string $code, array $context = []): void;
}
