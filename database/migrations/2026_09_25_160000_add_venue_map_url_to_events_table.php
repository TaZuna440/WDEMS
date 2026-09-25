<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an optional venue_map_url column.
     *
     * The interactive map picker was removed in the same session that
     * added this column. New events store a pasted map link instead of
     * coordinates. venue_latitude and venue_longitude remain in the
     * table for backward compatibility with events created before the
     * picker was removed.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('venue_map_url', 500)
                ->nullable()
                ->after('venue_address');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('venue_map_url');
        });
    }
};
