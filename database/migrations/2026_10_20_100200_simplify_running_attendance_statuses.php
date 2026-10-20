<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $registrations = DB::table('registrations')->select('registrations.id')
            ->join('events', 'events.id', '=', 'registrations.event_id')
            ->where('events.event_kind', 'running');

        DB::table('attendances')->whereIn('registration_id', clone $registrations)
            ->where('attendance_status', 'late')->update(['attendance_status' => 'present']);
        DB::table('attendances')->whereIn('registration_id', $registrations)
            ->where('attendance_status', 'excused')->update(['attendance_status' => 'absent']);
    }

    public function down(): void
    {
        // Deliberately irreversible: a Present/Absent record cannot tell us
        // whether it used to be Late/Excused. Never invent historical states.
    }
};
