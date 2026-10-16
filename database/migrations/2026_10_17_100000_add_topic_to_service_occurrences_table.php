<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a topic column to service_occurrences.
     *
     * Youth Service (Phase 4 of the UI restructure) is structurally
     * identical to the Services module -- dated occurrences with
     * attendance. The mockup shows a "Topic" column that Services
     * does not. Rather than create a fourth dated-meeting table,
     * extend the existing one.
     *
     * Other service types (morning, midweek, other) leave this
     * column null. The Show page renders the Topic column only
     * when the type is 'youth'.
     */
    public function up(): void
    {
        Schema::table('service_occurrences', function (Blueprint $table) {
            $table->string('topic', 255)->nullable()->after('service_leader');
        });
    }

    public function down(): void
    {
        Schema::table('service_occurrences', function (Blueprint $table) {
            $table->dropColumn('topic');
        });
    }
};
