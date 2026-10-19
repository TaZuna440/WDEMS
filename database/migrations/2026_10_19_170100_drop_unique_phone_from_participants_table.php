<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the global unique index on participants.contact_number_normalized.
     *
     * Added by 2026_10_02_123000_add_unique_normalized_phone_to_participants_table.
     * The composite duplicate rule (W7) allows multiple participants to
     * share one phone — e.g. a parent registering kids under the
     * parent's number. The global UNIQUE must go before such rows can
     * exist. Identity is now (normalized name + contact), not contact
     * alone.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['contact_number_normalized']);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->unique('contact_number_normalized');
        });
    }
};
