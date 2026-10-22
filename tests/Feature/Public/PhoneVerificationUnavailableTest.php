<?php

use App\Models\Event;
use App\Models\User;

test('phone verification route is unreachable because only email is routed', function () {
    $event = Event::create([
        'created_by' => User::factory()->create(['role' => 'staff'])->id,
        'event_type' => 'community_run',
        'event_name' => 'Email-only verification route',
        'event_date' => now()->addWeek(),
        'status' => 'registration_open',
        'registration_slug' => 'email-only-route-test',
    ]);

    $this->post('/r/'.$event->registration_slug.'/verification/phone', [
        'code' => '123456',
    ])->assertNotFound();
});
