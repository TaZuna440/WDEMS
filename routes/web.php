<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventOptionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Events — order matters: create before {event}
    Route::get('events', [EventController::class, 'index'])
        ->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])
        ->name('events.create');
    Route::post('events', [EventController::class, 'store'])
        ->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])
        ->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])
        ->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])
        ->name('events.update');

    // Event options
    Route::get('events/{event}/options', [EventOptionController::class, 'index'])
        ->name('events.options.index');
    Route::post('events/{event}/options', [EventOptionController::class, 'store'])
        ->name('events.options.store');
    Route::put('events/{event}/options/{option}', [EventOptionController::class, 'update'])
        ->name('events.options.update');
    Route::delete('events/{event}/options/{option}', [EventOptionController::class, 'destroy'])
        ->name('events.options.destroy');

    // Workflow transitions
    Route::post('events/{event}/configure', [EventController::class, 'configure'])
        ->name('events.configure');
    Route::post('events/{event}/open-registration', [EventController::class, 'openRegistration'])
        ->name('events.open-registration');
    Route::post('events/{event}/close-registration', [EventController::class, 'closeRegistration'])
        ->name('events.close-registration');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::inertia('admin/dashboard', 'admin/dashboard')
        ->name('admin.dashboard');
});

require __DIR__.'/settings.php';