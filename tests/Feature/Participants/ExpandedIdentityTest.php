<?php

use App\Models\Participant;
use Illuminate\Support\Facades\DB;

test('full name identity normalizes case and whitespace while distinguishing optional name parts', function () {
    $attributes = ['first_name' => 'Juan', 'last_name' => 'Santos', 'middle_name' => 'De  Leon', 'suffix' => 'Jr.'];
    $first = Participant::resolveFrom('running', 'family@example.com', null, $attributes);
    $same = Participant::resolveFrom('running', 'family@example.com', null, array_merge($attributes, ['middle_name' => ' DE LEON ', 'suffix' => 'JR.']));
    $senior = Participant::resolveFrom('running', 'family@example.com', null, array_merge($attributes, ['suffix' => 'Sr.']));
    $otherMiddle = Participant::resolveFrom('running', 'family@example.com', null, array_merge($attributes, ['middle_name' => 'Reyes']));
    $missing = Participant::resolveFrom('running', 'family@example.com', null, ['first_name' => 'Juan', 'last_name' => 'Santos']);
    $blank = Participant::resolveFrom('running', 'family@example.com', null, ['first_name' => 'Juan', 'last_name' => 'Santos', 'middle_name' => ' ', 'suffix' => '']);

    expect($same->id)->toBe($first->id)
        ->and($senior->id)->not->toBe($first->id)
        ->and($otherMiddle->id)->not->toBe($first->id)
        ->and($missing->id)->not->toBe($first->id)
        ->and($blank->id)->toBe($missing->id)
        ->and(Participant::count())->toBe(4);
});

test('expanded identity migration backfills existing display names', function () {
    $person = Participant::create(['pool' => 'running', 'first_name' => 'Juan', 'last_name' => 'Santos', 'middle_name' => 'De Leon', 'suffix' => 'Jr.']);
    $migration = require database_path('migrations/2026_10_20_100000_expand_participant_name_identity.php');
    $migration->down();
    DB::table('participants')->where('id', $person->id)->update(['first_name_normalized' => null, 'last_name_normalized' => null]);
    $migration->up();

    $row = DB::table('participants')->where('id', $person->id)->first();
    expect($row->first_name_normalized)->toBe('juan')
        ->and($row->last_name_normalized)->toBe('santos')
        ->and($row->middle_name_normalized)->toBe('de leon')
        ->and($row->suffix_normalized)->toBe('jr.');
});
