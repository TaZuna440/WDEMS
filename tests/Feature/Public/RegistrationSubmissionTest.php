<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Covers the anonymous public submission surface at /r/{slug}.
 *
 * The middleware (EnsureRegistrationIsOpen) decides between two
 * outcomes: render the form, or render the closed page. Both are HTTP
 * 200. An unknown slug 404s in SubstituteBindings before the
 * middleware runs.
 *
 * Helpers prefixed public_ to avoid collisions with sibling test files.
 *
 * The two Inertia pages this file asserts (registrations/public and
 * registrations/closed) are created in Block 5. Those assertions pass
 * `false` as the second argument to `component()` to skip Inertia's
 * page-existence check — the route, middleware, and controller are
 * what this file covers.
 */

function public_staff(): User
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
function public_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => public_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Public Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open',
        'registration_slug' => 'abcdefgh',
        'registration_start' => now(),
        'registration_form_saved_at' => now(),
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function public_field(Event $event, array $overrides = []): RegistrationField
{
    return RegistrationField::create(array_merge([
        'event_id' => $event->id,
        'label' => 'Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large'],
        'is_required' => false,
        'display_order' => 0,
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function public_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'contact_number' => '+63 917 000 0000',
        'age' => 28,
        'address' => 'Manila',
        'responses' => [],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// show — the middleware decides between form and closed page
// ---------------------------------------------------------------------------

test('the public form renders for an open event', function () {
    $event = public_event();

    $response = $this->get("/r/{$event->registration_slug}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('registrations/public', false));
});

test('the closed page renders for an event that is not registration_open', function () {
    $event = public_event(['status' => 'registration_closed']);

    $response = $this->get("/r/{$event->registration_slug}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('registrations/closed', false));
});

test('the closed page renders when registration_end has passed', function () {
    $event = public_event(['registration_end' => now()->subHour()]);

    $response = $this->get("/r/{$event->registration_slug}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('registrations/closed', false));
});

test('an unknown slug 404s', function () {
    $this->get('/r/zzzzzzzz')->assertNotFound();
});

// ---------------------------------------------------------------------------
// store — happy path
// ---------------------------------------------------------------------------

test('a valid submission creates participant, registration and responses', function () {
    $event = public_event();
    $field = public_field($event);

    $response = $this->post("/r/{$event->registration_slug}", public_payload([
        'responses' => [$field->id => 'Medium'],
    ]));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(Participant::where('email', 'maria@example.com')->count())->toBe(1);
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
    expect(RegistrationFieldResponse::count())->toBe(1);

    $registration = Registration::where('event_id', $event->id)->first();
    expect($registration->source)->toBe('form');
    expect($registration->registration_status->value)->toBe('confirmed');
});

// ---------------------------------------------------------------------------
// store — duplicate rule
// ---------------------------------------------------------------------------

test('a duplicate email for the same event is rejected', function () {
    $event = public_event();

    $this->post("/r/{$event->registration_slug}", public_payload());

    $response = $this->post("/r/{$event->registration_slug}", public_payload());

    $response->assertSessionHasErrors('email');
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('the same email can register for a different event', function () {
    $eventA = public_event(['registration_slug' => 'aaaaaaaa']);
    $eventB = public_event(['registration_slug' => 'bbbbbbbb']);

    $this->post('/r/aaaaaaaa', public_payload());
    $this->post('/r/bbbbbbbb', public_payload());

    expect(Registration::count())->toBe(2);
    expect(Participant::where('email', 'maria@example.com')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// store — validation
// ---------------------------------------------------------------------------

test('a missing required common field is rejected', function () {
    $event = public_event();
    $payload = public_payload();
    unset($payload['email']);

    $response = $this->post("/r/{$event->registration_slug}", $payload);

    $response->assertSessionHasErrors('email');
});

test('a required custom field with no value is rejected', function () {
    $event = public_event();
    $field = public_field($event, ['is_required' => true]);

    $response = $this->post("/r/{$event->registration_slug}", public_payload());

    $response->assertSessionHasErrors("responses.{$field->id}");
});

test('a select field rejects an invalid choice', function () {
    $event = public_event();
    $field = public_field($event);

    $response = $this->post("/r/{$event->registration_slug}", public_payload([
        'responses' => [$field->id => 'Huge'],
    ]));

    $response->assertSessionHasErrors("responses.{$field->id}");
});

test('a response key that does not belong to the event is rejected', function () {
    $event = public_event();
    $otherEvent = public_event(['registration_slug' => 'otherslu']);
    $otherField = public_field($otherEvent);

    $response = $this->post("/r/{$event->registration_slug}", public_payload([
        'responses' => [$otherField->id => 'Medium'],
    ]));

    $response->assertSessionHasErrors("responses.{$otherField->id}");
});

// ---------------------------------------------------------------------------
// closed-state guard on POST
// ---------------------------------------------------------------------------

test('a POST to a closed event renders the closed page and does not create a registration', function () {
    $event = public_event(['status' => 'registration_closed']);

    $response = $this->post("/r/{$event->registration_slug}", public_payload());

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('registrations/closed', false));
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Common field requirements — optional toggle changes server validation
// ---------------------------------------------------------------------------

test('a submission without email succeeds when email is optional', function () {
    $event = public_event([
        'registration_common_field_requirements' => ['email' => false],
    ]);

    $payload = public_payload();
    unset($payload['email']);

    $response = $this->post("/r/{$event->registration_slug}", $payload);

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a submission without contact_number succeeds when contact_number is optional', function () {
    $event = public_event([
        'registration_common_field_requirements' => ['contact_number' => false],
    ]);

    $payload = public_payload();
    unset($payload['contact_number']);

    $response = $this->post("/r/{$event->registration_slug}", $payload);

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a submission without address succeeds when address is optional', function () {
    $event = public_event([
        'registration_common_field_requirements' => ['address' => false],
    ]);

    $payload = public_payload();
    unset($payload['address']);

    $response = $this->post("/r/{$event->registration_slug}", $payload);

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('a submission without email is rejected when email is required', function () {
    $event = public_event([
        'registration_common_field_requirements' => ['email' => true],
    ]);

    $payload = public_payload();
    unset($payload['email']);

    $response = $this->post("/r/{$event->registration_slug}", $payload);

    $response->assertSessionHasErrors('email');
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('an optional email still validates format when provided', function () {
    $event = public_event([
        'registration_common_field_requirements' => ['email' => false],
    ]);

    $response = $this->post(
        "/r/{$event->registration_slug}",
        public_payload(['email' => 'not-an-email']),
    );

    $response->assertSessionHasErrors('email');
});

test('two submissions without email for the same event both succeed', function () {
    $event = public_event([
        'registration_common_field_requirements' => ['email' => false],
    ]);

    $payload = public_payload();
    unset($payload['email']);

    $this->post("/r/{$event->registration_slug}", $payload);

    // Different name so participant identity does not collide — even
    // though the duplicate check is skipped, we want to prove the
    // second submission is a distinct participant + registration.
    $second = $payload;
    $second['first_name'] = 'Jose';
    $second['last_name'] = 'Rizal';

    $response = $this->post("/r/{$event->registration_slug}", $second);

    $response->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(2);
    expect(Participant::whereNull('email')->count())->toBe(2);
});

test('the duplicate email rule still applies when email is required', function () {
    $event = public_event();

    $this->post("/r/{$event->registration_slug}", public_payload());

    $response = $this->post("/r/{$event->registration_slug}", public_payload());

    $response->assertSessionHasErrors('email');
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});
