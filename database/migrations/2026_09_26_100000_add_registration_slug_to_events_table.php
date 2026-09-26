<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add events.registration_slug.
     *
     * Set once, inside EventController::openRegistration(), on the
     * same write that stamps registration_start. Before that moment
     * the event is Draft and has no public URL. After, the slug is the
     * event's public identifier at /r/{slug}.
     *
     * Format: 8 characters from a 31-character alphabet that excludes
     * the confusables I, L, O, 0, 1. 31^8 ≈ 8.5×10^11 possible slugs.
     * Readable over the phone, paste-able in chat. The generation loop
     * in EventController retries up to 3 times on collision before
     * throwing — the probability is negligible but the loop makes the
     * failure mode explicit rather than silent.
     *
     * Nullable because Draft events have no slug. There is no code
     * path that clears the slug once set — D6 of the Phase 3 plan
     * locks the form permanently at Open Registration.
     *
     * The unique index is on the slug alone, not (slug, event_id).
     * The public route uses implicit model binding on registration_slug
     * and requires a global namespace.
     *
     * Placed after registration_form_saved_at so the events table's
     * registration columns stay grouped: registration_start,
     * registration_end, registration_form_saved_at, registration_slug.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('registration_slug', 32)
                ->nullable()
                ->unique()
                ->after('registration_form_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['registration_slug']);
            $table->dropColumn('registration_slug');
        });
    }
};
