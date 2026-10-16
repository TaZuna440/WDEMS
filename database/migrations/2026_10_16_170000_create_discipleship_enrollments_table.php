<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create discipleship_enrollments.
     *
     * One row per participant per program. The UNIQUE on
     * (participant_id, program_id) is total, not partial -- MySQL 8
     * does not support filtered unique indexes. Withdrawn and
     * on_hold are statuses, not deletions. Re-enrollment resets
     * the status on the same row; a participant cannot hold two
     * enrollment rows for the same program.
     *
     * batch_id is the "batch pointer" -- it records which cohort
     * the participant is currently in. It is nullable for two
     * reasons:
     *   1. A participant may enroll at the program level before a
     *      specific batch is chosen.
     *   2. A deleted batch leaves its enrollments in place with a
     *      NULL pointer (nullOnDelete). The organizer can reassign
     *      them without losing the enrollment.
     *
     * status values come from DiscipleshipEnrollmentStatus. The
     * column is 24 chars, sized for 'in_progress' (11) and
     * 'withdrawn' (9) with headroom.
     *
     * enrolled_at records when the participant joined. Kept
     * separate from created_at because the two can diverge -- a
     * retroactive enrollment might be recorded later than it
     * began.
     *
     * created_by matches the codebase convention for user-created
     * records. RESTRICT so a deleted user does not silently
     * orphan historical enrollments.
     *
     * Table name is plural, matching Eloquent's default derivation
     * from DiscipleshipEnrollment.
     *
     * Phase 5C, per docs/church-events/07 section 3.6 (5C.1,
     * 5C.3).
     */
    public function up(): void
    {
        Schema::create('discipleship_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();
            $table->foreignId('program_id')
                ->constrained('discipleship_programs')
                ->cascadeOnDelete();
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('discipleship_batches')
                ->nullOnDelete();
            $table->string('status', 24);
            $table->timestamp('enrolled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['participant_id', 'program_id'],
                'discipleship_enrollments_unique',
            );

            $table->index(['program_id', 'status']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_enrollments');
    }
};
