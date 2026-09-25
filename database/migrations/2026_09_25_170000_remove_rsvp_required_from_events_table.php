<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the rsvp_required column.
     *
     * The column modeled a boolean that toggled whether participants
     * were required to register before the event. Community Run now
     * supports both pre-registration and walk-in paths unconditionally,
     * so the flag no longer decides anything. Removing the column
     * rather than leaving it nullable-and-meaningless keeps the schema
     * honest — a future reader will not wonder which value was the
     * "not yet configured" state.
     *
     * The reverse migration re-adds the column in the same position and
     * with the same default as the original add-column migration
     * (2026_09_21_093134_add_event_metadata_to_events_table.php).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('rsvp_required');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('rsvp_required')
                ->default(false)
                ->after('registration_end');
        });
    }
};
