<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('registrations', 'duplicate_of_registration_id')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->foreignId('duplicate_of_registration_id')->nullable()->constrained('registrations')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('registration_review_resolutions')) {
            Schema::create('registration_review_resolutions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registration_id')->index();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 40);
                $table->text('reason');
                $table->json('before_snapshot');
                $table->json('after_snapshot');
                $table->timestamp('created_at');
            });
        }
        // Preserve original records and every history row. A shared record keeps
        // its running ID; only its church references move to a separate copy.
        // After a successful split it is no longer shared, making retries safe.
        DB::transaction(function () {
            $churchTables = ['members', 'first_timers', 'service_attendance_members', 'service_attendance_first_timers', 'fellowship_attendances', 'discipleship_enrollments'];
            $runningEvents = DB::table('events')->where('event_kind', 'running')->pluck('id');
            $churchEvents = DB::table('events')->where('event_kind', 'church')->pluck('id');
            foreach (DB::table('participants')->orderBy('id')->get() as $person) {
                $running = DB::table('registrations')->where('participant_id', $person->id)->whereIn('event_id', $runningEvents)->exists();
                $church = DB::table('registrations')->where('participant_id', $person->id)->whereIn('event_id', $churchEvents)->exists();
                foreach ($churchTables as $table) {
                    $church = $church || DB::table($table)->where('participant_id', $person->id)->exists();
                }
                if ($running && $church) {
                    $copy = (array) $person;
                    unset($copy['id']);
                    $copy['pool'] = 'church';
                    $copy['provisional_event_id'] = null;
                    $churchId = DB::table('participants')->insertGetId($copy);
                    foreach ($churchTables as $table) {
                        DB::table($table)->where('participant_id', $person->id)->update(['participant_id' => $churchId]);
                    }
                    DB::table('registrations')->where('participant_id', $person->id)->whereIn('event_id', $churchEvents)->update(['participant_id' => $churchId]);
                }
                if ($running || $church) {
                    DB::table('participants')->where('id', $person->id)->update(['pool' => $running ? 'running' : 'church']);
                }
            }
            // Repair resolutions already made by the interrupted implementation.
            DB::table('registrations')->where('resolution_action', 'confirm_duplicate')->where('registration_status', 'cancelled')
                ->update(['registration_status' => 'duplicate', 'duplicate_of_registration_id' => DB::raw('held_candidate_registration_id')]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This history-preserving repair cannot be reversed by merging participant pools.');
    }
};
