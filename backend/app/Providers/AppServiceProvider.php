<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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

        $this->configureMailFromSiteSettings();

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

        RateLimiter::for('password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'anonymous'));

            return [
                Limit::perMinute(3)->by('reset-email:'.sha1($email)),
                Limit::perHour(10)->by('reset-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('newsletter', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'anonymous'));

            return [
                Limit::perMinute(5)->by('newsletter-email:'.sha1($email)),
                Limit::perHour(30)->by('newsletter-ip:'.$request->ip()),
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

    private function configureMailFromSiteSettings(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $mailer = SiteSetting::getOrConfig('mail_mailer', 'mail.default', 'smtp') ?? 'smtp';
        $host = SiteSetting::getOrConfig('mail_host', 'mail.mailers.smtp.host', '127.0.0.1') ?? '127.0.0.1';
        $port = (int) SiteSetting::getOrConfig('mail_port', 'mail.mailers.smtp.port', '2525');
        $username = SiteSetting::getOrConfig('mail_username', 'mail.mailers.smtp.username', '') ?? '';
        $password = SiteSetting::getOrConfig('mail_password', 'mail.mailers.smtp.password', '') ?? '';
        $encryption = SiteSetting::getOrConfig('mail_encryption', 'mail.mailers.smtp.scheme', '') ?? '';
        $fromAddress = SiteSetting::getOrConfig('mail_from_address', 'mail.from.address', 'hello@example.com') ?? 'hello@example.com';
        $fromName = SiteSetting::getOrConfig('mail_from_name', 'mail.from.name', config('app.name', 'Laravel')) ?? config('app.name', 'Laravel');

        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $username !== '' ? $username : null,
            'mail.mailers.smtp.password' => $password !== '' ? $password : null,
            'mail.mailers.smtp.scheme' => $encryption !== '' ? $encryption : null,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);
    }
}
