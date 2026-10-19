<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
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
        'contact_number' => '+63 917 000 0000', 'age' => 28, 'address' => 'Manila', 'emergency_contact_name' => 'Jose Santos', 'emergency_contact_relationship' => 'Father', 'emergency_contact_number' => '+63 917 765 4321', 'responses' => [],
    ], $overrides);
}

test('the public form renders for an open event', function () {
    $event = public_event();
    $this->get("/r/{$event->registration_slug}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('registrations/public'));
});

test('the public form exposes the existing event details before personal fields', function () {
    $event = public_event([
        'event_type' => 'community_run',
        'start_time' => '06:30:00',
        'end_time' => '09:00:00',
        'distance_value' => 5,
        'distance_unit' => 'km',
        'description' => 'A community course through the park.',
        'venue' => 'Rizal Park',
        'venue_address' => 'Manila',
        'venue_map_url' => 'https://maps.example.test/run',
        'partners' => [['name' => 'Run Club', 'type' => 'host']],
        'wheelchair_accessible' => true,
    ]);

    $this->get("/r/{$event->registration_slug}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.event_name', 'Public Test Run')
            ->where('event.event_type', 'Community Run')
            ->where('event.event_date', $event->fresh()->event_date?->toDateString())
            ->where('event.start_time', '06:30')
            ->where('event.end_time', '09:00')
            ->where('event.distance', '5 KM')
            ->where('event.venue', 'Rizal Park')
            ->where('event.venue_address', 'Manila')
            ->where('event.description', 'A community course through the park.')
            ->where('event.venue_map_url', 'https://maps.example.test/run')
            ->where('event.partners.0.name', 'Run Club')
            ->where('event.accessibility', ['Wheelchair accessible']));

    expect(Schema::hasColumn('events', 'event_type'))
        ->toBeTrue()
        ->and(Schema::hasColumn('events', 'end_time'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'distance_value'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'partners'))->toBeTrue();
});

test('the public event summary omits absent values cleanly', function () {
    $event = public_event([
        'start_time' => null,
        'end_time' => null,
        'distance_value' => null,
        'venue' => null,
        'venue_address' => null,
        'venue_map_url' => null,
        'description' => null,
        'partners' => null,
    ]);

    $this->get("/r/{$event->registration_slug}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.start_time', null)
            ->where('event.end_time', null)
            ->where('event.distance', null)
            ->where('event.venue', null)
            ->where('event.venue_map_url', null)
            ->where('event.description', null)
            ->where('event.partners', [])
            ->where('event.accessibility', []));
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
    $this->post('/r/'.$event->registration_slug, public_payload())
        ->assertSessionHasErrors('identity');
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
})->with(['email', 'address']);

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
