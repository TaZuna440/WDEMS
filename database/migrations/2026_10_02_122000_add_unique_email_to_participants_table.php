<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique index on participants.email.
     *
     * Before this migration, two Participant rows could share the same
     * email. After, they cannot — one row per distinct email,
     * globally. This is the first integrity constraint on this column.
     *
     * MySQL and SQLite both permit multiple NULL values in a unique
     * index. Participants who submitted phone-only (email NULL) do
     * not collide with each other, and the pre-existing NULL-email
     * row does not conflict with anything.
     *
     * A non-unique index on email already exists — declared in the
     * base participants migration. It stays. Redundant for lookups,
     * but harmless. Dropping it would complicate down().
     *
     * Failure mode if existing data violates the constraint:
     * duplicate emails among existing rows would cause this migration
     * to fail. Phase 0 reconnaissance (2026-10-02) confirmed zero
     * duplicates in the current database. A future deployment with
     * different data must verify before running.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }
};
