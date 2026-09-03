<?php

namespace App\Services\Sms;

use App\Contracts\Sms\SmsGateway;
use Illuminate\Support\Str;

/**
 * Hook for future SMS OTP endpoints (e.g. POST /member/profile/send-mobile-code).
 * Current verify-mobile uses WeChat phone authorization, not SMS.
 */
class MemberSmsVerificationService
{
    public function __construct(
        private readonly SmsGateway $sms,
    ) {}

    /**
     * @return array{expiresInSeconds: int}
     */
    public function sendMobileVerificationCode(string $phoneE164): array
    {
        $code = (string) random_int(100000, 999999);

        $this->sms->sendVerificationCode($phoneE164, $code, [
            'purpose' => 'member_mobile_verification',
        ]);

        // Production should persist hashed code + expiry; omitted in dev scaffolding.
        return ['expiresInSeconds' => 300];
    }

    public function generateDevCode(): string
    {
        return Str::padLeft((string) random_int(0, 999999), 6, '0');
    }
}
