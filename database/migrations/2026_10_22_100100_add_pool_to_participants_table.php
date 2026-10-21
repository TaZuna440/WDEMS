<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('pool', 20)->default('running')->after('provisional_event_id')->index();
        });

        // Backfill: participants linked to church activities belong to the church pool
        DB::table('participants')
            ->whereIn('id', function ($query) {
                $query->select('participant_id')->from('service_attendance_members');
            })
            ->orWhereIn('id', function ($query) {
                $query->select('participant_id')->from('service_attendance_first_timers');
            })
            ->orWhereIn('id', function ($query) {
                $query->select('participant_id')->from('fellowship_attendances');
            })
            ->orWhereIn('id', function ($query) {
                $query->select('participant_id')->from('discipleship_enrollments');
            })
            ->orWhereIn('id', function ($query) {
                $query->select('participant_id')->from('registrations')
                    ->join('events', 'events.id', '=', 'registrations.event_id')
                    ->where('events.event_kind', 'church');
            })
            ->update(['pool' => 'church']);
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('pool');
        });
    }
};
