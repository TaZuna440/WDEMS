<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_verification_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->longText('payload')->nullable();
            $table->boolean('email_required');
            $table->boolean('phone_required');
            $table->timestamp('expires_at')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();

            foreach (['email', 'phone'] as $channel) {
                $table->char("{$channel}_code_hash", 64)->nullable();
                $table->unsignedTinyInteger("{$channel}_attempts")->default(0);
                $table->timestamp("{$channel}_issued_at")->nullable();
                $table->timestamp("{$channel}_expires_at")->nullable();
                $table->timestamp("{$channel}_verified_at")->nullable();
            }

            $table->boolean('email_delivery_failed')->default(false);
            $table->timestamps();
            $table->index(['event_id', 'finalized_at'], 'verification_event_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_verification_challenges');
    }
};
