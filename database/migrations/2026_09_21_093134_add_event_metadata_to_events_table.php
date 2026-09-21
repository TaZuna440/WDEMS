<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Venue extensions — logical position after `venue`
            $table->string('venue_address', 500)->nullable()->after('venue');
            $table->decimal('venue_latitude', 10, 7)->nullable()->after('venue_address');
            $table->decimal('venue_longitude', 10, 7)->nullable()->after('venue_latitude');

            // Logistics extras — logical position after `end_time`
            $table->string('distance_label', 50)->nullable()->after('end_time');
            $table->string('course_url', 500)->nullable()->after('distance_label');

            // Registration extras — after `registration_end`
            $table->boolean('rsvp_required')->default(false)->after('registration_end');

            // Structured data
            $table->json('partners')->nullable()->after('rsvp_required');
            $table->json('faq')->nullable()->after('partners');

            // Accessibility — 9 booleans, all default false
            $table->boolean('walkers_welcome')->default(false);
            $table->boolean('all_paces_welcome')->default(false);
            $table->boolean('all_ages_welcome')->default(false);
            $table->boolean('stroller_friendly')->default(false);
            $table->boolean('wheelchair_accessible')->default(false);
            $table->boolean('sweeper_present')->default(false);
            $table->boolean('service_animals_allowed')->default(false);
            $table->boolean('leashed_pets_allowed')->default(false);
            $table->boolean('quiet_space_available')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'venue_address',
                'venue_latitude',
                'venue_longitude',
                'distance_label',
                'course_url',
                'rsvp_required',
                'partners',
                'faq',
                'walkers_welcome',
                'all_paces_welcome',
                'all_ages_welcome',
                'stroller_friendly',
                'wheelchair_accessible',
                'sweeper_present',
                'service_animals_allowed',
                'leashed_pets_allowed',
                'quiet_space_available',
            ]);
        });
    }
};
