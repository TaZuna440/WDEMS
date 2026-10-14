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
        Route::post('phone/firebase', [RegistrationVerificationController::class, 'firebasePhone'])
            ->middleware('throttle:registration-verification-attempt')
            ->name('public-registration.verification.phone.firebase');
        Route::post('{channel}', [RegistrationVerificationController::class, 'verify'])
            ->whereIn('channel', ['email'])
            ->middleware('throttle:registration-verification-attempt')
            ->name('public-registration.verification.verify');
    });

