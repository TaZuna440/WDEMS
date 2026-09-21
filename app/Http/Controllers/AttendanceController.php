<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function show(Event $event): Response
    {
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

    public function mark(Request $request, Event $event, Registration $registration): RedirectResponse
    {
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
