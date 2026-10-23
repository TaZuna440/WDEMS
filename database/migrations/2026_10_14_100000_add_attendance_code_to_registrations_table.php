<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add registrations.attendance_code.
     *
     * Set once, inside RegistrationVerificationService::finalize(),
     * on the same write that creates the Registration row. Null for
     * walk-ins ('walk_in' and 'paper' sources) — they are physically
     * present when their paper form is filled, so there is nothing to
     * verify at the table.
     *
     * Format: 8 characters from a 31-character alphabet that excludes
     * the confusables I, L, O, 0, 1, uppercased, with a dash between
     * the two groups of four. Nine characters total (KM4X-P2N7).
     * Readable over the phone; distinguishable from the lowercase
     * event slug by case alone.
     *
     * The column is varchar(16) rather than varchar(9) for the same
     * reason registration_slug is varchar(32) for an 8-character
     * value: the format is allowed to grow without a schema change.
     * A second dash or a check digit would fit; string(9) would
     * truncate silently.
     *
     * Uniqueness is scoped to (event_id, attendance_code) — a code
     * only needs to be unique within the event it was issued for.
     * The same value may appear on registrations for two different
     * events without confusion; the workspace looks up by code
     * inside one event.
     *
     * Nullable for legacy/name-only walk-ins without a registration
     * code. New staff-entered registrations receive a code when they
     * provide an email address. MySQL treats NULL as distinct in
     * unique indexes, so multiple code-less rows per event coexist.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('attendance_code', 16)
                ->nullable()
                ->after('submitted_contact_number');

            $table->unique(
                ['event_id', 'attendance_code'],
                'registrations_event_attendance_code_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_event_attendance_code_unique');
            $table->dropColumn('attendance_code');
        });
    }
};
