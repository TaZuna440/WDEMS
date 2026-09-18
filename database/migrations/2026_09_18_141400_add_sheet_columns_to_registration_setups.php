<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_setups', function (Blueprint $table) {
            $table->string('google_sheet_id')->nullable();
            $table->string('sheet_url', 500)->nullable();
            $table->timestamp('sheet_linked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registration_setups', function (Blueprint $table) {
            $table->dropColumn(['google_sheet_id', 'sheet_url', 'sheet_linked_at']);
        });
    }
};
