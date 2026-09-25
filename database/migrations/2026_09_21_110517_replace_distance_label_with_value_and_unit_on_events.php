<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Add the new structured fields
            $table->decimal('distance_value', 6, 2)
                ->nullable()
                ->after('end_time');
            $table->string('distance_unit', 2)
                ->default('km')
                ->after('distance_value');

            // Drop the old free-text column
            $table->dropColumn('distance_label');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('distance_label', 50)
                ->nullable()
                ->after('end_time');

            $table->dropColumn(['distance_value', 'distance_unit']);
        });
    }
};
