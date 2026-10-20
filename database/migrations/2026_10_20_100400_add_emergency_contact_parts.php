<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('emergency_contact_first_name', 50)->nullable();
            $table->string('emergency_contact_last_name', 50)->nullable();
            $table->string('emergency_contact_relationship_other', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn([
            'emergency_contact_first_name', 'emergency_contact_last_name', 'emergency_contact_relationship_other',
        ]));
    }
};
