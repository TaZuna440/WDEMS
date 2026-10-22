<?php

use App\Enums\ChurchEventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\ChurchSampleDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the complete church system scope to signed-in users', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('events.church.system-scope'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('events/church/system-scope')
            ->where('modules', fn ($modules) => collect($modules)->pluck('name')->all() === [
                'Services',
                'Fellowship',
                'Discipleship',
                'Youth Ministry',
                'Kids Ministry',
                'Young Adult',
                'Worship & Prayer',
                'Church Administration',
                'Special Occasions',
            ])
            ->has('included')
            ->has('limitations'));
});

it('creates, edits, and displays an optional church event coordinator', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $payload = [
        'event_kind' => 'church',
        'event_type' => EventType::Church->value,
        'church_category' => 'Youth',
        'church_status' => ChurchEventStatus::Upcoming->value,
        'event_name' => 'Coordinator Test Event',
        'description' => 'A church event for coordinator coverage.',
        'coordinator' => 'Jordan Lee',
        'event_date' => today()->addDays(4)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'venue' => 'Youth Hall',
        'venue_address' => '123 Sample Street, Manila',
    ];

    $this->actingAs($user)->post(route('events.store'), $payload)->assertSessionHasNoErrors();
    $event = Event::query()->where('event_name', $payload['event_name'])->sole();

    expect($event->coordinator)->toBe('Jordan Lee');

    $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page
        ->where('event.coordinator', 'Jordan Lee'));
    $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->where('event.coordinator', 'Jordan Lee'));

    $this->put(route('events.update', $event), [
        ...$payload,
        'coordinator' => 'Taylor Morgan',
    ])->assertSessionHasNoErrors();

    expect($event->fresh()->coordinator)->toBe('Taylor Morgan');

    $this->put(route('events.update', $event), [
        ...$payload,
        'coordinator' => str_repeat('a', 256),
    ])->assertSessionHasErrors('coordinator');
});

it('accepts the five church participant types for staff registration only', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'created_by' => $user->id,
        'event_kind' => Event::KIND_CHURCH,
        'event_type' => EventType::Church,
        'church_category' => 'Fellowship',
        'church_status' => ChurchEventStatus::Upcoming,
        'event_name' => 'Participant Type Test Event',
        'event_date' => today()->addWeek()->toDateString(),
    ]);

    foreach (['Member', 'First-Timer', 'Guest', 'Graduate', 'Ninong/Ninang'] as $index => $type) {
        $firstNames = ['Alina', 'Beatriz', 'Carmen', 'Daphne', 'Elena'];
        $this->actingAs($user)
            ->post(route('church.registrations.store', $event), [
                'first_name' => $firstNames[$index],
                'last_name' => 'Test',
                'email' => 'church-type-'.$index.'@example.com',
                'participant_type' => $type,
            ])
            ->assertSessionHasNoErrors();
    }

    expect(Registration::query()->where('event_id', $event->id)->pluck('participant_type')->sort()->values()->all())
        ->toBe(collect(['Member', 'First-Timer', 'Guest', 'Graduate', 'Ninong/Ninang'])->sort()->values()->all());

    $this->post(route('church.registrations.store', $event), [
        'first_name' => 'Invalid',
        'last_name' => 'Type',
        'participant_type' => 'Volunteer',
    ])->assertSessionHasErrors('participant_type');
});

it('requires a venue for church and running event creation', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $common = [
        'event_name' => 'Venue Requirement Event',
        'event_date' => today()->addDays(4)->toDateString(),
        'start_time' => '10:00',
        'venue_address' => '123 Sample Street, Manila',
    ];

    $this->actingAs($user)->post(route('events.store'), [
        ...$common,
        'event_kind' => 'church',
        'event_type' => EventType::Church->value,
        'church_category' => 'Youth',
        'church_status' => ChurchEventStatus::Upcoming->value,
    ])->assertSessionHasErrors('venue');

    $this->post(route('events.store'), [
        ...$common,
        'event_kind' => 'running',
        'event_type' => EventType::CommunityRun->value,
        'distance_value' => 5,
        'distance_unit' => 'km',
    ])->assertSessionHasErrors('venue');

    $running = [
        ...$common,
        'event_kind' => 'running',
        'event_type' => EventType::CommunityRun->value,
        'distance_value' => 5,
        'distance_unit' => 'km',
        'venue' => 'Running Park',
        'coordinator' => 'Must not apply to running events',
    ];
    $this->post(route('events.store'), $running)->assertSessionHasNoErrors();

    expect(Event::query()->where('event_name', $common['event_name'])->sole()->coordinator)->toBeNull();
});

it('seeds specialist church demo data idempotently without changing the default seed path', function () {
    Artisan::call('db:seed', [
        '--class' => ChurchSampleDataSeeder::class,
        '--no-interaction' => true,
    ]);

    $eventCount = Event::query()->whereIn('event_name', [
        'Youth Camp (Sample)',
        'Kids Service (Sample)',
        'GRC Fellowship (Sample)',
    ])->count();
    $registrationCount = Registration::query()
        ->whereIn('event_id', Event::query()->whereIn('event_name', [
            'Youth Camp (Sample)',
            'Kids Service (Sample)',
            'GRC Fellowship (Sample)',
        ])->select('id'))
        ->count();
    $attendanceCount = DB::table('attendances')
        ->whereIn('registration_id', Registration::query()
            ->whereIn('event_id', Event::query()->whereIn('event_name', [
                'Youth Camp (Sample)',
                'Kids Service (Sample)',
                'GRC Fellowship (Sample)',
            ])->select('id'))
            ->select('id'))
        ->count();

    $sampleEvent = Event::query()->where('event_name', 'Youth Camp (Sample)')->sole();
    $sampleEvent->update(['event_date' => today()->subDay()->toDateString()]);
    $manualAttendance = Registration::query()
        ->where('event_id', $sampleEvent->id)
        ->whereHas('attendance', fn ($query) => $query->where('attendance_status', 'present'))
        ->firstOrFail()
        ->attendance()
        ->firstOrFail();
    $manualAttendance->update(['attendance_status' => 'absent']);

    Artisan::call('db:seed', [
        '--class' => ChurchSampleDataSeeder::class,
        '--no-interaction' => true,
    ]);

    expect($eventCount)->toBe(3)
        ->and($registrationCount)->toBe(12)
        ->and($attendanceCount)->toBe(9)
        ->and(Participant::query()->where('pool', 'church')->count())->toBe(12)
        ->and($sampleEvent->fresh()->event_date->toDateString())->toBe(today()->addDays(7)->toDateString())
        ->and($manualAttendance->fresh()->attendance_status->value)->toBe('absent')
        ->and(Event::query()->whereIn('event_name', [
            'Youth Camp (Sample)',
            'Kids Service (Sample)',
            'GRC Fellowship (Sample)',
        ])->count())->toBe($eventCount)
        ->and(Event::query()->where('event_kind', Event::KIND_CHURCH)->whereDate('event_date', '>', today()->toDateString())
            ->whereDate('event_date', '<=', today()->addDays(14)->toDateString())
            ->whereIn('event_name', ['Youth Camp (Sample)', 'Kids Service (Sample)', 'GRC Fellowship (Sample)'])
            ->whereNotNull('venue')
            ->count())->toBe(3)
        ->and(Event::query()->where('event_name', 'Youth Camp (Sample)')->value('church_activity_category'))->toBe('Youth Camp')
        ->and(Event::query()->where('event_name', 'Kids Service (Sample)')->value('church_activity_category'))->toBe('Kids Service')
        ->and(Event::query()->where('event_name', 'GRC Fellowship (Sample)')->value('church_activity_category'))->toBe('GRC Fellowship')
        ->and(Registration::query()->count())->toBe($registrationCount)
        ->and(DB::table('attendances')->count())->toBe($attendanceCount)
        ->and(Participant::query()->where('pool', 'running')->count())->toBe(0);
});
