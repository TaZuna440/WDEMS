<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('participant_type', 20)->nullable()->after('source');
            $table->string('guardian_name', 100)->nullable()->after('participant_type');
            $table->string('guardian_contact', 20)->nullable()->after('guardian_name');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['participant_type', 'guardian_name', 'guardian_contact']);
        });
    }
};
