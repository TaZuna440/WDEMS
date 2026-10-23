<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Event;
use App\Models\RegistrationVerificationChallenge;
use App\Services\Participants\DuplicateReview;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use App\Support\RegistrationLegalText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicRegistrationController extends Controller
{
    public function show(Request $request, Event $event): Response
    {
        if ($event->isChurchEvent()) {
            return Inertia::render('registrations/church-public', [
                'event' => $event->only('event_name', 'church_category', 'registration_slug'),
                'common_field_requirements' => collect(Event::COMMON_FIELDS_TOGGLEABLE)
                    ->mapWithKeys(fn (string $field) => [$field => $event->isCommonFieldRequired($field)])
                    ->all(),
                'submit_url' => route('public-registration.store', ['event' => $event->registration_slug]),
                'disclaimer_paragraphs' => RegistrationLegalText::disclaimerFor($event),
                'privacy_consent_text' => RegistrationLegalText::privacyConsentFor($event),
            ]);
        }

        return $this->renderForm($event, $request->session()->pull('success'));
    }

    public function similarity(Request $request, Event $event): JsonResponse
    {
        abort_unless($event->acceptsPublicRegistration(), 404);
        $payload = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:255'],
            'last_name' => ['required', 'string', 'min:2', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'], 'suffix' => ['nullable', 'string', 'max:16'],
            'birthday' => ['nullable', 'date_format:Y-m-d'],
            'email' => ['nullable', 'email', 'max:255'], 'contact_number' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(['similar' => app(DuplicateReview::class)->hasSimilar($event, $payload)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function preview(Event $event): Response
    {
        abort_unless($event->isRunningEvent(), 404);
        abort_if($event->registration_form_saved_at === null, 404);

        return $this->renderForm($event, null, true);
    }

    private function renderForm(Event $event, ?string $success, bool $preview = false): Response
    {
        return Inertia::render('registrations/public', [
            'preview' => $preview,
            'form_version' => $event->registration_form_version ?? 1,
            'tshirt' => $event->registration_tshirt ? [
                'label' => $event->registration_tshirt['label'],
                'required' => $event->registration_tshirt['required'],
                'choices' => $event->registration_tshirt['choices'],
                'photos' => array_map(fn ($photo) => Storage::disk('public')->url($photo['path']), $event->registration_tshirt['photos']),
            ] : null,
            'preview_return_url' => $preview ? route('events.show', $event) : null,
            'event' => $this->eventPayload($event),
            'fields' => $this->fieldsPayload($event),
            'common_field_requirements' => $this->resolveCommonFieldRequirements($event),
            'submit_url' => $preview ? '' : route('public-registration.store', [
                'event' => $event->registration_slug,
            ]),
            'success' => $success,
            // W6 Step 1: disclaimer paragraphs + privacy consent text.
            'disclaimer_paragraphs' => RegistrationLegalText::disclaimerFor($event),
            'disclaimer_html' => $event->registration_disclaimer_html,
            'privacy_consent_text' => RegistrationLegalText::privacyConsentFor($event),
            // W6: localStorage key for the wizard draft.
            'event_slug' => $event->registration_slug ?? '',
        ]);
    }

    public function confirmed(Event $event): Response
    {
        if ($event->isChurchEvent()) {
            abort_unless(RegistrationVerificationChallenge::where('event_id', $event->id)->whereNotNull('finalized_at')->exists(), 404);
        }

        return Inertia::render('registrations/confirmed', [
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->toDateString(),
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
            $deviceToken = RegistrationVerificationService::deviceToken($request);
            if ($result['created'] && $event->isCommonFieldRequired('email') && ! $event->usesEmailOnlyRegistration()) {
                $error = $verification->sendEmail($event, $result['token'], $deviceToken);
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
        ))->withCookie(cookie(
            // Device trust identifier (W5.3). Server stores only the hash.
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

    /** @return array<string, mixed> */
    private function eventPayload(Event $event): array
    {
        return [
            'event_name' => $event->event_name,
            'event_type' => $event->event_type?->label(),
            'event_date' => $event->event_date?->toDateString(),
            'start_time' => $event->start_time?->format('H:i'),
            'end_time' => $event->end_time?->format('H:i'),
            'distance' => $event->distanceLabel(),
            'venue' => $event->venue,
            'venue_address' => $event->venue_address,
            'venue_map_url' => $event->venue_map_url,
            'description' => $event->description,
            'partners' => collect($event->partners ?? [])
                ->filter(fn ($partner) => is_array($partner)
                    && is_string($partner['name'] ?? null)
                    && trim($partner['name']) !== ''
                    && is_string($partner['type'] ?? null))
                ->map(fn (array $partner) => [
                    'name' => $partner['name'],
                    'type' => ucfirst($partner['type']),
                ])
                ->values()
                ->all(),
            'accessibility' => collect([
                'walkers_welcome' => 'Walkers welcome',
                'all_paces_welcome' => 'All paces welcome',
                'all_ages_welcome' => 'All ages welcome',
                'stroller_friendly' => 'Stroller friendly',
                'wheelchair_accessible' => 'Wheelchair accessible',
                'sweeper_present' => 'Sweeper present',
                'service_animals_allowed' => 'Service animals allowed',
                'leashed_pets_allowed' => 'Leashed pets allowed',
            ])->filter(fn ($label, $field) => (bool) $event->getAttribute($field))
                ->values()
                ->all(),
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
