<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Services\Payments\ManagedPaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, ManagedPaymentGateway::class);
    }

    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            $login = Str::lower((string) $request->input('login'));

            return Limit::perMinute(5)->by($login.'|'.$request->ip());
        });

        Route::middleware('api')
            ->prefix('api/v1/admin')
            ->group(base_path('routes/admin.php'));

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(base_path('routes/admin-public.php'));
    }
}
