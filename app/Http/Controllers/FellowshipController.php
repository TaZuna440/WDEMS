<?php

namespace App\Http\Controllers;

use App\Enums\FellowshipType;
use App\Http\Requests\Fellowship\StoreFellowshipEventRequest;
use App\Http\Requests\Fellowship\UpdateFellowshipEventRequest;
use App\Models\FellowshipEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fellowship module -- event creation, update, and deletion.
 *
 * Attendance recording is in FellowshipAttendanceController.
 *
 * Phase 4, per client mockup.
 */
class FellowshipController extends Controller
{
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
        DB::transaction(function () use ($event, $validated): void {
            $row = FellowshipEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($row->getAttribute('registration_event_id') !== null, 409, 'Edit the activated church event instead.');
            $row->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'event_date' => $validated['event_date'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'venue' => $validated['venue'] ?? null,
                'facilitator' => $validated['facilitator'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

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
        DB::transaction(function () use ($event): void {
            $row = FellowshipEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($row->getAttribute('registration_event_id') !== null, 409, 'Delete the activated church event instead.');
            $row->delete();
        });

        return redirect()
            ->route('fellowship.show', ['type' => $type])
            ->with('success', 'Fellowship event deleted.');
    }
}
