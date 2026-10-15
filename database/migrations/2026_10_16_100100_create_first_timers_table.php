<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the first_timers table.
     *
     * First-visit record attached to an existing participant. A
     * participant may accumulate multiple first_timer records over
     * time (a first-timer who lapses and returns later), so
     * participant_id is NOT unique.
     *
     * Per docs/church-events/06 sub-decisions Q3a + Q3b (2026-10-16).
     *
     * Promotion: when a first-timer is confirmed as a member, the
     * promotion service creates a members row for the same
     * participant and fills promoted_at + promoted_to_member_id on
     * this record. The first_timers row is never deleted -- it stays
     * as a historical record of the first visit.
     *
     * promoted_to_member_id uses nullOnDelete so that deleting a
     * member does not remove the first-visit history. The record
     * survives with a null pointer.
     */
    public function up(): void
    {
        Schema::create('first_timers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();

            $table->date('first_service_date')->nullable();
            $table->string('invited_by', 255)->nullable();
            $table->text('follow_up_notes')->nullable();

            $table->timestamp('promoted_at')->nullable();

            $table->foreignId('promoted_to_member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('promoted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('first_timers');
    }
};
