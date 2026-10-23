<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChurchAttendanceController extends Controller
{
    public function show(Request $request, Event $event): Response
    {
        abort_unless($event->isChurchEvent(), 404);
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'filter' => ['nullable', 'in:all,unmarked,present,absent'],
            'code' => ['nullable', 'string', 'max:20'],
        ]);
        $summary = $request->boolean('summary');
        $rows = Registration::active()
            ->where('event_id', $event->id)
            ->whereHas('participant', fn ($query) => $query->where('pool', 'church'))
            ->with(['participant', 'attendance'])
            ->get();
        $counts = ['registered' => $rows->count(), 'present' => 0, 'absent' => 0, 'unmarked' => 0];
        /** @var list<array<string, mixed>> $mapped */
        $mapped = [];
        foreach ($rows as $row) {
            $person = $row->participant;
            abort_if($person === null, 404);
            $attendance = $row->attendance;
            $status = $attendance?->getRawOriginal('attendance_status') ?? 'unmarked';
            $counts[$status]++;
            $mapped[] = [
                'id' => $row->id,
                'name' => $person->fullName(),
                'email' => $row->submitted_email ?? $person->email,
                'phone' => $row->submitted_contact_number ?? $person->contact_number,
                'code' => $row->attendance_code,
                'participant_type' => $row->participant_type,
                'status' => $status,
                'fellowship_group_id' => $row->fellowship_group_id,
            ];
        }
        $code = strtoupper(preg_replace('/[^a-z0-9]/i', '', $filter['code'] ?? ''));
        $q = mb_strtolower(trim($filter['q'] ?? ''));
        if (! $summary) {
            $mapped = array_values(array_filter(
                $mapped,
                fn (array $row) => (($filter['filter'] ?? 'all') === 'all' || $row['status'] === $filter['filter'])
                    && ($code === '' || str_replace('-', '', $row['code'] ?? '') === $code)
                    && ($q === '' || str_contains(mb_strtolower($row['name'].' '.$row['email'].' '.$row['phone']), $q)),
            ));
        }

        return Inertia::render('events/church/attendance', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'status' => $event->church_status->value,
                'status_label' => $event->church_status->label(),
            ],
            'rows' => $mapped,
            'counts' => $counts,
            'filters' => $filter,
            'summary' => $summary,
            'urls' => [
                'registrations' => route('church.registrations.index', $event),
                'attendance' => route('church.attendance', $event),
                'reports' => route('reports.index', ['kind' => 'church']),
            ],
        ]);
    }

    public function mark(Request $request, Event $event, Registration $registration): RedirectResponse
    {
        abort_unless($event->isChurchEvent() && $registration->event_id === $event->id, 404);
        $this->ensureAttendanceWritable($event);
        $data = $request->validate([
            'status' => ['required', 'in:present,absent,unmarked'],
        ]);
        DB::transaction(function () use ($event, $registration, $request, $data): void {
            $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->ensureAttendanceWritable($lockedEvent);
            $row = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $row->isActive() && Participant::findOrFail($row->participant_id)->pool === 'church',
                422,
            );
            if ($data['status'] === 'unmarked') {
                $row->attendance()->delete();
            } else {
                $row->attendance()->updateOrCreate(
                    ['registration_id' => $row->id],
                    [
                        'attendance_status' => $data['status'],
                        'attendance_time' => $data['status'] === 'present' ? now() : null,
                        'recorded_by' => $request->user()->id,
                    ],
                );
            }
        });

        return back();
    }

    private function ensureAttendanceWritable(Event $event): void
    {
        abort_if(
            $event->isAttendanceReadOnly(),
            403,
            'Attendance cannot be modified because this event is completed.',
        );
    }
}
