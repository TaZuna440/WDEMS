<?php

namespace App\Http\Controllers;

use App\Enums\ChurchCategory;
use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Services\EventWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class EventController extends Controller
{
    /**
     * Slug alphabet. Lowercase letters a-z except i, l, o, plus digits
     * 2-9. Excludes the confusables I, L, O, 0, 1 to keep slugs
     * readable over the phone and paste-able in chat. 31 characters.
     */
    private const SLUG_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const SLUG_LENGTH = 8;

    private const SLUG_MAX_ATTEMPTS = 3;

    /**
     * List all events, optionally filtered by kind.
     *
     * The `kind` query parameter accepts 'running' or 'church'. Absent
     * or invalid values return all events, preserving the previous
     * behavior for callers that do not filter.
     *
     * `status_label` is dispatched through Event::statusLabel() so
     * church events display their ChurchEventStatus value and running
     * events display their EventStatus value.
     */
    public function index(Request $request): Response
    {
        $kindFilter = $request->input('kind');
        $validKinds = [Event::KIND_RUNNING, Event::KIND_CHURCH];

        $query = Event::query()->with('creator:id,name');

        if (is_string($kindFilter) && in_array($kindFilter, $validKinds, true)) {
            $query->where('event_kind', $kindFilter);
        }

        $events = $query
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'event_kind' => $event->event_kind,
                'event_name' => $event->event_name,
                'event_type' => $event->event_type->value,
                'event_type_label' => $event->event_type->label(),
                'church_category' => $event->church_category,
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'distance_label' => $event->distanceLabel(),
                'status' => $event->isChurchEvent()
                    ? $event->church_status?->value
                    : $event->status->value,
                'status_label' => $event->statusLabel(),
                'creator' => $event->creator?->name,
            ]);

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => [
                'kind' => is_string($kindFilter) && in_array($kindFilter, $validKinds, true)
                    ? $kindFilter
                    : null,
            ],
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        $event->load('creator:id,name');
        $related = $event->relatedRecordCounts();

        return Inertia::render('events/show', [
            'event' => [
                'id' => $event->id,
                'event_kind' => $event->event_kind,
                'church_category' => $event->church_category,
                'church_status' => $event->church_status?->value,
                'event_type' => $event->event_type->value,
                'event_type_label' => $event->event_type->label(),
                'event_name' => $event->event_name,
                'description' => $event->description,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_time' => $event->end_time?->format('H:i'),
                'distance_value' => $event->distance_value,
                'distance_unit' => $event->distance_unit,
                'distance_label' => $event->distanceLabel(),
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
                'status' => $event->isChurchEvent()
                    ? $event->church_status?->value
                    : $event->status->value,
                'status_label' => $event->statusLabel(),
                'registration_start' => $event->registration_start?->toDateTimeString(),
                'registration_end' => $event->registration_end?->toDateTimeString(),
                'registration_form_saved_at' => $event->registration_form_saved_at?->toDateTimeString(),
                'registration_slug' => $event->registration_slug,
                'creator' => $event->creator?->name,
                'created_at' => $event->created_at?->toDateTimeString(),
                'can_edit' => $event->canEdit(),
                'can_open_registration' => $event->canOpenRegistration(),
                'can_close_registration' => $event->canCloseRegistration(),
                'can_finalize' => $event->canFinalize(),
            ],
            'related' => $related,
            'has_related_records' => array_sum($related) > 0,
            'requires_otp' => ! $request->user()->isAdmin(),
            'can_record_attendance' => $event->canRecordAttendance(),
        ]);
    }

    /**
     * Show the create-entry page.
     *
     * Two cards: Community Run and Church Event. Each routes into
     * the appropriate wizard or category picker.
     */
    public function create(): Response
    {
        return Inertia::render('events/create-entry');
    }

    /**
     * Show the running-event wizard with the type locked.
     */
    public function createCommunityRun(): Response
    {
        return Inertia::render('events/create', [
            'event_types' => collect([EventType::CommunityRun])
                ->map(fn (EventType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values(),
            'church_categories' => collect(ChurchCategory::cases())
                ->map(fn (ChurchCategory $category) => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])
                ->values(),
            'preset_event_type' => EventType::CommunityRun->value,
            'preset_church_category' => null,
            'lock_type' => true,
            'storage_key' => 'wdems-wizard-create-community-run',
        ]);
    }

    /**
     * Show the church category cards in create mode.
     */
    public function createChurch(): Response
    {
        return Inertia::render('events/church/create-cards');
    }

    /**
     * Show the unified church wizard with a category preset.
     *
     * Only the six unbuilt categories route here. The three built
     * ones (Services, Fellowship, Discipleship) have their own
     * landing pages and creation flows — the create cards route
     * directly there.
     */
    public function createChurchCategory(string $category): Response
    {
        $valid = [
            'kids',
            'young-adult',
            'worship-prayer',
            'special-occasions',
            'church-administration',
        ];

        if (! in_array($category, $valid, true)) {
            abort(404);
        }

        // The wizard restricts church_category to the six unbuilt
        // categories so a user cannot accidentally create a plain
        // event with a category that has its own module.
        return Inertia::render('events/create', [
            'event_types' => collect([EventType::Church])
                ->map(fn (EventType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values(),
            'church_categories' => collect(ChurchCategory::cases())
                ->filter(fn (ChurchCategory $c) => in_array($c->value, [
                    'Kids Ministry', 'Young Adult',
                    'Worship & Prayer', 'Special Occasions',
                    'Church Administration',
                ], true))
                ->map(fn (ChurchCategory $category) => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])
                ->values(),
            'preset_event_type' => EventType::Church->value,
            'preset_church_category' => ChurchCategory::from(match ($category) {
                'kids' => 'Kids Ministry',
                'young-adult' => 'Young Adult',
                'worship-prayer' => 'Worship & Prayer',
                'special-occasions' => 'Special Occasions',
                'church-administration' => 'Church Administration',
            })->value,
            'lock_type' => true,
            'storage_key' => "wdems-wizard-create-church-{$category}",
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $kind = $validated['event_kind'] ?? Event::KIND_RUNNING;
        $isChurch = $kind === Event::KIND_CHURCH;

        Event::create([
            'created_by' => $request->user()->id,
            'event_kind' => $kind,
            'church_category' => $isChurch ? ($validated['church_category'] ?? null) : null,
            'church_status' => $isChurch ? ($validated['church_status'] ?? null) : null,
            'event_type' => $validated['event_type'],
            'event_name' => $validated['event_name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'distance_value' => $isChurch ? null : ($validated['distance_value'] ?? null),
            'distance_unit' => $isChurch ? null : ($validated['distance_unit'] ?? null),
            'venue' => $validated['venue'],
            'venue_address' => $validated['venue_address'],
            'venue_map_url' => $validated['venue_map_url'] ?? null,
            'venue_latitude' => $validated['venue_latitude'] ?? null,
            'venue_longitude' => $validated['venue_longitude'] ?? null,
            'partners' => $isChurch ? null : ($validated['partners'] ?? null),
            'walkers_welcome' => $isChurch ? false : ($validated['walkers_welcome'] ?? false),
            'all_paces_welcome' => $isChurch ? false : ($validated['all_paces_welcome'] ?? false),
            'all_ages_welcome' => $isChurch ? false : ($validated['all_ages_welcome'] ?? false),
            'stroller_friendly' => $isChurch ? false : ($validated['stroller_friendly'] ?? false),
            'wheelchair_accessible' => $isChurch ? false : ($validated['wheelchair_accessible'] ?? false),
            'sweeper_present' => $isChurch ? false : ($validated['sweeper_present'] ?? false),
            'service_animals_allowed' => $isChurch ? false : ($validated['service_animals_allowed'] ?? false),
            'leashed_pets_allowed' => $isChurch ? false : ($validated['leashed_pets_allowed'] ?? false),
            // Running events start in Draft. Church events do not use
            // the running status machine, but the `status` column is
            // not nullable, so they also default to Draft. The UI reads
            // church_status for church events, not status.
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
                'event_kind' => $event->event_kind,
                'church_category' => $event->church_category,
                'church_status' => $event->church_status?->value,
                'event_type' => $event->event_type->value,
                'event_type_label' => $event->event_type->label(),
                'event_name' => $event->event_name,
                'description' => $event->description,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_time' => $event->end_time?->format('H:i'),
                'distance_value' => $event->distance_value,
                'distance_unit' => $event->distance_unit,
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
                'status' => $event->isChurchEvent()
                    ? $event->church_status?->value
                    : $event->status->value,
                'status_label' => $event->statusLabel(),
            ],
            'church_categories' => collect(ChurchCategory::cases())
                ->map(fn (ChurchCategory $category) => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])
                ->values(),
        ]);
    }

    /**
     * Update an existing event.
     *
     * event_kind and event_type are locked after creation (see
     * event-creation.md Trap #2 for the running-event rationale,
     * applied identically here). Church-specific fields are updatable
     * only for church events.
     *
     * Church-status transitions are validated before the write:
     * each requested church_status change must be legal per
     * ChurchEventStatus::allowedTransitions(). See
     * assertChurchStatusTransitionIsLegal() for the rule and
     * docs/audit-2026-10-19.md ISSUE-014 for the reason Phase A
     * ships this check.
     *
     * event_kind is not in the update payload, so a church event
     * cannot be converted into a running event (or vice versa) via
     * this action.
     */
    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        if (! $event->canEdit()) {
            abort(403, 'This event can no longer be edited.');
        }

        $validated = $request->validated();
        $isChurch = $event->isChurchEvent();

        if ($isChurch) {
            $this->assertChurchStatusTransitionIsLegal(
                $event,
                $validated['church_status'] ?? null,
            );
        }

        $event->update([
            'church_category' => $isChurch
                ? ($validated['church_category'] ?? $event->church_category)
                : $event->church_category,
            'church_status' => $isChurch
                ? ($validated['church_status'] ?? $event->church_status?->value)
                : $event->church_status,
            'event_name' => $validated['event_name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'distance_value' => $isChurch
                ? $event->distance_value
                : ($validated['distance_value'] ?? null),
            'distance_unit' => $isChurch
                ? $event->distance_unit
                : ($validated['distance_unit'] ?? null),
            'venue' => $validated['venue'],
            'venue_address' => $validated['venue_address'],
            'venue_map_url' => $validated['venue_map_url'] ?? null,
            'venue_latitude' => $validated['venue_latitude'] ?? null,
            'venue_longitude' => $validated['venue_longitude'] ?? null,
            'partners' => $isChurch
                ? $event->partners
                : ($validated['partners'] ?? null),
            'walkers_welcome' => $isChurch
                ? $event->walkers_welcome
                : ($validated['walkers_welcome'] ?? false),
            'all_paces_welcome' => $isChurch
                ? $event->all_paces_welcome
                : ($validated['all_paces_welcome'] ?? false),
            'all_ages_welcome' => $isChurch
                ? $event->all_ages_welcome
                : ($validated['all_ages_welcome'] ?? false),
            'stroller_friendly' => $isChurch
                ? $event->stroller_friendly
                : ($validated['stroller_friendly'] ?? false),
            'wheelchair_accessible' => $isChurch
                ? $event->wheelchair_accessible
                : ($validated['wheelchair_accessible'] ?? false),
            'sweeper_present' => $isChurch
                ? $event->sweeper_present
                : ($validated['sweeper_present'] ?? false),
            'service_animals_allowed' => $isChurch
                ? $event->service_animals_allowed
                : ($validated['service_animals_allowed'] ?? false),
            'leashed_pets_allowed' => $isChurch
                ? $event->leashed_pets_allowed
                : ($validated['leashed_pets_allowed'] ?? false),
        ]);

        return redirect()->route('events.show', $event);
    }

    /**
     * Validate a requested church_status change against the current
     * one, using ChurchEventStatus::allowedTransitions() as the
     * source of truth.
     *
     * No-op when:
     *   - The request did not submit a church_status (absence means
     *     "keep the current value" -- the update() payload already
     *     falls back to $event->church_status).
     *   - The submitted value equals the current value (no change,
     *     no transition to validate).
     *
     * Throws ValidationException with a church_status error otherwise.
     *
     * The target enum value is validated by Rule::enum in
     * EventValidationRules::eventRules(). tryFrom returns null only
     * if a caller somehow bypassed the rule -- the throw is
     * defense-in-depth.
     */
    private function assertChurchStatusTransitionIsLegal(
        Event $event,
        ?string $requested,
    ): void {
        if ($requested === null || $requested === '') {
            return;
        }

        $current = $event->church_status;
        $target = ChurchEventStatus::tryFrom($requested);

        if ($target === null) {
            throw ValidationException::withMessages([
                'church_status' => 'Invalid church status value.',
            ]);
        }

        if ($current === null) {
            throw ValidationException::withMessages([
                'church_status' => 'This event has no current church status to transition from.',
            ]);
        }

        if ($current === $target) {
            return;
        }

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'church_status' => "Cannot transition from {$current->label()} to {$target->label()}.",
            ]);
        }
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

        return redirect()->route('registration-monitor.show', $event);
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
     * Finalize the event — the terminal transition from Ongoing to
     * Completed.
     *
     * Feature B of docs/attendance-encoding-and-reporting.md.
     * Password-gated at the route via Laravel's RequirePassword
     * middleware, matching the settings/security endpoints. The
     * controller does not validate a password itself; a missing or
     * expired password session redirects to /confirm-password and
     * back.
     *
     * Guard order:
     *   1. canFinalize() — status must be Ongoing.
     *   2. workflow->canTransition() — defense-in-depth, catches a
     *      state change between the guard and the transition.
     *
     * Idempotent? No. A second POST returns 403 because the status
     * is no longer Ongoing. This is intentional — the button
     * disappears after the first click.
     *
     * Redirect target is events.report — Feature C's end-of-event
     * report. After a successful finalize the user lands on the
     * report, not the event show page. This matches section 5.2 of
     * the same doc: the report is the natural next destination once
     * the event is locked.
     */
    public function finalize(Event $event): RedirectResponse
    {
        if (! $event->canFinalize()) {
            abort(403, 'This event cannot be finalized in its current state.');
        }

        $workflow = app(EventWorkflow::class);

        if (! $workflow->canTransition($event, EventStatus::Completed)) {
            abort(403, 'This event cannot be finalized.');
        }

        $workflow->complete($event);

        Log::info('event.finalized', [
            'event_id' => $event->id,
            'actor_id' => auth()->id(),
            'fired_at' => now()->toIso8601String(),
        ]);

        return redirect()
            ->route('events.report', $event)
            ->with('success', 'Event finalized. Attendance and registration data are now read-only.');
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
