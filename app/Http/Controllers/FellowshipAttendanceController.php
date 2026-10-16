<?php

namespace App\Http\Controllers;

use App\Http\Requests\Fellowship\AddFellowshipAttendanceRequest;
use App\Http\Requests\Fellowship\MarkFellowshipAttendanceRequest;
use App\Models\FellowshipAttendance;
use App\Models\FellowshipEvent;
use App\Models\FellowshipGroup;
use App\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Attendance recording for one Fellowship event.
 *
 * One unified track (unlike Services, which has Members and
 * First-Timers). Each row may optionally be assigned to a group
 * within the same event.
 *
 * Phase 4, per client mockup.
 */
class FellowshipAttendanceController extends Controller
{
    /**
     * Show the attendance page for one event: the roster, plus the
     * event's groups so the operator can assign or reassign.
     */
    public function show(FellowshipEvent $event): Response
    {
        $event->load('creator:id,name');

        $groups = FellowshipGroup::query()
            ->where('fellowship_event_id', $event->id)
            ->withCount('attendances')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (FellowshipGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'display_order' => $g->display_order,
                'attendance_count' => $g->attendances_count,
            ]);

        $attendances = FellowshipAttendance::query()
            ->where('fellowship_event_id', $event->id)
            ->with('participant:id,first_name,last_name,age,birthday,address,contact_number')
            ->orderBy('id')
            ->get()
            ->map(fn (FellowshipAttendance $row) => [
                'id' => $row->id,
                'participant_id' => $row->participant_id,
                'first_name' => $row->participant?->first_name,
                'last_name' => $row->participant?->last_name,
                'full_name' => $row->participant?->fullName(),
                'age' => $row->participant?->age,
                'birthday' => $row->participant?->birthday?->toDateString(),
                'address' => $row->participant?->address,
                'contact_number' => $row->participant?->contact_number,
                'fellowship_group_id' => $row->fellowship_group_id,
                'attendance_status' => $row->attendance_status,
                'notes' => $row->notes,
            ]);

        return Inertia::render('fellowship/attendance', [
            'event' => [
                'id' => $event->id,
                'fellowship_type' => $event->fellowship_type->value,
                'fellowship_type_label' => $event->fellowship_type->label(),
                'name' => $event->name,
                'event_date' => $event->event_date->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_time' => $event->end_time?->format('H:i'),
                'venue' => $event->venue,
                'facilitator' => $event->facilitator,
                'created_by' => $event->creator?->name,
            ],
            'groups' => $groups,
            'attendances' => $attendances,
        ]);
    }

    /**
     * Add a participant to this event's attendance.
     *
     * If participant_id is provided, that participant is linked.
     * Otherwise, a new participant is created from the submitted
     * fields.
     *
     * Duplicate check: the UNIQUE (fellowship_event_id,
     * participant_id) index would catch a repeat, but a friendly
     * 422 is better than a QueryException.
     */
    public function store(
        AddFellowshipAttendanceRequest $request,
        FellowshipEvent $event,
    ): RedirectResponse {
        $validated = $request->validated();

        $participant = $this->resolveParticipant($validated);

        $exists = FellowshipAttendance::query()
            ->where('fellowship_event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->exists();

        if ($exists) {
            abort(422, 'This participant is already marked for this fellowship event.');
        }

        $groupId = $validated['fellowship_group_id'] ?? null;

        if ($groupId !== null) {
            $this->assertGroupBelongsToEvent((int) $groupId, $event->id);
        }

        DB::transaction(function () use ($event, $participant, $groupId): void {
            FellowshipAttendance::create([
                'fellowship_event_id' => $event->id,
                'participant_id' => $participant->id,
                'fellowship_group_id' => $groupId,
                'attendance_status' => 'present',
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ]);
        });

        return back();
    }

    /**
     * Update status, group, or notes on one attendance row.
     */
    public function mark(
        MarkFellowshipAttendanceRequest $request,
        FellowshipEvent $event,
        FellowshipAttendance $attendance,
    ): RedirectResponse {
        $this->assertBelongsToEvent($attendance->fellowship_event_id, $event->id);

        $validated = $request->validated();

        $groupId = $validated['fellowship_group_id'] ?? null;

        if ($groupId !== null) {
            $this->assertGroupBelongsToEvent((int) $groupId, $event->id);
        }

        $attendance->update([
            'attendance_status' => $validated['attendance_status'],
            'fellowship_group_id' => $groupId,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);

        return back();
    }

    /**
     * Remove one attendance row.
     *
     * Does not delete the underlying participant. The participant
     * may be on other Fellowship events.
     */
    public function destroy(
        FellowshipEvent $event,
        FellowshipAttendance $attendance,
    ): RedirectResponse {
        $this->assertBelongsToEvent($attendance->fellowship_event_id, $event->id);
        $attendance->delete();

        return back();
    }

    /**
     * Resolve an existing participant or create a new one.
     *
     * Same policy as ServiceAttendanceController::resolveParticipant.
     * No email lookup -- the add dialog does not collect one.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveParticipant(array $validated): Participant
    {
        if (! empty($validated['participant_id'])) {
            $existing = Participant::find($validated['participant_id']);
            if ($existing !== null) {
                return $existing;
            }
        }

        $phoneRaw = $validated['contact_number'] ?? null;
        $normalized = Participant::normalizeContactNumber(
            is_string($phoneRaw) ? $phoneRaw : null,
        );

        if ($normalized !== null) {
            $byPhone = Participant::where('contact_number_normalized', $normalized)->first();
            if ($byPhone !== null) {
                return $byPhone;
            }
        }

        return Participant::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'age' => $validated['age'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'address' => $validated['address'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
        ]);
    }

    /**
     * Guard: the group must belong to this event. Prevents a
     * participant from being assigned to another event's group via
     * URL or payload tampering.
     */
    private function assertGroupBelongsToEvent(int $groupId, int $eventId): void
    {
        $belongs = FellowshipGroup::query()
            ->where('id', $groupId)
            ->where('fellowship_event_id', $eventId)
            ->exists();

        if (! $belongs) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * Guard: the attendance row's event must match the route's
     * event. Same pattern as ServiceAttendanceController.
     */
    private function assertBelongsToEvent(int $rowEventId, int $routeEventId): void
    {
        if ($rowEventId !== $routeEventId) {
            throw new NotFoundHttpException();
        }
    }
}
