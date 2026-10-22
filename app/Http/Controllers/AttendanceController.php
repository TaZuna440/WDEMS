<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ChurchCategory;
use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\BulkMarkAttendanceRequest;
use App\Http\Requests\BulkWalkInRegistrationRequest;
use App\Http\Requests\PublicRegistrationRequest;
use App\Http\Requests\WalkInRegistrationRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationFieldResponse;
use App\Services\Participants\DuplicateReview;
use App\Support\RegistrationLegalText;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $kind = $request->query('kind', 'running');
        abort_unless(in_array($kind, ['running', 'church'], true), 404);

        $events = Event::query()
            ->where('event_kind', $kind)
            ->when($kind === 'running', fn ($query) => $query->whereIn('status', [
                EventStatus::RegistrationOpen->value,
                EventStatus::RegistrationClosed->value,
                EventStatus::Ongoing->value,
                EventStatus::Completed->value,
            ]))
            ->when($kind === 'church', fn ($query) => $query
                ->whereIn('church_status', [
                    ChurchEventStatus::Upcoming->value,
                    ChurchEventStatus::Ongoing->value,
                    ChurchEventStatus::Completed->value,
                ])
                ->whereIn('church_category', ChurchCategory::values()))
            ->whereDate('event_date', '<=', today()->addDay())
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();
        if ($kind === 'running') {
            $events = $events->filter(fn (Event $event) => $event->canViewAttendance());
        }

        $map = fn (Event $event) => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->toDateString(),
            'venue' => $event->venue,
            'status' => $event->isChurchEvent()
                ? ChurchEventStatus::tryFrom((string) $event->getRawOriginal('church_status'))->value
                : $event->status->value,
            'status_label' => $event->isChurchEvent()
                ? ChurchEventStatus::tryFrom((string) $event->getRawOriginal('church_status'))->label()
                : $event->status->label(),
            'church_category' => $event->church_category,
            'action_url' => $event->isChurchEvent()
                ? route('church.attendance', $event)
                : route('events.attendance', $event),
            'registered_count' => $event->registrations()->active()
                ->when($event->isChurchEvent(), fn ($query) => $query->whereHas('participant', fn ($participant) => $participant->where('pool', 'church')))
                ->count(),
            'marked_count' => $event->registrations()->active()
                ->whereHas('attendance')
                ->count(),
        ];

        $mapped = $events->map($map);
        $groups = $mapped->groupBy(fn (array $row) => match ($row['status']) {
            EventStatus::RegistrationOpen->value,
            EventStatus::RegistrationClosed->value,
            ChurchEventStatus::Upcoming->value => 'needsAttention',
            'ongoing' => 'open',
            'completed' => 'closed',
            default => 'closed',
        });

        return Inertia::render('attendance/index', [
            'kind' => $kind,
            'categories' => array_map(
                fn (ChurchCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ],
                ChurchCategory::cases(),
            ),
            'groups' => $groups,
            'today' => $mapped
                ->filter(fn (array $row) => $row['event_date'] >= today()->toDateString()
                    && $row['event_date'] <= today()->addDay()->toDateString()
                    && ! in_array($row['status'], [
                        EventStatus::Completed->value,
                        ChurchEventStatus::Completed->value,
                    ], true))
                ->values(),
            'recent' => $mapped
                ->filter(fn (array $row) => $row['event_date'] < today()->toDateString()
                    && ! in_array($row['status'], [
                        EventStatus::Completed->value,
                        ChurchEventStatus::Completed->value,
                    ], true))
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

        $allowedFilters = ['all', 'unmarked', 'present', 'absent'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        // Attendance-code lookup. Accepts KM4X-P2N7 and its common
        // typographic variants (lowercase, missing dash, spaced).
        // Normalizes to the stored format. When present, the code takes
        // precedence over q and filter — the operator's mental model is
        // "show me this person," not "filter the list by this code."
        $code = $this->normalizeAttendanceCode(
            (string) $request->input('code', ''),
        );
        $codeActive = $code !== '';

        $baseQuery = function () use ($event, $search, $code, $codeActive) {
            $q = Registration::query()->active()
                ->where('registrations.event_id', $event->id)
                ->where('registrations.registration_status', '!=', RegistrationStatus::Held)
                ->join('participants', 'participants.id', '=', 'registrations.participant_id')
                ->select('registrations.*');

            if ($codeActive) {
                $q->where('registrations.attendance_code', $code);

                return $q;
            }

            if ($search !== '') {
                foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
                    $like = '%'.$term.'%';
                    $q->where(function ($sub) use ($like) {
                        $sub->where('participants.first_name', 'like', $like)
                            ->orWhere('participants.middle_name', 'like', $like)
                            ->orWhere('participants.last_name', 'like', $like)
                            ->orWhere('participants.suffix', 'like', $like)
                            ->orWhere('participants.email', 'like', $like)
                            ->orWhere('participants.contact_number', 'like', $like);
                    });
                }
            }

            return $q;
        };

        // Chip counts reflect the current search (or the code, when
        // active). This is deliberate and tested: with ?q=Maria, the
        // All chip reads "2," not "4" — the volunteer sees the size of
        // their current result set, not the size of the event.
        $counts = [
            'all' => $baseQuery()->count(),
            'unmarked' => $baseQuery()->whereDoesntHave('attendance')->count(),
            'present' => $baseQuery()
                ->whereHas('attendance', fn ($q) => $q->where('attendance_status', 'present'))
                ->count(),
            'absent' => $baseQuery()
                ->where(fn ($q) => $q->whereDoesntHave('attendance')->orWhereHas('attendance', fn ($a) => $a->where('attendance_status', 'absent')))
                ->count(),
        ];

        $query = $baseQuery()->with([
            'participant:id,first_name,middle_name,last_name,suffix,email,contact_number',
            'fieldResponses.registrationField',
            'attendance:id,registration_id,attendance_status,attendance_time,notes,verified_at',
        ]);

        // Filter chips are ignored when a code is active — the code
        // already narrows to at most one row.
        if (! $codeActive) {
            if ($filter === 'unmarked') {
                $query->whereDoesntHave('attendance');
            } elseif ($filter === 'absent') {
                $query->where(fn ($q) => $q->whereDoesntHave('attendance')->orWhereHas('attendance', fn ($a) => $a->where('attendance_status', 'absent')));
            } elseif ($filter !== 'all') {
                $query->whereHas('attendance', fn ($q) => $q->where('attendance_status', $filter));
            }
        }

        $query->orderBy('participants.last_name')
            ->orderBy('participants.first_name')
            ->orderBy('registrations.id');

        $paginator = $query->paginate(50)->withQueryString();

        $rows = collect($paginator->items())->map(fn (Registration $r) => [
            'id' => $r->id,
            'source' => $r->source,
            'registered_at' => $r->registered_at?->toIso8601String(),
            'attendance_code' => $r->attendance_code,
            'tshirt_choice' => $r->tshirt_choice,
            'custom_fields' => $r->fieldResponses->map(fn ($response) => ['label' => $response->registrationField?->label ?? 'Answer', 'value' => $response->registrationField?->field_type === 'checkbox' ? (json_decode($response->value, true) ?? $response->value) : $response->value])->values(),
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
                'verified_at' => $r->attendance->verified_at?->toIso8601String(),
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
                'form_version' => $event->registration_form_version,
                'tshirt' => $event->registration_tshirt ? array_intersect_key($event->registration_tshirt, array_flip(['label', 'choices', 'required'])) : null,
            ],
            'rows' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'check_in_summary' => [
                'total' => $event->registrations()->active()->where('registration_status', '!=', RegistrationStatus::Held)->count(),
                'present' => $event->registrations()->active()->whereHas('attendance', fn ($q) => $q->where('attendance_status', 'present'))->count(),
                'recent' => $event->registrations()->active()->with(['participant', 'attendance'])
                    ->join('attendances', 'attendances.registration_id', '=', 'registrations.id')
                    ->where('attendances.attendance_status', 'present')
                    ->orderByDesc('attendances.updated_at')->limit(8)->select('registrations.*')->get()
                    ->map(fn ($r) => ['id' => $r->id, 'name' => $r->participant->fullName(), 'time' => $r->attendance?->attendance_time?->toIso8601String()])->values(),
            ],
            'counts' => $counts,
            'filters' => [
                'q' => $search,
                'filter' => $filter,
                'code' => $code,
            ],
            'can_mark' => $forceOpen || $event->canRecordAttendanceNow(),
            'is_admin' => $isAdmin,
            'force_open' => $forceOpen,
            'attendance_statuses' => collect([AttendanceStatus::Present, AttendanceStatus::Absent])
                ->map(fn (AttendanceStatus $s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ])
                ->values(),
            'urls' => [
                'monitor' => in_array($event->getRawOriginal('status'), [
                    EventStatus::RegistrationOpen->value,
                    EventStatus::RegistrationClosed->value,
                    EventStatus::Ongoing->value,
                ], true)
                    ? route('registration-monitor.show', $event)
                    : null,
                'report' => $event->isRunningEvent() && in_array($event->getRawOriginal('status'), [EventStatus::Ongoing->value, EventStatus::Completed->value], true)
                    ? route('events.report', $event)
                    : null,
            ],
        ]);
    }

    /**
     * Mark a single registration.
     *
     * Accepts an optional `verified_code`. When present, the server
     * checks it against the registration's stored attendance_code. If
     * the code matches AND the status being set is Present or Late
     * (the two states that mean "this person showed up"), the row's
     * `verified_at` is stamped with now(). The code is the proof;
     * anything else leaves `verified_at` null.
     *
     * Absent and Excused never set `verified_at` — the code proves
     * presence, not absence. A verified absence is a contradiction.
     *
     * See docs/attendance-encoding-and-reporting.md §12 and
     * SPEC-GAP-4 for the design rationale.
     */
    public function mark(Request $request, Event $event, Registration $registration): RedirectResponse
    {
        abort_unless($event->isRunningEvent(), 403);

        $forceOpen = $request->user()?->isAdmin() === true
            && $request->boolean('force_open');

        if (! $forceOpen && ! $event->canRecordAttendanceNow()) {
            abort(403, 'Attendance can only be recorded on the event day.');
        }

        if ($registration->event_id !== $event->id) {
            abort(404);
        }

        if (! $registration->isActive()) {
            abort(422, 'Only active registrations can be marked for attendance.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['present', 'absent'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'verified_code' => ['nullable', 'string', 'max:16'],
        ]);

        $status = AttendanceStatus::from($validated['status']);
        $user = $request->user();

        $shouldRecordTime = $status === AttendanceStatus::Present;

        // Verification: the submitted code must normalize to the
        // registration's stored code. The status must be one of the
        // presence states. Anything else leaves `verified_at` null.
        $verifiedAt = $shouldRecordTime ? $registration->attendance?->verified_at : null;
        $submittedCode = $this->normalizeAttendanceCode(
            (string) ($validated['verified_code'] ?? ''),
        );

        if (
            $shouldRecordTime
            && $submittedCode !== ''
            && $registration->attendance_code !== null
            && $submittedCode === $registration->attendance_code
        ) {
            $verifiedAt = now();
        }

        $registration->attendance()->updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'attendance_status' => $status,
                'attendance_time' => $shouldRecordTime ? now() : null,
                'recorded_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
                'verified_at' => $verifiedAt,
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
     * Bulk marking never sets `verified_at`. Reconciling from a paper
     * sheet is a bulk action, not a verification — the paper sheet is
     * its own record, and the system is not independently proving that
     * any of those people were physically present. Rows marked through
     * this path carry `verified_at = null` and are counted as
     * unverified in the report.
     *
     * No rate limiter — this is a trusted-staff write operation, and the
     * single-row mark endpoint has no limiter either. If abuse becomes a
     * concern, add a throttle:attendance-bulk-mark middleware here.
     */
    public function bulkMark(BulkMarkAttendanceRequest $request, Event $event): RedirectResponse
    {
        abort_unless($event->isRunningEvent(), 403);

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
            $registrations = Registration::active()->whereIn('id', $ids)->get();

            foreach ($registrations as $registration) {
                $registration->attendance()->updateOrCreate(
                    ['registration_id' => $registration->id],
                    [
                        'attendance_status' => AttendanceStatus::Present,
                        'attendance_time' => now(),
                        'recorded_by' => $user->id,
                        'verified_at' => null,
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
            $ownedIds = Registration::active()->where('event_id', $event->id)
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
        $q = Registration::query()->active()
            ->where('registrations.event_id', $event->id)
            ->where('registrations.registration_status', '!=', RegistrationStatus::Held)
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
     *
     * Encoding mode is a single top-level field for the whole batch.
     *
     *   - Live (default). Each row's attendance_time is now() — the
     *     batch is being entered as people arrive. Same as the
     *     original behavior.
     *
     *   - Deferred. Each row's attendance_time is computed from the
     *     event's date and that row's `arrival_time`. The batch
     *     preserves when each person actually arrived, not when the
     *     transcription happened.
     *
     * Per-row arrival_time is validation-required when deferred (see
     * BulkWalkInRegistrationRequest Pass 3). In live mode, any
     * submitted arrival_time is ignored.
     */
    public function bulkWalkIn(BulkWalkInRegistrationRequest $request, Event $event): RedirectResponse
    {
        abort_unless($event->isRunningEvent(), 403);

        $forceOpen = $request->user()?->isAdmin() === true
            && $request->boolean('force_open');

        if (! $forceOpen && ! $event->canRecordAttendanceNow()) {
            abort(403, 'Bulk walk-ins can only be registered during the event window.');
        }

        $validated = $request->validated();
        $user = $request->user();
        $rows = $validated['rows'];

        $encodingMode = $validated['encoding_mode'] ?? 'live';
        $deferred = $encodingMode === 'deferred';

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

            // Compute the per-row attendance timestamp. Live mode uses
            // now(); deferred mode uses the row's arrival_time on the
            // event's date. Validation guarantees arrival_time is
            // present when deferred. Dateless events opened by an
            // admin fall back to now().
            if ($deferred && $event->event_date !== null && isset($row['arrival_time']) && $row['arrival_time'] !== null) {
                $attendanceTime = $event->event_date
                    ->copy()
                    ->setTimeFromTimeString($row['arrival_time']);
            } else {
                $attendanceTime = now();
            }

            // Demographic check per row (birthdate swap Phase 4): at
            // least one of `age` (legacy) or `birthday`. The error
            // lands on the `rows.{i}.birthday` key so the table
            // highlights the birthday cell. A single bad row must not
            // block the batch (D6) — this lives in the controller
            // loop, not in the request, mirroring the identity rule.
            $rowHasAge = isset($row['age']) && $row['age'] !== '';
            $rowBirthday = $row['birthday'] ?? null;
            $rowHasBirthday = is_string($rowBirthday) && trim($rowBirthday) !== '';

            if (! $rowHasAge && ! $rowHasBirthday) {
                $errors["rows.{$i}.birthday"] = 'Please provide your date of birth.';

                continue;
            }

            $error = $this->persistBulkWalkInRow(
                $row,
                $event,
                $user,
                $attendanceTime,
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
     * The attendance timestamp is passed in by the caller — either
     * now() for live encoding, or the row's arrival_time on the
     * event's date for deferred. This method does not compute it.
     *
     * The parameter is typed as CarbonInterface rather than the
     * concrete Carbon or CarbonImmutable because the two callers
     * produce different concrete classes: now() returns a mutable
     * Illuminate\Support\Carbon, and event_date (cast as
     * immutable_datetime) returns a CarbonImmutable. Both implement
     * CarbonInterface.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $seenParticipantIds  Mutable, accumulates
     *                                               participants already
     *                                               seen in this batch.
     */
    private function persistBulkWalkInRow(
        array $row,
        Event $event,
        $user,
        CarbonInterface $attendanceTime,
        array &$seenParticipantIds,
    ): ?string {
        $email = is_string($row['email'] ?? null) ? trim($row['email']) : '';
        $phone = is_string($row['contact_number'] ?? null) ? $row['contact_number'] : null;

        $normalized = Participant::normalizeContactNumber($phone);

        if ($email === '' && $normalized === null) {
            return 'Please provide at least an email address or a contact number.';
        }

        if (app(DuplicateReview::class)->findExact($event, $row)) {
            return 'This person is already registered for this event.';
        }

        // Read-only lookup. If the identity already exists, we know
        // the row will resolve to it. No write yet.
        $existing = Participant::findByComposite('running',
            $email,
            $phone,
            $row['first_name'] ?? null,
            $row['last_name'] ?? null,
            $row['middle_name'] ?? null,
            $row['suffix'] ?? null,
        );

        // Duplicate within this batch?
        if ($existing !== null && in_array($existing->id, $seenParticipantIds, true)) {
            return 'Duplicate within this batch.';
        }

        // Already registered for this event?
        if ($existing !== null) {
            $alreadyRegistered = Registration::query()->where('event_id', $event->id)
                ->where('participant_id', $existing->id)
                ->exists();

            if ($alreadyRegistered) {
                return 'This person is already registered for this event.';
            }
        }

        // All checks passed. Persist.
        DB::transaction(function () use ($row, $event, $user, $attendanceTime, &$seenParticipantIds, $email, $phone): void {
            $participant = Participant::resolveFrom('running',
                $email !== '' ? $email : null,
                $phone,
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'middle_name' => $row['middle_name'] ?? null,
                    'suffix' => $row['suffix'] ?? null,
                    // Demographic write: prefer `birthday` (new shape,
                    // bounded to age 3-120 by the request). Fall back
                    // to `age` for legacy callers. Exactly one is
                    // guaranteed present by the loop's demographic
                    // check above. First-write-wins preserved:
                    // resolveFrom() discards these on an existing match.
                    ...(isset($row['birthday']) && $row['birthday'] !== ''
                        ? ['birthday' => $row['birthday']]
                        : ['age' => (int) $row['age']]),
                    'email' => $email !== '' ? $email : null,
                    'contact_number' => $phone,
                    'address' => $row['address'] ?? null,
                ],
            );

            $seenParticipantIds[] = $participant->id;

            $candidate = app(DuplicateReview::class)->findCandidate($event, $row);
            $registration = Registration::create([
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'registration_date' => now(),
                'registration_status' => ($candidate ? RegistrationStatus::Held : RegistrationStatus::Confirmed),
                'held_reason' => $candidate ? implode(', ', $candidate['reasons']) : null,
                'held_candidate_registration_id' => $candidate['registration']->id ?? null,
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

            if (! $registration->isActive()) {
                return;
            }
            $registration->attendance()->create([
                'attendance_status' => AttendanceStatus::Present,
                'attendance_time' => $attendanceTime,
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

    /**
     * Register a single walk-in participant.
     *
     * Two encoding modes:
     *
     *   - Live (default, or encoding_mode = 'live'). The staff member
     *     is registering the participant at the table, right now. The
     *     server stamps attendance_time with now(). This matches the
     *     original behavior; requests that omit encoding_mode fall
     *     through here.
     *
     *   - Deferred (encoding_mode = 'deferred'). The staff member is
     *     transcribing a paper form after the fact. The submitted
     *     `arrival_time` (HH:MM) is combined with the event's
     *     event_date and used as attendance_time, so the record
     *     preserves when the participant actually arrived — not when
     *     the encoding happened.
     *
     * Arrival time is validation-required when deferred (see
     * WalkInRegistrationRequest Pass 3). In live mode, any submitted
     * arrival_time is ignored.
     */
    public function encodePaper(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->isRunningEvent(), 403);
        $forceOpen = $request->user()?->isAdmin() === true && $request->boolean('force_open');
        abort_unless($forceOpen || $event->canRecordAttendanceNow(), 403);
        $timing = $request->validate([
            'encoding_mode' => ['required', 'in:live,deferred'],
            'arrival_time' => ['required_if:encoding_mode,deferred', 'nullable', 'date_format:H:i'],
        ]);
        $held = DB::transaction(function () use ($event, $request, $timing, $forceOpen): bool {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->isRunningEvent() && ($forceOpen || $event->canRecordAttendanceNow()), 403);
            $data = PublicRegistrationRequest::validatePending($event, $request->all());
            if (app(DuplicateReview::class)->findExact($event, $data)) {
                throw ValidationException::withMessages(['identity' => 'This person already has a submission for this event.']);
            }
            $participant = Participant::resolveFrom('running', $data['email'] ?? null, $data['contact_number'] ?? null,
                array_intersect_key($data, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'birthday', 'age', 'address'])));
            if ($event->registrations()->where('participant_id', $participant->id)->exists()) {
                throw ValidationException::withMessages(['identity' => 'This person is already registered for this event. Use Check-in instead.']);
            }
            $candidate = app(DuplicateReview::class)->findCandidate($event, $data);
            $registration = Registration::create([
                'event_id' => $event->id, 'participant_id' => $participant->id,
                'registration_date' => now(), 'registered_at' => now(),
                'registration_status' => $candidate ? RegistrationStatus::Held : RegistrationStatus::Confirmed, 'source' => 'form',
                'held_reason' => $candidate ? implode(', ', $candidate['reasons']) : null,
                'held_candidate_registration_id' => $candidate['registration']->id ?? null,
                'encoded_by' => $request->user()->id, 'contact_attested_at' => now(),
                'consent_accepted_at' => now(), 'privacy_notice_version' => RegistrationLegalText::PRIVACY_NOTICE_VERSION,
                'submitted_email' => $data['email'] ?? null, 'submitted_contact_number' => $data['contact_number'] ?? null,
                'emergency_contact_name' => $event->usesEmailOnlyRegistration()
                    ? $data['emergency_contact_first_name'].' '.$data['emergency_contact_last_name'] : $data['emergency_contact_name'],
                'emergency_contact_first_name' => $data['emergency_contact_first_name'] ?? null,
                'emergency_contact_last_name' => $data['emergency_contact_last_name'] ?? null,
                'emergency_contact_relationship' => $data['emergency_contact_relationship'],
                'emergency_contact_relationship_other' => $data['emergency_contact_relationship_other'] ?? null,
                'emergency_contact_number' => $data['emergency_contact_number'],
                'tshirt_choice' => $data['tshirt_choice'] ?? null,
            ]);
            foreach ($data['responses'] ?? [] as $fieldId => $value) {
                $registration->fieldResponses()->create(['registration_field_id' => $fieldId, 'value' => is_array($value) ? json_encode($value) : (string) $value]);
            }
            if ($candidate) {
                return true;
            }
            $time = $timing['encoding_mode'] === 'deferred' && $event->event_date !== null
                ? $event->event_date->copy()->setTimeFromTimeString($timing['arrival_time']) : now();
            $registration->attendance()->create(['attendance_status' => AttendanceStatus::Present,
                'attendance_time' => $time, 'recorded_by' => $request->user()->id]);

            return false;
        });

        return redirect()->route('events.attendance', $event)->with('success', $held ? 'Paper submission held for organizer review; no attendance recorded.' : 'Paper registration saved and marked present.');
    }

    public function quickWalkIn(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->isRunningEvent(), 403);
        $forceOpen = $request->user()?->isAdmin() === true && $request->boolean('force_open');
        abort_unless($forceOpen || $event->canRecordAttendanceNow(), 403);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\\pL\\pM '\\-]+$/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\\pL\\pM '\\-]+$/u"],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:10'],
        ]);
        DB::transaction(function () use ($event, $validated, $request): void {
            // A name alone cannot establish identity. Never resolve or merge it.
            $participant = Participant::create(['pool' => 'running', ...$validated, 'provisional_event_id' => $event->id]);
            $registration = Registration::create([
                'event_id' => $event->id, 'participant_id' => $participant->id,
                'registration_date' => now(), 'registered_at' => now(),
                'registration_status' => RegistrationStatus::Confirmed, 'source' => 'walk_in',
                'flagged_at' => now(), 'notes' => 'Name-only provisional walk-in. Staff review required before linking identity.',
            ]);
            $registration->attendance()->create([
                'attendance_status' => AttendanceStatus::Present, 'attendance_time' => now(),
                'recorded_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('events.attendance', $event)->with('success', 'Quick walk-in marked present. Identity remains provisional.');
    }

    public function walkIn(WalkInRegistrationRequest $request, Event $event): RedirectResponse
    {
        abort_unless($event->isRunningEvent(), 403);

        $forceOpen = $request->user()?->isAdmin() === true
            && $request->boolean('force_open');

        if (! $forceOpen && ! $event->canRecordAttendanceNow()) {
            abort(403, 'Walk-ins can only be registered on the event day.');
        }

        $validated = $request->validated();
        $user = $request->user();

        $encodingMode = $validated['encoding_mode'] ?? 'live';
        $arrivalTime = $validated['arrival_time'] ?? null;

        // Compute the attendance timestamp once, outside the
        // transaction. Live mode uses now(); deferred mode uses the
        // arrival time on the event's date. Dateless events opened
        // by an admin fall back to now().
        if ($encodingMode === 'deferred' && $event->event_date !== null && $arrivalTime !== null) {
            $attendanceTime = $event->event_date
                ->copy()
                ->setTimeFromTimeString($arrivalTime);
        } else {
            $attendanceTime = now();
        }

        DB::transaction(function () use ($validated, $event, $user, $attendanceTime): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (app(DuplicateReview::class)->findExact($event, $validated)) {
                throw ValidationException::withMessages(['identity' => 'This person already has a submission for this event.']);
            }
            $participant = Participant::resolveFrom('running',
                $validated['email'] ?? null,
                $validated['contact_number'] ?? null,
                [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'suffix' => $validated['suffix'] ?? null,
                    // Demographic write: prefer `birthday` (new shape,
                    // bounded to age 3-120 by WalkInRegistrationRequest,
                    // D4: required on walk-in like the public form).
                    // Fall back to `age` for legacy callers and tests
                    // that still POST `age`.
                    //
                    // Exactly one is guaranteed present by the request's
                    // demographic pass. If both are present, birthday
                    // wins on write; the accessor prefers it on read
                    // anyway, so writing both would leave stale age
                    // data on the row. First-write-wins is preserved:
                    // on an existing participant match, resolveFrom()
                    // discards these attributes entirely.
                    ...(isset($validated['birthday']) && $validated['birthday'] !== ''
                        ? ['birthday' => $validated['birthday']]
                        : ['age' => (int) $validated['age']]),
                    'email' => $validated['email'] ?? null,
                    'contact_number' => $validated['contact_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                ],
            );

            $candidate = app(DuplicateReview::class)->findCandidate($event, $validated);
            $registration = Registration::create([
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'registration_date' => now(),
                'registration_status' => ($candidate ? RegistrationStatus::Held : RegistrationStatus::Confirmed),
                'held_reason' => $candidate ? implode(', ', $candidate['reasons']) : null,
                'held_candidate_registration_id' => $candidate['registration']->id ?? null,
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

            if ($registration->isActive() && ($validated['mark_present'] ?? true)) {
                $registration->attendance()->create([
                    'attendance_status' => AttendanceStatus::Present,
                    'attendance_time' => $attendanceTime,
                    'recorded_by' => $user->id,
                ]);
            }
        });

        return redirect()->route('events.attendance', $event);
    }

    /**
     * Normalize an attendance-code input to its stored form.
     *
     * The stored format is KM4X-P2N7 (4 + dash + 4). Operators may
     * type it in any of these forms:
     *
     *   KM4X-P2N7   (canonical)
     *   km4x-p2n7   (lowercase)
     *   KM4XP2N7    (no dash)
     *   KM4X P2N7   (space instead of dash)
     *
     * All normalize to KM4X-P2N7. Returns '' for anything that does
     * not reduce to 8 alphanumerics — those never match anything, and
     * the caller treats the code as inactive.
     */
    private function normalizeAttendanceCode(string $input): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');

        if (strlen($clean) !== 8) {
            return '';
        }

        return substr($clean, 0, 4).'-'.substr($clean, 4);
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
