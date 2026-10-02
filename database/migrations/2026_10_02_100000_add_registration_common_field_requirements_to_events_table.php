<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add events.registration_common_field_requirements.
     *
     * JSON map of { field_name: bool } for the toggleable common
     * fields. The six common participant fields split into two
     * categories:
     *
     *   Always required (never stored here):
     *     first_name, last_name, age
     *
     *   Toggleable per event (stored here):
     *     email, contact_number, address
     *
     * A NULL column means "all toggleable fields are required" — the
     * default. No backfill is needed for existing events: a missing
     * key in the JSON and a NULL column both resolve to required.
     *
     * Shape when set:
     *     { "email": true, "contact_number": false, "address": true }
     *
     * Partial maps are allowed. A missing key falls back to true —
     * see Event::isCommonFieldRequired().
     *
     * The column is nullable and additive. Existing rows (NULL)
     * continue to behave as they did before this migration for
     * email and contact_number. Address changes from optional to
     * required by default — deliberate per Q4-a1 (2026-10-02): one
     * rule, no exceptions. Organizers who want address optional
     * uncheck it in the form builder.
     *
     * Placed after registration_slug so the events table's
     * registration columns stay grouped.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('registration_common_field_requirements')
                ->nullable()
                ->after('registration_slug');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('registration_common_field_requirements');
        });
    }
};
