<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationFieldResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicRegistrationController extends Controller
{
    /**
     * Render the public registration form.
     *
     * `common_field_requirements` is a resolved three-key map for the
     * toggleable common fields. `success` is read from the session
     * flash set by store(). `submit_url` is passed explicitly so the
     * frontend does not have to reconstruct the route.
     */
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

    /**
     * Accept a submission.
     *
     * Identity resolution goes through Participant::resolveFrom().
     * Email is the primary key when present; normalized phone is the
     * fallback. The rule that at least one must be present is enforced
     * upstream by PublicRegistrationRequest::withValidator().
     *
     * Duplicate rule (D5): one registration per participant per event.
     * The check is a pre-query against the resolved participant, not a
     * whereHas on email — this makes it work uniformly for email-based
     * and phone-based identities. The (event_id, participant_id) UNIQUE
     * constraint on the registrations table is the final backstop if
     * two requests race.
     *
     * Behavior change from the pre-identity version: a second
     * submission sharing a phone number with an earlier registration
     * on the same event is now rejected with a validation error
     * (previously produced a 500 from the UNIQUE constraint on
     * contact_number_normalized). Closes ISSUE-009.
     */
    public function store(PublicRegistrationRequest $request, Event $event): RedirectResponse
    {
        $validated = $request->validated();

        $email = $validated['email'] ?? null;
        $phone = $validated['contact_number'] ?? null;

        // Pre-check: does a participant already exist with this
        // identity? If so, and they are already registered for this
        // event, reject with a field-anchored error.
        $existingParticipant = $this->findExistingParticipant($email, $phone);

        if ($existingParticipant !== null) {
            $alreadyRegistered = Registration::where('event_id', $event->id)
                ->where('participant_id', $existingParticipant->id)
                ->exists();

            if ($alreadyRegistered) {
                throw $this->duplicateRegistrationError($email, $phone);
            }
        }

        DB::transaction(function () use ($event, $validated, $email, $phone): void {
            $participant = Participant::resolveFrom($email, $phone, [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'age' => $validated['age'],
                'contact_number' => $phone,
                'address' => $validated['address'] ?? null,
            ]);

            $registration = Registration::create([
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'registration_date' => now(),
                'registration_status' => RegistrationStatus::Confirmed,
                'source' => 'form',
                'registered_at' => now(),
            ]);

            foreach ($validated['responses'] ?? [] as $fieldId => $value) {
                RegistrationFieldResponse::create([
                    'registration_id' => $registration->id,
                    'registration_field_id' => (int) $fieldId,
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                ]);
            }
        });

        return redirect()
            ->route('public-registration.show', ['event' => $event->registration_slug])
            ->with('success', 'Your registration is confirmed.');
    }

    /**
     * Look up a participant by the identity rule used in §6 of
     * docs/participant-identity.md: email wins when non-blank,
     * normalized phone is the fallback. Returns null when neither
     * key matches an existing participant.
     */
    private function findExistingParticipant(?string $email, ?string $phone): ?Participant
    {
        $trimmedEmail = is_string($email) ? trim($email) : '';

        if ($trimmedEmail !== '') {
            return Participant::where('email', $trimmedEmail)->first();
        }

        $normalized = Participant::normalizeContactNumber($phone);

        if ($normalized !== null) {
            return Participant::where('contact_number_normalized', $normalized)->first();
        }

        return null;
    }

    /**
     * Build the appropriate validation error for a duplicate
     * registration. The error key is the identity field that matched
     * (email or contact_number), so the frontend's InputError slot
     * lands under the correct field.
     */
    private function duplicateRegistrationError(?string $email, ?string $phone): ValidationException
    {
        $trimmedEmail = is_string($email) ? trim($email) : '';

        if ($trimmedEmail !== '') {
            return ValidationException::withMessages([
                'email' => 'This email is already registered for this event.',
            ]);
        }

        return ValidationException::withMessages([
            'contact_number' => 'This contact number is already registered for this event.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<string, bool>
     */
    private function resolveCommonFieldRequirements(Event $event): array
    {
        $resolved = [];

        foreach (Event::COMMON_FIELDS_TOGGLEABLE as $field) {
            $resolved[$field] = $event->isCommonFieldRequired($field);
        }

        return $resolved;
    }
}
