<?php

use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
});

test('a Filipino event name is accepted', function () {
    $this->actingAs($this->staff)->post(route('events.store'), [
        'event_type' => 'community_run',
        'event_name' => 'Pagsamba ng Kabataan',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'venue' => 'Test Venue',
        'venue_address' => 'Test Address',
    ])->assertRedirect(route('events.index'));
});

test('a Spanish event name with non-ASCII letters is accepted', function () {
    $this->actingAs($this->staff)->post(route('events.store'), [
        'event_type' => 'community_run',
        'event_name' => 'Peñaflor Run',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'venue' => 'Test Venue',
        'venue_address' => 'Test Address',
    ])->assertRedirect(route('events.index'));
});

test('a name starting with a non-ASCII letter is accepted', function () {
    $this->actingAs($this->staff)->post(route('events.store'), [
        'event_type' => 'community_run',
        'event_name' => 'Ñino Run',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'venue' => 'Test Venue',
        'venue_address' => 'Test Address',
    ])->assertRedirect(route('events.index'));
});

test('keyboard mashing is still rejected', function () {
    $this->actingAs($this->staff)->post(route('events.store'), [
        'event_type' => 'community_run',
        'event_name' => 'dfdsfdsfd',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'venue' => 'Test Venue',
        'venue_address' => 'Test Address',
    ])->assertSessionHasErrors('event_name');
});

test('ASCII names with no vowel are still rejected', function () {
    $this->actingAs($this->staff)->post(route('events.store'), [
        'event_type' => 'community_run',
        'event_name' => 'Rnnng',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'venue' => 'Test Venue',
        'venue_address' => 'Test Address',
    ])->assertSessionHasErrors('event_name');
});

test('the three-in-a-row rule still applies to non-ASCII input', function () {
    $this->actingAs($this->staff)->post(route('events.store'), [
        'event_type' => 'community_run',
        'event_name' => 'Pasko ååå sa Nayon',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'distance_value' => 5.0,
        'distance_unit' => 'km',
        'venue' => 'Test Venue',
        'venue_address' => 'Test Address',
    ])->assertSessionHasErrors('event_name');
});
