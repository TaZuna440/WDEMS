<?php

use App\Enums\FellowshipType;
use App\Enums\ServiceType;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\FellowshipAttendance;
use App\Models\FellowshipEvent;
use App\Models\FellowshipGroup;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ServiceAttendanceMember;
use App\Models\ServiceOccurrence;
use App\Models\User;
use App\Services\Church\RegistrationBridge;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $this->actingAs($this->staff);
    $this->occurrence = ServiceOccurrence::create([
        'service_type' => ServiceType::Morning->value, 'occurrence_date' => now()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
});

it('activates once and preserves original attendance and its recorder', function () {
    $person = Participant::create(['pool' => 'church', 'first_name' => 'Maria', 'last_name' => 'Santos']);
    $history = ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id, 'participant_id' => $person->id,
        'attendance_status' => 'present', 'recorded_by' => $this->staff->id, 'recorded_at' => now(), 'notes' => 'Original note',
    ]);
    $bridge = app(RegistrationBridge::class);
    $event = $bridge->activate($this->occurrence);
    expect($bridge->activate($this->occurrence)->id)->toBe($event->id);
    $row = Registration::sole();
    expect($row->participant_id)->toBe($person->id)->and($row->attendance->notes)->toBe('Original note')
        ->and($row->attendance->recorded_by)->toBe($this->staff->id)->and($history->fresh())->not->toBeNull();
    $this->get(route('services.attendance.show', $this->occurrence))->assertRedirect(route('church.attendance', $event));
    $this->get(route('church.attendance', $event))->assertInertia(fn ($page) => $page
        ->where('counts.registered', 1)->where('counts.present', 1)->where('counts.unmarked', 0));
    $this->post(route('church.attendance.mark', [$event, $row]), ['status' => 'unmarked'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(Attendance::where('registration_id', $row->id)->exists())->toBeFalse()
        ->and($history->fresh()->getRawOriginal('attendance_status'))->toBe('present');
});

it('preserves Fellowship details, group assignments, and original attendance rows', function () {
    $legacy = FellowshipEvent::create([
        'fellowship_type' => FellowshipType::Family->value,
        'name' => 'Family Fellowship',
        'description' => 'Monthly gathering',
        'event_date' => today()->toDateString(),
        'venue' => 'Main Hall',
        'facilitator' => 'Ana Leader',
        'notes' => 'Bring food',
        'created_by' => $this->staff->id,
    ]);
    $group = FellowshipGroup::create([
        'fellowship_event_id' => $legacy->id,
        'name' => 'Parents',
        'display_order' => 1,
    ]);
    $person = Participant::create(['pool' => 'church', 'first_name' => 'Maria', 'last_name' => 'Santos']);
    $history = FellowshipAttendance::create([
        'fellowship_event_id' => $legacy->id,
        'participant_id' => $person->id,
        'fellowship_group_id' => $group->id,
        'attendance_status' => 'absent',
        'recorded_by' => $this->staff->id,
        'recorded_at' => now(),
        'notes' => 'Called ahead',
    ]);

    $event = app(RegistrationBridge::class)->activate($legacy);
    $registration = Registration::sole();
    expect($event->description)->toContain('Monthly gathering', 'Facilitator: Ana Leader', 'Bring food')
        ->and($registration->fellowship_group_id)->toBe($group->id)
        ->and($registration->attendance->getRawOriginal('attendance_status'))->toBe('absent')
        ->and($registration->attendance->notes)->toBe('Called ahead')
        ->and($history->fresh())->not->toBeNull();
});

it('keeps excused legacy attendance unmarked and records the prior status in notes', function () {
    $person = Participant::create(['pool' => 'church', 'first_name' => 'Jose', 'last_name' => 'Reyes']);
    ServiceAttendanceMember::create([
        'service_occurrence_id' => $this->occurrence->id,
        'participant_id' => $person->id,
        'attendance_status' => 'excused',
        'recorded_by' => $this->staff->id,
        'notes' => 'Medical reason',
    ]);

    app(RegistrationBridge::class)->activate($this->occurrence);
    $registration = Registration::sole();
    expect($registration->attendance)->toBeNull()
        ->and($registration->participant->serviceAttendanceMembers()->sole()->attendance_status)->toBe('excused');
});

it('rejects legacy Service and Fellowship writes after bridge activation', function () {
    app(RegistrationBridge::class)->activate($this->occurrence);
    $fellowship = FellowshipEvent::create([
        'fellowship_type' => FellowshipType::Family->value,
        'name' => 'Family Fellowship',
        'event_date' => today()->toDateString(),
        'created_by' => $this->staff->id,
    ]);
    $group = FellowshipGroup::create(['fellowship_event_id' => $fellowship->id, 'name' => 'Parents']);
    app(RegistrationBridge::class)->activate($fellowship);

    $this->post(route('services.attendance.store', ['occurrence' => $this->occurrence->id]), [
        'track' => 'member', 'first_name' => 'Juan', 'last_name' => 'Santos', 'age' => 30,
    ])->assertStatus(409);
    $this->post(route('fellowship.attendance.store', ['event' => $fellowship->id]), [
        'first_name' => 'Ana', 'last_name' => 'Santos',
    ])->assertStatus(409);
    $this->delete(route('services.occurrences.destroy', ['occurrence' => $this->occurrence->id]))->assertStatus(409);
    $this->put(route('fellowship.events.update', ['event' => $fellowship->id]), [
        'name' => 'Changed', 'event_date' => today()->toDateString(),
    ])->assertStatus(409);
    $this->delete(route('fellowship.groups.destroy', ['event' => $fellowship->id, 'group' => $group->id]))->assertStatus(409);
    $this->delete(route('fellowship.events.destroy', ['event' => $fellowship->id]))->assertStatus(409);

    expect(ServiceAttendanceMember::count())->toBe(0)->and(FellowshipAttendance::count())->toBe(0);
    expect(FellowshipGroup::whereKey($group->id)->exists())->toBeTrue()
        ->and($fellowship->fresh())->not->toBeNull()
        ->and($this->occurrence->fresh())->not->toBeNull();
});

it('rolls activation back rather than importing a running participant', function () {
    $person = Participant::create(['pool' => 'running', 'first_name' => 'Maria', 'last_name' => 'Santos']);
    ServiceAttendanceMember::create(['service_occurrence_id' => $this->occurrence->id, 'participant_id' => $person->id, 'attendance_status' => 'present']);
    expect(fn () => app(RegistrationBridge::class)->activate($this->occurrence))->toThrow(ValidationException::class);
    expect(Event::count())->toBe(0)->and(Registration::count())->toBe(0)
        ->and($this->occurrence->fresh()->getAttribute('registration_event_id'))->toBeNull();
});

it('excludes held registrations and prevents marking them or running records', function () {
    $event = app(RegistrationBridge::class)->activate($this->occurrence);
    $person = Participant::create(['pool' => 'church', 'first_name' => 'Maria', 'last_name' => 'Santos']);
    $held = Registration::create(['event_id' => $event->id, 'participant_id' => $person->id, 'source' => 'manual', 'registration_status' => 'held']);
    $this->get(route('church.attendance', $event))->assertInertia(fn ($page) => $page->where('counts.registered', 0)->has('rows', 0));
    $this->post(route('church.attendance.mark', [$event, $held]), ['status' => 'present'])->assertStatus(422);
    $event->update(['event_kind' => 'running']);
    $this->get(route('church.attendance', $event))->assertNotFound();
});

it('shows every active registration and event-wide totals in summary regardless of list filters', function () {
    $event = app(RegistrationBridge::class)->activate($this->occurrence);
    $presentPerson = Participant::create(['pool' => 'church', 'first_name' => 'Maria', 'last_name' => 'Santos']);
    $unmarkedPerson = Participant::create(['pool' => 'church', 'first_name' => 'Jose', 'last_name' => 'Reyes']);
    $present = Registration::create([
        'event_id' => $event->id,
        'participant_id' => $presentPerson->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
    ]);
    Registration::create([
        'event_id' => $event->id,
        'participant_id' => $unmarkedPerson->id,
        'source' => 'manual',
        'registration_status' => 'confirmed',
    ]);
    $present->attendance()->create(['attendance_status' => 'present']);

    $this->get(route('church.attendance', ['event' => $event, 'summary' => 1, 'filter' => 'present', 'q' => 'Maria']))
        ->assertInertia(fn ($page) => $page
            ->where('summary', true)
            ->where('counts.registered', 2)
            ->where('counts.present', 1)
            ->where('counts.unmarked', 1)
            ->has('rows', 2));
});

it('requires authenticated staff before activating or publishing a church event', function () {
    auth()->logout();
    $this->post(route('church.bridge.service', $this->occurrence))->assertRedirect(route('login'));

    $event = Event::create([
        'created_by' => $this->staff->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Fellowship',
        'church_status' => 'upcoming',
        'event_name' => 'Private church event',
    ]);
    $this->post(route('church.registration.publish', $event), ['open' => true])->assertRedirect(route('login'));

    expect($this->occurrence->fresh()->getAttribute('registration_event_id'))->toBeNull()
        ->and($event->fresh()->church_registration_open)->toBeFalse();
});
