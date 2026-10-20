<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Existing forms remain on their published contact contract.
            $table->unsignedTinyInteger('registration_form_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('registration_form_version'));
    }
};
