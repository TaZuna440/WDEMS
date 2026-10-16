<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create discipleship_attendances.
     *
     * One row per enrollment per session. The UNIQUE on
     * (enrollment_id, session_id) makes re-marking idempotent --
     * a second upsert for the same cell updates the existing row
     * rather than creating a duplicate.
     *
     * enrollment_id, not participant_id. The enrollment is the
     * record of "this participant is pursuing this program" --
     * attendance is a fact about that enrollment's progress, not
     * about the participant directly. If a participant is
     * enrolled in two programs, their two enrollments have
     * independent attendance trails.
     *
     * Cascade on both parents. Deleting an enrollment or a
     * session removes its attendance rows. Attendance without a
     * parent is meaningless.
     *
     * recorded_by is nullable with nullOnDelete -- attendance can
     * survive a deleted user account, losing only the attribution.
     * Contrast with created_by on events/batches/sessions, where
     * RESTRICT is used. Attendance is factual data; the fact
     * outlives the recorder's account.
     *
     * status values come from DiscipleshipAttendanceStatus
     * (present, absent, excused). Column is 16 chars.
     *
     * Table name is plural, matching Eloquent's default
     * derivation from DiscipleshipAttendance.
     *
     * Phase 5E, per docs/church-events/07 section 3.6 (5E.1,
     * 5E.2).
     */
    public function up(): void
    {
        Schema::create('discipleship_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')
                ->constrained('discipleship_enrollments')
                ->cascadeOnDelete();
            $table->foreignId('session_id')
                ->constrained('discipleship_sessions')
                ->cascadeOnDelete();
            $table->string('status', 16);
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['enrollment_id', 'session_id'],
                'discipleship_attendances_unique',
            );

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_attendances');
    }
};
