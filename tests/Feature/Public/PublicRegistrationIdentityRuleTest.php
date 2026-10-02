<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

/**
 * Covers the at-least-one identity rule on the public submission
 * route. A submission must provide an email or a phone that
 * normalizes to a PH mobile. A phone that fails to normalize counts
 * as no phone.
 *
 * The rule is form-level: the error key is `identity`, not `email`
 * or `contact_number`.
 *
 * Helpers prefixed identity_ to match the pattern in
 * ParticipantIdentityConstraintsTest.php — but namespaced per
 * file via the `_pub` suffix to avoid Pest collision.
 */

function identity_pub_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function identity_pub_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => identity_pub_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Identity Rule Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open',
        'registration_slug' => 'identity',
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => false,
            'contact_number' => false,
        ],
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function identity_pub_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => '',
        'contact_number' => '',
        'age' => 28,
        'address' => 'Manila',
        'responses' => [],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Accepted combinations
// ---------------------------------------------------------------------------

test('an email-only submission is accepted', function () {
    $event = identity_pub_event();

    $response = $this->post("/r/{$event->registration_slug}", identity_pub_payload([
        'email' => 'maria@example.com',
        'contact_number' => '',
    ]));

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a phone-only submission is accepted', function () {
    $event = identity_pub_event();

    $response = $this->post("/r/{$event->registration_slug}", identity_pub_payload([
        'email' => '',
        'contact_number' => '09171234567',
    ]));

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a submission with both email and phone is accepted', function () {
    $event = identity_pub_event();

    $response = $this->post("/r/{$event->registration_slug}", identity_pub_payload([
        'email' => 'maria@example.com',
        'contact_number' => '09171234567',
    ]));

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a +63-prefixed phone counts as a valid identity key', function () {
    $event = identity_pub_event();

    $response = $this->post("/r/{$event->registration_slug}", identity_pub_payload([
        'email' => '',
        'contact_number' => '+639171234567',
    ]));

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Rejected combinations
// ---------------------------------------------------------------------------

test('a submission with neither email nor phone is rejected', function () {
    $event = identity_pub_event();

    $response = $this->post("/r/{$event->registration_slug}", identity_pub_payload());

    $response->assertSessionHasErrors('identity');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
    expect(Participant::count())->toBe(0);
});

test('a submission with blank email and unparseable phone is rejected', function () {
    $event = identity_pub_event();

    $response = $this->post("/r/{$event->registration_slug}", identity_pub_payload([
        'email' => '',
        'contact_number' => 'not-a-phone',
    ]));

    $response->assertSessionHasErrors('identity');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('a submission with a whitespace email and no phone is rejected', function () {
    $event = identity_pub_event();

    $response = $this->post("/r/{$event->registration_slug}", identity_pub_payload([
        'email' => '   ',
        'contact_number' => '',
    ]));

    $response->assertSessionHasErrors('identity');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});
