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
use RuntimeException;

class EventController extends Controller
{
    /**
     * Slug alphabet. Lowercase letters a–z except i, l, o, plus digits
     * 2–9. Excludes the confusables I, L, O, 0, 1 to keep slugs
     * readable over the phone and paste-able in chat. 31 characters.
     */
    private const SLUG_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const SLUG_LENGTH = 8;

    private const SLUG_MAX_ATTEMPTS = 3;

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
                'registration_slug' => $event->registration_slug,
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
        $event->registration_slug = $this->generateUniqueSlug();
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
     * Generate a slug that does not collide with any existing event.
     *
     * Format: 8 characters from SLUG_ALPHABET. 31^8 ≈ 8.5×10^11 possible
     * slugs. Collisions are retried up to SLUG_MAX_ATTEMPTS times; a
     * persistent collision throws rather than silently producing a
     * duplicate or an empty slug.
     *
     * The throw is effectively unreachable at any realistic scale — the
     * loop exists so the failure mode is explicit if the invariant is
     * ever violated (e.g. a manual data import with a hostile seed).
     */
    private function generateUniqueSlug(): string
    {
        for ($attempt = 0; $attempt < self::SLUG_MAX_ATTEMPTS; $attempt++) {
            $candidate = $this->randomSlug();

            if (! Event::where('registration_slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique registration slug after '
            .self::SLUG_MAX_ATTEMPTS.' attempts.',
        );
    }

    /**
     * Build a random slug from SLUG_ALPHABET. Uniqueness is checked by
     * the caller, not here.
     */
    private function randomSlug(): string
    {
        $max = strlen(self::SLUG_ALPHABET) - 1;
        $slug = '';

        for ($i = 0; $i < self::SLUG_LENGTH; $i++) {
            $slug .= self::SLUG_ALPHABET[random_int(0, $max)];
        }

        return $slug;
    }
}
