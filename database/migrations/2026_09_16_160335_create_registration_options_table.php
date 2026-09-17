<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('registration_options');
    }
};