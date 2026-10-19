<?php

use App\Enums\ChurchEventStatus;
use App\Models\Event;
use App\Models\User;

/**
 * Pins the church-event edit transition behavior.
 *
 * Three layers, each with its own test group:
 *
 *   1. EventRequest::prepareForValidation — church_status defaults
 *      to 'upcoming' on create. On update, prepareForValidation does
 *      NOT default it; absence reaches validation as absence.
 *
 *   2. EventValidationRules::eventRules() — church_status is required
 *      for church events (event_kind=church), regardless of route. So
 *      an update that omits church_status produces a validation error,
 *      not a silent default and not a silent preserve. The frontend
 *      must include church_status in every PUT payload.
 *
 *   3. EventController::update — church_status transitions are
 *      validated against ChurchEventStatus::allowedTransitions()
 *      AFTER canEdit passes. Legal transitions are accepted; illegal
 *      jumps from editable states (upcoming → completed) are
 *      rejected with 422.
 *
 *   4. Event::canEdit — completed and cancelled church events are
 *      locked at the model layer, so their PUTs return 403 before
 *      the transition check even runs.
 *
 * Layers 3 and 4 are complementary defense. A completed church
 * event is rejected by layer 4 (403); an attempted illegal jump
 * from a still-editable state (upcoming → completed) is rejected
 * by layer 3 (422). Both are deliberate.
 *
 * Note on unreachable branches: because eventRules() requires
 * church_status for church events, two defenses in EventController
 * and assertChurchStatusTransitionIsLegal are unreachable today:
 *
 *   - the `if ($requested === null || $requested === '')` guard
 *     at the top of assertChurchStatusTransitionIsLegal
 *   - the `?? $event->church_status?->value` fallback in
 *     EventController::update's payload
 *
 * Both remain as defense-in-depth for a possible future where the
 * rule is loosened. Their presence does not change current behavior.
 *
 * Payload shape mirrors ChurchEventTest: church events do not
 * require distance, accessibility flags, partners, or venue
 * coordinates.
 *
 * Helpers prefixed edge_ to avoid Pest collisions.
 */

function edge_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function edge_church_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => edge_staff()->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Special Occasions',
        'church_status' => 'upcoming',
        'event_name' => 'Edge Test Church '.uniqid(),
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'venue' => 'Main Hall',
        'venue_address' => '123 Church Street',
        'status' => 'draft',
    ], $overrides));
}

/**
 * Minimal PUT payload for a church event. Mirrors the POST shape
 * from ChurchEventTest but omits church_status — individual tests
 * supply it explicitly when testing transitions, and omit it
 * deliberately to test the required-error path.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function edge_church_payload(array $extra = []): array
{
    return array_merge([
        'event_type' => 'church',
        'church_category' => 'Special Occasions',
        'event_name' => 'Updated Church Event',
        'event_date' => now()->addWeeks(2)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'venue' => 'Main Hall',
        'venue_address' => '123 Church Street',
    ], $extra);
}

// ---------------------------------------------------------------------------
// prepareForValidation — church_status defaulting on create
// ---------------------------------------------------------------------------

test('create: absent church_status defaults to upcoming', function () {
    $staff = edge_staff();

    $this->actingAs($staff)
        ->post(route('events.store'), edge_church_payload())
        ->assertRedirect(route('events.index'));

    $created = Event::latest('id')->first();
    expect($created->church_status)->toBe(ChurchEventStatus::Upcoming);
});

test('create: explicit church_status is preserved, not overridden', function () {
    $staff = edge_staff();

    $this->actingAs($staff)
        ->post(route('events.store'), edge_church_payload([
            'church_status' => 'ongoing',
        ]))
        ->assertRedirect(route('events.index'));

    $created = Event::latest('id')->first();
    expect($created->church_status)->toBe(ChurchEventStatus::Ongoing);
});

// ---------------------------------------------------------------------------
// eventRules() requires church_status on update
// ---------------------------------------------------------------------------

test('update: echoing the current church_status is a no-op', function () {
    $event = edge_church_event(['church_status' => 'ongoing']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'ongoing',
        ]))
        ->assertRedirect(route('events.show', $event));

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Ongoing);
});

test('update: absent church_status is rejected as required', function () {
    // eventRules() marks church_status as required for church events,
    // regardless of route. Omitting it on PUT produces a validation
    // error, not a silent default and not a silent preserve. A.3a
    // removed the create-only default from prepareForValidation, so
    // absence now reaches validation as absence.
    $event = edge_church_event(['church_status' => 'ongoing']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload())
        ->assertSessionHasErrors('church_status');

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Ongoing);
});

test('update: explicit church_status is applied', function () {
    $event = edge_church_event(['church_status' => 'upcoming']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'ongoing',
        ]))
        ->assertRedirect(route('events.show', $event));

    $event->refresh();
    expect($event->church_status)->toBe(ChurchEventStatus::Ongoing);
});

// ---------------------------------------------------------------------------
// Transitions — legal (canEdit passes, transition check passes)
// ---------------------------------------------------------------------------

test('transition: upcoming → ongoing is accepted', function () {
    $event = edge_church_event(['church_status' => 'upcoming']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'ongoing',
        ]))
        ->assertRedirect(route('events.show', $event));

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Ongoing);
});

test('transition: upcoming → cancelled is accepted', function () {
    $event = edge_church_event(['church_status' => 'upcoming']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'cancelled',
        ]))
        ->assertRedirect(route('events.show', $event));

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Cancelled);
});

test('transition: ongoing → completed is accepted', function () {
    $event = edge_church_event(['church_status' => 'ongoing']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'completed',
        ]))
        ->assertRedirect(route('events.show', $event));

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Completed);
});

test('transition: ongoing → cancelled is accepted', function () {
    $event = edge_church_event(['church_status' => 'ongoing']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'cancelled',
        ]))
        ->assertRedirect(route('events.show', $event));

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Cancelled);
});

// ---------------------------------------------------------------------------
// Transition check — illegal jump from an editable state
// ---------------------------------------------------------------------------

test('transition: upcoming → completed is rejected by the transition check', function () {
    $event = edge_church_event(['church_status' => 'upcoming']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'completed',
        ]))
        ->assertSessionHasErrors('church_status');

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Upcoming);
});

// ---------------------------------------------------------------------------
// canEdit guard — terminal church states are locked before transition check
// ---------------------------------------------------------------------------

test('locked: completed church event PUT is blocked by canEdit (403)', function () {
    $event = edge_church_event(['church_status' => 'completed']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'ongoing',
        ]))
        ->assertForbidden();

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Completed);
});

test('locked: cancelled church event PUT is blocked by canEdit (403)', function () {
    $event = edge_church_event(['church_status' => 'cancelled']);
    $staff = edge_staff();

    $this->actingAs($staff)
        ->put(route('events.update', $event), edge_church_payload([
            'church_status' => 'ongoing',
        ]))
        ->assertForbidden();

    expect($event->refresh()->church_status)->toBe(ChurchEventStatus::Cancelled);
});

// ---------------------------------------------------------------------------
// Running events are unaffected
// ---------------------------------------------------------------------------

test('running event: church_status in payload does not trigger the church transition check', function () {
    $staff = edge_staff();
    $event = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'event_name' => 'Running Test '.uniqid(),
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'venue' => 'Start Line',
        'venue_address' => '456 Run Street',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'status' => 'draft',
    ]);

    $this->actingAs($staff)
        ->put(route('events.update', $event), [
            'event_type' => 'community_run',
            'event_name' => 'Running Updated',
            'event_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '06:00',
            'venue' => 'Start Line',
            'venue_address' => '456 Run Street',
            'distance_value' => 5.0,
            'distance_unit' => 'km',
            'church_status' => 'ongoing',
        ])
        ->assertRedirect(route('events.show', $event));

    // Running event keeps its null church_status -- the payload
    // value is ignored by the update() branch for non-church events.
    expect($event->refresh()->church_status)->toBeNull();
    expect($event->refresh()->status->value)->toBe('draft');
});
