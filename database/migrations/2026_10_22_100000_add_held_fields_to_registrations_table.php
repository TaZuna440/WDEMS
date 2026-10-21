<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('held_reason')->nullable()->after('notes');
            $table->foreignId('held_candidate_registration_id')
                ->nullable()
                ->after('held_reason')
                ->constrained('registrations')
                ->nullOnDelete();
            $table->foreignId('resolved_by')
                ->nullable()
                ->after('held_candidate_registration_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            $table->string('resolution_action')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_candidate_registration_id');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['held_reason', 'resolved_at', 'resolution_action']);
        });
    }
};
