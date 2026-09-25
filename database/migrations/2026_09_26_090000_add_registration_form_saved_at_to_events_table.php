<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add events.registration_form_saved_at.
     *
     * NULL means the registration form has never been saved for this
     * event. A timestamp means it was saved at least once. The show
     * page's Open Registration action requires a non-null value —
     * registration cannot be published for an event with no form.
     *
     * The column is NOT backfilled. Events that had a form saved before
     * this migration have no way to prove it after the fact. They read
     * as "never saved" until the organizer opens the form builder and
     * clicks Save once more.
     *
     * ============================================================
     * KNOWN RISK — RECORDED HERE FOR FUTURE READERS
     * ============================================================
     *
     * Any Draft event whose registration form was saved BEFORE this
     * migration lands will be locked out of Open Registration until the
     * form is saved again through the UI. The event itself is fine; the
     * guard just doesn't know a form exists.
     *
     * The alternative — backfilling every Draft event with a non-null
     * timestamp — would set the timestamp on events whose form
     * genuinely does not exist. That defeats the gate.
     *
     * If a future deployment needs to backfill — for example, a data
     * restoration that precedes this migration — do so with an explicit
     * UPDATE against the events table, NOT by editing this migration.
     * Migrations are append-only; this one records the choice made at
     * the time, not the choice that should be made later.
     *
     * See docs/progress.md Phase 2 close entry for the full record.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('registration_form_saved_at')
                ->nullable()
                ->after('registration_end');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('registration_form_saved_at');
        });
    }
};
