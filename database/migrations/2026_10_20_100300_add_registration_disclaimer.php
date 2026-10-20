<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->text('registration_disclaimer_html')->nullable());
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('registration_disclaimer_html'));
    }
};
