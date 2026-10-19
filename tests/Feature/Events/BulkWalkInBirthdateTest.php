<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Covers the birthdate swap Phase 4: the bulk walk-in endpoint
 * accepts `birthday` (preferred) or legacy `age` per row.
 *
 * Two layers are exercised:
 *
 *   - Shape (request): a malformed or out-of-bounds birthday fails
 *     the whole batch, consistent with the existing age range rule.
 *   - Business rules (controller): a row with neither age nor
 *     birthday is rejected per row under `rows.{i}.birthday` (D6)
 *     while the other rows still persist.
 *
 * Helpers are prefixed bwb_ to avoid Pest function collisions with
 * AttendanceBulkWalkInTest (bwt_) and friends.
 */

function bwb_staff(): User
{
    return User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
        'email_two_factor_enabled' => false,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 */
function bwb_event(array $overrides = []): Event
{
    return Event::create(array_merge([
        'created_by' => bwb_staff()->id,
        'event_type' => 'community_run',
        'event_name' => 'BWB Test Run',
        'event_date' => today()->toDateString(),
        'status' => 'registration_closed',
        'registration_form_saved_at' => now(),
        'registration_common_field_requirements' => [
            'email' => true,
            'contact_number' => false,
            'address' => false,
        ],
    ], $overrides));
}

/**
 * A bulk row defaulting to the new shape (birthday). Override with
 * `'birthday' => null` + unset, or pass `'age'` for legacy rows.
 *
 * @param array<string, mixed> $overrides
 */
function bwb_row(array $overrides = []): array
{
    return array_merge([
        'client_uuid' => Str::uuid()->toString(),
        'first_name' => 'Walk',
        'last_name' => 'In',
        'birthday' => now()->subYears(30)->toDateString(),
        'email' => 'bwb'.Str::random(8).'@example.com',
        'contact_number' => '0917'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
        'address' => '',
        'responses' => [],
    ], $overrides);
}

test('bulk walk-in with birthdays persists them and derives ages', function () {
    $staff = bwb_staff();
    $event = bwb_event();
    $birthday = now()->subYears(28)->toDateString();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [bwb_row(['birthday' => $birthday])],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);

    $registration = Registration::where('event_id', $event->id)->first();
    expect($registration->source)->toBe('paper');

    $participant = $registration->participant;
    expect($participant->birthday->toDateString())->toBe($birthday);
    expect($participant->age)->toBe(Carbon::parse($birthday)->age);
});

test('bulk walk-in prefers birthday over age when a row carries both', function () {
    $staff = bwb_staff();
    $event = bwb_event();
    $birthday = now()->subYears(22)->toDateString();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [bwb_row(['birthday' => $birthday, 'age' => 99])],
        ])
        ->assertRedirect();

    $participant = Participant::firstOrFail();
    expect($participant->birthday->toDateString())->toBe($birthday);
    expect($participant->age)->toBe(Carbon::parse($birthday)->age);
    expect($participant->getRawOriginal('age'))->toBeNull();
});

test('bulk walk-in with legacy age-only rows still works', function () {
    $staff = bwb_staff();
    $event = bwb_event();

    $legacyRow = bwb_row(['age' => 40]);
    unset($legacyRow['birthday']);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [$legacyRow],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);

    $participant = Participant::firstOrFail();
    expect($participant->birthday)->toBeNull();
    expect($participant->age)->toBe(40);
});

test('bulk walk-in rejects a row with no demographic but keeps the others', function () {
    $staff = bwb_staff();
    $event = bwb_event();

    $goodRow = bwb_row(['first_name' => 'Good']);
    $badRow = bwb_row(['first_name' => 'Bad']);
    unset($badRow['birthday']);

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [$goodRow, $badRow],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('rows.1.birthday');

    // D6: the good row persists, the bad row does not block it.
    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
    expect(Participant::where('first_name', 'Good')->exists())->toBeTrue();
    expect(Participant::where('first_name', 'Bad')->exists())->toBeFalse();
});

test('bulk walk-in rejects an under-3 birthday at the shape layer', function () {
    $staff = bwb_staff();
    $event = bwb_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [bwb_row(['birthday' => now()->subYears(2)->toDateString()])],
        ])
        ->assertSessionHasErrors('rows.0.birthday');

    // Shape failures are all-or-nothing, like the age range rule.
    expect(Registration::where('event_id', $event->id)->count())->toBe(0);
});

test('bulk walk-in accepts the exact boundary birthdays', function () {
    $staff = bwb_staff();
    $event = bwb_event();

    $this->actingAs($staff)
        ->post("/events/{$event->id}/attendance/bulk-walk-in", [
            'rows' => [
                bwb_row([
                    'first_name' => 'Young',
                    'birthday' => now()->subYears(3)->toDateString(),
                ]),
                bwb_row([
                    'first_name' => 'Old',
                    'birthday' => now()->subYears(120)->toDateString(),
                ]),
            ],
        ])
        ->assertRedirect();

    expect(Registration::where('event_id', $event->id)->count())->toBe(2);
    expect(Participant::where('first_name', 'Young')->firstOrFail()->age)->toBe(3);
    expect(Participant::where('first_name', 'Old')->firstOrFail()->age)->toBe(120);
});
