<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('source')->default('form')->index();
            $table->string('google_form_response_id')->nullable();
            $table->timestamp('registered_at')->nullable();

            $table->unique(
                ['event_id', 'google_form_response_id'],
                'registrations_event_response_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_event_response_unique');
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'google_form_response_id', 'registered_at']);
        });
    }
};
