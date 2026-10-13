<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Event;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicRegistrationController extends Controller
{
    public function show(Request $request, Event $event): Response
    {
        return Inertia::render('registrations/public', [
            'event' => $this->eventPayload($event),
            'fields' => $this->fieldsPayload($event),
            'common_field_requirements' => $this->resolveCommonFieldRequirements($event),
            'submit_url' => route('public-registration.store', [
                'event' => $event->registration_slug,
            ]),
            'success' => $request->session()->pull('success'),
        ]);
    }

    /** Initial submission creates only an encrypted pending challenge. */
    public function store(
        PublicRegistrationRequest $request,
        Event $event,
        RegistrationVerificationService $verification,
    ): RedirectResponse {
        try {
            $result = $verification->begin(
                $event,
                $request->validated(),
                is_string($token = $request->cookie(RegistrationVerificationService::cookieName($event))) ? $token : null,
            );
            $error = null;
            if ($result['created'] && $event->isCommonFieldRequired('email')) {
                $error = $verification->sendEmail($event, $result['token']);
            }
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Throwable) {
            Log::error('registration.verification.start_failed', ['event_id' => $event->id]);

            return back()->withErrors(['identity' => 'We could not start verification. Please try again.']);
        }

        $response = redirect()->route('public-registration.verification', ['event' => $event->registration_slug]);
        if ($error !== null) {
            $response->withErrors(['email' => $error]);
        }

        // Laravel EncryptCookies encrypts this cookie. Only its hash is stored
        // server-side; it is never included in a redirect URL or Inertia props.
        return $response->withCookie(cookie(
            RegistrationVerificationService::cookieName($event),
            $result['token'],
            60 * RegistrationVerificationService::CHALLENGE_TTL_HOURS,
            '/r/'.$event->registration_slug,
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }

    /** @return array<string, mixed> */
    private function eventPayload(Event $event): array
    {
        return [
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->toDateString(),
            'start_time' => $event->start_time?->format('H:i'),
            'venue' => $event->venue,
            'venue_address' => $event->venue_address,
            'venue_map_url' => $event->venue_map_url,
            'course_url' => $event->course_url,
            'description' => $event->description,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fieldsPayload(Event $event): array
    {
        return $event->registrationFields()
            ->orderBy('display_order')
            ->get()
            ->map(fn ($field) => [
                'id' => $field->id,
                'label' => $field->label,
                'field_type' => $field->field_type,
                'options' => $field->options ?? [],
                'is_required' => $field->is_required,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, bool> */
    private function resolveCommonFieldRequirements(Event $event): array
    {
        $resolved = [];
        foreach (Event::COMMON_FIELDS_TOGGLEABLE as $field) {
            $resolved[$field] = $event->isCommonFieldRequired($field);
        }

        return $resolved;
    }
}
