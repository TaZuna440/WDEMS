<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create service_attendance_members.
     *
     * One row per participant per service occurrence, for the
     * Members track. The mockup shows Members as a distinct tab
     * from First-Timers; this table stores the Members side.
     *
     * attendance_status is one of: 'present', 'absent', 'excused'.
     * Stored as a string, not an enum, so the allowed set can grow
     * without a schema change.
     *
     * Unique on (service_occurrence_id, participant_id) — a
     * participant cannot be marked twice for the same service.
     *
     * Cascade on occurrence delete: attendance is meaningless
     * without the service. Cascade on participant delete: the
     * participant record owns this attendance.
     *
     * Phase 3, per client mockup.
     */
    public function up(): void
    {
        Schema::create('service_attendance_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_occurrence_id')
                ->constrained('service_occurrences')
                ->cascadeOnDelete();
            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();
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
                'svc_attend_members_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_attendance_members');
    }
};
