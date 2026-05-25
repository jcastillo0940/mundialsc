<?php

namespace App\Providers;

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
