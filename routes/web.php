<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventDeletionController;
use App\Http\Controllers\EventOptionController;
use App\Http\Controllers\RegistrationSetupController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');

    Route::get('events/{event}/options', [EventOptionController::class, 'index'])->name('events.options.index');
    Route::post('events/{event}/options', [EventOptionController::class, 'store'])->name('events.options.store');
    Route::put('events/{event}/options/{option}', [EventOptionController::class, 'update'])->name('events.options.update');
    Route::delete('events/{event}/options/{option}', [EventOptionController::class, 'destroy'])->name('events.options.destroy');

    Route::post('events/{event}/configure', [EventController::class, 'configure'])->name('events.configure');
    Route::post('events/{event}/open-registration', [EventController::class, 'openRegistration'])->name('events.open-registration');
    Route::post('events/{event}/close-registration', [EventController::class, 'closeRegistration'])->name('events.close-registration');

    Route::delete('events/{event}', [EventDeletionController::class, 'destroy'])->name('events.destroy');
    Route::post('events/{event}/deletion/request-otp', [EventDeletionController::class, 'requestOtp'])
        ->middleware('throttle:event-deletion-otp-request')->name('events.deletion.request-otp');
    Route::post('events/{event}/deletion/verify-otp', [EventDeletionController::class, 'verifyOtp'])
        ->middleware('throttle:event-deletion-otp-verify')->name('events.deletion.verify-otp');

    Route::get('events/{event}/registration/setup', [RegistrationSetupController::class, 'show'])->name('events.registration.setup');
    Route::post('events/{event}/registration/setup', [RegistrationSetupController::class, 'store'])->name('events.registration.setup.store');
    Route::post('events/{event}/registration/setup/questions/sync', [RegistrationSetupController::class, 'syncQuestions'])->name('events.registration.setup.questions.sync');
    Route::post('events/{event}/registration/setup/verify-sheet', [RegistrationSetupController::class, 'verifySheet'])->name('events.registration.setup.verify-sheet');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('admin/dashboard', DashboardController::class)->name('admin.dashboard');
});

require __DIR__.'/settings.php';
