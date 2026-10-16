<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create discipleship_programs.
     *
     * Six fixed programs, seeded by DiscipleshipProgramSeeder:
     *   Life Class, Encounter God Retreat, School of Leaders 1-3,
     *   Cell Convergence.
     *
     * A program is the parent of many batches (Phase 5B). Each
     * batch belongs to exactly one program. Enrollment can be at
     * the program level or the batch level -- the decision is
     * deferred to Phase 5C and does not affect this table.
     *
     * slug is unique and used for the per-program route
     * (/discipleship/programs/{slug}). Auto-generated from the
     * name in the seeder.
     *
     * Table name is plural, matching Eloquent's default derivation
     * from DiscipleshipProgram. No $table override on the model.
     *
     * Phase 5A, per docs/church-events/07 section 3.6 (5A.1).
     */
    public function up(): void
    {
        Schema::create('discipleship_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 64)->unique();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_programs');
    }
};
