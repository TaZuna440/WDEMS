<?php

use App\Models\Member;
use App\Models\Participant;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->participant = Participant::create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'member-test-'.uniqid().'@example.com',
    ]);
});

test('a member can be created for a participant', function () {
    $member = Member::create([
        'participant_id' => $this->participant->id,
        'membership_status' => 'active',
        'membership_date' => '2026-01-15',
        'cell_group' => 'Cell A',
        'ministry' => 'Worship',
        'cell_leader' => 'Pedro Reyes',
    ]);

    expect($member->participant_id)->toBe($this->participant->id);
    expect($member->membership_status)->toBe('active');
    expect($member->cell_group)->toBe('Cell A');
    expect($member->membership_date)->not->toBeNull();
});

test('membership_status defaults to active when not provided', function () {
    $member = Member::create(['participant_id' => $this->participant->id]);

    expect($member->fresh()->membership_status)->toBe('active');
});

test('a member belongs to its participant', function () {
    $member = Member::create(['participant_id' => $this->participant->id]);

    expect($member->participant->id)->toBe($this->participant->id);
    expect($member->participant->is($this->participant))->toBeTrue();
});

test('a participant cannot have two members', function () {
    Member::create(['participant_id' => $this->participant->id]);

    expect(fn () => Member::create(['participant_id' => $this->participant->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the participant member relation returns the member', function () {
    Member::create(['participant_id' => $this->participant->id]);

    $fresh = $this->participant->fresh();

    expect($fresh->member)->not->toBeNull();
    expect($fresh->member->participant_id)->toBe($this->participant->id);
});

test('the participant member relation is null when no member exists', function () {
    expect($this->participant->member)->toBeNull();
});

test('deleting a participant cascades to their member', function () {
    Member::create(['participant_id' => $this->participant->id]);

    $this->participant->delete();

    expect(Member::count())->toBe(0);
});
