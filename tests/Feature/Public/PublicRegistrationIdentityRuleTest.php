<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

require_once __DIR__.'/../../Support/PublicVerification.php';

function identity_pub_event(array $requirements): Event
{
    return Event::create([
        'created_by' => User::factory()->create(['role' => 'staff', 'email_two_factor_enabled' => false])->id,
        'event_type' => 'community_run', 'event_name' => 'Identity Rule Test Run',
        'event_date' => now()->addWeek(), 'status' => 'registration_open',
        'registration_slug' => 'identity', 'registration_start' => now(),
        'registration_form_saved_at' => now(), 'registration_common_field_requirements' => $requirements,
    ]);
}

function identity_pub_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => '',
        'contact_number' => '', 'age' => 28, 'address' => 'Manila', 'responses' => [],
    ], $overrides);
}

test('required identity combinations are accepted after verification', function (bool $email, bool $phone, string $number) {
    $event = identity_pub_event(['email' => $email, 'contact_number' => $phone]);
    completePublicRegistration($this, $event, identity_pub_payload([
        'email' => $email ? 'maria@example.com' : '', 'contact_number' => $number,
    ]))->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(1);
})->with([[false, true, '09171234567'], [true, true, '09171234567'], [false, true, '+639171234567']]);

test('a submission without a usable required identity is rejected', function (string $email, string $phone) {
    $event = identity_pub_event(['email' => false, 'contact_number' => true]);
    $this->post('/r/identity', identity_pub_payload(['email' => $email, 'contact_number' => $phone]))->assertSessionHasErrors('identity');
    expect(Registration::count())->toBe(0);
    expect(Participant::count())->toBe(0);
})->with([['', ''], ['', 'not-a-phone'], ['   ', ''], ['optional@example.com', '']]);


