<?php

use App\Models\Event;
use App\Models\User;

/**
 * Covers the slug generated inside EventController::openRegistration().
 *
 * Format: 8 characters from a 31-character alphabet excluding the
 * confusables I, L, O, 0, 1. The slug is the event's public identifier
 * at /r/{slug} (Phase 3 block 4 adds the route).
 *
 * All helper names are prefixed slug_ to avoid collisions with helpers
 * in sibling test files (workflow_, regform_).
 */

function slug_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

function slug_event_with_form(): Event
{
    return Event::create([
        'created_by' => slug_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'Slug Test Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'registration_form_saved_at' => now(),
    ]);
}

function slug_event_without_form(): Event
{
    return Event::create([
        'created_by' => slug_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'No Form Run',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

test('opening registration generates a slug', function () {
    $staff = slug_staff();
    $event = slug_event_with_form();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/open-registration")
        ->assertRedirect();

    expect($event->fresh()->registration_slug)->not->toBeNull();
});

test('the generated slug is exactly 8 characters', function () {
    $staff = slug_staff();
    $event = slug_event_with_form();

    $this->actingAs($staff)->post("/events/{$event->id}/open-registration");

    expect($event->fresh()->registration_slug)->toHaveLength(8);
});

test('the generated slug uses only the allowed alphabet', function () {
    $staff = slug_staff();
    $event = slug_event_with_form();

    $this->actingAs($staff)->post("/events/{$event->id}/open-registration");

    expect($event->fresh()->registration_slug)
        ->toMatch('/^[abcdefghjkmnpqrstuvwxyz23456789]{8}$/');
});

test('two events opening registration get different slugs', function () {
    $staff = slug_staff();
    $eventA = slug_event_with_form();
    $eventB = slug_event_with_form();

    $this->actingAs($staff)->post("/events/{$eventA->id}/open-registration");
    $this->actingAs($staff)->post("/events/{$eventB->id}/open-registration");

    expect($eventA->fresh()->registration_slug)
        ->not->toBe($eventB->fresh()->registration_slug);
});

// ---------------------------------------------------------------------------
// Excluded confusables — 20 samples, none may contain I, L, O, 0, 1
// ---------------------------------------------------------------------------

test('the slug never contains the excluded confusables I, L, O, 0, 1', function () {
    $staff = slug_staff();

    for ($i = 0; $i < 20; $i++) {
        $event = slug_event_with_form();
        $this->actingAs($staff)->post("/events/{$event->id}/open-registration");

        expect($event->fresh()->registration_slug)
            ->not->toMatch('/[ilo01]/');
    }
});

// ---------------------------------------------------------------------------
// Null on Draft
// ---------------------------------------------------------------------------

test('a draft event has no slug', function () {
    $event = slug_event_with_form();

    expect($event->registration_slug)->toBeNull();
});

// ---------------------------------------------------------------------------
// Guard: no form saved means no slug
// ---------------------------------------------------------------------------

test('opening registration is rejected when no form has been saved', function () {
    $staff = slug_staff();
    $event = slug_event_without_form();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/open-registration")
        ->assertForbidden();

    expect($event->fresh()->registration_slug)->toBeNull();
});
