<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->text('medical_notes')->nullable();
            $table->boolean('parental_consent')->nullable();
            $table->string('child_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('officiating_pastor')->nullable();
            $table->string('assigned_teacher')->nullable();
            $table->string('program_day_session')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_name',
                'emergency_contact_number',
                'medical_notes',
                'parental_consent',
                'child_name',
                'mother_name',
                'father_name',
                'officiating_pastor',
                'assigned_teacher',
                'program_day_session',
            ]);
        });
    }
};
