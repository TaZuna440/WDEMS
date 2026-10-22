<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('church_registration_open')->default(false);
        });
        foreach (['service_occurrences', 'fellowship_events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('registration_event_id')->nullable()->unique()->constrained('events')->restrictOnDelete();
            });
        }
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('fellowship_group_id')->nullable()->constrained('fellowship_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropConstrainedForeignId('fellowship_group_id'));
        foreach (['service_occurrences', 'fellowship_events'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('registration_event_id'));
        }
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('church_registration_open'));
    }
};
