<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 of docs/participant-identity-and-monitoring-plan.md.
 *
 * Adds the two columns behind the monitor's actions menu:
 *
 *   - `flagged_at` — timestamp. Non-null means the organizer flagged
 *     the registration for review. Toggling clear sets it back to
 *     null. The Flagged filter chip in the monitor reads this.
 *
 *   - `notes` — free-form text, capped at 2000 characters by the
 *     request class that writes it. Nullable. No default.
 *
 * Neither column is indexed. The Flagged filter is a client-side
 * count over the feed's 100-row window; a full feed-wide filter by
 * flagged state is a future concern if the volume warrants it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('flagged_at')->nullable()->after('registered_at');
            $table->text('notes')->nullable()->after('flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['flagged_at', 'notes']);
        });
    }
};
