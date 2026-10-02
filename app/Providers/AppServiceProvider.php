<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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

        // Public registration is anonymous — no user to key on. Per IP,
        // 5 per minute. Slow enough that a single person filling the
        // form once never hits it; tight enough that a script cannot
        // flood.
        RateLimiter::for('public-registration-submit', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
