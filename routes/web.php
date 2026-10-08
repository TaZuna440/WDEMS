<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\DeviceVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventDeletionController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegistrationFormController;
use App\Http\Controllers\RegistrationMonitorController;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public registration — anonymous submission surface.
// No auth, no device trust, no email verification. The middleware
// renders a "closed" page (200) when the event is not accepting
// submissions. The POST is rate-limited per IP.
//
// The {event:registration_slug} binding key means Laravel resolves
// the Event by slug, not by ID. An unknown slug 404s before the
// middleware runs (SubstituteBindings fires first).
Route::middleware([EnsureRegistrationIsOpen::class])->group(function () {
    Route::get('r/{event:registration_slug}', [PublicRegistrationController::class, 'show'])
        ->name('public-registration.show');
    Route::post('r/{event:registration_slug}', [PublicRegistrationController::class, 'store'])
        ->middleware('throttle:public-registration-submit')
        ->name('public-registration.store');
});

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
    Route::get('registrations', [RegistrationController::class, 'index'])
        ->name('registrations.index');

    // Registration monitor — landing (all open events) and per-event
    // feed. Reachable while status is registration_open or
    // registration_closed; the second case is the read-only snapshot.
    Route::get('registrations/monitor', [RegistrationMonitorController::class, 'index'])
        ->name('registration-monitor.index');
    Route::get('registrations/monitor/{event}', [RegistrationMonitorController::class, 'show'])
        ->name('registration-monitor.show');

    // Attendance directory — lists events eligible for attendance
    // recording. Gate: event_date <= today (see AttendanceController).
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');

    // Registration form builder — Phase 2 of the registration plan.
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

    // Attendance page + walk-in + mark.
    //
    // Route order matters here for clarity: `walk-in` is a literal
    // segment in the position where `{registration}` would otherwise
    // sit. The paths have different segment counts (`walk-in` is 4
    // segments, `{registration}/mark` is 5), so they cannot collide.
    // Listing walk-in first keeps the pattern obvious to a reader.
    Route::get('events/{event}/attendance', [AttendanceController::class, 'show'])->name('events.attendance');
    Route::post('events/{event}/attendance/walk-in', [AttendanceController::class, 'walkIn'])->name('events.attendance.walk-in');
    Route::post('events/{event}/attendance/{registration}/mark', [AttendanceController::class, 'mark'])->name('events.attendance.mark');

    // Admin-only routes — same trusted-device pipeline, admin gate layered on top.
    Route::middleware('admin')->group(function () {
        Route::get('admin/dashboard', DashboardController::class)->name('admin.dashboard');
    });
});

require __DIR__.'/settings.php';
