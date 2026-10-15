<?php

use App\Models\User;

/**
 * Covers URL validation on venue_map_url.
 *
 * The regex requires an http(s) scheme AND a host with at least one
 * dot. This rejects garbage such as "https://fdhgdgfdgf" while
 * accepting real Google Maps, Waze, and OSM links.
 *
 * See docs/event-creation-issues.md.
 *
 * The course_url tests were removed on 2026-10-15 when the field was
 * dropped from the Schedule step and its validation rules.
 */

function url_payload(array $overrides = []): array
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
        'venue' => 'Test Venue',
        'venue_address' => '123 Test Street, Test City',
        'venue_map_url' => '',
        'partners' => [],
    ], $overrides);
}

function url_staff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

// venue_map_url

test('a venue_map_url with a real domain is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://maps.app.goo.gl/xyz789',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a venue_map_url with a subdomain is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://www.waze.com/ul?ll=14.5995,120.9842',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a venue_map_url without a dot in the host is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://fdhgdgfdgf',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a venue_map_url with a data scheme is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'data:text/html,<script>alert(1)</script>',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a venue_map_url with a javascript scheme is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'javascript:alert(1)',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('an empty venue_map_url is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => '',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

// Strict mode — bare domains must be rejected

test('a venue_map_url with a bare domain is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://maps.google.com',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a venue_map_url with a bare domain and trailing slash is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://waze.com/',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a venue_map_url with a query string is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://maps.google.com/?q=14.5995,120.9842',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a venue_map_url with a fragment is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://www.openstreetmap.org/#map=16/14.5/120.9',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});
