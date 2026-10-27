<?php

use App\Enums\EventType;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
});

test('the running event selection page is available', function () {
    $this->actingAs($this->staff)
        ->get(route('events.create.running'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('events/create-running'));
});

test('Fun Run is labeled but is not an enabled running type', function () {
    expect(EventType::FunRun->label())->toBe('Fun Run')
        ->and(EventType::runningTypes())->toBe([EventType::CommunityRun]);
});

test('Fun Run cannot be submitted as a running event', function () {
    $this->actingAs($this->staff)
        ->post(route('events.store'), [
            'event_type' => EventType::FunRun->value,
        ])
        ->assertSessionHasErrors('event_type');
});
