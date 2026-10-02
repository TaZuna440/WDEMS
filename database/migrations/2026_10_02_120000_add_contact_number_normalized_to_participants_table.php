<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add participants.contact_number_normalized.
     *
     * Canonical form: 09XXXXXXXXX — 11 digits, PH mobile format.
     * Populated by the Participant model mutator (Phase 3). NULL when
     * the raw contact_number could not be parsed into that format.
     *
     * No index here. The unique index on this column is added by
     * 2026_10_02_123000_add_unique_normalized_phone_to_participants_table
     * so the column and the constraint land in separate migrations
     * (easier rollback if the constraint fails on existing data).
     *
     * The raw contact_number column is preserved for display. The
     * normalized value is what identity resolution queries against.
     *
     * Placed after contact_number so the pair is grouped.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('contact_number_normalized', 11)
                ->nullable()
                ->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('contact_number_normalized');
        });
    }
};
