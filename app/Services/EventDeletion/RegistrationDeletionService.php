<?php

namespace App\Services\EventDeletion;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Deletes a single registration.
 *
 * Cascade behavior is handled at the schema level:
 *
 *   - registration_field_responses.registration_id → CASCADE
 *   - attendances.registration_id → CASCADE
 *
 * The participant row is deliberately preserved. Participants are
 * shared across events — deleting one person's registration for one
 * event must not remove their history on other events. This matches
 * the identity model in docs/participant-identity.md.
 *
 * The event itself is also preserved. Deleting a registration is a
 * single-row operation, not a cascade through the event.
 *
 * Named under App\Services\EventDeletion because it sits alongside
 * EventDeletionService in the same sub-namespace. The name is
 * historical — the directory predates the registration-deletion
 * feature. Moving the whole sub-namespace to a more general
 * App\Services\Deletion is a future refactor, not part of this
 * phase.
 */
class RegistrationDeletionService
{
    /**
     * Delete a registration and log the action.
     *
     * Single DELETE — cascades are at the schema level. The
     * transaction is defensive: if a cascade trigger were ever added,
     * the delete rolls back atomically.
     */
    public function delete(Registration $registration, User $user): void
    {
        $context = [
            'user_id' => $user->id,
            'registration_id' => $registration->id,
            'event_id' => $registration->event_id,
            'participant_id' => $registration->participant_id,
        ];

        \DB::transaction(function () use ($registration): void {
            $registration->delete();
        });

        Log::info('registration.deleted', $context);
    }
}
