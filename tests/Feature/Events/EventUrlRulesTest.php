<?php

use App\Models\User;

/**
 * Covers URL validation on venue_map_url.
 *
 * The rule accepts only Google Maps URLs (canonical, regional, and
 * short-link forms). Non-Google URLs are rejected. See
 * app/Concerns/EventValidationRules::googleMapsUrlPattern().
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

// Accepted forms

test('a canonical Google Maps place URL is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://www.google.com/maps/place/DAMPALIT+MEGADIKE/@14.6914014,120.9369768,16.59z/data=!4m14',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a Google Maps URL without the www prefix is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://google.com/maps/place/Manila/@14.6,120.9,15z',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a Google Maps URL on a regional domain is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://www.google.com.ph/maps/place/Manila/@14.6,120.9,15z',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a maps.google.com URL with a query string is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://maps.google.com/maps?q=14.5995,120.9842',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a maps.app.goo.gl short link is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://maps.app.goo.gl/abc123xyz',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('a goo.gl/maps short link is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://goo.gl/maps/abc123xyz',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

test('an empty venue_map_url is accepted', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => '',
    ]));

    $response->assertRedirect(route('events.index'));
    $response->assertSessionHasNoErrors();
});

// Rejected forms

test('a non-Google URL is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://www.openstreetmap.org/#map=16/14.5/120.9',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a Waze URL is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://www.waze.com/ul?ll=14.5995,120.9842',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a fake google host is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://fakegoogle.com/maps/place/foo',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a Google URL without the /maps path is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'https://www.google.com/search?q=manila',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a Google Maps URL without a scheme is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'www.google.com/maps/place/foo',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a data scheme URL is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'data:text/html,<script>alert(1)</script>',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});

test('a javascript scheme URL is rejected', function () {
    $user = url_staff();

    $response = $this->actingAs($user)->post(route('events.store'), url_payload([
        'venue_map_url' => 'javascript:alert(1)',
    ]));

    $response->assertSessionHasErrors('venue_map_url');
});
