<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;

test('attendance conversion changes only running event records and preserves attendance times', function () {
    $user = User::factory()->create();
    $rows = [];
    foreach (['running', 'church'] as $kind) {
        $event = Event::create(['created_by' => $user->id, 'event_kind' => $kind, 'event_type' => 'community_run', 'event_name' => 'Status Migration', 'status' => 'ongoing']);
        foreach (['late', 'excused'] as $status) {
            $participant = Participant::create(['first_name' => 'Maria', 'last_name' => 'Santos']);
            $registration = Registration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'registration_date' => now(), 'source' => 'form', 'registration_status' => 'confirmed']);
            $rows[$kind][$status] = Attendance::create(['registration_id' => $registration->id, 'attendance_status' => $status, 'attendance_time' => '2026-10-20 06:30:00', 'recorded_by' => $user->id]);
        }
    }
    $migration = require database_path('migrations/2026_10_20_100200_simplify_running_attendance_statuses.php');
    $migration->up();
    expect($rows['running']['late']->fresh()->attendance_status->value)->toBe('present')
        ->and($rows['running']['excused']->fresh()->attendance_status->value)->toBe('absent')
        ->and($rows['church']['late']->fresh()->attendance_status->value)->toBe('late')
        ->and($rows['church']['excused']->fresh()->attendance_status->value)->toBe('excused')
        ->and($rows['running']['late']->fresh()->attendance_time->format('H:i:s'))->toBe('06:30:00');
});
