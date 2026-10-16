<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make distance_unit nullable on events.
     *
     * Church events do not have a distance. The Phase 2 controller
     * passes explicit null for distance_value and distance_unit on
     * church-event creation, which fails the NOT NULL constraint on
     * distance_unit -- explicit null bypasses the column's 'km'
     * default.
     *
     * distance_value is already nullable (added that way in the
     * 2026_09_21 metadata migration). This migration makes only
     * distance_unit nullable to match.
     *
     * The 'km' default is preserved so that running events that omit
     * the field still default sensibly. Church events pass null
     * explicitly.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('distance_unit', 2)->nullable()->default('km')->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('distance_unit', 2)->nullable(false)->default('km')->change();
        });
    }
};
