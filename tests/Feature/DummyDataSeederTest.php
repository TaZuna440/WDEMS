<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\DummyDataSeeder;

it('creates the presentation dataset and is safe to rerun', function () {
    $seeder = app(DummyDataSeeder::class);
    $seeder->run();

    $initial = [
        'running_events' => Event::query()
            ->where('event_kind', 'running')
            ->where('event_name', 'like', 'Community Run - %')
            ->count(),
        'church_events' => Event::query()
            ->where('event_kind', 'church')
            ->where('event_name', 'like', '% - %')
            ->count(),
        'registrations' => Registration::query()
            ->whereHas('event', fn ($query) => $query->where('event_name', 'like', 'Community Run - %')
                ->orWhere('event_name', 'like', '% - %'))
            ->count(),
    ];

    $seeder->run();

    expect($initial)->toBe([
        'running_events' => 4,
        'church_events' => 9,
        'registrations' => 86,
    ])->and(User::query()->where('role', 'admin')->exists())->toBeTrue()
        ->and(User::query()->where('role', 'staff')->count())->toBeGreaterThanOrEqual(2);
});
