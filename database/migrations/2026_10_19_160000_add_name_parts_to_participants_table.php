<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add optional middle name and suffix to participants.
     *
     * Both feed the composite identity key in W7:
     * normalized(first + middle + last + suffix) + verified contact.
     * The suffix is what lets "Juan Dela Cruz Jr." and
     * "Juan Dela Cruz Sr." share one email without colliding.
     *
     * Nullable: existing rows and pre-composite intake paths simply
     * leave them empty. Suffix is capped at 16 chars ("Jr.", "Sr.",
     * "II", "III" — comfortably fits, blocks essays).
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('last_name');
            $table->string('suffix', 16)->nullable()->after('middle_name');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['suffix', 'middle_name']);
        });
    }
};
