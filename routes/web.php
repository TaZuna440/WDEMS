<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\DeviceVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventDeletionController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegistrationFormController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Device verification routes — must be OUTSIDE the device.trusted middleware.
// These stay under strict `verified` — an unverified user must verify their
// email before touching the 2FA challenge. Admins never reach this group
// because EnsureDeviceIsTrusted exempts them before redirecting.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('verify-device', [DeviceVerificationController::class, 'show'])
        ->name('device.verify');
    Route::post('verify-device/send-code', [DeviceVerificationController::class, 'sendCode'])
        ->middleware('throttle:device-verification-send')
        ->name('device.verify.send');
    Route::post('verify-device/verify', [DeviceVerificationController::class, 'verify'])
        ->middleware('throttle:device-verification-verify')
        ->name('device.verify.confirm');
});

// Protected application routes — require a trusted device.
// Admins are self-exempt from both email verification
// (EnsureEmailIsVerifiedOrAdmin) and the device challenge
// (EnsureDeviceIsTrusted), so the admin dashboard lives on the same
// pipeline as everything else.
Route::middleware(['auth', 'verified.or.admin', 'device.trusted'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Registration queue — the organizer's daily workflow entry point.
    // Phase 1 of the registration plan: visual only. Phase 3 adds the
    // public submission surface.
    Route::get('registrations', [RegistrationController::class, 'index'])
        ->name('registrations.index');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');

    // Registration form builder — Phase 2 of the registration plan.
    // Replaces the events.options.* routes from Phase 1. The old
    // EventOptionController and its page are removed in Block 6 of the
    // same phase.
    Route::get('events/{event}/registration-form', [RegistrationFormController::class, 'show'])
        ->name('events.registration-form.show');
    Route::put('events/{event}/registration-form', [RegistrationFormController::class, 'update'])
        ->name('events.registration-form.update');

    Route::post('events/{event}/open-registration', [EventController::class, 'openRegistration'])->name('events.open-registration');
    Route::post('events/{event}/close-registration', [EventController::class, 'closeRegistration'])->name('events.close-registration');

    Route::delete('events/{event}', [EventDeletionController::class, 'destroy'])->name('events.destroy');
    Route::post('events/{event}/deletion/request-otp', [EventDeletionController::class, 'requestOtp'])
        ->middleware('throttle:event-deletion-otp-request')->name('events.deletion.request-otp');
    Route::post('events/{event}/deletion/verify-otp', [EventDeletionController::class, 'verifyOtp'])
        ->middleware('throttle:event-deletion-otp-verify')->name('events.deletion.verify-otp');

    Route::get('events/{event}/attendance', [AttendanceController::class, 'show'])->name('events.attendance');
    Route::post('events/{event}/attendance/{registration}/mark', [AttendanceController::class, 'mark'])->name('events.attendance.mark');

    // Admin-only routes — same trusted-device pipeline, admin gate layered on top.
    Route::middleware('admin')->group(function () {
        Route::get('admin/dashboard', DashboardController::class)->name('admin.dashboard');
    });
});

require __DIR__.'/settings.php';
