<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the members table.
     *
     * Church membership record attached to an existing participant.
     * The participant_id is UNIQUE -- one person, one membership. The
     * participants table continues to own identity (email, normalized
     * phone, name, age, address); members carries church-specific
     * fields on top.
     *
     * Per docs/church-events/06 sub-decision Q2a (2026-10-16).
     *
     * membership_status allowed values: active | inactive | transferred
     * (string, not enum, so the allowed set can grow without a
     * migration).
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();

            $table->unique('participant_id');

            $table->string('membership_status', 32)->default('active');
            $table->date('membership_date')->nullable();
            $table->string('cell_group', 255)->nullable();
            $table->string('ministry', 255)->nullable();
            $table->string('cell_leader', 255)->nullable();
            $table->string('joined_via', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
