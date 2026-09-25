<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the options scaffolding.
     *
     * event_options and registration_options were built for the Google
     * Forms integration that was removed. They have no rows and no
     * readers after Phase 2. registration_fields (created by the
     * migration immediately preceding this one) replaces them.
     *
     * Drop order: registration_options first (it has an outgoing FK to
     * event_options), then event_options.
     *
     * down() is a faithful recreation — copy of the two original
     * create-table migrations. If ever rolled back, both tables return
     * exactly as they were.
     */
    public function up(): void
    {
        Schema::dropIfExists('registration_options');
        Schema::dropIfExists('event_options');
    }

    public function down(): void
    {
        Schema::create('event_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('option_type');
            $table->string('option_name');
            $table->string('option_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'option_type']);
        });

        Schema::create('registration_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->constrained('registrations')
                ->cascadeOnDelete();
            $table->foreignId('event_option_id')
                ->constrained('event_options')
                ->restrictOnDelete();
            $table->string('option_value')->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'event_option_id']);
        });
    }
};
