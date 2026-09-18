<?php

use App\Http\Controllers\GoogleIntegrationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // Google integration
    Route::get('settings/google', [GoogleIntegrationController::class, 'show'])
        ->name('settings.google');
    Route::get('auth/google/redirect', [GoogleIntegrationController::class, 'redirect'])
        ->name('google.redirect');
    Route::get('auth/google/callback', [GoogleIntegrationController::class, 'callback'])
        ->name('google.callback');
    Route::delete('settings/google', [GoogleIntegrationController::class, 'destroy'])
        ->name('settings.google.destroy');
});
