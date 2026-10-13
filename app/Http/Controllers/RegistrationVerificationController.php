<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\RegistrationVerificationChallenge as Challenge;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegistrationVerificationController extends Controller
{
    public function __construct(private readonly RegistrationVerificationService $verification) {}

    public function show(Request $request, Event $event): Response
    {
        return Inertia::render('registrations/verify', [
            'event_name' => $event->event_name,
            'verification' => $this->verification->publicState($event, $this->token($request, $event)),
            'form_url' => route('public-registration.show', ['event' => $event->registration_slug]),
            'verification_url' => route('public-registration.verification', ['event' => $event->registration_slug]),
        ]);
    }

    public function verify(Request $request, Event $event, string $channel): RedirectResponse
    {
        $validator = Validator::make($request->only('code'), [
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ]);
        if ($validator->fails()) {
            // Do not flash the submitted OTP into session old input.
            return $this->backToVerification($event)->withErrors([$channel => 'Enter a six-digit code.']);
        }

        return $this->publicAction($event, $channel, fn () => $this->verification->verify(
            $event, $this->token($request, $event), $channel, $validator->validated()['code'],
        ));
    }

    public function resendEmail(Request $request, Event $event): RedirectResponse
    {
        return $this->publicAction($event, 'email', fn () => $this->verification->sendEmail(
            $event, $this->token($request, $event),
        ));
    }

    public function complete(Request $request, Event $event): RedirectResponse
    {
        return $this->publicAction($event, 'verification', function () use ($request, $event): ?string {
            $this->verification->finalize($event, $this->token($request, $event));

            return null;
        });
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->role === 'staff', 403);

        $pending = Challenge::query()
            ->where('phone_required', true)->whereNull('phone_verified_at')
            ->whereNull('finalized_at')->where('expires_at', '>', now())
            ->where('created_at', '>', now()->subHours(RegistrationVerificationService::CHALLENGE_TTL_HOURS))
            ->whereHas('event', function ($query) use ($user) {
                $query->where('status', EventStatus::RegistrationOpen)
                    ->where(fn ($query) => $query->whereNull('registration_end')->orWhere('registration_end', '>', now()));
                if (! $user->isAdmin()) {
                    $query->where('created_by', $user->id);
                }
            })->with('event')->oldest()->paginate(25);

        // Explicit projection: no email, name, address, custom answers, token or hash.
        $rows = $pending->getCollection()->filter(
            fn (Challenge $challenge) => $this->verification->challengeState($challenge, $challenge->event) === 'pending',
        )->map(fn (Challenge $challenge) => [
            'id' => $challenge->id,
            'event_name' => $challenge->event->event_name,
            'phone' => $challenge->payload['contact_number'] ?? '',
            'created_at' => $challenge->created_at->toIso8601String(),
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'channel' => $this->verification->channelState($challenge, 'phone'),
            'generate_url' => route('registration-verification.phone-code', ['challenge' => $challenge->id]),
        ])->values();

        return Inertia::render('registrations/verification-queue', [
            'challenges' => $rows,
            'previous_url' => $pending->previousPageUrl(),
            'next_url' => $pending->nextPageUrl(),
        ]);
    }

    public function generatePhone(Request $request, Challenge $challenge): JsonResponse
    {
        // Also checked again under the event lock before issuing the code.
        $this->verification->authorizeStaff($challenge->event, $request->user());
        try {
            $code = $this->verification->generatePhone($challenge, $request->user());
        } catch (ValidationException $exception) {
            return response()->json(['message' => collect($exception->errors())->flatten()->first()], 422);
        } catch (HttpException $exception) {
            throw $exception;
        } catch (\Throwable) {
            Log::error('registration.verification.phone_failed', ['challenge_id' => $challenge->id]);

            return response()->json(['message' => 'The code could not be generated. Refresh and try again.'], 503);
        }

        return response()->json([
            'code' => $code,
            'expires_in_seconds' => RegistrationVerificationService::CODE_TTL_SECONDS,
            'message' => 'Communicate this code to the submitted phone number yourself. WDEMS has not sent an SMS.',
        ]);
    }

    private function token(Request $request, Event $event): ?string
    {
        $token = $request->cookie(RegistrationVerificationService::cookieName($event));

        return is_string($token) ? $token : null;
    }

    /** @param callable(): ?string $action */
    private function publicAction(Event $event, string $field, callable $action): RedirectResponse
    {
        try {
            $error = $action();

            return $error === null ? $this->backToVerification($event)
                : $this->backToVerification($event)->withErrors([$field => $error]);
        } catch (ValidationException $exception) {
            // Never flash request input, contact identifiers, or exception details.
            return $this->backToVerification($event)->withErrors([
                'verification' => collect($exception->errors())->flatten()->first(),
            ]);
        } catch (\Throwable) {
            Log::error('registration.verification.action_failed', ['event_id' => $event->id]);

            return $this->backToVerification($event)->withErrors([
                'verification' => 'We could not finish this step. Refresh and try again. Your registration is not complete until confirmation appears.',
            ]);
        }
    }

    private function backToVerification(Event $event): RedirectResponse
    {
        return redirect()->route('public-registration.verification', ['event' => $event->registration_slug]);
    }
}
