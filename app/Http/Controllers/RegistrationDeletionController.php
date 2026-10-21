<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventDeletion\OtpResendTooSoonException;
use App\Services\EventDeletion\OtpVerificationResult;
use App\Services\EventDeletion\RegistrationDeletionOtpService;
use App\Services\EventDeletion\RegistrationDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Delete a single registration.
 *
 * Mirrors EventDeletionController. Two role paths:
 *
 *   - Admin: DELETE /registrations/{registration} — direct, no OTP.
 *   - Staff: OTP flow via request-otp + verify-otp endpoints.
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
     * Admin path: delete immediately. Staff get 403 — they must use
     * the OTP flow.
     */
    public function destroy(Request $request, Registration $registration): RedirectResponse
    {
        $this->ensureManageable($registration);

        if (! $request->user()->isAdmin()) {
            abort(403, 'Staff must verify registration deletion via email OTP.');
        }

        return $this->performDeletion($registration, $request->user());
    }

    /**
     * Staff path, step 1: send a code.
     *
     * Rate-limited at the route. Cooldown enforced inside the
     * service.
     */
    public function requestOtp(Request $request, Registration $registration): RedirectResponse
    {
        $this->ensureManageable($registration);

        $user = $request->user();

        if ($user->isAdmin()) {
            abort(403, 'Admins do not require OTP verification.');
        }

        $service = app(RegistrationDeletionOtpService::class);

        try {
            $service->issue($user, $registration);
        } catch (OtpResendTooSoonException) {
            return back()->withErrors([
                'code' => 'Please wait before requesting another code.',
            ]);
        } catch (\Throwable $e) {
            // Roll back the OTP + cooldown we just wrote, so the user
            // isn't locked out by an OTP that was never delivered.
            $service->invalidate($user, $registration);

            Log::error('registration.deletion.otp_request_failed', [
                'user_id' => $user->id,
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return back()->withErrors([
                'code' => 'We could not send the verification code. Please try again in a moment. If this keeps happening, contact your administrator.',
            ]);
        }

        return back();
    }

    /**
     * Staff path, step 2: verify the code and, on success, delete.
     *
     * The verification result drives the response. Verified triggers
     * the delete. Every other state surfaces a specific message under
     * the `code` error key.
     */
    public function verifyOtp(Request $request, Registration $registration): RedirectResponse
    {
        $this->ensureManageable($registration);

        $user = $request->user();

        if ($user->isAdmin()) {
            abort(403, 'Admins do not require OTP verification.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        try {
            $result = app(RegistrationDeletionOtpService::class)
                ->verify($user, $registration, $validated['code']);

            return match ($result) {
                OtpVerificationResult::Verified => $this->performDeletion($registration, $user),
                OtpVerificationResult::Invalid => back()->withErrors([
                    'code' => 'The verification code is incorrect.',
                ]),
                OtpVerificationResult::Expired => back()->withErrors([
                    'code' => 'The verification code has expired. Please request a new one.',
                ]),
                OtpVerificationResult::Locked => back()->withErrors([
                    'code' => 'Too many incorrect attempts. Please request a new code later.',
                ]),
                OtpVerificationResult::NotFound => back()->withErrors([
                    'code' => 'No active verification code. Please request a new one.',
                ]),
            };
        } catch (\Throwable $e) {
            Log::error('registration.deletion.verify_failed', [
                'user_id' => $user->id,
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return back()->withErrors([
                'code' => 'Something went wrong while verifying. Please try again in a moment.',
            ]);
        }
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
     * Event status gate. Same statuses the monitor's own actions
     * accept: open or closed. Draft, ongoing, completed, and
     * cancelled reject with 403.
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
        ], true)) {
            abort(403, 'This registration can no longer be deleted.');
        }
    }
}
