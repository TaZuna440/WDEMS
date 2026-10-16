<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create service_attendance_first_timers.
     *
     * One row per first-timer per service occurrence. The First-
     * Timers track is distinct from Members: a first-timer is
     * someone attending a service for the first time, tracked
     * separately until an organizer promotes them to membership.
     *
     * invited_by is the field unique to this track (from the
     * mockup: "Invited By"). Name, age, birthday, address, and
     * contact live on the participant record.
     *
     * attendance_status is one of: 'present', 'absent', 'excused'.
     *
     * Unique on (service_occurrence_id, participant_id): a
     * participant cannot be marked twice for the same service on
     * the first-timers track.
     *
     * Controller-level constraint: a participant cannot appear on
     * both the Members and First-Timers tracks for the same
     * occurrence. Enforced in the controller, not here — a cross-
     * table uniqueness check is not expressible as a single index.
     *
     * Phase 3, per client mockup.
     */
    public function up(): void
    {
        Schema::create('service_attendance_first_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_occurrence_id')
                ->constrained('service_occurrences')
                ->cascadeOnDelete();
            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();
            $table->string('invited_by', 255)->nullable();
            $table->string('attendance_status', 16);
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['service_occurrence_id', 'participant_id'],
                'svc_attend_first_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_attendance_first_timers');
    }
};
