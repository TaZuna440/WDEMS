<?php

namespace App\Providers;

use App\Services\TwoFactor\EmailTwoFactorService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureLogoutCleanup();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rules = Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return app()->environment('testing')
                ? $rules
                : $rules->uncompromised();
        });
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('event-deletion-otp-request', function (Request $request) {
            return Limit::perMinute(3)->by(
                $request->user()?->id.'|'.$request->route('event')
            );
        });

        RateLimiter::for('event-deletion-otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id.'|'.$request->route('event')
            );
        });

        RateLimiter::for('device-verification-send', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id);
        });

        RateLimiter::for('device-verification-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id);
        });
    }

    protected function configureLogoutCleanup(): void
    {
        // Clear the trusted-device cookie on logout so a shared browser
        // doesn't carry stale device-trust state into the next session.
        Event::listen(Logout::class, function (): void {
            Cookie::queue(Cookie::forget(EmailTwoFactorService::DEVICE_COOKIE_NAME));
        });
    }
}
