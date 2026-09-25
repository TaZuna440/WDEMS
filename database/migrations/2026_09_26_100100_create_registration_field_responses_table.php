<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create registration_field_responses.
     *
     * One row per answered custom field per submission. The six common
     * participant fields (first_name, last_name, email, contact_number,
     * age, address) live in the participants table — they are not
     * stored here.
     *
     * CASCADE on registration_id: deleting a registration removes its
     * responses. Event deletion cascades registrations, so responses
     * clean up transitively without an explicit step in
     * EventDeletionService.
     *
     * RESTRICT on registration_field_id: a field with responses cannot
     * be silently deleted. In practice this cannot happen because the
     * form is locked the moment registration opens (D6 in the Phase 3
     * plan) and responses only exist after that point.
     *
     * The unique constraint name is explicit. Laravel's auto-generated
     * name — registration_field_responses_registration_id_registration_field_id_unique
     * — is 71 characters, over MySQL's 64-character identifier limit.
     */
    public function up(): void
    {
        Schema::create('registration_field_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->constrained('registrations')
                ->cascadeOnDelete();
            $table->foreignId('registration_field_id')
                ->constrained('registration_fields')
                ->restrictOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['registration_id', 'registration_field_id'],
                'reg_field_responses_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_field_responses');
    }
};
