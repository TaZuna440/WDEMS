<?php

use App\Models\FirstTimer;
use App\Models\Member;
use App\Models\Participant;

beforeEach(function () {
    $this->participant = Participant::create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'first-timer-test-'.uniqid().'@example.com',
    ]);
});

test('a first_timer can be created for a participant', function () {
    $firstTimer = FirstTimer::create([
        'participant_id' => $this->participant->id,
        'first_service_date' => '2026-10-12',
        'invited_by' => 'Ana Reyes',
        'follow_up_notes' => 'Interested in joining a cell group.',
    ]);

    expect($firstTimer->participant_id)->toBe($this->participant->id);
    expect($firstTimer->invited_by)->toBe('Ana Reyes');
    expect($firstTimer->first_service_date)->not->toBeNull();
    expect($firstTimer->promoted_at)->toBeNull();
    expect($firstTimer->promoted_to_member_id)->toBeNull();
});

test('a participant can have many first_timers', function () {
    FirstTimer::create(['participant_id' => $this->participant->id]);
    FirstTimer::create(['participant_id' => $this->participant->id]);
    FirstTimer::create(['participant_id' => $this->participant->id]);

    $fresh = $this->participant->fresh();

    expect($fresh->firstTimers)->toHaveCount(3);
});

test('a first_timer belongs to its participant', function () {
    $firstTimer = FirstTimer::create(['participant_id' => $this->participant->id]);

    expect($firstTimer->participant->is($this->participant))->toBeTrue();
});

test('isPromoted returns false when the record is not promoted', function () {
    $firstTimer = FirstTimer::create(['participant_id' => $this->participant->id]);

    expect($firstTimer->isPromoted())->toBeFalse();
});

test('isPromoted returns true once the record is promoted', function () {
    $member = Member::create(['participant_id' => $this->participant->id]);

    $firstTimer = FirstTimer::create([
        'participant_id' => $this->participant->id,
        'promoted_at' => now(),
        'promoted_to_member_id' => $member->id,
    ]);

    expect($firstTimer->isPromoted())->toBeTrue();
});

test('promotedToMember links to the member record', function () {
    $member = Member::create(['participant_id' => $this->participant->id]);

    $firstTimer = FirstTimer::create([
        'participant_id' => $this->participant->id,
        'promoted_at' => now(),
        'promoted_to_member_id' => $member->id,
    ]);

    expect($firstTimer->promotedToMember)->not->toBeNull();
    expect($firstTimer->promotedToMember->is($member))->toBeTrue();
});

test('promotedToMember is null for an unpromoted first_timer', function () {
    $firstTimer = FirstTimer::create(['participant_id' => $this->participant->id]);

    expect($firstTimer->promotedToMember)->toBeNull();
});

test('deleting a participant cascades to their first_timers', function () {
    FirstTimer::create(['participant_id' => $this->participant->id]);
    FirstTimer::create(['participant_id' => $this->participant->id]);

    $this->participant->delete();

    expect(FirstTimer::count())->toBe(0);
});

test('deleting a promoted member nulls promoted_to_member_id without removing the first_timer', function () {
    $member = Member::create(['participant_id' => $this->participant->id]);

    $firstTimer = FirstTimer::create([
        'participant_id' => $this->participant->id,
        'promoted_at' => now(),
        'promoted_to_member_id' => $member->id,
    ]);

    $member->delete();

    $fresh = $firstTimer->fresh();

    expect($fresh)->not->toBeNull();
    expect($fresh->promoted_to_member_id)->toBeNull();
    expect($fresh->promoted_at)->not->toBeNull();
});
