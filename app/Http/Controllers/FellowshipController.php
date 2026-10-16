<?php

namespace App\Http\Controllers;

use App\Enums\FellowshipType;
use App\Http\Requests\Fellowship\StoreFellowshipEventRequest;
use App\Http\Requests\Fellowship\UpdateFellowshipEventRequest;
use App\Models\FellowshipEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fellowship module -- landing, per-type event list, event creation,
 * update, deletion.
 *
 * Attendance recording is in FellowshipAttendanceController.
 *
 * Phase 4, per client mockup.
 */
class FellowshipController extends Controller
{
    /**
     * Landing: fellowship type cards with counts.
     *
     * Shows each of the six fellowship types with a small aggregate
     * (total events, latest event date). Clicking a card navigates
     * to show().
     */
    public function index(): Response
    {
        $types = collect(FellowshipType::cases())->map(function (FellowshipType $type) {
            $events = FellowshipEvent::where('fellowship_type', $type->value);

            return [
                'value' => $type->value,
                'label' => $type->label(),
                'event_count' => (clone $events)->count(),
                'latest_date' => (clone $events)
                    ->orderByDesc('event_date')
                    ->value('event_date'),
            ];
        })->values();

        return Inertia::render('fellowship/index', [
            'fellowship_types' => $types,
        ]);
    }

    /**
     * Per-type view: list events for one fellowship type, most
     * recent first. Includes group and attendance counts so the
     * organizer can see at a glance how full each event is.
     */
    public function show(Request $request, string $type): Response
    {
        $fellowshipType = FellowshipType::tryFrom($type);

        if ($fellowshipType === null) {
            abort(404);
        }

        $events = FellowshipEvent::query()
            ->where('fellowship_type', $fellowshipType->value)
            ->withCount(['groups', 'attendances'])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FellowshipEvent $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'description' => $e->description,
                'event_date' => $e->event_date->toDateString(),
                'start_time' => $e->start_time?->format('H:i'),
                'end_time' => $e->end_time?->format('H:i'),
                'venue' => $e->venue,
                'facilitator' => $e->facilitator,
                'notes' => $e->notes,
                'group_count' => $e->groups_count,
                'attendance_count' => $e->attendances_count,
            ]);

        return Inertia::render('fellowship/show', [
            'fellowship_type' => [
                'value' => $fellowshipType->value,
                'label' => $fellowshipType->label(),
            ],
            'events' => $events,
        ]);
    }

    /**
     * Create one Fellowship event.
     *
     * The fellowship_type comes from the route. All other fields
     * come from the request.
     */
    public function store(
        StoreFellowshipEventRequest $request,
        string $type,
    ): RedirectResponse {
        $fellowshipType = FellowshipType::tryFrom($type);

        if ($fellowshipType === null) {
            abort(404);
        }

        $validated = $request->validated();

        FellowshipEvent::create([
            'fellowship_type' => $fellowshipType->value,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'venue' => $validated['venue'] ?? null,
            'facilitator' => $validated['facilitator'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('fellowship.show', ['type' => $fellowshipType->value])
            ->with('success', 'Fellowship event created.');
    }

    /**
     * Update one Fellowship event.
     *
     * fellowship_type is immutable after creation and is not part
     * of the payload. Everything else can change.
     */
    public function update(
        UpdateFellowshipEventRequest $request,
        FellowshipEvent $event,
    ): RedirectResponse {
        $validated = $request->validated();

        $event->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'venue' => $validated['venue'] ?? null,
            'facilitator' => $validated['facilitator'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('fellowship.show', ['type' => $event->fellowship_type->value])
            ->with('success', 'Fellowship event updated.');
    }

    /**
     * Delete one Fellowship event.
     *
     * Groups cascade-delete. Attendance rows cascade-delete via
     * the event FK. This is intentional -- an event with its own
     * history going away takes its attendance with it. Same policy
     * as ServiceOccurrence deletion.
     */
    public function destroy(FellowshipEvent $event): RedirectResponse
    {
        $type = $event->fellowship_type->value;
        $event->delete();

        return redirect()
            ->route('fellowship.show', ['type' => $type])
            ->with('success', 'Fellowship event deleted.');
    }
}
