<?php

namespace App\Http\Controllers;

use App\Http\Requests\Fellowship\StoreFellowshipGroupRequest;
use App\Http\Requests\Fellowship\UpdateFellowshipGroupRequest;
use App\Models\FellowshipEvent;
use App\Models\FellowshipGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Group management for one Fellowship event.
 *
 * Groups are event-specific labels (Parents, Youth, Children). The
 * organizer sets them up before recording attendance. Attendance
 * rows reference a group via a nullable FK; deleting a group leaves
 * its attendance rows in place with a NULL group pointer (see the
 * fellowship_attendance migration's nullOnDelete on
 * fellowship_group_id).
 *
 * All three methods return back() -- groups are managed inline on
 * the event detail page, not via a dedicated full-page view.
 *
 * Phase 4, shape A (first-class group resource).
 */
class FellowshipGroupController extends Controller
{
    /**
     * Create one group on the event.
     */
    public function store(
        StoreFellowshipGroupRequest $request,
        FellowshipEvent $event,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($event, $validated): void {
            $lockedEvent = FellowshipEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedEvent->getAttribute('registration_event_id') !== null, 409, 'Groups are managed through the activated church registration event.');
            FellowshipGroup::create([
                'fellowship_event_id' => $lockedEvent->id,
                'name' => $validated['name'],
                'display_order' => $validated['display_order'] ?? 0,
            ]);
        });

        return back();
    }

    /**
     * Rename or reorder one group.
     */
    public function update(
        UpdateFellowshipGroupRequest $request,
        FellowshipEvent $event,
        FellowshipGroup $group,
    ): RedirectResponse {
        $validated = $request->validated();
        DB::transaction(function () use ($event, $group, $validated): void {
            $lockedEvent = FellowshipEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedEvent->getAttribute('registration_event_id') !== null, 409, 'Groups are managed through the activated church registration event.');
            $row = FellowshipGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->assertGroupBelongsToEvent($row->fellowship_event_id, $lockedEvent->id);
            $row->update([
                'name' => $validated['name'],
                'display_order' => $validated['display_order'] ?? $row->display_order,
            ]);
        });

        return back();
    }

    /**
     * Delete one group.
     *
     * Attendance rows assigned to this group are preserved with a
     * NULL group pointer -- the nullOnDelete on the migration's FK.
     * The organizer can reassign them afterwards without losing the
     * attendance record.
     */
    public function destroy(
        FellowshipEvent $event,
        FellowshipGroup $group,
    ): RedirectResponse {
        DB::transaction(function () use ($event, $group): void {
            $lockedEvent = FellowshipEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedEvent->getAttribute('registration_event_id') !== null, 409, 'Groups are managed through the activated church registration event.');
            $row = FellowshipGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->assertGroupBelongsToEvent($row->fellowship_event_id, $lockedEvent->id);
            $row->delete();
        });

        return back();
    }

    /**
     * Guard: the group's event must match the route's event.
     * Prevents a rename or delete from being applied across events
     * via URL tampering.
     */
    private function assertGroupBelongsToEvent(int $rowEventId, int $routeEventId): void
    {
        if ($rowEventId !== $routeEventId) {
            throw new NotFoundHttpException;
        }
    }
}
