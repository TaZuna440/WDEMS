<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Services\EventWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->with('creator:id,name')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_type' => $event->event_type->value,
                'event_type_label' => $event->event_type->label(),
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'distance_label' => $event->distanceLabel(),
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'creator' => $event->creator?->name,
            ]);

        return Inertia::render('events/index', [
            'events' => $events,
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        $event->load('creator:id,name');
        $related = $event->relatedRecordCounts();

        return Inertia::render('events/show', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_type' => $event->event_type->value,
                'event_type_label' => $event->event_type->label(),
                'description' => $event->description,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_time' => $event->end_time?->format('H:i'),
                'distance_value' => $event->distance_value,
                'distance_unit' => $event->distance_unit,
                'distance_label' => $event->distanceLabel(),
                'course_url' => $event->course_url,
                'venue' => $event->venue,
                'venue_address' => $event->venue_address,
                'venue_map_url' => $event->venue_map_url,
                'venue_latitude' => $event->venue_latitude,
                'venue_longitude' => $event->venue_longitude,
                'partners' => $event->partners,
                'faq' => $event->faq,
                'walkers_welcome' => $event->walkers_welcome,
                'all_paces_welcome' => $event->all_paces_welcome,
                'all_ages_welcome' => $event->all_ages_welcome,
                'stroller_friendly' => $event->stroller_friendly,
                'wheelchair_accessible' => $event->wheelchair_accessible,
                'sweeper_present' => $event->sweeper_present,
                'service_animals_allowed' => $event->service_animals_allowed,
                'leashed_pets_allowed' => $event->leashed_pets_allowed,
                'quiet_space_available' => $event->quiet_space_available,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'registration_start' => $event->registration_start?->toDateTimeString(),
                'registration_end' => $event->registration_end?->toDateTimeString(),
                'registration_form_saved_at' => $event->registration_form_saved_at?->toDateTimeString(),
                'creator' => $event->creator?->name,
                'created_at' => $event->created_at?->toDateTimeString(),
                'can_edit' => $event->canEdit(),
                'can_open_registration' => $event->canOpenRegistration(),
                'can_close_registration' => $event->canCloseRegistration(),
            ],
            'related' => $related,
            'has_related_records' => array_sum($related) > 0,
            'requires_otp' => ! $request->user()->isAdmin(),
            'can_record_attendance' => $event->canRecordAttendance(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('events/create', [
            'event_types' => collect(EventType::cases())
                ->map(fn (EventType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values(),
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Event::create([
            'created_by' => $request->user()->id,
            'event_type' => $validated['event_type'],
            'event_name' => $validated['event_name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'distance_value' => $validated['distance_value'],
            'distance_unit' => $validated['distance_unit'],
            'course_url' => $validated['course_url'] ?? null,
            'venue' => $validated['venue'],
            'venue_address' => $validated['venue_address'],
            'venue_map_url' => $validated['venue_map_url'] ?? null,
            'venue_latitude' => $validated['venue_latitude'] ?? null,
            'venue_longitude' => $validated['venue_longitude'] ?? null,
            'partners' => $validated['partners'] ?? null,
            'faq' => $this->defaultFaq(),
            'walkers_welcome' => $validated['walkers_welcome'] ?? false,
            'all_paces_welcome' => $validated['all_paces_welcome'] ?? false,
            'all_ages_welcome' => $validated['all_ages_welcome'] ?? false,
            'stroller_friendly' => $validated['stroller_friendly'] ?? false,
            'wheelchair_accessible' => $validated['wheelchair_accessible'] ?? false,
            'sweeper_present' => $validated['sweeper_present'] ?? false,
            'service_animals_allowed' => $validated['service_animals_allowed'] ?? false,
            'leashed_pets_allowed' => $validated['leashed_pets_allowed'] ?? false,
            'quiet_space_available' => $validated['quiet_space_available'] ?? false,
            'status' => EventStatus::Draft,
        ]);

        return redirect()->route('events.index');
    }

    public function edit(Event $event): Response
    {
        if (! $event->canEdit()) {
            abort(403, 'This event can no longer be edited.');
        }

        return Inertia::render('events/edit', [
            'event' => [
                'id' => $event->id,
                'event_type' => $event->event_type->value,
                'event_type_label' => $event->event_type->label(),
                'event_name' => $event->event_name,
                'description' => $event->description,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_time' => $event->end_time?->format('H:i'),
                'distance_value' => $event->distance_value,
                'distance_unit' => $event->distance_unit,
                'course_url' => $event->course_url,
                'venue' => $event->venue,
                'venue_address' => $event->venue_address,
                'venue_map_url' => $event->venue_map_url,
                'venue_latitude' => $event->venue_latitude,
                'venue_longitude' => $event->venue_longitude,
                'partners' => $event->partners,
                'faq' => $event->faq,
                'walkers_welcome' => $event->walkers_welcome,
                'all_paces_welcome' => $event->all_paces_welcome,
                'all_ages_welcome' => $event->all_ages_welcome,
                'stroller_friendly' => $event->stroller_friendly,
                'wheelchair_accessible' => $event->wheelchair_accessible,
                'sweeper_present' => $event->sweeper_present,
                'service_animals_allowed' => $event->service_animals_allowed,
                'leashed_pets_allowed' => $event->leashed_pets_allowed,
                'quiet_space_available' => $event->quiet_space_available,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
            ],
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        if (! $event->canEdit()) {
            abort(403, 'This event can no longer be edited.');
        }

        $validated = $request->validated();

        $event->update([
            'event_name' => $validated['event_name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'distance_value' => $validated['distance_value'],
            'distance_unit' => $validated['distance_unit'],
            'course_url' => $validated['course_url'] ?? null,
            'venue' => $validated['venue'],
            'venue_address' => $validated['venue_address'],
            'venue_map_url' => $validated['venue_map_url'] ?? null,
            'venue_latitude' => $validated['venue_latitude'] ?? null,
            'venue_longitude' => $validated['venue_longitude'] ?? null,
            'partners' => $validated['partners'] ?? null,
            'walkers_welcome' => $validated['walkers_welcome'] ?? false,
            'all_paces_welcome' => $validated['all_paces_welcome'] ?? false,
            'all_ages_welcome' => $validated['all_ages_welcome'] ?? false,
            'stroller_friendly' => $validated['stroller_friendly'] ?? false,
            'wheelchair_accessible' => $validated['wheelchair_accessible'] ?? false,
            'sweeper_present' => $validated['sweeper_present'] ?? false,
            'service_animals_allowed' => $validated['service_animals_allowed'] ?? false,
            'leashed_pets_allowed' => $validated['leashed_pets_allowed'] ?? false,
            'quiet_space_available' => $validated['quiet_space_available'] ?? false,
        ]);

        return redirect()->route('events.show', $event);
    }


    public function openRegistration(Event $event): RedirectResponse
    {
        if (! $event->canOpenRegistration()) {
            abort(403, 'Cannot open registration for this event in its current state.');
        }

        $workflow = app(EventWorkflow::class);

        if (! $workflow->canTransition($event, EventStatus::RegistrationOpen)) {
            abort(403, 'Cannot open registration for this event.');
        }

        $event->registration_start = now();
        $event->save();

        $workflow->openRegistration($event);

        return redirect()->route('events.show', $event);
    }

    public function closeRegistration(Event $event): RedirectResponse
    {
        if (! $event->canCloseRegistration()) {
            abort(403, 'Cannot close registration for this event in its current state.');
        }

        $workflow = app(EventWorkflow::class);

        if (! $workflow->canTransition($event, EventStatus::RegistrationClosed)) {
            abort(403, 'Cannot close registration for this event.');
        }

        $event->registration_end = now();
        $event->save();

        $workflow->closeRegistration($event);

        return redirect()->route('events.show', $event);
    }

    /**
     * Default FAQ entries seeded on event creation.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    private function defaultFaq(): array
    {
        return [
            [
                'question' => 'Is it OK if I walk?',
                'answer' => 'Yes — all paces are welcome. A sweeper stays at the back so nobody is left alone.',
            ],
            [
                'question' => 'Can I bring my family?',
                'answer' => 'Yes — all ages are welcome. Strollers and young children are fine unless noted otherwise.',
            ],
            [
                'question' => 'Are dogs allowed?',
                'answer' => 'Friendly, leashed dogs are welcome at most events. Check the accessibility section for this specific event.',
            ],
            [
                'question' => 'Do I need to RSVP?',
                'answer' => 'Check the event details — some events ask for a headcount while others welcome walk-ins.',
            ],
            [
                'question' => 'What should I bring?',
                'answer' => 'Water, comfortable running shoes, and weather-appropriate clothing.',
            ],
            [
                'question' => 'Where do we meet?',
                'answer' => 'See the venue address above. Arrive 10–15 minutes early to check in.',
            ],
        ];
    }
}
