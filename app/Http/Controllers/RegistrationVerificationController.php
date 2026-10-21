<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationVerificationController extends Controller
{
    public function __construct(private readonly RegistrationVerificationService $verification) {}

    public function show(Request $request, Event $event): Response|RedirectResponse
    {
        $verification = $this->verification->publicState($event, $this->token($request, $event));

        if ($verification['status'] === 'acknowledged') {
            return redirect()->route('public-registration.confirmed', ['event' => $event->registration_slug]);
        }

        return Inertia::render('registrations/verify', [
            'event_name' => $event->event_name,
            'email_only' => $event->usesEmailOnlyRegistration(),
            'recaptcha_site_key' => $event->usesEmailOnlyRegistration() ? config('services.recaptcha.site_key') : null,
            'verification' => $verification,
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

        $deviceToken = $channel === 'email'
            ? RegistrationVerificationService::deviceToken($request)
            : null;

        $response = $this->publicAction($event, $channel, fn () => $this->verification->verify(
            $event, $this->token($request, $event), $channel, $validator->validated()['code'], $deviceToken,
        ));

        return $this->withDeviceTrustCookie($request, $response, $deviceToken);
    }

    public function resendEmail(Request $request, Event $event): RedirectResponse
    {
        $deviceToken = RegistrationVerificationService::deviceToken($request);

        $response = $this->publicAction($event, 'email', fn () => $this->verification->sendEmail(
            $event, $this->token($request, $event), $deviceToken,
            is_string($request->input('recaptcha_token')) ? $request->input('recaptcha_token') : null,
        ));

        return $this->withDeviceTrustCookie($request, $response, $deviceToken);
    }

    public function complete(Request $request, Event $event): RedirectResponse
    {
        return $this->publicAction($event, 'verification', function () use ($request, $event): ?string {
            $this->verification->finalize($event, $this->token($request, $event));

            return null;
        });
    }

    public function acknowledge(Request $request, Event $event): RedirectResponse
    {
        try {
            $this->verification->acknowledgeCode($event, $this->token($request, $event));
        } catch (ValidationException $exception) {
            return $this->backToVerification($event)->withErrors([
                'verification' => collect($exception->errors())->flatten()->first(),
            ]);
        } catch (\Throwable) {
            Log::error('registration.verification.acknowledgement_failed', ['event_id' => $event->id]);

            return $this->backToVerification($event)->withErrors([
                'verification' => 'We could not finish this step. Refresh and try again.',
            ]);
        }

        return redirect()->route($request->boolean('start_another') ? 'public-registration.show' : 'public-registration.confirmed', ['event' => $event->registration_slug])
            ->withCookie(cookie(
                RegistrationVerificationService::cookieName($event),
                '',
                -1,
                '/r/'.$event->registration_slug,
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
    }

    public function chooseChannel(Request $request, Event $event): RedirectResponse
    {
        $validator = Validator::make($request->only('channel'), [
            'channel' => ['required', 'string', 'in:email,phone'],
        ]);
        if ($validator->fails()) {
            return $this->backToVerification($event)->withErrors([
                'channel' => 'Choose email or phone verification.',
            ]);
        }

        return $this->publicAction($event, 'channel', fn () => $this->verification->chooseChannel(
            $event, $this->token($request, $event), $validator->validated()['channel'],
        ));
    }

    /**
     * Attach (or refresh) the device-trust cookie. The cookie carries
     * only a random device identifier; trust itself is recorded
     * server-side on successful email verification.
     */
    private function withDeviceTrustCookie(
        Request $request,
        RedirectResponse $response,
        ?string $deviceToken = null,
    ): RedirectResponse {
        $deviceToken ??= RegistrationVerificationService::deviceToken($request);

        return $response->withCookie(cookie(
            RegistrationVerificationService::DEVICE_TRUST_COOKIE,
            $deviceToken,
            60 * 24 * RegistrationVerificationService::DEVICE_TRUST_TTL_DAYS,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
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
