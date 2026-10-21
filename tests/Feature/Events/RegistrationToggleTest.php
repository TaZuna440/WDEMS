<?php

use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now(), 'email_two_factor_enabled' => false]);
    $this->actingAs($this->staff);
    $this->event = Event::create(['created_by' => $this->staff->id, 'event_name' => 'Toggle run',
        'event_type' => 'community_run', 'status' => 'registration_open',
        'registration_slug' => 'toggle-run', 'registration_form_saved_at' => now()]);
});

test('closing and reopening require the current password and retain the public link', function () {
    $this->post(route('events.close-registration', $this->event), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');
    expect($this->event->fresh()->status->value)->toBe('registration_open');
    $this->post(route('events.close-registration', $this->event), ['password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('registration-monitor.show', $this->event));
    expect($this->event->fresh()->status->value)->toBe('registration_closed')
        ->and($this->event->fresh()->registration_end)->not->toBeNull();
    $this->post(route('events.reopen-registration', $this->event))->assertSessionHasErrors('password');
    $this->post(route('events.reopen-registration', $this->event), ['password' => 'password'])->assertSessionHasNoErrors();
    expect($this->event->fresh()->status->value)->toBe('registration_open')
        ->and($this->event->fresh()->registration_slug)->toBe('toggle-run')
        ->and($this->event->fresh()->registration_end)->toBeNull();
});

test('church and terminal events cannot use the registration toggle', function () {
    $this->event->forceFill(['event_kind' => 'church', 'status' => 'registration_closed'])->save();
    $this->post(route('events.reopen-registration', $this->event), ['password' => 'password'])->assertForbidden();
    $this->event->forceFill(['event_kind' => 'running', 'status' => 'completed'])->save();
    $this->post(route('events.reopen-registration', $this->event), ['password' => 'password'])->assertForbidden();
});

test('on-site registration can close and reopen without rewinding an ongoing event', function () {
    $this->event->forceFill(['status' => 'ongoing'])->save();
    $this->get('/r/toggle-run')->assertInertia(fn ($page) => $page->component('registrations/public'));
    $this->post(route('events.close-registration', $this->event), ['password' => 'password'])->assertSessionHasNoErrors();
    expect($this->event->fresh()->status->value)->toBe('ongoing');
    $this->get('/r/toggle-run')->assertInertia(fn ($page) => $page->component('registrations/closed'));
    $this->get(route('registration-monitor.show', $this->event))->assertOk();
    $this->post(route('events.reopen-registration', $this->event), ['password' => 'password'])->assertSessionHasNoErrors();
    expect($this->event->fresh()->status->value)->toBe('ongoing');
    $this->get('/r/toggle-run')->assertInertia(fn ($page) => $page->component('registrations/public'));
});
