<?php

namespace Tests\Unit;

use App\Contracts\Sms\SmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\MemberSmsVerificationService;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    public function test_log_sms_gateway_can_send_without_throwing(): void
    {
        $gateway = new LogSmsGateway;
        $gateway->sendVerificationCode('+8613800138000', '123456', ['purpose' => 'member_mobile_verification']);

        $this->assertTrue(true);
    }

    public function test_member_sms_verification_service_uses_gateway_binding(): void
    {
        $spy = new class
        {
            public bool $sent = false;
        };

        $this->app->instance(SmsGateway::class, new class($spy) implements SmsGateway
        {
            public function __construct(private object $spy) {}

            public function sendVerificationCode(string $phoneE164, string $code, array $context = []): void
            {
                $this->spy->sent = true;
            }
        });

        $service = app(MemberSmsVerificationService::class);
        $result = $service->sendMobileVerificationCode('+8613800138000');

        $this->assertSame(300, $result['expiresInSeconds']);
        $this->assertTrue($spy->sent);
    }
}
