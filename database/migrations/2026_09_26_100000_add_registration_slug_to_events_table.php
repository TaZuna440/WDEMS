<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add events.registration_slug.
     *
     * Populated by EventController::openRegistration() at the moment
     * the organizer clicks Open Registration. Null until then.
     *
     * Nullable + unique. MySQL allows multiple NULL values in a unique
     * index, so Draft events (slug = null) never conflict with each
     * other.
     *
     * The route /r/{event:registration_slug} uses implicit model
     * binding on this column.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('registration_slug', 32)
                ->nullable()
                ->unique()
                ->after('registration_form_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['registration_slug']);
            $table->dropColumn('registration_slug');
        });
    }
};
