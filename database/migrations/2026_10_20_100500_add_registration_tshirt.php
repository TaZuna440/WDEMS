<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->json('registration_tshirt')->nullable());
        Schema::table('registrations', fn (Blueprint $table) => $table->string('tshirt_choice', 50)->nullable());
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('tshirt_choice'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('registration_tshirt'));
    }
};
