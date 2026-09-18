<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\EventDeletion\EventDeletionOtpService;
use App\Services\EventDeletion\EventDeletionService;
use App\Services\EventDeletion\OtpResendTooSoonException;
use App\Services\EventDeletion\OtpVerificationResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventDeletionController extends Controller
{
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'Staff must verify event deletion via email OTP.');
        }

        return $this->performDeletion($event, $request->user());
    }

    public function requestOtp(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            abort(403, 'Admins do not require OTP verification.');
        }

        $service = app(EventDeletionOtpService::class);

        try {
            $service->issue($user, $event);
        } catch (OtpResendTooSoonException) {
            return back()->withErrors([
                'code' => 'Please wait before requesting another code.',
            ]);
        } catch (\Throwable $e) {
            // Roll back the OTP + cooldown we just wrote, so the user
            // isn't locked out by an OTP that was never delivered.
            $service->invalidate($user, $event);

            Log::error('event.deletion.otp_request_failed', [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return back()->withErrors([
                'code' => 'We could not send the verification code. Please try again in a moment. If this keeps happening, contact your administrator.',
            ]);
        }

        return back();
    }

    public function verifyOtp(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            abort(403, 'Admins do not require OTP verification.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        try {
            $result = app(EventDeletionOtpService::class)
                ->verify($user, $event, $validated['code']);

            return match ($result) {
                OtpVerificationResult::Verified => $this->performDeletion($event, $user),
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
            Log::error('event.deletion.verify_failed', [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return back()->withErrors([
                'code' => 'Something went wrong while verifying. Please try again in a moment.',
            ]);
        }
    }

    private function performDeletion(Event $event, User $user): RedirectResponse
    {
        app(EventDeletionService::class)->delete($event, $user);

        return redirect()->route('events.index');
    }
}
