<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track the participant's chosen verification channel.
     *
     * W5 (stricter channel choice): when an event requires both email
     * and phone, the participant picks ONE to verify. The choice is
     * stored here; allVerified() passes when the chosen channel is
     * verified. When only one channel is required, begin() sets it
     * automatically — no choice screen.
     *
     * The verified (chosen) channel becomes the identity contact at
     * finalize(). The other required-but-unverified contact is stored
     * as a snapshot only.
     */
    public function up(): void
    {
        Schema::table('registration_verification_challenges', function (Blueprint $table) {
            $table->string('chosen_channel', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registration_verification_challenges', function (Blueprint $table) {
            $table->dropColumn('chosen_channel');
        });
    }
};
