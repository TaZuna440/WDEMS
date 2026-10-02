<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Covers the identity constraints added in Phase 1 of the
 * participant-identity plan:
 *
 *   - participants.email UNIQUE on non-null
 *   - participants.contact_number_normalized UNIQUE on non-null
 *   - registrations (event_id, participant_id) UNIQUE  (pre-existing)
 *
 * Multiple NULLs are permitted in both unique indexes, so phone-only
 * and email-only participants coexist without collision.
 *
 * Helpers prefixed identity_ to avoid collisions with sibling test files.
 */

function identity_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function identity_event(): Event
{
    return Event::create([
        'created_by' => identity_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Identity Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function identity_participant(array $overrides = []): Participant
{
    return Participant::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// email — unique on non-null, multiple nulls allowed
// ---------------------------------------------------------------------------

test('a participant can be created with a null email', function () {
    $participant = identity_participant(['email' => null]);

    expect($participant->fresh()->email)->toBeNull();
});

test('two participants with null emails can coexist', function () {
    identity_participant(['email' => null, 'first_name' => 'Maria']);
    identity_participant(['email' => null, 'first_name' => 'Jose']);

    expect(Participant::whereNull('email')->count())->toBe(2);
});

test('two participants cannot share a non-null email', function () {
    identity_participant(['email' => 'maria@example.com']);

    expect(function () {
        identity_participant([
            'email' => 'maria@example.com',
            'first_name' => 'Different',
            'last_name' => 'Person',
        ]);
    })->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// contact_number_normalized — unique on non-null, multiple nulls allowed
// ---------------------------------------------------------------------------

test('a participant can be created with a null normalized phone', function () {
    $participant = identity_participant(['contact_number_normalized' => null]);

    expect($participant->fresh()->contact_number_normalized)->toBeNull();
});

test('two participants with null normalized phones can coexist', function () {
    identity_participant(['contact_number_normalized' => null, 'first_name' => 'Maria']);
    identity_participant(['contact_number_normalized' => null, 'first_name' => 'Jose']);

    expect(Participant::whereNull('contact_number_normalized')->count())->toBe(2);
});

test('two participants cannot share a non-null normalized phone', function () {
    identity_participant(['contact_number_normalized' => '09171234567']);

    expect(function () {
        identity_participant([
            'contact_number_normalized' => '09171234567',
            'first_name' => 'Different',
            'last_name' => 'Person',
        ]);
    })->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// registrations (event_id, participant_id) — pre-existing constraint
// ---------------------------------------------------------------------------

test('two registrations cannot share the same event and participant', function () {
    $event = identity_event();
    $participant = identity_participant();

    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);

    expect(function () use ($event, $participant) {
        Registration::create([
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'registration_date' => now(),
            'registration_status' => 'confirmed',
            'source' => 'form',
            'registered_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// consent columns — added in this phase, nullable
// ---------------------------------------------------------------------------

test('consent columns default to null on a new registration', function () {
    $event = identity_event();
    $participant = identity_participant();

    $registration = Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);

    expect($registration->fresh()->consent_accepted_at)->toBeNull();
    expect($registration->fresh()->privacy_notice_version)->toBeNull();
});
