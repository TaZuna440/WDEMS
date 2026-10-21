<?php

// Read-only audit. Outputs IDs and aggregate counts, never contact details.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Models\Event;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$churchTables = ['members', 'first_timers', 'service_attendance_members', 'service_attendance_first_timers', 'fellowship_attendances', 'discipleship_enrollments'];
$church = [];
$activity = [];
foreach ($churchTables as $table) {
    $ids = DB::table($table)->pluck('participant_id')->all();
    $church = array_merge($church, $ids);
    if (! in_array($table, ['members', 'first_timers'])) {
        $activity = array_merge($activity, $ids);
    }
}
$registrations = DB::table('registrations as r')->join('events as e', 'e.id', '=', 'r.event_id')->join('participants as p', 'p.id', '=', 'r.participant_id')->get(['r.id', 'r.participant_id', 'e.event_kind', 'p.pool']);
$running = $registrations->where('event_kind', 'running')->pluck('participant_id')->all();
$church = array_unique(array_merge($church, $registrations->where('event_kind', 'church')->pluck('participant_id')->all()));
$both = array_values(array_intersect(array_unique($running), $church));
$wrong = $registrations->filter(fn ($r) => $r->event_kind !== $r->pool)->pluck('id')->values()->all();
$wrongChurch = [];
foreach ($churchTables as $table) {
    $wrongChurch[$table] = DB::table($table.' as c')->join('participants as p', 'p.id', '=', 'c.participant_id')->where('p.pool', '!=', 'church')->count();
}
$standalone = DB::table('participants')->whereIn('id', $church)->whereNotIn('id', array_unique(array_merge($activity, $registrations->where('event_kind', 'church')->pluck('participant_id')->all())))->where('pool', '!=', 'church')->pluck('id')->all();
$forms = Event::query()->whereNotNull('registration_form_saved_at')->whereIn('status', ['registration_open', 'registration_closed', 'ongoing'])->get()->filter(fn ($e) => ! $e->usesEmailOnlyRegistration() && $e->isCommonFieldRequired('contact_number'))->map(fn ($e) => ['id' => $e->id, 'name' => $e->event_name, 'status' => $e->status->value])->values()->all();
echo json_encode([
    'participants' => DB::table('participants')->count(), 'registrations' => DB::table('registrations')->count(),
    'attendances' => DB::table('attendances')->count(),
    'shared_participants' => ['count' => count($both), 'example_ids' => array_slice($both, 0, 10)],
    'wrong_pool_registrations' => ['count' => count($wrong), 'example_ids' => array_slice($wrong, 0, 10)],
    'wrong_pool_attendances' => DB::table('attendances')->whereIn('registration_id', $wrong)->count(),
    'wrong_pool_church_references' => $wrongChurch,
    'standalone_church_wrong_pool' => ['count' => count($standalone), 'example_ids' => array_slice($standalone, 0, 10)],
    'live_phone_forms' => ['count' => count($forms), 'events' => $forms],
    'pending_phone_challenges' => DB::table('registration_verification_challenges')->where('phone_required', true)->whereNull('finalized_at')->where('expires_at', '>', now())->count(),
], JSON_PRETTY_PRINT).PHP_EOL;
