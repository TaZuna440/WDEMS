<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            // Retain provenance even if the original event is later removed.
            $table->unsignedBigInteger('provisional_event_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('provisional_event_id'));
    }
};
