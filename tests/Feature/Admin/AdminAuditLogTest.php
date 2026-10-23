<?php

use App\Mail\RegistrationConfirmationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('allows only admins to access the audit log', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($staff)->get('/admin/audit-log')->assertForbidden();
    $this->actingAs($admin)->get('/admin/audit-log')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/audit-log/index'));
});

it('records event creation in the audit log', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('events.store'), [
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Services',
        'church_status' => 'upcoming',
        'event_name' => 'Audit Test Service',
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'venue' => 'Main Hall',
        'venue_address' => '1 Main Street',
    ])->assertRedirect(route('events.index'));

    $event = Event::where('event_name', 'Audit Test Service')->firstOrFail();

    expect(AuditLog::query()
        ->where('action', 'event.created')
        ->where('user_id', $admin->id)
        ->where('target_type', Event::class)
        ->where('target_id', $event->id)
        ->where('details->event_name', 'Audit Test Service')
        ->exists())->toBeTrue();
});

it('records event deletion in the audit log', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'created_by' => $admin->id,
        'event_kind' => 'church',
        'event_type' => 'church',
        'church_category' => 'Services',
        'church_status' => 'upcoming',
        'event_name' => 'Deleted Service',
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '10:00',
        'venue' => 'Main Hall',
        'venue_address' => '1 Main Street',
        'status' => 'draft',
    ]);

    $this->actingAs($admin)
        ->delete(route('events.destroy', $event))
        ->assertRedirect(route('events.index'));

    expect(AuditLog::query()
        ->where('action', 'event.deleted')
        ->where('user_id', $admin->id)
        ->where('target_type', Event::class)
        ->where('target_id', $event->id)
        ->where('details->event_name', 'Deleted Service')
        ->exists())->toBeTrue();
});

it('records sent mail as a system audit action', function () {
    config(['mail.default' => 'array']);

    Mail::to('participant@example.com')->send(new RegistrationConfirmationMail('KM4X-P2N7', 'Test Event'));

    expect(AuditLog::query()
        ->where('actor_type', 'system')
        ->whereNull('user_id')
        ->where('action', 'email.sent')
        ->where('details->to', 'like', '%participant@example.com%')
        ->where('details->subject', 'WDEMS — Your Test Event Registration Code')
        ->exists())->toBeTrue();
});

it('logs system actions with a null user and system actor type', function () {
    $log = app(AuditLogger::class)->logSystem('event.auto_advanced', [
        'event_name' => 'Auto-advanced Run',
        'new_status' => 'ongoing',
    ]);

    expect($log->user_id)->toBeNull()
        ->and($log->actor_type)->toBe('system')
        ->and($log->action)->toBe('event.auto_advanced')
        ->and($log->details)->toBe(['event_name' => 'Auto-advanced Run', 'new_status' => 'ongoing']);
});

it('filters audit entries by action, user, and search query', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'user.registered',
        'details' => ['email' => 'filter-me@example.test'],
    ]);
    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'event.created',
        'details' => ['event_name' => 'Another action'],
    ]);

    $this->actingAs($admin)
        ->get('/admin/audit-log?action=user.registered&user_id='.$admin->id.'&search=filter-me')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.action', 'user.registered')
            ->where('filters.user_id', (string) $admin->id));
});

it('filters audit entries by actor type', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    AuditLog::create([
        'actor_type' => 'user',
        'user_id' => $admin->id,
        'action' => 'event.created',
    ]);
    app(AuditLogger::class)->logSystem('email.sent', ['to' => ['participant@example.com']]);

    $this->actingAs($admin)
        ->get('/admin/audit-log?actor_type=system')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.actor_type', 'system')
            ->where('logs.data.0.action', 'email.sent')
            ->where('filters.actor_type', 'system'));
});
