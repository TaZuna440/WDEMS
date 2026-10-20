<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Participants\DuplicateReview;

it('audits similar names with shared evidence without treating a family email as proof', function () {
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $event = Event::create(['event_name' => 'Review run', 'event_type' => 'community_run', 'created_by' => $staff->id]);
    foreach (['Maria', 'Marya', 'Juan'] as $name) {
        $person = Participant::create(['first_name' => $name, 'last_name' => 'Santos', 'email' => 'family@example.com']);
        Registration::create(['event_id' => $event->id, 'participant_id' => $person->id, 'registration_status' => 'confirmed', 'source' => 'form']);
    }
    $pairs = app(DuplicateReview::class)->audit($event);
    expect($pairs)->toHaveCount(1)->and($pairs[0]['reasons'])->toBe(['Similar name', 'Same email']);
    $this->actingAs($staff)->getJson("/events/{$event->id}/participant-review/search?q=Maria")->assertOk()->assertJsonCount(1, 'matches');
    $this->get("/events/{$event->id}/possible-duplicates")->assertOk();
    $event->forceFill(['event_kind' => 'church'])->save();
    $this->getJson("/events/{$event->id}/participant-review/search?q=Maria")->assertNotFound();
    $this->get("/events/{$event->id}/possible-duplicates")->assertNotFound();
});

it('keeps lookup and duplicate evidence staff-only and scoped to one event', function () {
    $staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $event = Event::create(['event_name' => 'Empty run', 'event_type' => 'community_run', 'created_by' => $staff->id]);
    Participant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'private@example.com']);
    $this->getJson("/events/{$event->id}/participant-review/search?q=Maria")->assertUnauthorized();
    $this->actingAs($staff)->getJson("/events/{$event->id}/participant-review/search?q=Maria")->assertOk()->assertExactJson(['matches' => []]);
    expect(app(DuplicateReview::class)->audit($event))->toBeEmpty();
});
