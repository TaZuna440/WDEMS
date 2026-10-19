<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add participants.first_name_normalized and last_name_normalized.
     *
     * Canonical form: trim, collapse internal whitespace, mb_strtolower
     * (Unicode-aware). Populated by the Participant model mutators.
     * NULL when the raw name is null/blank.
     *
     * These power the composite duplicate rule (W7): a person is
     * (normalized name + verified contact). The normalized values are
     * what identity resolution queries against; the raw columns are
     * preserved for display.
     *
     * No unique index — duplicate names are allowed. The duplicate
     * check is (name + contact + event), enforced in application
     * logic, not the schema.
     *
     * Placed after their raw counterparts so each pair is grouped.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('first_name_normalized')->nullable()->after('first_name');
            $table->string('last_name_normalized')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['first_name_normalized', 'last_name_normalized']);
        });
    }
};
