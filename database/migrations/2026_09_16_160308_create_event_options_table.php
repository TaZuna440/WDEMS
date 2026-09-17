<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('option_type');       // e.g. distance, shirt, cap
            $table->string('option_name');       // e.g. 5 KM, Large Shirt
            $table->string('option_value')->nullable(); // optional extra data
            $table->boolean('is_required')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'option_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_options');
    }
};