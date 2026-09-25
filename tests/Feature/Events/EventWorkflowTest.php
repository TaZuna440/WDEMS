<?php

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use App\Services\EventWorkflow;

/**
 * Covers the EventStatus enum and the EventWorkflow service.
 *
 * Phase 1 of the registration plan removed the Configured state.
 * Draft now transitions directly to RegistrationOpen. These tests
 * pin that shape — any future change to the state machine must
 * update this file as part of the same change.
 */

function workflow_staff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function event_with_status(EventStatus $status): Event
{
    return Event::create([
        'created_by' => workflow_staff()->id,
        'event_type' => EventType::CommunityRun,
        'event_name' => 'Test Event',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => $status,
    ]);
}

// ---------------------------------------------------------------------------
// Enum shape
// ---------------------------------------------------------------------------

test('EventStatus::values does not contain the removed configured value', function () {
    expect(EventStatus::values())->not->toContain('configured');
});

test('EventStatus::tryFrom returns null for the removed configured value', function () {
    expect(EventStatus::tryFrom('configured'))->toBeNull();
});

test('EventStatus has exactly six cases', function () {
    expect(EventStatus::cases())->toHaveCount(6);
});

// ---------------------------------------------------------------------------
// allowedTransitions
// ---------------------------------------------------------------------------

test('Draft transitions to RegistrationOpen and Cancelled', function () {
    $allowed = EventStatus::Draft->allowedTransitions();

    expect($allowed)->toContain(EventStatus::RegistrationOpen);
    expect($allowed)->toContain(EventStatus::Cancelled);
    expect($allowed)->toHaveCount(2);
});

test('Draft does not transition to RegistrationClosed directly', function () {
    expect(EventStatus::Draft->canTransitionTo(EventStatus::RegistrationClosed))
        ->toBeFalse();
});

test('Draft does not transition to Ongoing directly', function () {
    expect(EventStatus::Draft->canTransitionTo(EventStatus::Ongoing))
        ->toBeFalse();
});

test('RegistrationOpen transitions to RegistrationClosed and Cancelled', function () {
    $allowed = EventStatus::RegistrationOpen->allowedTransitions();

    expect($allowed)->toContain(EventStatus::RegistrationClosed);
    expect($allowed)->toContain(EventStatus::Cancelled);
    expect($allowed)->toHaveCount(2);
});

test('Completed and Cancelled are terminal', function () {
    expect(EventStatus::Completed->allowedTransitions())->toBeEmpty();
    expect(EventStatus::Cancelled->allowedTransitions())->toBeEmpty();
});

// ---------------------------------------------------------------------------
// EventWorkflow::transition
// ---------------------------------------------------------------------------

test('EventWorkflow transitions Draft to RegistrationOpen', function () {
    $event = event_with_status(EventStatus::Draft);
    $workflow = new EventWorkflow();

    $workflow->transition($event, EventStatus::RegistrationOpen);

    expect($event->fresh()->status)->toBe(EventStatus::RegistrationOpen);
});

test('EventWorkflow allows Draft to Cancelled', function () {
    $event = event_with_status(EventStatus::Draft);
    $workflow = new EventWorkflow();

    $workflow->transition($event, EventStatus::Cancelled);

    expect($event->fresh()->status)->toBe(EventStatus::Cancelled);
});

test('EventWorkflow walks the full lifecycle', function () {
    $event = event_with_status(EventStatus::Draft);
    $workflow = new EventWorkflow();

    $workflow->transition($event, EventStatus::RegistrationOpen);
    $workflow->transition($event, EventStatus::RegistrationClosed);
    $workflow->transition($event, EventStatus::Ongoing);
    $workflow->transition($event, EventStatus::Completed);

    expect($event->fresh()->status)->toBe(EventStatus::Completed);
});

test('EventWorkflow throws when skipping a state', function () {
    $event = event_with_status(EventStatus::Draft);
    $workflow = new EventWorkflow();

    expect(fn () => $workflow->transition($event, EventStatus::RegistrationClosed))
        ->toThrow(InvalidArgumentException::class);
});

test('EventWorkflow throws when transitioning from a terminal state', function () {
    $event = event_with_status(EventStatus::Completed);
    $workflow = new EventWorkflow();

    expect(fn () => $workflow->transition($event, EventStatus::RegistrationOpen))
        ->toThrow(InvalidArgumentException::class);
});

test('EventWorkflow throws when moving backward', function () {
    $event = event_with_status(EventStatus::RegistrationOpen);
    $workflow = new EventWorkflow();

    expect(fn () => $workflow->transition($event, EventStatus::Draft))
        ->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// EventWorkflow::canTransition — read-only check
// ---------------------------------------------------------------------------

test('EventWorkflow::canTransition returns true for a legal transition', function () {
    $event = event_with_status(EventStatus::Draft);
    $workflow = new EventWorkflow();

    expect($workflow->canTransition($event, EventStatus::RegistrationOpen))
        ->toBeTrue();
});

test('EventWorkflow::canTransition returns false for an illegal transition', function () {
    $event = event_with_status(EventStatus::Draft);
    $workflow = new EventWorkflow();

    expect($workflow->canTransition($event, EventStatus::Completed))
        ->toBeFalse();
});

test('EventWorkflow::canTransition does not save the event', function () {
    $event = event_with_status(EventStatus::Draft);
    $workflow = new EventWorkflow();

    $workflow->canTransition($event, EventStatus::RegistrationOpen);

    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});
