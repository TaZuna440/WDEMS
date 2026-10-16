<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create fellowship_events.
     *
     * One row per Fellowship gathering. The fellowship_type is one
     * of FellowshipType::values(): family, church, pastors, leaders,
     * womens, mens.
     *
     * Groups per event are in fellowship_groups. Attendance per
     * participant per event is in fellowship_attendance.
     *
     * Per client mockup: the sidebar of the Fellowship page is a
     * type selector; picking a type shows that type's events. Each
     * event has its own set of groups (Parents, Youth, Children,
     * Other Participants).
     *
     * Phase 4, per doc 07 section 3.5.
     */
    public function up(): void
    {
        Schema::create('fellowship_events', function (Blueprint $table) {
            $table->id();
            $table->string('fellowship_type', 32);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue', 255)->nullable();
            $table->string('facilitator', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['fellowship_type', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fellowship_events');
    }
};
