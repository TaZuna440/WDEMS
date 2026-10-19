<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Device-scoped email verification trust for public participants.
     *
     * One row per (email, device). After a successful email OTP
     * verification, W5 records the trust here and sets the
     * wdems_email_trust cookie holding the device token. A later
     * registration from the same browser for the same email skips
     * OTP issuance — this is what lets a parent register two kids
     * from one phone without re-verifying.
     *
     * Trust TTL is 7 days for participants. Organizer/staff device
     * trust stays at its existing 30 days — separate population,
     * separate mechanism, untouched by this table.
     *
     * Only the SHA-256 hash of the token is stored; the raw token
     * lives in the HttpOnly cookie. The unique pair makes
     * re-verification an upsert, not a duplicate row.
     */
    public function up(): void
    {
        Schema::create('email_device_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('device_token_hash', 64);
            $table->timestamp('verified_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['email', 'device_token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_device_verifications');
    }
};
