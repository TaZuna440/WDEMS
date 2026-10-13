<?php

use App\Http\Controllers\RegistrationVerificationController;
use App\Http\Middleware\ProtectRegistrationVerification;
use Illuminate\Support\Facades\Route;

// No bearer secrets in route parameters. Public authorization comes from the
// event-scoped, encrypted HttpOnly cookie and persisted challenge state.
Route::middleware([ProtectRegistrationVerification::class])
    ->prefix('r/{event:registration_slug}/verification')
    ->group(function () {
        Route::get('/', [RegistrationVerificationController::class, 'show'])
            ->middleware('throttle:registration-verification-view')
            ->name('public-registration.verification');
        Route::post('email/resend', [RegistrationVerificationController::class, 'resendEmail'])
            ->middleware('throttle:registration-verification-resend')
            ->name('public-registration.verification.resend');
        Route::post('complete', [RegistrationVerificationController::class, 'complete'])
            ->middleware('throttle:registration-verification-attempt')
            ->name('public-registration.verification.complete');
        Route::post('{channel}', [RegistrationVerificationController::class, 'verify'])
            ->whereIn('channel', ['email', 'phone'])
            ->middleware('throttle:registration-verification-attempt')
            ->name('public-registration.verification.verify');
    });

// Admins retain the existing verified.or.admin and device.trusted exemptions.
// Staff must additionally own the event; this is checked in the module on every
// generation request and in the queue query before projecting contact data.
Route::middleware(['auth', 'verified.or.admin', 'device.trusted', ProtectRegistrationVerification::class])
    ->group(function () {
        Route::get('registrations/verification', [RegistrationVerificationController::class, 'index'])
            ->middleware('throttle:registration-verification-staff-view')
            ->name('registration-verification.index');
        Route::post('registrations/verification/{challenge}/phone-code', [RegistrationVerificationController::class, 'generatePhone'])
            ->middleware('throttle:registration-verification-phone')
            ->name('registration-verification.phone-code');
    });
