<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Sms\SmsGateway;
use App\Services\Payments\DemoPaymentGateway;
use App\Services\Payments\WechatPaymentGateway;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, function () {
            return match (config('sms.driver')) {
                'log' => $this->app->make(LogSmsGateway::class),
                default => $this->app->make(LogSmsGateway::class),
            };
        });

        $this->app->singleton(PaymentGateway::class, function () {
            return match (config('payment.driver')) {
                'wechat' => $this->app->make(WechatPaymentGateway::class),
                default => $this->app->make(DemoPaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
