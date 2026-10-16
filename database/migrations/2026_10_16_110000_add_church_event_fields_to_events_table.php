<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add church-event columns to the events table.
     *
     * Three new columns:
     *
     *   event_kind       Discriminator. 'running' or 'church'.
     *                    Default 'running' for existing rows.
     *                    Indexed for fast filtering.
     *
     *   church_category  The church category (Special Occasions,
     *                    Youth, Fellowship, etc.). String for
     *                    now; becomes a FK to event_categories
     *                    in a later phase.
     *
     *   church_status    One of ChurchEventStatus::values().
     *                    Null for running events. Set for church
     *                    events only.
     *
     * Per doc 06 sub-decisions Q5, Q6 (2026-10-16).
     *
     * The running-event `status` column is untouched. Running
     * events continue to use EventStatus; church events use
     * church_status. The two are independent.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_kind', 16)
                ->default('running')
                ->after('created_by');

            $table->string('church_category', 64)
                ->nullable()
                ->after('event_kind');

            $table->string('church_status', 32)
                ->nullable()
                ->after('church_category');

            $table->index('event_kind');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['event_kind']);
            $table->dropColumn(['event_kind', 'church_category', 'church_status']);
        });
    }
};
