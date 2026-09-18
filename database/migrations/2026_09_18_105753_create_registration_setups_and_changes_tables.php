<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->unique()
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('google_form_id')->nullable();
            $table->string('form_url', 500)->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('registration_setup_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_setup_id')
                ->constrained('registration_setups')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('google_email_used');
            $table->string('action');
            $table->string('google_item_id')->nullable();
            $table->string('item_title')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_setup_changes');
        Schema::dropIfExists('registration_setups');
    }
};
