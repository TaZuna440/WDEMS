<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create fellowship_attendances.
     *
     * One row per participant per fellowship event. Unlike Services,
     * which has two tracks (Members, First-Timers), Fellowship has
     * one unified track plus a nullable group assignment. The
     * "special feature" for Fellowship is per-group summary, not a
     * second track.
     *
     * Table name is plural to match the WDEMS convention (events,
     * participants, attendances, service_attendance_members, etc.)
     * and to match Eloquent's default derivation from the
     * FellowshipAttendance model. No $table override is needed on
     * the model.
     *
     * fellowship_group_id is nullable:
     *   - A participant may be marked present without a group.
     *   - If a group is deleted, its attendance rows survive with
     *     a NULL group pointer (nullOnDelete).
     *
     * UNIQUE on (fellowship_event_id, participant_id): a participant
     * cannot be marked twice for the same event.
     *
     * Cascade on event delete: attendance belongs to its event.
     * Cascade on participant delete: attendance is owned by the
     * participant.
     *
     * Phase 4.
     */
    public function up(): void
    {
        Schema::create('fellowship_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fellowship_event_id')
                ->constrained('fellowship_events')
                ->cascadeOnDelete();
            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();
            $table->foreignId('fellowship_group_id')
                ->nullable()
                ->constrained('fellowship_groups')
                ->nullOnDelete();
            $table->string('attendance_status', 16);
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['fellowship_event_id', 'participant_id'],
                'fellowship_attendances_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fellowship_attendances');
    }
};
