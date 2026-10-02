<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make participants.contact_number nullable.
     *
     * The common-field-requirements feature (Q1: D, 2026-10-02) lets
     * organizers mark contact_number as optional per event. A
     * participant can register without providing one, so the column
     * must accept NULL.
     *
     * The column was NOT NULL until now because every prior code path
     * treated contact_number as required. The rule change in
     * PublicRegistrationRequest::commonFieldRules() is what surfaces
     * the mismatch.
     *
     * Length preserved at 255. The original migration declares
     * varchar(255); an earlier draft of this migration proposed
     * shrinking to varchar(50) to match the request's max:50 rule,
     * but that would truncate any existing value longer than 50
     * characters. The request rule is the ceiling for new
     * submissions; the column stays as wide as it was.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('contact_number', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('contact_number', 255)->nullable(false)->change();
        });
    }
};
