<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;

require_once __DIR__.'/../../Support/PublicVerification.php';

function pname_event(): Event
{
    return Event::create([
        'created_by' => User::factory()->create(['role' => 'staff', 'email_two_factor_enabled' => false])->id,
        'event_type' => 'community_run', 'event_name' => 'Name Validation Run',
        'event_date' => now()->addWeek(), 'status' => 'registration_open',
        'registration_slug' => 'pname'.random_int(1000, 9999),
        'registration_start' => now(), 'registration_form_saved_at' => now(),
    ]);
}

function pname_payload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'contact_number' => '09171234567', 'age' => 28, 'address' => 'Manila', 'responses' => [],
    ], $overrides);
}

test('invalid participant names are rejected before verification', function (string $field, string $value) {
    $event = pname_event();
    $this->post('/r/'.$event->registration_slug, pname_payload([$field => $value]))->assertSessionHasErrors($field);
    expect(Registration::count())->toBe(0);
})->with([
    ['first_name', 'sdfghjkl'], ['first_name', 'aeiou'], ['first_name', 'Maaaria'],
    ['first_name', '-Maria'], ['last_name', 'sdfghjkl'],
]);

test('valid participant names allow registration after verification', function (string $first, string $last) {
    $event = pname_event();
    completePublicRegistration($this, $event, pname_payload(['first_name' => $first, 'last_name' => $last]))->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(1);
})->with([['Maria', 'Santos'], ['Al', 'Bo'], ['Jean-Luc', 'Dela-Cruz']]);
