<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $this->actingAs($this->staff);
});

test('name-only walk-ins remain event-local and present without merging', function () {
    $first = Event::create(['event_name' => 'First run', 'event_type' => 'community_run', 'status' => 'ongoing', 'created_by' => $this->staff->id]);
    $second = Event::create(['event_name' => 'Second run', 'event_type' => 'community_run', 'status' => 'ongoing', 'created_by' => $this->staff->id]);
    foreach ([$first, $second] as $event) {
        $this->post(route('events.attendance.quick-walk-in', $event), ['first_name' => 'Maria', 'last_name' => 'Santos', 'force_open' => true])
            ->assertSessionHasNoErrors();
    }
    expect(Participant::count())->toBe(2)->and(Registration::count())->toBe(2);
    foreach (Registration::with(['participant', 'attendance'])->get() as $registration) {
        expect($registration->participant->provisional_event_id)->toBe($registration->event_id)
            ->and($registration->participant->email)->toBeNull()
            ->and($registration->attendance->attendance_status->value)->toBe('present')
            ->and($registration->flagged_at)->not->toBeNull();
    }
    $provisional = Participant::first();
    $provisional->update(['email' => 'maria@example.com']);
    expect(Participant::findByComposite('running', 'maria@example.com', null, 'Maria', 'Santos'))->toBeNull();
});

test('quick walk-in cannot bypass church isolation even with admin force open', function () {
    $event = Event::create(['event_name' => 'Church', 'event_type' => 'community_run', 'event_kind' => 'church', 'status' => 'ongoing', 'created_by' => $this->staff->id]);
    $this->post(route('events.attendance.quick-walk-in', $event), ['first_name' => 'Maria', 'last_name' => 'Santos', 'force_open' => true])->assertForbidden();
    expect(Participant::count())->toBe(0);
});
