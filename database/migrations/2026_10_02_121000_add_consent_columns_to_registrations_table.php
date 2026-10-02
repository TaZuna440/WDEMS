<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add consent tracking columns to registrations.
     *
     * consent_accepted_at — set to now() when the participant checks
     * the consent checkbox on the public form. NULL means the
     * submission predates consent recording (existing rows) or the
     * checkbox was not part of the form.
     *
     * privacy_notice_version — records which version of the notice
     * the participant agreed to. Initially a build-time constant
     * (see resources/js/lib/demo-legal-content.ts). Per-event notice
     * versions are a Phase 3.5 concern; this column exists so the
     * data is captured from the start.
     *
     * Both columns are nullable so existing registrations are
     * unaffected.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('consent_accepted_at')->nullable();
            $table->string('privacy_notice_version', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['consent_accepted_at', 'privacy_notice_version']);
        });
    }
};
