<?php

use App\Models\Participant;

it('derives age from birthday when birthday is present', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => null,
        'birthday' => now()->subYears(30)->subDay(),
    ]);

    expect($participant->age)->toBe(30);
});

it('falls back to the stored age column when birthday is null', function () {
    $participant = Participant::create([
        'first_name' => 'Legacy',
        'last_name' => 'Participant',
        'contact_number' => null,
        'age' => 45,
    ]);

    expect($participant->age)->toBe(45);
});

it('returns null when neither birthday nor stored age is present', function () {
    $participant = Participant::create([
        'first_name' => 'Unknown',
        'last_name' => 'Age',
        'contact_number' => null,
    ]);

    expect($participant->age)->toBeNull();
});

it('prefers birthday-derived age over a conflicting stored age', function () {
    $participant = Participant::create([
        'first_name' => 'Both',
        'last_name' => 'Set',
        'contact_number' => null,
        'birthday' => now()->subYears(28)->subDay(),
        'age' => 50,
    ]);

    expect($participant->age)->toBe(28);
});

it('recomputes age when the birthday crosses another year boundary', function () {
    $participant = Participant::create([
        'first_name' => 'Birthday',
        'last_name' => 'Child',
        'contact_number' => null,
        'birthday' => now()->subYears(10),
    ]);

    // Born exactly 10 years ago today. On this exact day, age is 10
    // (the birthday has been reached).
    expect($participant->age)->toBe(10);
});

it('returns the previous year\'s age on the day before the birthday', function () {
    // Born exactly 10 years ago *plus one day* — the birthday is
    // tomorrow. Age must still be 9, not 10. This pins the
    // Carbon::diffInYears semantics that the accessor depends on.
    // If Carbon's behavior ever flips (e.g. a version bump that
    // changes boundary handling), this test catches it before the
    // attendance views start showing wrong ages.
    $participant = Participant::create([
        'first_name' => 'Almost',
        'last_name' => 'Ten',
        'contact_number' => null,
        'birthday' => now()->subYears(10)->addDay(),
    ]);

    expect($participant->age)->toBe(9);
});
