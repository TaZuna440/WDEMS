<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create discipleship_sessions.
     *
     * One row per meeting of a batch (e.g. "Week 1 -- Welcome and
     * Orientation"). Sessions belong to exactly one batch and
     * cascade-delete with it.
     *
     * session_date is required. The task list (5D.1) names only
     * batch_id, date, topic -- notes is added here for parity with
     * every other record type in the codebase, which all carry a
     * free-form notes field.
     *
     * No session_number column. Session order is derivable from
     * session_date; if two sessions share a date, id breaks the
     * tie. Same reasoning as batches: no artificial ordinal that
     * has to be kept in sync with reality.
     *
     * No unique on (batch_id, session_date). Two sessions can
     * legitimately happen in one day (a morning and an evening
     * gathering). If duplicate-day enforcement becomes a data
     * quality need, add it then.
     *
     * created_by matches the codebase convention for user-created
     * records. RESTRICT so a deleted user does not silently
     * orphan historical sessions.
     *
     * Index on (batch_id, session_date) -- how the batch detail
     * page lists sessions, chronological.
     *
     * Table name is plural, matching Eloquent's default derivation
     * from DiscipleshipSession.
     *
     * Phase 5D, per docs/church-events/07 section 3.6 (5D.1).
     */
    public function up(): void
    {
        Schema::create('discipleship_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('discipleship_batches')
                ->cascadeOnDelete();
            $table->date('session_date');
            $table->string('topic', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_sessions');
    }
};
