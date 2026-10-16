<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create service_occurrences.
     *
     * One row per service date. service_type is one of
     * ServiceType::values(): 'morning', 'midweek', 'youth', 'other'.
     *
     * Occurrences are created two ways:
     *   1. Manual: organizer clicks "+ Add Record" and enters a date
     *      and time. One row per submission.
     *   2. Recurring: organizer defines a schedule (weekly or
     *      fortnightly, day of week, time range) and the system
     *      generates a batch of rows. Each row is independent after
     *      creation; there is no series concept in Phase 3.
     *
     * Phase 3, per client mockup.
     */
    public function up(): void
    {
        Schema::create('service_occurrences', function (Blueprint $table) {
            $table->id();
            $table->string('service_type', 32);
            $table->date('occurrence_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('service_leader', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['service_type', 'occurrence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_occurrences');
    }
};
