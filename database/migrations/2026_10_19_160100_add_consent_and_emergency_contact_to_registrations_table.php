<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add emergency contact to registrations.
     *
     * emergency_contact_* live on the registration, not the
     * participant: the contact is per-event (who to call if something
     * happens at THIS event), not per-person identity.
     *
     * NOTE: consent_accepted_at + privacy_notice_version are NOT
     * added here — migration 2026_10_02_121000 already created them.
     * They exist in the schema; nothing writes them yet (audit P5).
     * W2 starts writing both at finalize().
     *
     * All nullable at the DB level. Requiredness is enforced by
     * PublicRegistrationRequest at intake (W4), so legacy rows and
     * walk-in registrations (which collect no emergency contact)
     * stay valid.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship', 64)->nullable();
            $table->string('emergency_contact_number', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_number',
                'emergency_contact_relationship',
                'emergency_contact_name',
            ]);
        });
    }
};
