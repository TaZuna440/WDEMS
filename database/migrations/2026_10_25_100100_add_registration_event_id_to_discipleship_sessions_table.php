<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discipleship_sessions', function (Blueprint $table) {
            $table->foreignId('registration_event_id')
                ->nullable()
                ->unique()
                ->constrained('events')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discipleship_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_event_id');
        });
    }
};
