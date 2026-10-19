<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the global unique index on participants.email.
     *
     * Added by 2026_10_02_122000_add_unique_email_to_participants_table.
     * The composite duplicate rule (W7) allows multiple participants to
     * share one email — e.g. a parent registering kids under the
     * parent's email. The global UNIQUE must go before such rows can
     * exist.
     *
     * The non-unique email index from the base participants migration
     * stays for lookups.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
