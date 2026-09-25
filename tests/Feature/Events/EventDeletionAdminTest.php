<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wdems_make_event(User $creator): Event
{
    return Event::create([
        'created_by' => $creator->id,
        'event_type' => 'community_run',
        'event_name' => 'Test Event',
        'event_date' => now()->toDateString(),
        'status' => 'draft',
    ]);
}

it('lets an admin delete an event with no related records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = wdems_make_event($admin);

    $response = $this->actingAs($admin)->delete("/events/{$event->id}");

    $response->assertRedirect(route('events.index'));
    expect(Event::find($event->id))->toBeNull();
});

it('removes registrations and attendances', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = wdems_make_event($admin);

    $participant = Participant::create([
        'first_name' => 'Test',
        'last_name' => 'Runner',
        'contact_number' => '09000000000',
    ]);

    $registration = Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
    ]);

    $attendance = Attendance::create([
        'registration_id' => $registration->id,
    ]);

    $this->actingAs($admin)->delete("/events/{$event->id}");

    expect(Event::find($event->id))->toBeNull();
    expect(Registration::find($registration->id))->toBeNull();
    expect(Attendance::find($attendance->id))->toBeNull();
});

it('preserves participants when an event is deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = wdems_make_event($admin);

    $participant = Participant::create([
        'first_name' => 'Keep',
        'last_name' => 'Me',
        'contact_number' => '09000000000',
    ]);

    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
    ]);

    $this->actingAs($admin)->delete("/events/{$event->id}");

    expect(Participant::find($participant->id))->not->toBeNull();
});

it('does not let staff delete an event via the destroy endpoint', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $event = wdems_make_event($staff);

    $response = $this->actingAs($staff)->delete("/events/{$event->id}");

    $response->assertForbidden();
    expect(Event::find($event->id))->not->toBeNull();
});

it('does not let a guest delete an event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = wdems_make_event($admin);

    $response = $this->delete("/events/{$event->id}");

    $response->assertRedirect(route('login'));
    expect(Event::find($event->id))->not->toBeNull();
});
