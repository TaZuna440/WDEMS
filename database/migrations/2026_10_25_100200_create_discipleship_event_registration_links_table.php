<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipleship_event_registration_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')
                ->constrained('discipleship_enrollments')
                ->restrictOnDelete();
            $table->foreignId('session_id')
                ->constrained('discipleship_sessions')
                ->restrictOnDelete();
            $table->foreignId('registration_id')
                ->unique()
                ->constrained('registrations')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['enrollment_id', 'session_id'],
                'discipleship_links_enrollment_session_unique',
            );
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_event_registration_links');
    }
};
