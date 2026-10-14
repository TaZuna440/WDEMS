<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add attendances.verified_at.
     *
     * Set when a pre-registered participant is marked present through
     * the attendance-code lookup path — the participant presented
     * their code, staff entered it, the system resolved it to a
     * registration, and the mark was recorded. The timestamp is the
     * moment of verification.
     *
     * Left NULL when the mark came from a search-and-click, from bulk
     * marking, or from an admin override. Those marks are staff's
     * word; this column records whether the system could verify the
     * claim independently.
     *
     * Walk-ins never set this column. Their presence is a fact of
     * their encoding, not a claim that needs verification.
     *
     * Report queries count verified vs unverified attendances
     * separately so the show-rate number is honest about its own
     * strength.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('verified_at')
                ->nullable()
                ->after('recorded_by');

            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['verified_at']);
            $table->dropColumn('verified_at');
        });
    }
};
