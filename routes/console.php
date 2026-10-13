<?php

use App\Models\RegistrationVerificationChallenge;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pending registration payloads are no longer needed after the 24-hour window.
Schedule::command('model:prune', [
    '--model' => [RegistrationVerificationChallenge::class],
])->hourly();
