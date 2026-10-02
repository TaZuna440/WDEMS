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
     * toggleable common fields (email, contact_number, address). The
     * frontend uses it to render asterisks and to decide which fields
     * are required client-side.
     *
     * `success` is read from the session flash set by store(). The
     * project does not share flash globally (see authentication.md),
     * so it is pulled here and passed explicitly.
     *
     * `submit_url` is passed explicitly so the frontend does not have
     * to reconstruct the route from window.location.
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
     * Duplicate rule (D5): one registration per email per event. The
     * check is a query, not a unique constraint — participants are
     * shared across events and the email column is not unique.
     *
     * When email is optional and not provided, both the duplicate
     * check and Participant identity reuse are skipped:
     *   - No email to match on, so the D5 rule cannot apply. Two
     *     submissions without email for the same event create two
     *     registrations. That is a deliberate limitation — an
     *     anonymous registration is by definition un-deduplicable.
     *   - firstOrCreate on [email => null, first_name, last_name]
     *     would match any earlier participant with a null email and
     *     the same name. A fresh Participant row is created instead.
     *
     * Find-or-create on Participant uses (email, first_name, last_name)
     * as the composite key when email is present. Same three values →
     * same person, reuse. Different name → different person.
     */
    public function store(PublicRegistrationRequest $request, Event $event): RedirectResponse
    {
        $validated = $request->validated();

        $email = $validated['email'] ?? null;
        $emailProvided = is_string($email) && $email !== '';

        if ($emailProvided) {
            $alreadyRegistered = Registration::where('event_id', $event->id)
                ->whereHas('participant', fn ($q) => $q->where('email', $email))
                ->exists();

            if ($alreadyRegistered) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already registered for this event.',
                ]);
            }
        }

        DB::transaction(function () use ($event, $validated, $email, $emailProvided): void {
            $participantAttributes = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'age' => $validated['age'],
                'contact_number' => $validated['contact_number'] ?? null,
                'address' => $validated['address'] ?? null,
            ];

            if ($emailProvided) {
                $participant = Participant::firstOrCreate(
                    [
                        'email' => $email,
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                    ],
                    $participantAttributes,
                );
            } else {
                // No email → no identity key. Always create a new row.
                $participant = Participant::create($participantAttributes);
            }

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
     * Resolve the toggleable common field requirements into a
     * three-key map for the page.
     *
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
