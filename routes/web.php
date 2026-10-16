<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\DeviceVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscipleshipBatchController;
use App\Http\Controllers\DiscipleshipController;
use App\Http\Controllers\DiscipleshipEnrollmentController;
use App\Http\Controllers\DiscipleshipSessionController;
use App\Http\Controllers\ChurchCategoryPlaceholderController;
use App\Http\Controllers\DiscipleshipAttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventDeletionController;
use App\Http\Controllers\FellowshipAttendanceController;
use App\Http\Controllers\FellowshipController;
use App\Http\Controllers\FellowshipGroupController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportIndexController;
use App\Http\Controllers\RegistrationDeletionController;
use App\Http\Controllers\RegistrationFormController;
use App\Http\Controllers\RegistrationMonitorController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceAttendanceController;
use App\Http\Controllers\ServiceController;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// ============================================================
// Legacy URLs — 301 redirects to the /events/church prefix.
// See docs/restructure/phase-1-navigation.md §4.
// ============================================================

Route::permanentRedirect('/services', '/events/church/services');
Route::permanentRedirect('/services/{type}', '/events/church/services/{type}')
    ->whereIn('type', ['morning', 'midweek', 'youth', 'other']);

Route::permanentRedirect('/fellowship', '/events/church/fellowship');
Route::permanentRedirect('/fellowship/{type}', '/events/church/fellowship/{type}')
    ->whereIn('type', ['family', 'church', 'pastors', 'leaders', 'womens', 'mens']);

Route::permanentRedirect('/discipleship', '/events/church/discipleship');

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

// Code-free completion destination remains available after registration closes.
Route::get('r/{event:registration_slug}/registered', [PublicRegistrationController::class, 'confirmed'])
    ->middleware([
        App\Http\Middleware\ProtectRegistrationVerification::class,
        'throttle:registration-verification-view',
    ])
    ->name('public-registration.confirmed');

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
//
// `event.advance` auto-advances a RegistrationClosed event to
// Ongoing once its start moment has passed. It runs after auth and
// device.trusted, so an unauthenticated request never flips a
// status. See app/Http/Middleware/AdvanceEventStatus.php.
Route::middleware(['auth', 'verified.or.admin', 'device.trusted', 'event.advance'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('reports', ReportIndexController::class)->name('reports.index');

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

    // Monitor actions - Phase 8. Scoped to a single registration.
    // The event status gate is enforced in the controller.
    Route::get('registrations/monitor/{event}/export', [RegistrationMonitorController::class, 'exportCsv'])
        ->name('registration-monitor.export');
    Route::put('registrations/{registration}/participant', [RegistrationMonitorController::class, 'editParticipant'])
        ->name('registration-monitor.edit-participant');
    Route::post('registrations/{registration}/flag', [RegistrationMonitorController::class, 'toggleFlag'])
        ->name('registration-monitor.toggle-flag');
    Route::post('registrations/{registration}/note', [RegistrationMonitorController::class, 'saveNote'])
        ->name('registration-monitor.save-note');

    // Registration deletion — Phase 9. Same admin/staff split as
    // event deletion: admin hits DELETE, staff walk through
    // request-otp → verify-otp. The status gate (open or closed)
    // lives inside RegistrationDeletionController::ensureManageable().
    Route::delete('registrations/{registration}', [RegistrationDeletionController::class, 'destroy'])
        ->name('registrations.destroy');
    Route::post('registrations/{registration}/deletion/request-otp', [RegistrationDeletionController::class, 'requestOtp'])
        ->middleware('throttle:registration-deletion-otp-request')
        ->name('registrations.deletion.request-otp');
    Route::post('registrations/{registration}/deletion/verify-otp', [RegistrationDeletionController::class, 'verifyOtp'])
        ->middleware('throttle:registration-deletion-otp-verify')
        ->name('registrations.deletion.verify-otp');

    // Attendance directory — lists events eligible for attendance
    // recording. Gate: event_date <= today (see AttendanceController).
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::get('events/create/community-run', [EventController::class, 'createCommunityRun'])->name('events.create.community-run');
    Route::get('events/create/church', [EventController::class, 'createChurch'])->name('events.create.church');
    Route::get('events/create/church/{category}', [EventController::class, 'createChurchCategory'])
        ->whereIn('category', ['youth', 'kids', 'young-adult', 'worship-prayer', 'special-occasions', 'church-administration'])
        ->name('events.create.church.category');
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

    // Finalize event — the terminal transition from Ongoing to
    // Completed. Password-gated via Laravel's RequirePassword
    // middleware, matching the settings/security endpoints. The
    // controller checks the status and calls EventWorkflow::complete().
    //
    // Placed next to the other workflow routes so a reader sees the
    // full lifecycle action set in one place.
    Route::post('events/{event}/finalize', [EventController::class, 'finalize'])
        ->middleware(RequirePassword::class)
        ->name('events.finalize');

    // End-of-event report — Feature C of
    // docs/attendance-encoding-and-reporting.md. Reachable when the
    // event's status is ongoing or completed (the controller 404s
    // otherwise). Also the finalize redirect target — after a
    // successful finalize the user lands on the report, not the
    // event show page.
    Route::get('events/{event}/report', [ReportController::class, 'show'])
        ->name('events.report');

    // CSV export of the participants section. Separate route so the
    // browser downloads the file directly (Content-Disposition:
    // attachment) rather than an Inertia visit. No rate limiter —
    // same reasoning as RegistrationMonitorController::exportCsv().
    Route::get('events/{event}/report/export', [ReportController::class, 'exportCsv'])
        ->name('events.report.export');

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
    Route::post('events/{event}/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('events.attendance.bulk-mark');
    Route::post('events/{event}/attendance/bulk-walk-in', [AttendanceController::class, 'bulkWalkIn'])
        ->middleware('throttle:walk-in-batch')->name('events.attendance.bulk-walk-in');
    Route::post('events/{event}/attendance/{registration}/mark', [AttendanceController::class, 'mark'])->name('events.attendance.mark');

    // ============================================================
    // Church modules nested under /events/church. Names unchanged.
    Route::prefix('events/church')->group(function () {
    // Services (church) — Phase 3 of the church-events plan.
    //
    // Route ordering: literal segments appear before parameterized
    // ones at the same position so `services/occurrences/...` routes
    // resolve before `services/{type}` can match. The {type} route
    // also constrains the segment to the four ServiceType values, so
    // a URL like `services/occurrences` cannot match `show()`.
    // ============================================================

    // Attendance actions on one occurrence (5-6 segments) — most specific.
    Route::delete('services/occurrences/{occurrence}/attendance/members/{attendance}', [ServiceAttendanceController::class, 'destroyMember'])
        ->name('services.attendance.destroy-member');
    Route::delete('services/occurrences/{occurrence}/attendance/first-timers/{attendance}', [ServiceAttendanceController::class, 'destroyFirstTimer'])
        ->name('services.attendance.destroy-first-timer');
    Route::put('services/occurrences/{occurrence}/attendance/members/{attendance}', [ServiceAttendanceController::class, 'markMember'])
        ->name('services.attendance.mark-member');
    Route::put('services/occurrences/{occurrence}/attendance/first-timers/{attendance}', [ServiceAttendanceController::class, 'markFirstTimer'])
        ->name('services.attendance.mark-first-timer');

    // Attendance show/store on one occurrence (4 segments).
    Route::get('services/occurrences/{occurrence}/attendance', [ServiceAttendanceController::class, 'show'])
        ->name('services.attendance.show');
    Route::post('services/occurrences/{occurrence}/attendance', [ServiceAttendanceController::class, 'store'])
        ->name('services.attendance.store');

    // Occurrence deletion (3 segments).
    Route::delete('services/occurrences/{occurrence}', [ServiceController::class, 'destroyOccurrence'])
        ->name('services.occurrences.destroy');

    // Recurring batch creation (4 segments, but the literal
    // 'occurrences' + 'recurring' segments disambiguate from
    // anything above).
    Route::post('services/{type}/occurrences/recurring', [ServiceController::class, 'storeRecurring'])
        ->whereIn('type', ['morning', 'midweek', 'youth', 'other'])
        ->middleware('throttle:service-recurring-batch')
        ->name('services.occurrences.store-recurring');

    // Single manual occurrence creation (3 segments).
    Route::post('services/{type}/occurrences', [ServiceController::class, 'storeOccurrence'])
        ->whereIn('type', ['morning', 'midweek', 'youth', 'other'])
        ->name('services.occurrences.store');

    // Landing and per-type view (1-2 segments) — least specific.
    Route::get('services', [ServiceController::class, 'index'])
        ->name('services.index');
    Route::get('services/{type}', [ServiceController::class, 'show'])
        ->whereIn('type', ['morning', 'midweek', 'youth', 'other'])
        ->name('services.show');


    // ============================================================
    // Fellowship module (M4) -- Phase 4.
    //
    // Route registration order matters: most specific first, least
    // specific last. Laravel matches in declaration order, so
    // `fellowship/events/{event}/groups` must be registered before
    // `fellowship/{type}` can match the "events" segment. The {type}
    // route also constrains the segment to the six FellowshipType
    // values, so a URL like `fellowship/events` cannot match show().
    //
    // {event} binds to FellowshipEvent via controller type-hint.
    // {attendance} binds to FellowshipAttendance.
    // {group} binds to FellowshipGroup.
    // ============================================================

    Route::prefix('fellowship')->name('fellowship.')->group(function () {
        // Group CRUD -- 4 segments, literal 'events' + 'groups'.
        Route::post('events/{event}/groups', [FellowshipGroupController::class, 'store'])
            ->name('groups.store');
        Route::put('events/{event}/groups/{group}', [FellowshipGroupController::class, 'update'])
            ->name('groups.update');
        Route::delete('events/{event}/groups/{group}', [FellowshipGroupController::class, 'destroy'])
            ->name('groups.destroy');

        // Attendance actions -- 4 segments, literal 'events' + 'attendance'.
        Route::get('events/{event}/attendance', [FellowshipAttendanceController::class, 'show'])
            ->name('attendance.show');
        Route::post('events/{event}/attendance', [FellowshipAttendanceController::class, 'store'])
            ->name('attendance.store');
        Route::put('events/{event}/attendance/{attendance}', [FellowshipAttendanceController::class, 'mark'])
            ->name('attendance.mark');
        Route::delete('events/{event}/attendance/{attendance}', [FellowshipAttendanceController::class, 'destroy'])
            ->name('attendance.destroy');

        // Event update/delete -- 3 segments, literal 'events'.
        Route::put('events/{event}', [FellowshipController::class, 'update'])
            ->name('events.update');
        Route::delete('events/{event}', [FellowshipController::class, 'destroy'])
            ->name('events.destroy');

        // Event creation under a type -- 3 segments, {type} constrained.
        Route::post('{type}/events', [FellowshipController::class, 'store'])
            ->whereIn('type', ['family', 'church', 'pastors', 'leaders', 'womens', 'mens'])
            ->name('events.store');

        // Landing and per-type view -- least specific, last.
        Route::get('/', [FellowshipController::class, 'index'])
            ->name('index');
        Route::get('{type}', [FellowshipController::class, 'show'])
            ->whereIn('type', ['family', 'church', 'pastors', 'leaders', 'womens', 'mens'])
            ->name('show');
    });

    // ============================================================
    // Discipleship module (M5) -- Phase 5A.
    //
    // Two routes at 5A. Later sub-phases extend this block:
    // 5B adds /programs/{program}/batches, 5C adds enrollment,
    // 5D adds sessions, 5E adds session attendance.
    //
    // {program:slug} binds by slug, not ID. The slug is the
    // stable public identifier (life-class, school-of-leaders-1).
    // Reuse the same {program:slug} binding in 5B+ routes for
    // consistency.
    // ============================================================

    Route::prefix('discipleship')->name('discipleship.')->group(function () {

        // CSV export of the program's enrollment list (Phase 5G).
        // Mirrors the same q/status/batch filters as the show page.
        Route::get('programs/{program:slug}/enrollments/export', [DiscipleshipController::class, 'exportCsv'])
            ->name('enrollments.export');

        // Attendance (Phase 5E). Bulk upsert for the batch matrix;
        // single destroy for a cell the operator clears.
        Route::post('batches/{batch}/attendances/bulk', [DiscipleshipAttendanceController::class, 'bulkUpsert'])
            ->name('attendances.bulk');
        Route::delete('attendances/{attendance}', [DiscipleshipAttendanceController::class, 'destroy'])
            ->name('attendances.destroy');

        // Batch detail + Session CRUD (Phase 5D). Batches have no
        // slug -- {batch} binds by id. Sessions belong to a batch
        // and cascade-delete with it.
        Route::get('batches/{batch}', [DiscipleshipBatchController::class, 'show'])
            ->name('batches.show');
        Route::post('batches/{batch}/sessions', [DiscipleshipSessionController::class, 'store'])
            ->name('sessions.store');
        Route::put('sessions/{session}', [DiscipleshipSessionController::class, 'update'])
            ->name('sessions.update');
        Route::delete('sessions/{session}', [DiscipleshipSessionController::class, 'destroy'])
            ->name('sessions.destroy');

        // Enrollment CRUD (Phase 5C). The destroy route is
        // semantically a "withdraw" -- it sets status = withdrawn
        // and keeps the row for history. See the controller
        // docblock.
        Route::post('programs/{program:slug}/enrollments', [DiscipleshipEnrollmentController::class, 'store'])
            ->name('enrollments.store');
        Route::put('enrollments/{enrollment}', [DiscipleshipEnrollmentController::class, 'update'])
            ->name('enrollments.update');
        Route::delete('enrollments/{enrollment}', [DiscipleshipEnrollmentController::class, 'destroy'])
            ->name('enrollments.withdraw');

        // Batch CRUD (Phase 5B). More specific than the program
        // routes below, so declared first.
        Route::post('programs/{program:slug}/batches', [DiscipleshipBatchController::class, 'store'])
            ->name('batches.store');
        Route::put('batches/{batch}', [DiscipleshipBatchController::class, 'update'])
            ->name('batches.update');
        Route::delete('batches/{batch}', [DiscipleshipBatchController::class, 'destroy'])
            ->name('batches.destroy');

        // Participant search for the Add Enrollment picker.
        // JSON, not Inertia -- a debounced type-ahead cannot use
        // the partial-reload pattern. Returns up to 20 matches.
        Route::get('programs/{program:slug}/participant-search', [DiscipleshipController::class, 'participantSearch'])
            ->name('participants.search');
        Route::get('/', [DiscipleshipController::class, 'index'])
            ->name('index');
        Route::get('programs/{program:slug}', [DiscipleshipController::class, 'show'])
            ->name('programs.show');
    });
        // Placeholder landing for the six unbuilt church categories.
        // Phase 4 replaces each with a real module page.
        Route::get('{category}', ChurchCategoryPlaceholderController::class)
            ->whereIn('category', [
                'youth',
                'kids',
                'young-adult',
                'worship-prayer',
                'special-occasions',
                'church-administration',
            ])
            ->name('events.church.placeholder');
    }); // close /events/church prefix group
    // Admin-only routes — same trusted-device pipeline, admin gate layered on top.
    Route::middleware('admin')->group(function () {
        Route::get('admin/dashboard', DashboardController::class)->name('admin.dashboard');
    });
});

require __DIR__.'/settings.php';

require __DIR__.'/registration-verification.php';
