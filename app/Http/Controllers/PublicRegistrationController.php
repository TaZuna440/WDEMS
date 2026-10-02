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
     * The middleware already guaranteed the event is RegistrationOpen
     * and within registration_end. The payload is the six common
     * fields (rendered as a fixed block on the frontend) plus the
     * event's custom fields.
     *
     * `success` is read from the session flash set by store(). The
     * project does not share flash globally (see authentication.md),
     * so it is pulled here and passed explicitly. pull() removes it
     * from the session — subsequent requests do not see it.
     *
     * `submit_url` is passed explicitly so the frontend does not have
     * to reconstruct the route from window.location.
     */
    public function show(Request $request, Event $event): Response
    {
        return Inertia::render('registrations/public', [
            'event' => $this->eventPayload($event),
            'fields' => $this->fieldsPayload($event),
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
     * Find-or-create on Participant uses (email, first_name, last_name)
     * as the composite key. Same three values → same person, reuse.
     * Different name → different person, create new.
     */
    public function store(PublicRegistrationRequest $request, Event $event): RedirectResponse
    {
        $validated = $request->validated();

        $alreadyRegistered = Registration::where('event_id', $event->id)
            ->whereHas('participant', fn ($q) => $q->where('email', $validated['email']))
            ->exists();

        if ($alreadyRegistered) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered for this event.',
            ]);
        }

        DB::transaction(function () use ($event, $validated): void {
            $participant = Participant::firstOrCreate(
                [
                    'email' => $validated['email'],
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                ],
                [
                    'contact_number' => $validated['contact_number'],
                    'age' => $validated['age'],
                    'address' => $validated['address'] ?? null,
                ],
            );

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
}
