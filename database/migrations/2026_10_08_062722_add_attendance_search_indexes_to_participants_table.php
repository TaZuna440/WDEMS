<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the indexes needed for the attendance page's name and phone
     * search (Phase 4).
     *
     * Existing state before this migration:
     *   - participants_email_unique           — email (unique)
     *   - participants_email_index            — email (non-unique, redundant)
     *   - participants_contact_number_normalized_unique
     *                                         — contact_number_normalized (unique)
     *   - participants_last_name_first_name_index
     *                                         — (last_name, first_name) composite
     *
     * Added here:
     *   - participants_first_name_index       — first_name standalone
     *   - participants_last_name_index        — last_name standalone
     *   - participants_contact_number_index   — contact_number (plain)
     *
     * The composite (last_name, first_name) covers last_name prefix
     * queries, but MySQL's optimizer does not reliably use it for
     * OR-joined conditions across both columns. Standalone indexes make
     * each OR branch independently index-usable.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->index('first_name');
            $table->index('last_name');
            $table->index('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['first_name']);
            $table->dropIndex(['last_name']);
            $table->dropIndex(['contact_number']);
        });
    }
};
