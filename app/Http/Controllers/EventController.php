<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Services\EventWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                'venue' => $event->venue,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'registration_start' => $event->registration_start?->toDateTimeString(),
                'registration_end' => $event->registration_end?->toDateTimeString(),
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', Rule::enum(EventType::class)],
            'event_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
        ]);

        Event::create([
            'created_by' => $request->user()->id,
            'event_type' => $validated['event_type'],
            'event_name' => $validated['event_name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'venue' => $validated['venue'] ?? null,
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
                'event_type_label' => $event->event_type->label(),
                'event_name' => $event->event_name,
                'description' => $event->description,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_time' => $event->end_time?->format('H:i'),
                'venue' => $event->venue,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
            ],
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        if (! $event->canEdit()) {
            abort(403, 'This event can no longer be edited.');
        }

        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
        ]);

        $event->update([
            'event_name' => $validated['event_name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'venue' => $validated['venue'] ?? null,
        ]);

        return redirect()->route('events.show', $event);
    }

    public function configure(Event $event): RedirectResponse
    {
        $workflow = app(EventWorkflow::class);

        if (! $workflow->canTransition($event, EventStatus::Configured)) {
            abort(403, 'Cannot configure event in its current state.');
        }

        $workflow->configure($event);

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
}
