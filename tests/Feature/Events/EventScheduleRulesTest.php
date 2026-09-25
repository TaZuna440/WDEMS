<?php

use App\Models\User;

/**
 * Covers the schedule-step validation rules:
 *
 *   SCH-01a — end_time must be after start_time
 *   SCH-01b — when set, end_time must be at least 60 minutes after start_time
 *   SCH-01c — end_time remains optional
 *   SCH-02/03 — course_url must be http(s)://
 *
 * See docs/event-creation-issues.md.
 */

function schedule_payload(array $overrides = []): array
{
    return array_merge([
        'event_type' => 'community_run',
        'event_name' => 'Test Run',
        'description' => '',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '18:00',
        'end_time' => '20:00',
        'distance_value' => 5,
        'distance_unit' => 'km',
        'course_url' => '',
        'venue' => 'Test Venue',
        'venue_address' => '123 Test Street',
        'venue_latitude' => 14.5995,
        'venue_longitude' => 120.9842,
        'rsvp_required' => false,
        'partners' => [],
    ], $overrides);
}

function schedule_staff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

// SCH-01c — end_time optional

test('an event with no end_time is accepted', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'end_time' => null,
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

// SCH-01a — end must be after start

test('an event where end_time is before start_time is rejected', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'start_time' => '18:00',
        'end_time' => '06:00',
    ]));

    $response->assertSessionHasErrors('end_time');
});

test('an event where end_time equals start_time is rejected', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'start_time' => '18:00',
        'end_time' => '18:00',
    ]));

    $response->assertSessionHasErrors('end_time');
});

// SCH-01b — minimum 60 minutes

test('an event with exactly 60 minutes is accepted', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'start_time' => '18:00',
        'end_time' => '19:00',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('an event with 59 minutes is rejected', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'start_time' => '18:00',
        'end_time' => '18:59',
    ]));

    $response->assertSessionHasErrors('end_time');
});

test('an event with 30 minutes is rejected', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'start_time' => '18:00',
        'end_time' => '18:30',
    ]));

    $response->assertSessionHasErrors('end_time');
});

test('an event with 120 minutes is accepted', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'start_time' => '18:00',
        'end_time' => '20:00',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

// SCH-02/03 — course_url scheme restriction

test('a course_url with https scheme is accepted', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'course_url' => 'https://maps.app.goo.gl/abc123',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a course_url with http scheme is accepted', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'course_url' => 'http://example.com/route',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a course_url with no scheme is rejected', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'course_url' => 'maps.app.goo.gl/abc123',
    ]));

    $response->assertSessionHasErrors('course_url');
});

test('a course_url with a data scheme is rejected', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'course_url' => 'data:text/html,<script>alert(1)</script>',
    ]));

    $response->assertSessionHasErrors('course_url');
});

test('a course_url with a javascript scheme is rejected', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'course_url' => 'javascript:alert(1)',
    ]));

    $response->assertSessionHasErrors('course_url');
});

test('an empty course_url is accepted', function () {
    $user = schedule_staff();

    $response = $this->actingAs($user)->post(route('events.store'), schedule_payload([
        'course_url' => '',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});
