<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create fellowship_groups.
     *
     * Event-specific groups. The mockup's Family Fellowship example
     * shows Parents, Youth, Children, Other Participants. Group
     * names are chosen by the organizer, not fixed.
     *
     * UNIQUE on (fellowship_event_id, name): an event cannot have
     * two groups with the same name.
     *
     * Cascade on event delete: groups belong to their event.
     *
     * Phase 4.
     */
    public function up(): void
    {
        Schema::create('fellowship_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fellowship_event_id')
                ->constrained('fellowship_events')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['fellowship_event_id', 'name'],
                'fellowship_groups_event_name_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fellowship_groups');
    }
};
