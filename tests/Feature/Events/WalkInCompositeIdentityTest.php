<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Str;

test('walk-in duplicate checks distinguish family members sharing contact details', function (string $endpoint, string $contact) {
    $staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
    $event = Event::create([
        'created_by' => $staff->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'event_name' => 'Family Run',
        'event_date' => today()->subDay(),
        'status' => 'ongoing',
        'registration_common_field_requirements' => ['email' => false, 'contact_number' => false, 'address' => false],
    ]);
    $contactDetails = $contact === 'email'
        ? ['email' => 'family@example.com']
        : ['contact_number' => '09171234567'];
    $parent = Participant::create(array_merge($contactDetails, ['first_name' => 'Maria', 'last_name' => 'Santos']));
    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $parent->id,
        'registration_date' => now(),
        'registration_status' => 'confirmed',
        'source' => 'form',
        'registered_at' => now(),
    ]);
    $row = array_merge($contactDetails, [
        'first_name' => 'Juan',
        'last_name' => 'Santos',
        'age' => 15,
    ]);
    $payload = fn () => $endpoint === 'bulk-walk-in'
        ? ['rows' => [array_merge($row, ['client_uuid' => (string) Str::uuid()])]]
        : $row;

    $this->actingAs($staff)->post("/events/{$event->id}/attendance/{$endpoint}", $payload())
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($event->registrations()->count())->toBe(2)
        ->and(Participant::where('first_name', 'Juan')->count())->toBe(1);

    $this->post("/events/{$event->id}/attendance/{$endpoint}", $payload())
        ->assertRedirect()
        ->assertSessionHasErrors($endpoint === 'bulk-walk-in' ? 'rows.0.identity' : 'identity');

    expect($event->registrations()->count())->toBe(2);
})->with(['walk-in', 'bulk-walk-in'])->with(['email', 'phone']);
