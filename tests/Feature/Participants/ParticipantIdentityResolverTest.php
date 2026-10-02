<?php

use App\Exceptions\IdentityRequiredException;
use App\Models\Participant;

/**
 * Covers the Phase 3 additions to the Participant model:
 *
 *   - setContactNumberAttribute mutator
 *   - normalizeContactNumber (via the mutator)
 *   - resolveFrom
 *
 * Path: tests/Feature rather than tests/Unit because resolveFrom
 * hits the DB via firstOrCreate, and only the Feature suite gets
 * RefreshDatabase (per tests/Pest.php).
 */

// ---------------------------------------------------------------------------
// Mutator — normalization
// ---------------------------------------------------------------------------

test('the mutator normalizes 09-prefixed numbers', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '09171234567',
    ]);

    expect($participant->fresh()->contact_number_normalized)->toBe('09171234567');
});

test('the mutator normalizes +63-prefixed numbers', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '+639171234567',
    ]);

    expect($participant->fresh()->contact_number_normalized)->toBe('09171234567');
});

test('the mutator normalizes 63-prefixed numbers without the plus', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '639171234567',
    ]);

    expect($participant->fresh()->contact_number_normalized)->toBe('09171234567');
});

test('the mutator normalizes bare 9-prefixed numbers', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '9171234567',
    ]);

    expect($participant->fresh()->contact_number_normalized)->toBe('09171234567');
});

test('the mutator normalizes numbers with separators', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '0917 123 4567',
    ]);

    expect($participant->fresh()->contact_number_normalized)->toBe('09171234567');
});

test('the mutator returns null for unparseable input', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => 'not-a-phone',
    ]);

    expect($participant->fresh()->contact_number_normalized)->toBeNull();
});

test('the mutator preserves the raw contact_number', function () {
    $participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '+639171234567',
    ]);

    expect($participant->fresh()->contact_number)->toBe('+639171234567');
});

// ---------------------------------------------------------------------------
// resolveFrom — email path
// ---------------------------------------------------------------------------

test('resolveFrom matches on email when email is present', function () {
    $existing = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'age' => 28,
    ]);

    $resolved = Participant::resolveFrom('maria@example.com', null, [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
    ]);

    expect($resolved->id)->toBe($existing->id);
});

test('resolveFrom creates a new participant when email does not match', function () {
    Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'age' => 28,
    ]);

    $resolved = Participant::resolveFrom('jose@example.com', null, [
        'first_name' => 'Jose',
        'last_name' => 'Rizal',
        'age' => 35,
    ]);

    expect($resolved->id)->not->toBeNull();
    expect($resolved->email)->toBe('jose@example.com');
    expect(Participant::count())->toBe(2);
});

test('resolveFrom prefers email when both email and phone are present', function () {
    $byEmail = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'age' => 28,
    ]);

    $resolved = Participant::resolveFrom('maria@example.com', '+639171234567', [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
    ]);

    expect($resolved->id)->toBe($byEmail->id);
});

// ---------------------------------------------------------------------------
// resolveFrom — phone path
// ---------------------------------------------------------------------------

test('resolveFrom matches on normalized phone when email is absent', function () {
    $existing = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '+639171234567',
        'age' => 28,
    ]);

    $resolved = Participant::resolveFrom(null, '09171234567', [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
    ]);

    expect($resolved->id)->toBe($existing->id);
});

test('resolveFrom matches on phone across different raw formats', function () {
    $existing = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'contact_number' => '09171234567',
        'age' => 28,
    ]);

    // Same person, phone typed with the +63 prefix this time.
    $resolved = Participant::resolveFrom(null, '+639171234567', [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'age' => 28,
    ]);

    expect($resolved->id)->toBe($existing->id);
});

test('resolveFrom ignores an unparseable phone and throws', function () {
    expect(function () {
        Participant::resolveFrom(null, 'not-a-phone', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'age' => 28,
        ]);
    })->toThrow(IdentityRequiredException::class);
});

// ---------------------------------------------------------------------------
// resolveFrom — no identity at all
// ---------------------------------------------------------------------------

test('resolveFrom throws when both email and phone are missing', function () {
    expect(function () {
        Participant::resolveFrom(null, null, [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'age' => 28,
        ]);
    })->toThrow(IdentityRequiredException::class);
});

test('resolveFrom throws when email is blank and phone is empty', function () {
    expect(function () {
        Participant::resolveFrom('', '', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'age' => 28,
        ]);
    })->toThrow(IdentityRequiredException::class);
});

// ---------------------------------------------------------------------------
// resolveFrom — first-write-wins
// ---------------------------------------------------------------------------

test('resolveFrom discards attributes on existing match', function () {
    Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'age' => 28,
    ]);

    // Second call uses the same email but a different name and age.
    Participant::resolveFrom('maria@example.com', null, [
        'first_name' => 'Maria D.',
        'last_name' => 'Santos',
        'age' => 45,
    ]);

    $stored = Participant::where('email', 'maria@example.com')->first();

    expect($stored->first_name)->toBe('Maria');
    expect($stored->age)->toBe(28);
    expect(Participant::count())->toBe(1);
});
