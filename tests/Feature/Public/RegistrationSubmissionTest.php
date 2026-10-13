<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../Support/PublicVerification.php';

function public_staff(): User
{
    return User::factory()->create(['role' => 'staff', 'email_verified_at' => now(), 'email_two_factor_enabled' => false]);
}

function public_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => public_staff()->id, 'event_type' => 'community_run',
        'event_name' => 'Public Test Run', 'event_date' => now()->addWeek()->toDateString(),
        'status' => 'registration_open', 'registration_slug' => 'abcdefgh',
        'registration_start' => now(), 'registration_form_saved_at' => now(),
    ], $overrides));
}

function public_field(Event $event, array $overrides = []): RegistrationField
{
    return RegistrationField::create(array_merge([
        'event_id' => $event->id, 'label' => 'Shirt Size', 'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large'], 'is_required' => false, 'display_order' => 0,
    ], $overrides));
}

function public_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'contact_number' => '+63 917 000 0000', 'age' => 28, 'address' => 'Manila', 'responses' => [],
    ], $overrides);
}

test('the public form renders for an open event', function () {
    $event = public_event();
    $this->get("/r/{$event->registration_slug}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('registrations/public'));
});

test('the closed page renders for an event that is not registration_open', function () {
    $event = public_event(['status' => 'registration_closed']);
    $this->get("/r/{$event->registration_slug}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('registrations/closed'));
});

test('the closed page renders when registration_end has passed', function () {
    $event = public_event(['registration_end' => now()->subHour()]);
    $this->get("/r/{$event->registration_slug}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('registrations/closed'));
});

test('an unknown slug 404s', function () {
    $this->get('/r/zzzzzzzz')->assertNotFound();
});

test('a verified submission creates participant registration and responses', function () {
    $event = public_event();
    $field = public_field($event);
    completePublicRegistration($this, $event, public_payload(['responses' => [$field->id => 'Medium']]))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(Participant::where('email', 'maria@example.com')->count())->toBe(1);
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
    expect(RegistrationFieldResponse::count())->toBe(1);
    expect(Registration::sole()->source)->toBe('form');
    expect(Registration::sole()->registration_status->value)->toBe('confirmed');
});

test('a duplicate verified identity for the same event is safely rejected', function () {
    $event = public_event();
    completePublicRegistration($this, $event, public_payload())->assertSessionHasNoErrors();
    completePublicRegistration($this, $event, public_payload())->assertSessionHasErrors('verification');
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

test('the same verified email can register for a different event', function () {
    $eventA = public_event(['registration_slug' => 'aaaaaaaa']);
    $eventB = public_event(['registration_slug' => 'bbbbbbbb']);
    completePublicRegistration($this, $eventA, public_payload())->assertSessionHasNoErrors();
    completePublicRegistration($this, $eventB, public_payload())->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(2);
    expect(Participant::where('email', 'maria@example.com')->count())->toBe(1);
});

test('a missing required common field is rejected', function () {
    $event = public_event();
    $payload = public_payload();
    unset($payload['email']);
    $this->post("/r/{$event->registration_slug}", $payload)->assertSessionHasErrors('email');
});

test('a required custom field with no value is rejected', function () {
    $event = public_event();
    $field = public_field($event, ['is_required' => true]);
    $this->post("/r/{$event->registration_slug}", public_payload())->assertSessionHasErrors("responses.{$field->id}");
});

test('a select field rejects an invalid choice', function () {
    $event = public_event();
    $field = public_field($event);
    $this->post("/r/{$event->registration_slug}", public_payload(['responses' => [$field->id => 'Huge']]))
        ->assertSessionHasErrors("responses.{$field->id}");
});

test('a response key that does not belong to the event is rejected', function () {
    $event = public_event();
    $otherField = public_field(public_event(['registration_slug' => 'otherslu']));
    $this->post("/r/{$event->registration_slug}", public_payload(['responses' => [$otherField->id => 'Medium']]))
        ->assertSessionHasErrors("responses.{$otherField->id}");
});

test('a POST to a closed event renders the closed page without a registration', function () {
    $event = public_event(['status' => 'registration_closed']);
    $this->post("/r/{$event->registration_slug}", public_payload())->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('registrations/closed'));
    expect(Registration::count())->toBe(0);
});

test('an omitted optional field allows registration after required contacts are verified', function (string $field) {
    $event = public_event(['registration_common_field_requirements' => [$field => false]]);
    $payload = public_payload();
    unset($payload[$field]);
    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
})->with(['email', 'contact_number', 'address']);

test('a submission without email is rejected when email is required', function () {
    $event = public_event(['registration_common_field_requirements' => ['email' => true]]);
    $payload = public_payload();
    unset($payload['email']);
    $this->post("/r/{$event->registration_slug}", $payload)->assertSessionHasErrors('email');
    expect(Registration::count())->toBe(0);
});

test('an optional email still validates format when provided', function () {
    $event = public_event(['registration_common_field_requirements' => ['email' => false]]);
    $this->post("/r/{$event->registration_slug}", public_payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
});

test('two verified phone submissions without email both succeed', function () {
    $event = public_event(['registration_common_field_requirements' => ['email' => false]]);
    $payload = public_payload();
    unset($payload['email']);
    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();
    $payload['first_name'] = 'Jose';
    $payload['last_name'] = 'Rizal';
    $payload['contact_number'] = '09170000001';
    completePublicRegistration($this, $event, $payload)->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(2);
    expect(Participant::whereNull('email')->count())->toBe(2);
});
