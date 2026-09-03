<?php

namespace App\Services\Sms;

use App\Contracts\Sms\SmsGateway;
use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function sendVerificationCode(string $phoneE164, string $code, array $context = []): void
    {
        Log::info('sms.verification_code', [
            'phoneLast4' => strlen($phoneE164) >= 4 ? substr($phoneE164, -4) : null,
            'purpose' => $context['purpose'] ?? 'verification',
            'codeLength' => strlen($code),
        ]);
    }
}
