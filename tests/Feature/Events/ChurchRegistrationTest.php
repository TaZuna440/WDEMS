<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'admin', 'email_two_factor_enabled' => false]);
    $this->actingAs($this->staff);
    $this->event = Event::create(['created_by' => $this->staff->id, 'event_kind' => 'church', 'event_type' => 'church', 'church_category' => 'Kids Ministry', 'church_status' => 'upcoming', 'event_name' => 'Kids gathering']);
    $this->url = "/events/{$this->event->id}/registrations";
    $this->data = ['first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com', 'participant_type' => 'Guest', 'birthday' => '2010-01-01', 'guardian_name' => 'Jose Santos', 'guardian_contact' => '09171234567'];
});

it('serves the church registration page and records staff attestation and formatted code', function () {
    $this->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('duplicates_url', route('events.possible-duplicates', $this->event))
            ->where('attendance_url', route('church.attendance', $this->event)));
    $this->get("/events/{$this->event->id}/church-attendance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('urls.registrations', route('church.registrations.index', $this->event))
            ->where('urls.attendance', route('church.attendance', $this->event))
            ->where('urls.reports', route('reports.index', ['kind' => 'church'])));
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $row = Registration::sole();
    expect($row->participant->pool)->toBe('church')->and($row->encoded_by)->toBe($this->staff->id)
        ->and($row->registered_at)->not->toBeNull()->and($row->contact_attested_at)->not->toBeNull()
        ->and($row->attendance_code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/')->and($row->attendance)->toBeNull();
});

it('blocks exact and repeat submissions and allows clearly different family members', function () {
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $this->post($this->url, $this->data)->assertSessionHasErrors('identity');
    $this->post($this->url, [...$this->data, 'first_name' => 'Jose'])->assertSessionHasNoErrors();
    expect(Registration::active()->count())->toBe(2)->and(Participant::count())->toBe(2);
});

it('holds additives and allows church review without crossing pools', function () {
    $this->post($this->url, [...$this->data, 'middle_name' => 'Reyes'])->assertSessionHasNoErrors();
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $held = Registration::held()->sole();
    expect(Registration::active()->count())->toBe(1)->and($held->attendance)->toBeNull();
    $this->get("/events/{$this->event->id}/possible-duplicates")->assertOk();
    $this->post("/events/{$this->event->id}/possible-duplicates/{$held->id}/resolve", ['action' => 'confirm_separate'])->assertSessionHasNoErrors();
    expect($held->fresh()->isActive())->toBeTrue();
});

it('reuses the church person across events and ignores the same running identity', function () {
    Participant::resolveFrom('running', 'maria@example.com', null, ['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $other = $this->event->replicate();
    $other->save();
    $this->post("/events/{$other->id}/registrations", $this->data)->assertSessionHasNoErrors();
    expect(Participant::count())->toBe(2)->and(Registration::pluck('participant_id')->unique())->toHaveCount(1);
});

it('does not expose a running-pool participant in church registrations', function () {
    $runningParticipant = Participant::create([
        'pool' => 'running',
        'first_name' => 'Running',
        'last_name' => 'Participant',
    ]);
    $this->event->registrations()->create([
        'participant_id' => $runningParticipant->id,
        'registration_status' => 'confirmed',
        'source' => 'manual',
    ]);

    $this->get($this->url)->assertInertia(fn ($page) => $page
        ->where('registrations', []));
});

it('edits identity and per-event details without rewriting another registration', function () {
    $this->post($this->url, $this->data);
    $row = Registration::sole();
    $original = $row->participant;
    $this->patch("{$this->url}/{$row->id}", [...$this->data, 'first_name' => 'Jose', 'participant_type' => 'Member'])->assertSessionHasNoErrors();
    expect($row->fresh()->participant->first_name)->toBe('Jose')->and($original->fresh()->first_name)->toBe('Maria')
        ->and($row->fresh()->participant_type)->toBe('Member');
});

it('rejects edit collisions and preserves attendance on rejected corrections', function () {
    $this->post($this->url, $this->data);
    $this->post($this->url, [...$this->data, 'first_name' => 'Jose']);
    $row = Registration::latest('id')->first();
    $row->attendance()->create(['attendance_status' => 'present']);
    $this->patch("{$this->url}/{$row->id}", $this->data)->assertSessionHasErrors('identity');
    expect($row->fresh()->participant->first_name)->toBe('Jose')->and($row->fresh()->attendance)->not->toBeNull();
});

it('deletes attendance and registration but keeps the person', function () {
    $this->post($this->url, $this->data);
    $row = Registration::sole();
    $row->attendance()->create(['attendance_status' => 'present']);
    $this->delete("{$this->url}/{$row->id}")->assertSessionHasNoErrors();
    expect(Registration::count())->toBe(0)->and(Attendance::count())->toBe(0)->and(Participant::count())->toBe(1);
});

it('allows contactless staff registration without name-only identity matching', function () {
    $data = [...$this->data, 'email' => null];
    $this->post($this->url, $data)->assertSessionHasNoErrors();
    $this->post($this->url, $data)->assertSessionHasNoErrors();
    expect(Participant::count())->toBe(2);
});

it('rejects invalid names and cross-event or running registration access', function () {
    $this->post($this->url, [...$this->data, 'first_name' => '1234'])->assertSessionHasErrors('first_name');
    $this->post($this->url, $this->data);
    $row = Registration::sole();
    $other = $this->event->replicate();
    $other->save();
    $this->patch("/events/{$other->id}/registrations/{$row->id}", $this->data)->assertNotFound();
    $this->delete("/events/{$other->id}/registrations/{$row->id}")->assertNotFound();
    $other->update(['event_kind' => 'running']);
    $this->post("/events/{$other->id}/registrations", $this->data)->assertNotFound();
});
