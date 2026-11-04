<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A stable row also serializes the first insert, when no participant exists yet.
        Schema::create('participant_identity_locks', function (Blueprint $table): void {
            $table->string('pool')->primary();
        });
        DB::table('participant_identity_locks')->insert([['pool' => 'running'], ['pool' => 'church']]);
        Schema::create('registration_identity_decisions', function (Blueprint $table): void {
            $table->id();
            // History survives removal of a registration; no cascading foreign keys.
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('action', 40);
            $table->string('rule_version', 20);
            $table->text('reason');
            $table->json('evidence');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_identity_decisions');
        Schema::dropIfExists('participant_identity_locks');
    }
};
