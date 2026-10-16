<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create discipleship_batches.
     *
     * A batch is one cohort of a program (e.g. "School of Leaders 1
     * — 2026 Spring"). Batches belong to exactly one program and
     * cascade-delete with it.
     *
     * start_date is required. end_date is not modelled at 5B -- if
     * the client needs an explicit end date later, add a migration.
     * Duration is derivable from the sessions (5D).
     *
     * No unique constraint on (program_id, name). Two batches can
     * share a name within a program: "Spring Cohort 2026" and
     * "Spring Cohort 2027" are legitimately distinct. If duplicate
     * names become a data-quality problem, add the constraint then.
     *
     * Index on (program_id, start_date) -- how the per-program view
     * lists batches, chronological.
     *
     * created_by matches the codebase convention for user-created
     * records (ServiceOccurrence, FellowshipEvent). RESTRICT on
     * delete so a deleted user account does not silently orphan
     * historical batches.
     *
     * Table name is plural, matching Eloquent's default derivation
     * from DiscipleshipBatch.
     *
     * Phase 5B, per docs/church-events/07 section 3.6 (5B.1).
     */
    public function up(): void
    {
        Schema::create('discipleship_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained('discipleship_programs')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->date('start_date');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['program_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_batches');
    }
};
