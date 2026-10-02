<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique index on participants.contact_number_normalized.
     *
     * Second identity key. A participant is identified by email when
     * present, otherwise by the normalized phone. Two participants
     * cannot share a normalized phone.
     *
     * NULL values (submissions with no phone, or with a phone that
     * could not be parsed) do not collide — MySQL and SQLite both
     * permit multiple NULLs in a unique index. This preserves the
     * "phone is optional" path.
     *
     * The original plan deferred this migration to Phase 2 (backfill)
     * on the theory that legacy phone values might normalize into
     * collisions. Phase 0 reconnaissance (2026-10-02) confirmed zero
     * existing phone values, so no backfill is required and the
     * constraint lands here.
     *
     * Failure mode if existing data violates the constraint:
     * duplicate normalized phones among existing rows. Phase 0
     * confirmed none exist. A future deployment with different data
     * must verify before running.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->unique('contact_number_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['contact_number_normalized']);
        });
    }
};
