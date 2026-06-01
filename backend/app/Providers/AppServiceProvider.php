<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Authenticate::redirectUsing(fn (Request $request) => route('admin.login'));

        RateLimiter::for('auth-login', function (Request $request) {
            $identity = strtolower((string) ($request->input('email') ?: $request->input('cedula') ?: 'anonymous'));
            $identityHash = sha1(substr($identity, 0, 150));

            return [
                Limit::perMinute(5)->by('login-user:'.$identityHash.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return [
                Limit::perMinute(3)->by('register-ip:'.$request->ip()),
                Limit::perHour(20)->by('register-hour-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-google', function (Request $request) {
            return [
                Limit::perMinute(10)->by('google-ip:'.$request->ip()),
                Limit::perMinute(5)->by('google-session:'.sha1((string) $request->input('credential')).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('invoice-scan', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perMinute(12)->by($key),
                Limit::perMinute(30)->by('scan-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('game-action', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perSecond(1)->by('game-second:'.$key),
                Limit::perMinute(20)->by('game-minute:'.$key),
            ];
        });

        RateLimiter::for('redeem-action', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perSecond(1)->by('redeem-second:'.$key),
                Limit::perMinute(20)->by('redeem-minute:'.$key),
            ];
        });

        RateLimiter::for('cashier-scan', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perSecond(2)->by('cashier-second:'.$key),
                Limit::perMinute(60)->by('cashier-minute:'.$key),
            ];
        });
    }
}
