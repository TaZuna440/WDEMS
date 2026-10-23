<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventDeletion\RegistrationDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Delete a single registration.
 *
 * Requires the authenticated user's password before deleting.
 *
 * Cascade behavior is at the schema level: field responses and
 * attendances vanish with the registration. The participant row is
 * preserved (shared across events). See RegistrationDeletionService.
 *
 * The event status gate is duplicated from
 * RegistrationMonitorController::ensureEditableRegistration(). A
 * future refactor can extract it to a trait or a model method.
 * Duplicating for now keeps this controller self-contained.
 */
class RegistrationDeletionController extends Controller
{
    /**
     * Validate the user's password before deleting.
     */
    public function destroy(Request $request, Registration $registration): RedirectResponse
    {
        $this->ensureManageable($registration);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The provided password does not match our records.',
            ]);
        }

        return $this->performDeletion($registration, $user);
    }

    /**
     * Run the delete. Captures the event ID before the delete so the
     * redirect target survives the FK becoming null.
     */
    private function performDeletion(Registration $registration, User $user): RedirectResponse
    {
        $eventId = $registration->event_id;

        app(RegistrationDeletionService::class)->delete($registration, $user);

        return redirect()
            ->route('registration-monitor.show', ['event' => $eventId])
            ->with('success', 'Registration deleted.');
    }

    /**
     * Status gate. The monitor remains available while registration
     * is open/closed and while the event is ongoing, so deletions
     * must remain available for those same states.
     */
    private function ensureManageable(Registration $registration): void
    {
        abort_unless(! in_array($registration->registration_status, [RegistrationStatus::Held, RegistrationStatus::Duplicate, RegistrationStatus::Removed], true) && $registration->participant?->pool === 'running', 422, 'Use organizer review for inactive submissions.');
        $event = $registration->event;

        if ($event === null) {
            abort(404);
        }

        if (! in_array($event->status, [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
            EventStatus::Ongoing,
        ], true)) {
            abort(403, 'This registration can no longer be deleted.');
        }
    }
}
