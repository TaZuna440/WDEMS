<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_verification_challenges', function (Blueprint $table) {
            $table->timestamp('code_acknowledged_at')->nullable()->after('finalized_at');
        });
    }

    public function down(): void
    {
        Schema::table('registration_verification_challenges', function (Blueprint $table) {
            $table->dropColumn('code_acknowledged_at');
        });
    }
};
