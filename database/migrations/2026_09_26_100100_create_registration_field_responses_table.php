<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registration field responses — one row per custom field per
     * registration.
     *
     * The six common participant fields (first_name, last_name, email,
     * contact_number, age, address) live in the participants table.
     * They are structural — every registration has them — so they are
     * not stored here. Only custom fields defined via the form builder
     * produce rows in this table.
     *
     * value is text for all field types. Rendering interprets it based
     * on the field's field_type:
     *   - text, textarea, email, date → the string, as-is
     *   - number → the string, cast on read
     *   - select, radio → the string, one of the field's options
     *   - checkbox → a JSON array of selected option strings
     *
     * The JSON-encoded case is the only one that stores structured data.
     * Everything else is a scalar string. This keeps the storage schema
     * uniform — one column, one type — and pushes the interpretation to
     * the read path where the field_type is known.
     *
     * RESTRICT on registration_field_id: a field with responses cannot
     * be silently deleted. In practice this cannot happen — the form
     * locks at Open Registration (D6), which is the moment responses
     * can first exist. The RESTRICT matches the pattern used by the
     * dropped registration_options table; it is defense-in-depth, not a
     * live guard.
     *
     * CASCADE on registration_id: when a registration is deleted (Phase
     * 5), its responses go with it. Event deletion cascades registrations
     * via the event_id FK, so responses clean up for free during event
     * deletion as well. The delete order in EventDeletionService does
     * not need to change — responses cascade when registrations cascade.
     *
     * The unique constraint on (registration_id, registration_field_id)
     * enforces one response per field per registration. A duplicate
     * submission of the same field within a single registration is a
     * controller bug, not a valid state.
     *
     * The constraint name is explicit. Laravel's auto-generated name
     * for this column pair —
     * registration_field_responses_registration_id_registration_field_id_unique
     * — is 73 characters, over MySQL's 64-character identifier limit.
     * SQLite accepts the overlong name; MySQL rejects it with error
     * 1059. The explicit name 'reg_field_responses_unique' (26 chars)
     * applies cleanly on both engines.
     */
    public function up(): void
    {
        Schema::create('registration_field_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->constrained('registrations')
                ->cascadeOnDelete();
            $table->foreignId('registration_field_id')
                ->constrained('registration_fields')
                ->restrictOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['registration_id', 'registration_field_id'],
                'reg_field_responses_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_field_responses');
    }
};
