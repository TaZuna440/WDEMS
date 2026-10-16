<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add birthday to participants.
     *
     * The Services mockup shows Birthday as an entry field on both
     * the Members and First-Timers attendance tabs. Participants is
     * the shared identity table for church members and first-timers,
     * so birthday belongs there rather than being duplicated on the
     * member and first_timer side tables.
     *
     * Nullable: existing running-event participants do not have a
     * birthday, and church participants may enter age instead.
     *
     * Phase 3, per client mockup.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('age');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });
    }
};
