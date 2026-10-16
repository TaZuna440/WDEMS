<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add completion criteria columns to discipleship_programs.
     *
     * required_sessions: minimum attended sessions for the
     * enrollment to be considered complete. Nullable -- a null
     * value means "no fixed requirement," and completion falls
     * back to attended == applicable (100% of sessions that
     * happened after enrollment). The seeder does not set a
     * value; the client confirms per-program thresholds later.
     *
     * extra_requirements: an optional JSON array of
     * {label, met_at?} objects for requirements beyond the
     * session count (e.g. "Attend a retreat", "Pass an
     * interview"). Stored but not enforced by this phase --
     * 5F.2's service reads it. Nullable.
     *
     * Both additive. No backfill. Existing rows keep their
     * nulls, which read correctly as "criteria not yet set."
     *
     * Phase 5F, per docs/church-events/07 section 3.6 (5F.2,
     * 5F.3).
     */
    public function up(): void
    {
        Schema::table('discipleship_programs', function (Blueprint $table) {
            $table->integer('required_sessions')->nullable()->after('display_order');
            $table->json('extra_requirements')->nullable()->after('required_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('discipleship_programs', function (Blueprint $table) {
            $table->dropColumn(['required_sessions', 'extra_requirements']);
        });
    }
};
