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
        $this->configureRegistrationVerificationLimits();
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

        // Bulk walk-in entry. Keyed by user|event so one operator
        // working two events simultaneously does not share the
        // budget. 10 requests per minute is generous for a single
        // staff member transcribing a stack of paper forms (each
        // request carries up to MAX_ROWS=100 rows) and tight enough
        // to catch a runaway loop.
        RateLimiter::for('walk-in-batch', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id.'|'.$request->route('event')
            );
        });

        // Staff registration deletion — OTP request. Keyed by
        // user|registration so one operator working multiple
        // registrations does not share the budget. Mirrors the
        // event-deletion-otp-request pattern.
        RateLimiter::for('registration-deletion-otp-request', function (Request $request) {
            return Limit::perMinute(3)->by(
                $request->user()?->id.'|'.$request->route('registration')
            );
        });

        // Staff registration deletion — OTP verify. Higher bound
        // than the request limiter so a fat-fingered typo does not
        // lock the operator out before the service's own 5-attempt
        // counter takes over.
        RateLimiter::for('registration-deletion-otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id.'|'.$request->route('registration')
            );
        });
    }

    protected function configureRegistrationVerificationLimits(): void
    {
        RateLimiter::for('registration-verification-view', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('registration-verification-attempt', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('registration-verification-resend', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('registration-verification-staff-view', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id));
        RateLimiter::for('registration-verification-phone', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id));
    }
}
