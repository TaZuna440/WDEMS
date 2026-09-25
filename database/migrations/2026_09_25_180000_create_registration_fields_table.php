<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The registration form builder's storage.
     *
     * One row per custom field. Common participant fields (first_name,
     * last_name, email, contact_number, age, address) are structural —
     * they live in the participants table and are never stored here.
     *
     * Phase 3 will add registration_field_responses to store answers.
     */
    public function up(): void
    {
        Schema::create('registration_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('field_type', 20);
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_fields');
    }
};
