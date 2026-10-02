<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Directory of events eligible for attendance.
     *
     * An event is eligible when canRecordAttendanceToday() is true —
     * status is registration_open, registration_closed, or ongoing,
     * AND event_date <= today. Events are grouped for display:
     * "today" (event_date === today) and "recent" (everything else
     * in the eligible set, which is by definition in the past).
     *
     * Completed events are excluded. Attendance should have been
     * recorded during ongoing; if it was missed, the event is not
     * the right place to fix it. Same reasoning as the gate.
     */
    public function index(): Response
    {
        $statuses = [
            EventStatus::RegistrationOpen->value,
            EventStatus::RegistrationClosed->value,
            EventStatus::Ongoing->value,
        ];

        $today = today()->toDateString();

        $events = Event::query()
            ->whereIn('status', $statuses)
            ->whereDate('event_date', '<=', $today)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        $map = fn (Event $event) => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->toDateString(),
            'venue' => $event->venue,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
            'registered_count' => $event->registrations()->count(),
            'marked_count' => $event->registrations()
                ->whereHas('attendance')
                ->count(),
        ];

        $mapped = $events->map($map);

        return Inertia::render('attendance/index', [
            'today' => $mapped
                ->filter(fn (array $row) => $row['event_date'] === $today)
                ->values(),
            'recent' => $mapped
                ->filter(fn (array $row) => $row['event_date'] !== $today)
                ->values(),
        ]);
    }

    /**
     * Attendance page for a single event.
     *
     * Gate: canRecordAttendanceToday(). A future event returns 403
     * with a message the organizer can understand — the event has
     * not happened yet.
     */
    public function show(Event $event): Response
    {
        if (! $event->canRecordAttendanceToday()) {
            abort(403, 'Attendance can only be recorded on the event day.');
        }

        $rows = $event->registrations()
            ->with([
                'participant:id,first_name,last_name,email,contact_number',
                'attendance:id,registration_id,attendance_status,attendance_time,notes',
            ])
            ->get()
            ->sortBy(fn (Registration $r) => strtolower(
                ($r->participant?->last_name ?? '').' '.($r->participant?->first_name ?? '')
            ))
            ->values()
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'source' => $r->source,
                'registered_at' => $r->registered_at?->toIso8601String(),
                'participant' => [
                    'id' => $r->participant?->id,
                    'full_name' => $r->participant?->fullName(),
                    'email' => $r->participant?->email,
                    'contact_number' => $r->participant?->contact_number,
                ],
                'attendance' => $r->attendance ? [
                    'status' => $r->attendance->attendance_status->value,
                    'status_label' => $r->attendance->attendance_status->label(),
                    'time' => $r->attendance->attendance_time?->toIso8601String(),
                    'notes' => $r->attendance->notes,
                ] : null,
            ]);

        return Inertia::render('events/attendance', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
            ],
            'rows' => $rows,
            'attendance_statuses' => collect(AttendanceStatus::cases())
                ->map(fn (AttendanceStatus $s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ])
                ->values(),
        ]);
    }

    /**
     * Mark or update a registration's attendance.
     *
     * Same gate as show() — a direct POST to a future event is
     * rejected, not just the UI path. The registration-belongs-to-
     * event check is a separate 404 for a malformed URL.
     */
    public function mark(Request $request, Event $event, Registration $registration): RedirectResponse
    {
        if (! $event->canRecordAttendanceToday()) {
            abort(403, 'Attendance can only be recorded on the event day.');
        }

        if ($registration->event_id !== $event->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = AttendanceStatus::from($validated['status']);
        $user = $request->user();

        $shouldRecordTime = in_array($status, [
            AttendanceStatus::Present,
            AttendanceStatus::Late,
        ], true);

        $registration->attendance()->updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'attendance_status' => $status,
                'attendance_time' => $shouldRecordTime ? now() : null,
                'recorded_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return back();
    }
}
