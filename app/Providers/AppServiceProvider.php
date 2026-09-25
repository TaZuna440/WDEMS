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
    }
}
