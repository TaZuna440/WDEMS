<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Covers Phase C of docs/attendance-redesign.md: the bulk walk-in
 * endpoint behind Batch mode.
 *
 * The endpoint accepts a `rows` array and persists each row
 * independently (D6). Shape validation runs in the request; business
 * rules (identity, duplicates) run in the controller per row and are
 * reported as `rows.{i}.identity` errors alongside successful writes.
 *
 * Helpers prefixed bwt_ to avoid Pest function collisions.
 */

function bwt_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function bwt_admin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function bwt_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => bwt_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'BWT Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => false,
        ],
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function bwt_field(Event $event, array $overrides = []): RegistrationField
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
function bwt_row(array $overrides = []): array
{
    return array_merge([
        'client_uuid' => Str::uuid()->toString(),
        'first_name' => 'Walk',
        'last_name' => 'In',
        'age' => 30,
        'email' => 'walkin'.Str::random(8).'@example.com',
        'contact_number' => '',
        'address' => '',
        'responses' => [],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

test('bulk walk-in registers a single row with attendance', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [bwt_row()],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
    expect(Participant::count())->toBe(1);

    $registration = Registration::where('event_id', $event->id)->first();
    expect($registration->source)->toBe('paper');
    expect($registration->attendance)->not->toBeNull();
});

test('bulk walk-in persists multiple rows in one request', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwt_row(['first_name' => 'Maria']),
                bwt_row(['first_name' => 'Juan']),
                bwt_row(['first_name' => 'Pedro']),
            ],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(3);
    expect(Participant::count())->toBe(3);
});

test('bulk walk-in stamps source as paper', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [bwt_row()],
        ])
        ->assertRedirect();

    $registration = Registration::where('event_id', $event->id)->first();
    expect($registration->source)->toBe('paper');
});

test('bulk walk-in persists custom field responses per row', function () {
    $staff = bwt_staff();
    $event = bwt_event();
    $field = bwt_field($event);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwt_row(['responses' => [$field->id => 'Small']]),
                bwt_row(['responses' => [$field->id => 'Large']]),
            ],
        ])
        ->assertRedirect();

    expect(RegistrationFieldResponse::count())->toBe(2);

    $values = RegistrationFieldResponse::pluck('value')->sort()->values()->all();
    expect($values)->toBe(['Large', 'Small']);
});

// ---------------------------------------------------------------------------
// Idempotency
// ---------------------------------------------------------------------------

test('bulk walk-in with the same client_uuid twice is idempotent', function () {
    $staff = bwt_staff();
    $event = bwt_event();
    $row = bwt_row();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [$row],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);

    // Same row, same UUID. Should be a no-op.
    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [$row],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Per-row business rules (D6)
// ---------------------------------------------------------------------------

test('bulk walk-in rejects a row with no identity but keeps the others', function () {
    $staff = bwt_staff();
    $event = bwt_event(['registration_common_field_requirements' => ['email' => false, 'contact_number' => false, 'address' => false]]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwt_row(['first_name' => 'Good']),
                bwt_row([
                    'first_name' => 'Bad',
                    'email' => '',
                    'contact_number' => '',
                ]),
                bwt_row(['first_name' => 'AlsoGood']),
            ],
        ])
        ->assertSessionHasErrors('rows.1.identity');

    expect(Registration::where('event_id', $event->id)->count())->toBe(2);
});

test('bulk walk-in rejects a row already registered but keeps the others', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    // Pre-existing participant + registration on the same event.
    $existing = Participant::create([
        'first_name' => 'Existing',
        'last_name' => 'Person',
        'age' => 28,
        'email' => 'existing@example.com',
    ]);

    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $existing->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwt_row([
                    'first_name' => 'Duplicate',
                    'email' => 'existing@example.com',
                ]),
                bwt_row(['first_name' => 'Fresh']),
            ],
        ])
        ->assertSessionHasErrors('rows.0.identity');

    // Only the fresh row persists.
    expect(Registration::where('event_id', $event->id)->count())->toBe(2);
});

test('bulk walk-in rejects duplicate identity within the same batch', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwt_row(['email' => 'same@example.com']),
                bwt_row(['email' => 'same@example.com']),
            ],
        ])
        ->assertSessionHasErrors('rows.1.identity');

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Shape validation (422)
// ---------------------------------------------------------------------------

test('bulk walk-in rejects an empty rows array', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [],
        ])
        ->assertSessionHasErrors('rows');
});

test('bulk walk-in rejects more than 100 rows', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $rows = [];
    for ($i = 0; $i < 101; $i++) {
        $rows[] = bwt_row();
    }

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => $rows,
        ])
        ->assertSessionHasErrors('rows');
});

test('bulk walk-in rejects a row with a missing client_uuid', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $row = bwt_row();
    unset($row['client_uuid']);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [$row],
        ])
        ->assertSessionHasErrors('rows.0.client_uuid');
});

test('bulk walk-in rejects two rows with the same client_uuid', function () {
    $staff = bwt_staff();
    $event = bwt_event();

    $uuid = Str::uuid()->toString();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwt_row(['client_uuid' => $uuid]),
                bwt_row(['client_uuid' => $uuid]),
            ],
        ])
        ->assertSessionHasErrors('rows.1.client_uuid');
});

test('bulk walk-in rejects a custom field response from another event', function () {
    $staff = bwt_staff();
    $event = bwt_event();
    $otherEvent = bwt_event(['event_name' => 'Other BWT Run']);
    $otherField = bwt_field($otherEvent);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwt_row(['responses' => [$otherField->id => 'Small']]),
            ],
        ])
        ->assertSessionHasErrors("rows.0.responses.{$otherField->id}");
});

// ---------------------------------------------------------------------------
// Gate
// ---------------------------------------------------------------------------

test('bulk walk-in is forbidden when the window is closed', function () {
    $staff = bwt_staff();
    $event = bwt_event(['event_date' => today()->addDays(3)->toDateString()]);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [bwt_row()],
        ])
        ->assertForbidden();

    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('bulk walk-in succeeds on a completed event with force_open for admin', function () {
    $admin = bwt_admin();
    $event = bwt_event([
        'status' => 'completed',
        'event_date' => today()->subDays(3)->toDateString(),
    ]);

    $this->actingAs($admin)
        ->post("/events/{$event->id}/attendance/bulk-walk-in?force_open=1", [
            'rows' => [bwt_row()],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Rate limit
// ---------------------------------------------------------------------------

test('bulk walk-in is rate-limited at 10 requests per minute per user and event', function () {
    // Clear the limiter first so we start from a known state.
    RateLimiter::clear('walk-in-batch');

    $staff = bwt_staff();
    $event = bwt_event();

    // Fire 10 requests. Each is a valid single-row batch.
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($staff)
            ->post("/events/{$event->id}/attendance/bulk-walk-in", [
                'rows' => [bwt_row()],
            ])
            ->assertRedirect();
    }

    // The 11th request hits the limiter.
    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [bwt_row()],
        ])
        ->assertStatus(429);
});
