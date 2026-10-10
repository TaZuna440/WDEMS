<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\BulkMarkAttendanceRequest;
use App\Http\Requests\BulkWalkInRegistrationRequest;
use App\Http\Requests\WalkInRegistrationRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationFieldResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Bulk mark as present.
     *
     * Two shapes accepted by the request (see BulkMarkAttendanceRequest):
     *
     *   - registration_ids: an explicit list. Every ID must belong to
     *     this event; a single mismatch rejects the whole batch.
     *   - mark_all_visible + filter + q: marks every registration
     *     matching the current filter and search. Scope is the search
     *     result, not the current page — pagination is a view concern,
     *     not a marking concern.
     *
     * Idempotent. Writes go through the same attendance()->updateOrCreate()
     * the single-row mark uses. Rerunning is a no-op for already-present
     * rows.
     *
     * No rate limiter — this is a trusted-staff write operation, and the
     * single-row mark endpoint has no limiter either. If abuse becomes a
     * concern, add a throttle:attendance-bulk-mark middleware here.
     */
    public function bulkMark(BulkMarkAttendanceRequest $request, Event $event): RedirectResponse
    {
        $forceOpen = $request->user()?->isAdmin() === true
            && $request->boolean('force_open');

        if (! $forceOpen && ! $event->canRecordAttendanceNow()) {
            abort(403, 'Attendance can only be recorded on the event day.');
        }

        $validated = $request->validated();
        $user = $request->user();

        $ids = $this->resolveBulkMarkIds($validated, $event);

        $count = 0;

        DB::transaction(function () use ($ids, $user, &$count): void {
            $registrations = Registration::whereIn('id', $ids)->get();

            foreach ($registrations as $registration) {
                $registration->attendance()->updateOrCreate(
                    ['registration_id' => $registration->id],
                    [
                        'attendance_status' => AttendanceStatus::Present,
                        'attendance_time' => now(),
                        'recorded_by' => $user->id,
                    ],
                );

                $count++;
            }
        });

        return back()->with(
            'success',
            $count === 1
                ? 'Marked 1 participant as present.'
                : "Marked {$count} participants as present.",
        );
    }

    /**
     * Resolve the two bulk-mark request shapes into a flat list of
     * registration IDs scoped to the event.
     *
     * Explicit shape: verifies every submitted ID belongs to this
     * event before returning. A single mismatch throws 422 rather
     * than partially applying — same "all-or-nothing at the request
     * level" principle as the duplicate check on the public form.
     *
     * WYSIWYG shape: rebuilds the search + filter clause the show()
     * page uses. See buildFilteredRegistrationIds().
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     */
    private function resolveBulkMarkIds(array $validated, Event $event): array
    {
        $explicit = $validated['registration_ids'] ?? null;

        if (is_array($explicit) && count($explicit) > 0) {
            $ownedIds = Registration::where('event_id', $event->id)
                ->whereIn('id', $explicit)
                ->pluck('id')
                ->all();

            if (count($ownedIds) !== count($explicit)) {
                abort(422, 'One or more registrations do not belong to this event.');
            }

            return $ownedIds;
        }

        $search = trim((string) ($validated['q'] ?? ''));
        $filter = $validated['filter'] ?? 'all';

        return $this->buildFilteredRegistrationIds($event, $search, $filter);
    }

    /**
     * Return IDs of every registration matching the current filter
     * and search — the same clause the show() page uses. Duplicated
     * from show()'s $baseQuery closure for now; Phase C consolidates
     * once the batch walk-in surface also needs the shape.
     *
     * @return array<int, int>
     */
    private function buildFilteredRegistrationIds(Event $event, string $search, string $filter): array
    {
        $q = Registration::query()
            ->where('registrations.event_id', $event->id)
            ->join('participants', 'participants.id', '=', 'registrations.participant_id')
            ->select('registrations.id');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($sub) use ($like) {
                $sub->where('participants.first_name', 'like', $like)
                    ->orWhere('participants.last_name', 'like', $like)
                    ->orWhere('participants.email', 'like', $like)
                    ->orWhere('participants.contact_number', 'like', $like);
            });
        }

        if ($filter === 'unmarked') {
            $q->whereDoesntHave('attendance');
        } elseif ($filter !== 'all' && in_array($filter, ['present', 'absent', 'late', 'excused'], true)) {
            $q->whereHas('attendance', fn ($qq) => $qq->where('attendance_status', $filter));
        }

        return $q->pluck('registrations.id')->all();
    }

    /**
     * Bulk walk-in registration.
     *
     * Accepts a `rows` array from the request. Each row is persisted
     * independently in its own DB transaction — one bad row does not
     * fail the batch (D6). Shape validation ran in the request;
     * business rules (identity presence, duplicate within batch,
     * already registered) run here per row and are reported as
     * `rows.{i}.identity` errors alongside the successful writes.
     *
     * Idempotency: after a row persists successfully, its
     * `client_uuid` is cached for 60 seconds. A resubmitted row with
     * the same UUID is a no-op. Failed rows do not cache, so they
     * retry naturally.
     *
     * `source = 'paper'` for every row — the operator is transcribing
     * a physical form, not registering someone who just walked up to
     * the table.
     */
    public function bulkWalkIn(BulkWalkInRegistrationRequest $request, Event $event): RedirectResponse
    {
        $forceOpen = $request->user()?->isAdmin() === true
            && $request->boolean('force_open');

        if (! $forceOpen && ! $event->canRecordAttendanceNow()) {
            abort(403, 'Bulk walk-ins can only be registered during the event window.');
        }

        $validated = $request->validated();
        $user = $request->user();
        $rows = $validated['rows'];

        $errors = [];
        $seenParticipantIds = [];
        $successCount = 0;
        $skippedCount = 0;

        foreach ($rows as $i => $row) {
            $uuid = $row['client_uuid'];
            $cacheKey = "walk-in-batch:{$uuid}";

            if (Cache::has($cacheKey)) {
                $skippedCount++;
                continue;
            }

            $error = $this->persistBulkWalkInRow(
                $row,
                $event,
                $user,
                $seenParticipantIds,
            );

            if ($error !== null) {
                $errors["rows.{$i}.identity"] = $error;
                continue;
            }

            Cache::put($cacheKey, true, 60);
            $successCount++;
        }

        $response = back();

        if ($errors !== []) {
            $response = $response->withErrors($errors);
        }

        return $response->with(
            'success',
            $this->bulkWalkInSummary($successCount, count($errors), $skippedCount),
        );
    }

    /**
     * Persist a single bulk walk-in row.
     *
     * Returns null on success, or a human-readable error message when
     * a business rule rejects the row. The read-only checks run
     * BEFORE `resolveFrom()` so a rejected row never creates an
     * orphan Participant.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $seenParticipantIds  Mutable, accumulates
     *                                                participants already
     *                                                seen in this batch.
     */
    private function persistBulkWalkInRow(
        array $row,
        Event $event,
        $user,
        array &$seenParticipantIds,
    ): ?string {
        $email = is_string($row['email'] ?? null) ? trim($row['email']) : '';
        $phone = is_string($row['contact_number'] ?? null) ? $row['contact_number'] : null;

        $normalized = Participant::normalizeContactNumber($phone);

        if ($email === '' && $normalized === null) {
            return 'Please provide at least an email address or a contact number.';
        }

        // Read-only lookup. If the identity already exists, we know
        // the row will resolve to it. No write yet.
        $existing = null;

        if ($email !== '') {
            $existing = Participant::where('email', $email)->first();
        }

        if ($existing === null && $normalized !== null) {
            $existing = Participant::where(
                'contact_number_normalized',
                $normalized,
            )->first();
        }

        // Duplicate within this batch?
        if ($existing !== null && in_array($existing->id, $seenParticipantIds, true)) {
            return 'Duplicate within this batch.';
        }

        // Already registered for this event?
        if ($existing !== null) {
            $alreadyRegistered = Registration::where('event_id', $event->id)
                ->where('participant_id', $existing->id)
                ->exists();

            if ($alreadyRegistered) {
                return 'This person is already registered for this event.';
            }
        }

        // All checks passed. Persist.
        DB::transaction(function () use ($row, $event, $user, &$seenParticipantIds, $email, $phone): void {
            $participant = Participant::resolveFrom(
                $email !== '' ? $email : null,
                $phone,
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'age' => $row['age'],
                    'email' => $email !== '' ? $email : null,
                    'contact_number' => $phone,
                    'address' => $row['address'] ?? null,
                ],
            );

            $seenParticipantIds[] = $participant->id;

            $registration = Registration::create([
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'registration_date' => now(),
                'registration_status' => RegistrationStatus::Confirmed,
                'source' => 'paper',
                'registered_at' => now(),
            ]);

            foreach ($row['responses'] ?? [] as $fieldId => $value) {
                RegistrationFieldResponse::create([
                    'registration_id' => $registration->id,
                    'registration_field_id' => (int) $fieldId,
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                ]);
            }

            $registration->attendance()->create([
                'attendance_status' => AttendanceStatus::Present,
                'attendance_time' => now(),
                'recorded_by' => $user->id,
            ]);
        });

        return null;
    }

    /**
     * Human-readable summary for the bulk walk-in flash message.
     */
    private function bulkWalkInSummary(int $success, int $failed, int $skipped): string
    {
        if ($success === 0 && $failed === 0 && $skipped > 0) {
            return 'Nothing to do — every row was already processed.';
        }

        $parts = [];

        if ($success > 0) {
            $parts[] = 'Registered '.$success.' '.($success === 1 ? 'participant' : 'participants').'.';
        }

        if ($failed > 0) {
            $parts[] = $failed.' '.($failed === 1 ? 'row' : 'rows').' failed — see inline errors.';
        }

        if ($skipped > 0) {
            $parts[] = $skipped.' '.($skipped === 1 ? 'row was' : 'rows were').' already processed.';
        }

        return implode(' ', $parts);
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
