<?php

namespace App\Http\Controllers;

use App\Http\Requests\Services\AddServiceAttendanceRequest;
use App\Http\Requests\Services\MarkServiceAttendanceRequest;
use App\Models\FirstTimer;
use App\Models\Participant;
use App\Models\ServiceAttendanceFirstTimer;
use App\Models\ServiceAttendanceMember;
use App\Models\ServiceOccurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Attendance recording for one service occurrence.
 *
 * Two independent tracks per occurrence: Members and First-Timers.
 * A participant may appear on at most one track for any given
 * occurrence. The cross-track constraint is enforced here because
 * it is not expressible as a single DB index.
 *
 * Phase 3, per client mockup.
 */
class ServiceAttendanceController extends Controller
{
    /**
     * Show the attendance page for one occurrence: both tracks,
     * plus a searchable list of church members for the "Add"
     * dialog.
     */
    public function show(ServiceOccurrence $occurrence): Response|RedirectResponse
    {
        if ($occurrence->getAttribute('registration_event_id') !== null) {
            return redirect()->route('church.attendance', $occurrence->getAttribute('registration_event_id'));
        }
        $occurrence->load('creator:id,name');

        $memberRows = ServiceAttendanceMember::query()
            ->where('service_occurrence_id', $occurrence->id)
            ->with('participant:id,first_name,last_name,age,birthday,address,contact_number')
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceAttendanceMember $row) => [
                'id' => $row->id,
                'participant_id' => $row->participant_id,
                'first_name' => $row->participant?->first_name,
                'last_name' => $row->participant?->last_name,
                'full_name' => $row->participant?->fullName(),
                'age' => $row->participant?->age,
                'birthday' => $row->participant?->birthday?->toDateString(),
                'address' => $row->participant?->address,
                'contact_number' => $row->participant?->contact_number,
                'cell_leader' => null, // reserved for a future column
                'attendance_status' => $row->attendance_status,
                'notes' => $row->notes,
            ]);

        $firstTimerRows = ServiceAttendanceFirstTimer::query()
            ->where('service_occurrence_id', $occurrence->id)
            ->with('participant:id,first_name,last_name,age,birthday,address,contact_number')
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceAttendanceFirstTimer $row) => [
                'id' => $row->id,
                'participant_id' => $row->participant_id,
                'first_name' => $row->participant?->first_name,
                'last_name' => $row->participant?->last_name,
                'full_name' => $row->participant?->fullName(),
                'age' => $row->participant?->age,
                'birthday' => $row->participant?->birthday?->toDateString(),
                'address' => $row->participant?->address,
                'contact_number' => $row->participant?->contact_number,
                'invited_by' => $row->invited_by,
                'attendance_status' => $row->attendance_status,
                'notes' => $row->notes,
            ]);

        return Inertia::render('services/attendance', [
            'occurrence' => [
                'id' => $occurrence->id,
                'service_type' => $occurrence->service_type->value,
                'service_type_label' => $occurrence->service_type->label(),
                'occurrence_date' => $occurrence->occurrence_date->toDateString(),
                'start_time' => $occurrence->start_time?->format('H:i'),
                'end_time' => $occurrence->end_time?->format('H:i'),
                'service_leader' => $occurrence->service_leader,
                'notes' => $occurrence->notes,
                'created_by' => $occurrence->creator?->name,
            ],
            'members' => $memberRows,
            'first_timers' => $firstTimerRows,
        ]);
    }

    /**
     * Add a participant to the given track.
     *
     * If participant_id is provided, that participant is linked.
     * Otherwise, a new participant is created from the submitted
     * fields.
     *
     * Cross-track check: if the participant already appears on the
     * *other* track for this occurrence, the request is rejected.
     */
    public function store(
        AddServiceAttendanceRequest $request,
        ServiceOccurrence $occurrence,
    ): RedirectResponse {
        $validated = $request->validated();
        $track = $validated['track'];
        DB::transaction(function () use ($occurrence, $validated, $track): void {
            $lockedOccurrence = ServiceOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOccurrence->getAttribute('registration_event_id') !== null, 409, 'Use the church Registration and Attendance pages for this event.');
            $participant = $this->resolveParticipant($validated);
            $this->assertNotOnOtherTrack($lockedOccurrence, $participant, $track);
            $this->assertNotOnSameTrack($lockedOccurrence, $participant, $track);

            if ($track === 'member') {
                ServiceAttendanceMember::create([
                    'service_occurrence_id' => $lockedOccurrence->id,
                    'participant_id' => $participant->id,
                    'attendance_status' => 'present',
                    'recorded_by' => auth()->id(),
                    'recorded_at' => now(),
                ]);
            } else {
                ServiceAttendanceFirstTimer::create([
                    'service_occurrence_id' => $lockedOccurrence->id,
                    'participant_id' => $participant->id,
                    'invited_by' => $validated['invited_by'] ?? null,
                    'attendance_status' => 'present',
                    'recorded_by' => auth()->id(),
                    'recorded_at' => now(),
                ]);

                // Create the FirstTimer registry record on this
                // participant's first-ever attendance. Idempotent.
                if (! FirstTimer::where('participant_id', $participant->id)->exists()) {
                    FirstTimer::create([
                        'participant_id' => $participant->id,
                        'first_service_date' => $lockedOccurrence->occurrence_date,
                    ]);
                }
            }
        });

        return back();
    }

    /**
     * Update attendance status for a Members-track row.
     */
    public function markMember(
        MarkServiceAttendanceRequest $request,
        ServiceOccurrence $occurrence,
        ServiceAttendanceMember $attendance,
    ): RedirectResponse {
        $validated = $request->validated();
        DB::transaction(function () use ($occurrence, $attendance, $validated): void {
            $lockedOccurrence = ServiceOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOccurrence->getAttribute('registration_event_id') !== null, 409, 'Use the church Registration and Attendance pages for this event.');
            $row = ServiceAttendanceMember::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            $this->assertBelongsToOccurrence($row->service_occurrence_id, $lockedOccurrence->id);
            $row->update([
                'attendance_status' => $validated['attendance_status'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ]);
        });

        return back();
    }

    /**
     * Update attendance status for a First-Timers-track row.
     */
    public function markFirstTimer(
        MarkServiceAttendanceRequest $request,
        ServiceOccurrence $occurrence,
        ServiceAttendanceFirstTimer $attendance,
    ): RedirectResponse {
        $validated = $request->validated();
        DB::transaction(function () use ($occurrence, $attendance, $validated): void {
            $lockedOccurrence = ServiceOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOccurrence->getAttribute('registration_event_id') !== null, 409, 'Use the church Registration and Attendance pages for this event.');
            $row = ServiceAttendanceFirstTimer::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            $this->assertBelongsToOccurrence($row->service_occurrence_id, $lockedOccurrence->id);
            $row->update([
                'attendance_status' => $validated['attendance_status'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ]);
        });

        return back();
    }

    /**
     * Remove a Members-track row.
     *
     * Does not delete the underlying participant. The participant
     * may be on other occurrences.
     */
    public function destroyMember(
        ServiceOccurrence $occurrence,
        ServiceAttendanceMember $attendance,
    ): RedirectResponse {
        DB::transaction(function () use ($occurrence, $attendance): void {
            $lockedOccurrence = ServiceOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOccurrence->getAttribute('registration_event_id') !== null, 409, 'Use the church Registration and Attendance pages for this event.');
            $row = ServiceAttendanceMember::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            $this->assertBelongsToOccurrence($row->service_occurrence_id, $lockedOccurrence->id);
            $row->delete();
        });

        return back();
    }

    /**
     * Remove a First-Timers-track row.
     */
    public function destroyFirstTimer(
        ServiceOccurrence $occurrence,
        ServiceAttendanceFirstTimer $attendance,
    ): RedirectResponse {
        DB::transaction(function () use ($occurrence, $attendance): void {
            $lockedOccurrence = ServiceOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOccurrence->getAttribute('registration_event_id') !== null, 409, 'Use the church Registration and Attendance pages for this event.');
            $row = ServiceAttendanceFirstTimer::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            $this->assertBelongsToOccurrence($row->service_occurrence_id, $lockedOccurrence->id);
            $row->delete();
        });

        return back();
    }

    /**
     * Resolve an existing participant or create a new one from the
     * submitted fields.
     *
     * First-write-wins is not applicable here -- the organizer is
     * explicitly adding someone to a service. If participant_id is
     * given, use it. Otherwise, look up by normalized name plus contact
     * if either is provided; fall back to creating a new participant
     * with the given name.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveParticipant(array $validated): Participant
    {
        if (! empty($validated['participant_id'])) {
            $existing = Participant::where('pool', 'church')->whereNull('provisional_event_id')->findOrFail($validated['participant_id']);
            if ($existing !== null) {
                return $existing;
            }
        }

        $email = isset($validated['email']) && is_string($validated['email'])
            ? trim($validated['email'])
            : null;

        $phoneRaw = $validated['contact_number'] ?? null;
        $existing = Participant::findByComposite('church',
            $email,
            is_string($phoneRaw) ? $phoneRaw : null,
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
            $validated['middle_name'] ?? null,
            $validated['suffix'] ?? null,
        );
        if ($existing !== null) {
            return $existing;
        }

        return Participant::create(['pool' => 'church',
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'suffix' => $validated['suffix'] ?? null,
            'age' => $validated['age'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'address' => $validated['address'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'email' => $email !== '' ? $email : null,
        ]);
    }

    /**
     * A participant may not appear on both tracks for the same
     * occurrence. Enforced at the controller because it is a cross-
     * table constraint.
     */
    private function assertNotOnOtherTrack(
        ServiceOccurrence $occurrence,
        Participant $participant,
        string $track,
    ): void {
        if ($track === 'member') {
            $onFirstTimer = ServiceAttendanceFirstTimer::query()
                ->where('service_occurrence_id', $occurrence->id)
                ->where('participant_id', $participant->id)
                ->exists();

            if ($onFirstTimer) {
                abort(422, 'This participant is already on the First-Timers track for this service.');
            }

            return;
        }

        $onMember = ServiceAttendanceMember::query()
            ->where('service_occurrence_id', $occurrence->id)
            ->where('participant_id', $participant->id)
            ->exists();

        if ($onMember) {
            abort(422, 'This participant is already on the Members track for this service.');
        }
    }

    /**
     * A participant may not appear twice on the same track for the
     * same occurrence. The DB unique index would catch this, but a
     * friendly 422 is better than a QueryException.
     */
    private function assertNotOnSameTrack(
        ServiceOccurrence $occurrence,
        Participant $participant,
        string $track,
    ): void {
        $model = $track === 'member'
            ? ServiceAttendanceMember::class
            : ServiceAttendanceFirstTimer::class;

        $exists = $model::query()
            ->where('service_occurrence_id', $occurrence->id)
            ->where('participant_id', $participant->id)
            ->exists();

        if ($exists) {
            abort(422, 'This participant is already on this track for this service.');
        }
    }

    /**
     * Guard: the attendance row's occurrence must match the route's
     * occurrence. Prevents a participant from being marked on
     * someone else's service via URL tampering.
     */
    private function assertBelongsToOccurrence(int $rowOccurrenceId, int $routeOccurrenceId): void
    {
        if ($rowOccurrenceId !== $routeOccurrenceId) {
            throw new NotFoundHttpException;
        }
    }
}
