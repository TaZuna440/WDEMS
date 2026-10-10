<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\WalkInRegistrationRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationFieldResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
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

    public function show(Request $request, Event $event): Response
    {
        if (! $event->canViewAttendance()) {
            abort(403, 'Attendance is not available for this event.');
        }

        // Admin bypass. Only an authenticated admin can force the
        // marking window open via the query string. Not persisted, not
        // sessioned — re-evaluated on every request.
        $isAdmin = $request->user()?->isAdmin() === true;
        $forceOpen = $isAdmin && $request->boolean('force_open');

        $search = trim((string) $request->input('q', ''));
        $filter = (string) $request->input('filter', 'all');

        $allowedFilters = ['all', 'unmarked', 'present', 'absent', 'late', 'excused'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $baseQuery = function () use ($event, $search) {
            $q = Registration::query()
                ->where('registrations.event_id', $event->id)
                ->join('participants', 'participants.id', '=', 'registrations.participant_id')
                ->select('registrations.*');

            if ($search !== '') {
                $like = '%'.$search.'%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('participants.first_name', 'like', $like)
                        ->orWhere('participants.last_name', 'like', $like)
                        ->orWhere('participants.email', 'like', $like)
                        ->orWhere('participants.contact_number', 'like', $like);
                });
            }

            return $q;
        };

        $counts = [
            'all' => $baseQuery()->count(),
            'unmarked' => $baseQuery()->whereDoesntHave('attendance')->count(),
            'present' => $baseQuery()
                ->whereHas('attendance', fn ($q) => $q->where('attendance_status', 'present'))
                ->count(),
            'absent' => $baseQuery()
                ->whereHas('attendance', fn ($q) => $q->where('attendance_status', 'absent'))
                ->count(),
            'late' => $baseQuery()
                ->whereHas('attendance', fn ($q) => $q->where('attendance_status', 'late'))
                ->count(),
            'excused' => $baseQuery()
                ->whereHas('attendance', fn ($q) => $q->where('attendance_status', 'excused'))
                ->count(),
        ];

        $query = $baseQuery()->with([
            'participant:id,first_name,last_name,email,contact_number',
            'attendance:id,registration_id,attendance_status,attendance_time,notes',
        ]);

        if ($filter === 'unmarked') {
            $query->whereDoesntHave('attendance');
        } elseif ($filter !== 'all') {
            $query->whereHas('attendance', fn ($q) => $q->where('attendance_status', $filter));
        }

        $query->orderBy('participants.last_name')
            ->orderBy('participants.first_name')
            ->orderBy('registrations.id');

        $paginator = $query->paginate(50)->withQueryString();

        $rows = collect($paginator->items())->map(fn (Registration $r) => [
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
                'common_field_requirements' => [
                    'email' => $event->isCommonFieldRequired('email'),
                    'contact_number' => $event->isCommonFieldRequired('contact_number'),
                    'address' => $event->isCommonFieldRequired('address'),
                ],
                'registration_fields' => $this->registrationFieldsPayload($event),
            ],
            'rows' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'counts' => $counts,
            'filters' => [
                'q' => $search,
                'filter' => $filter,
            ],
            'can_mark' => $forceOpen || $event->canRecordAttendanceNow(),
            'is_admin' => $isAdmin,
            'force_open' => $forceOpen,
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
        $forceOpen = $request->user()?->isAdmin() === true
            && $request->boolean('force_open');

        if (! $forceOpen && ! $event->canRecordAttendanceNow()) {
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

    public function walkIn(WalkInRegistrationRequest $request, Event $event): RedirectResponse
    {
        $forceOpen = $request->user()?->isAdmin() === true
            && $request->boolean('force_open');

        if (! $forceOpen && ! $event->canRecordAttendanceNow()) {
            abort(403, 'Walk-ins can only be registered on the event day.');
        }

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $event, $user): void {
            $participant = Participant::resolveFrom(
                $validated['email'] ?? null,
                $validated['contact_number'] ?? null,
                [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'age' => $validated['age'],
                    'email' => $validated['email'] ?? null,
                    'contact_number' => $validated['contact_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                ],
            );

            $registration = Registration::create([
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'registration_date' => now(),
                'registration_status' => RegistrationStatus::Confirmed,
                'source' => 'walk_in',
                'registered_at' => now(),
            ]);

            foreach ($validated['responses'] ?? [] as $fieldId => $value) {
                RegistrationFieldResponse::create([
                    'registration_id' => $registration->id,
                    'registration_field_id' => (int) $fieldId,
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                ]);
            }

            if ($validated['mark_present'] ?? true) {
                $registration->attendance()->create([
                    'attendance_status' => AttendanceStatus::Present,
                    'attendance_time' => now(),
                    'recorded_by' => $user->id,
                ]);
            }
        });

        return redirect()->route('events.attendance', $event);
    }

    /**
     * Custom field definitions for the event, in display order. Same
     * shape as PublicRegistrationController::fieldsPayload() so the
     * walk-in dialog and the public form can share the field-type
     * rendering logic.
     *
     * @return array<int, array<string, mixed>>
     */
    private function registrationFieldsPayload(Event $event): array
    {
        return $event->registrationFields()
            ->orderBy('display_order')
            ->get()
            ->map(fn ($field) => [
                'id' => $field->id,
                'label' => $field->label,
                'field_type' => $field->field_type,
                'options' => $field->options ?? [],
                'is_required' => $field->is_required,
            ])
            ->values()
            ->all();
    }
}
