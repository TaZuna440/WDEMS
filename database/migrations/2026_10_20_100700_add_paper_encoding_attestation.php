<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('contact_attested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encoded_by');
            $table->dropColumn('contact_attested_at');
        });
    }
};
