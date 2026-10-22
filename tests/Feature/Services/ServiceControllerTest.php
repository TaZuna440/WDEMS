<?php

use App\Enums\ServiceType;
use App\Models\ServiceOccurrence;
use App\Models\User;
use App\Services\Church\RegistrationBridge;

beforeEach(function () {
    $this->staff = User::factory()->create([
        'role' => 'staff',
        'email_verified_at' => now(),
    ]);
});

// ----------------------------------------------------------------
// Landing (index)
// ----------------------------------------------------------------

test('the services landing page renders four service types', function () {
    $this->actingAs($this->staff)
        ->get(route('services.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('services/index')
            ->has('service_types', 4)
        );
});

test('the landing page reports occurrence counts per type', function () {
    ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeeks(2)->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    ServiceOccurrence::create([
        'service_type' => ServiceType::Youth->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->get(route('services.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('service_types.0.value', 'morning')
            ->where('service_types.0.occurrence_count', 2)
            ->where('service_types.2.value', 'youth')
            ->where('service_types.2.occurrence_count', 1)
        );
});

// ----------------------------------------------------------------
// Per-type view (show)
// ----------------------------------------------------------------

test('the per-type view lists occurrences for that type only', function () {
    ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    ServiceOccurrence::create([
        'service_type' => ServiceType::Youth->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->get(route('services.show', ['type' => 'morning']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('services/show')
            ->where('service_type.value', 'morning')
            ->has('occurrences', 1)
            ->where('occurrences.0.registration_event_id', null)
        );
});

test('the per-type view includes the registration event id after activation', function () {
    $occurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $churchEvent = app(RegistrationBridge::class)->activate($occurrence);

    $this->actingAs($this->staff)
        ->get(route('services.show', ['type' => 'morning']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('occurrences.0.id', $occurrence->id)
            ->where('occurrences.0.registration_event_id', $churchEvent->id));
});

test('an unknown service type returns 404 from the route constraint', function () {
    $this->actingAs($this->staff)
        ->get('/services/not-a-real-type')
        ->assertNotFound();
});

test('occurrences are ordered most-recent-first', function () {
    ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeeks(3)->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->get(route('services.show', ['type' => 'morning']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('occurrences', 2)
            ->where('occurrences.0.occurrence_date', now()->addWeeks(3)->toDateString())
            ->where('occurrences.1.occurrence_date', now()->addWeek()->toDateString())
        );
});

// ----------------------------------------------------------------
// Manual occurrence creation
// ----------------------------------------------------------------

test('an authenticated user can create one manual occurrence', function () {
    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store', ['type' => 'morning']), [
            'occurrence_date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'service_leader' => 'Pastor David',
            'notes' => 'First service of the month.',
        ])
        ->assertRedirect(route('services.show', ['type' => 'morning']));

    expect(ServiceOccurrence::count())->toBe(1);

    $occurrence = ServiceOccurrence::first();
    expect($occurrence->service_type)->toBe(ServiceType::Morning);
    expect($occurrence->service_leader)->toBe('Pastor David');
    expect($occurrence->created_by)->toBe($this->staff->id);
});

test('occurrence creation requires a date', function () {
    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store', ['type' => 'morning']), [
            'start_time' => '10:00',
        ])
        ->assertSessionHasErrors('occurrence_date');

    expect(ServiceOccurrence::count())->toBe(0);
});

test('occurrence creation rejects an end_time before start_time', function () {
    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store', ['type' => 'morning']), [
            'occurrence_date' => now()->addWeek()->toDateString(),
            'start_time' => '12:00',
            'end_time' => '10:00',
        ])
        ->assertSessionHasErrors('end_time');

    expect(ServiceOccurrence::count())->toBe(0);
});

test('occurrence creation rejects a date more than 2 years out', function () {
    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store', ['type' => 'morning']), [
            'occurrence_date' => now()->addYears(3)->toDateString(),
        ])
        ->assertSessionHasErrors('occurrence_date');
});

test('occurrence creation rejects a past scheduling date', function () {
    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store', ['type' => 'morning']), [
            'occurrence_date' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('occurrence_date');

    expect(ServiceOccurrence::count())->toBe(0);
});

// ----------------------------------------------------------------
// Recurring batch
// ----------------------------------------------------------------

test('a weekly recurring batch creates N occurrences', function () {
    $first = now()->addWeek()->startOfWeek()->toDateString();

    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store-recurring', ['type' => 'morning']), [
            'pattern' => 'weekly',
            'day_of_week' => 0,
            'first_date' => $first,
            'weeks' => 4,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ])
        ->assertRedirect();

    expect(ServiceOccurrence::count())->toBe(4);
});

test('a fortnightly recurring batch spans 14 days between occurrences', function () {
    $first = now()->addWeek()->startOfWeek()->toDateString();

    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store-recurring', ['type' => 'morning']), [
            'pattern' => 'fortnightly',
            'day_of_week' => 0,
            'first_date' => $first,
            'weeks' => 3,
        ])
        ->assertRedirect();

    $dates = ServiceOccurrence::orderBy('occurrence_date')->pluck('occurrence_date');

    expect($dates->count())->toBe(3);
    expect((int) $dates[0]->diffInDays($dates[1]))->toBe(14);
});

test('the recurring generator skips dates that already have an occurrence', function () {
    // Pre-create on the exact date the generator will produce. The
    // controller adjusts first_date forward to match day_of_week, so
    // start on that day (Sunday, dayOfWeek=0) rather than Monday.
    $sunday = now()->addWeek()->startOfWeek()->addDays(6)->toDateString();

    ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => $sunday,
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store-recurring', ['type' => 'morning']), [
            'pattern' => 'weekly',
            'day_of_week' => 0,
            'first_date' => $sunday,
            'weeks' => 3,
        ])
        ->assertRedirect();

    // Pre-existing 1 + 2 new (the first generated date is skipped).
    expect(ServiceOccurrence::count())->toBe(3);
});

test('the recurring generator adjusts first_date forward to match day_of_week', function () {
    $monday = now()->addWeek()->startOfWeek()->toDateString();

    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store-recurring', ['type' => 'morning']), [
            'pattern' => 'weekly',
            'day_of_week' => 0,
            'first_date' => $monday,
            'weeks' => 1,
        ])
        ->assertRedirect();

    $occurrence = ServiceOccurrence::first();
    expect($occurrence->occurrence_date->dayOfWeek)->toBe(0);
});

test('the recurring generator rejects more than 52 occurrences', function () {
    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store-recurring', ['type' => 'morning']), [
            'pattern' => 'weekly',
            'day_of_week' => 0,
            'first_date' => now()->addWeek()->toDateString(),
            'weeks' => 53,
        ])
        ->assertSessionHasErrors('weeks');
});

test('recurring generation rejects schedules whose final occurrence exceeds two years', function () {
    $firstDate = now()->addYears(2)->subDays(7)->toDateString();

    $this->actingAs($this->staff)
        ->post(route('services.occurrences.store-recurring', ['type' => 'morning']), [
            'pattern' => 'weekly',
            'day_of_week' => (string) now()->parse($firstDate)->dayOfWeek,
            'first_date' => $firstDate,
            'weeks' => 3,
        ])
        ->assertSessionHasErrors('first_date');

    expect(ServiceOccurrence::count())->toBe(0);
});

// ----------------------------------------------------------------
// Deletion
// ----------------------------------------------------------------

test('an occurrence can be deleted', function () {
    $occurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value,
        'occurrence_date' => now()->addWeek()->toDateString(),
        'created_by' => $this->staff->id,
    ]);

    $this->actingAs($this->staff)
        ->delete(route('services.occurrences.destroy', ['occurrence' => $occurrence->id]))
        ->assertRedirect(route('services.show', ['type' => 'morning']));

    expect(ServiceOccurrence::count())->toBe(0);
});

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

test('guests are redirected to login from the services landing', function () {
    $this->get(route('services.index'))->assertRedirect(route('login'));
});
