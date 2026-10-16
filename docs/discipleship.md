# WDEMS — Discipleship Module

**Audience:** Whoever implements, modifies, or reasons about the
Discipleship module — the six programs, the batches, the
enrollment model, the session scheduling, the attendance matrix,
or the progress service.

**Purpose:** Reference for the shipped state of the module.
Every claim describes code that exists on `main` as of commit
`7cd20a1` (2026-10-16). Not a spec, not a plan.

**Status:** Live. Phase 5 shipped end to end across eight
sub-phases (5A–5H). The progress entries in `docs/progress.md`
are the chronological record; this file is the consolidated
reference.

**Related:**
- `docs/church-events/07-scope-and-task-division.md` §3.6 — the
  task breakdown this module implements
- `docs/church-events/09-mockup-scope-inventory.md` — what the
  client mockups show beyond this module
- `docs/progress.md` — the eight sub-phase entries (2026-10-16)
- `docs/participant-identity.md` — the Participant identity
  model every enrollment is anchored to
- `docs/attendance-code.md` — the running-event attendance
  model, structurally distinct from this one

---

## 1. The 60-second overview

Discipleship is the church-formation workstream. Six fixed
programs (Life Class, Encounter God Retreat, School of Leaders
1–3, Cell Convergence) track participants through a course of
sessions.

The module has three levels:

    Program → Batch → Session

- A **program** is a course or track. Six exist, seeded. No UI
  creates or edits them.
- A **batch** is one cohort of a program. Named by the organizer
  (e.g. "Spring 2026 Cohort"). Batches are where enrollment and
  attendance actually happen.
- A **session** is one meeting of a batch, with a date and a
  topic. Sessions are the unit that attendance is recorded
  against.

Parallel to those, two records:

- An **enrollment** says "this participant is pursuing this
  program." Keyed on (participant, program), one row per
  participant per program for life. Has a nullable *batch
  pointer* that says which cohort they are currently attending.
- An **attendance** row says "this enrollment was present (or
  not) at this session." Keyed on (enrollment, session).

Progress is a computation over those two — how many of the
sessions that have happened since enrollment has this
participant attended. See §6.

---

## 2. Where things live

### Database tables

| Table | Purpose |
|---|---|
| `discipleship_programs` | The six seeded programs |
| `discipleship_batches` | Cohorts, FK to program |
| `discipleship_enrollments` | Participant × program |
| `discipleship_sessions` | Meetings, FK to batch |
| `discipleship_attendances` | Enrollment × session |

### Models

| Model | File |
|---|---|
| `DiscipleshipProgram` | `app/Models/DiscipleshipProgram.php` |
| `DiscipleshipBatch` | `app/Models/DiscipleshipBatch.php` |
| `DiscipleshipEnrollment` | `app/Models/DiscipleshipEnrollment.php` |
| `DiscipleshipSession` | `app/Models/DiscipleshipSession.php` |
| `DiscipleshipAttendance` | `app/Models/DiscipleshipAttendance.php` |

The `Participant` model carries a `discipleshipEnrollments()`
relation.

### Enums

| Enum | Values |
|---|---|
| `DiscipleshipEnrollmentStatus` | enrolled, in_progress, completed, withdrawn, on_hold |
| `DiscipleshipAttendanceStatus` | present, absent, excused |

`DiscipleshipEnrollmentStatus` has a `label()` method and two
helpers: `values()` and `activeValues()` (enrolled +
in_progress).

### Controllers

| Controller | Actions |
|---|---|
| `DiscipleshipController` | index, show, exportCsv, participantSearch |
| `DiscipleshipBatchController` | show, store, update, destroy |
| `DiscipleshipEnrollmentController` | store, update, destroy (destroy = withdraw) |
| `DiscipleshipSessionController` | store, update, destroy |
| `DiscipleshipAttendanceController` | bulkUpsert, destroy |

### FormRequests

All under `app/Http/Requests/Discipleship/`:

- `StoreDiscipleshipBatchRequest`
- `UpdateDiscipleshipBatchRequest`
- `StoreDiscipleshipEnrollmentRequest`
- `UpdateDiscipleshipEnrollmentRequest`
- `StoreDiscipleshipSessionRequest`
- `UpdateDiscipleshipSessionRequest`
- `StoreDiscipleshipAttendanceBulkRequest`

### Services

| Service | File |
|---|---|
| `DiscipleshipProgressService` | `app/Services/Discipleship/DiscipleshipProgressService.php` |

### Seeder

`Database\Seeders\DiscipleshipProgramSeeder` — six rows,
idempotent via `firstOrCreate` on slug.

### Frontend

| Page | File |
|---|---|
| Landing | `resources/js/pages/discipleship/index.tsx` |
| Program detail | `resources/js/pages/discipleship/show.tsx` |
| Batch detail | `resources/js/pages/discipleship/batch-show.tsx` |

Sidebar entry in `app-sidebar.tsx` — `GraduationCap` icon,
between Fellowship and Events.

### Tests

`tests/Feature/Discipleship/`:

- `DiscipleshipProgramTest.php` — 6 tests
- `DiscipleshipBatchTest.php` — 12 tests
- `DiscipleshipEnrollmentTest.php` — 24 tests
- `DiscipleshipSessionTest.php` — 13 tests
- `DiscipleshipAttendanceTest.php` — 15 tests
- `DiscipleshipProgressTest.php` — 13 tests
- `DiscipleshipProgramReportTest.php` — 15 tests

**96 tests, 398 assertions** at Phase 5 closure.

---

## 3. The data model

### Program

Seeded. Columns: id, name, slug (unique), description,
display_order, required_sessions (nullable), extra_requirements
(nullable json), timestamps.

`slug` is the public identifier (`life-class`,
`school-of-leaders-1`). Routes bind programs by slug, not id.

`required_sessions` and `extra_requirements` were added in 5F
as completion criteria. All six seeded rows have null for both.

### Batch

Columns: id, program_id (FK, CASCADE), name, start_date,
created_by (FK, RESTRICT), timestamps.

Index on (program_id, start_date). No unique on
(program_id, name) — two cohorts may share a name
("Spring 2026", "Spring 2027" if organizers choose to name them
similarly).

### Enrollment

Columns: id, participant_id (FK, CASCADE), program_id (FK,
CASCADE), batch_id (FK, SET NULL, nullable), status,
enrolled_at (nullable timestamp), notes, created_by (FK,
RESTRICT), timestamps.

**UNIQUE on (participant_id, program_id)** — total, not partial.
A participant holds one enrollment row per program across their
lifetime. Withdrawn and on_hold are statuses, not deletions.
Re-enrolling resets the same row.

`batch_id` is the *batch pointer*: which cohort the participant
is currently in. Nullable because a participant may be enrolled
at the program level before a specific batch is chosen. Set to
NULL on batch delete via `nullOnDelete`.

### Session

Columns: id, batch_id (FK, CASCADE), session_date, topic, notes,
created_by (FK, RESTRICT), timestamps.

Index on (batch_id, session_date). No unique on
(batch_id, session_date) — two sessions can happen on the same
day. No `session_number` column — order is derived from
session_date, with id as tiebreak.

### Attendance

Columns: id, enrollment_id (FK, CASCADE), session_id (FK,
CASCADE), status (16 chars), recorded_by (FK, SET NULL,
nullable), recorded_at, notes, timestamps.

**UNIQUE on (enrollment_id, session_id).** Re-marking a cell
upserts rather than duplicating.

**`recorded_by` is the exception to the codebase convention.**
Every other user-attribution FK uses RESTRICT so a deleted
account cannot orphan history. Attendance uses `nullOnDelete`
because attendance is factual data — the fact outlives the
recorder. Losing the attribution is acceptable; losing the
attendance row is not.

---

## 4. The workflow

### Program lifecycle

None. Programs are seeded. No create, update, or destroy.
If the client adds a seventh program, the change point is
`DiscipleshipProgramSeeder`.

### Batch lifecycle

Batches are created, renamed, and deleted by staff. Any
authenticated user can perform any action; there is no per-batch
ownership check. Created from the program show page; rendered on
the same page (each row links to the batch detail page).

### Enrollment lifecycle

Enrollments are created, updated, and withdrawn by staff.

- **Create:** `POST /discipleship/programs/{program:slug}/enrollments`
  Initial status is always `enrolled`. `enrolled_at` defaults to
  `now()` but accepts a past date for retroactive logging.
- **Update:** `PUT /discipleship/enrollments/{enrollment}`
  Changes status, batch pointer, or notes. Participant and
  program are immutable.
- **Withdraw:** `DELETE /discipleship/enrollments/{enrollment}`
  Sets `status = withdrawn`. Does **not** delete the row. See
  §10 for the rationale.

### Session lifecycle

Standard CRUD. Sessions are created on the batch detail page,
renamed or rescheduled via a dialog, deleted via confirm.

### Attendance marking

The batch detail page renders a matrix: rows = enrollments,
columns = sessions, cells = status dropdowns. The operator
changes cells; a Save button appears when any cell is dirty and
fires one bulk upsert with the changed cells only.

Clearing a cell back to "—" is filtered out of the bulk payload
— it is not persisted as a status. The `destroy` endpoint
handles clearing, though the current frontend does not wire it
up. A future small block can add a clear-cell action.

---

## 5. Routes

All under the `/discipleship` prefix, in the main authenticated
group (`auth`, `verified.or.admin`, `device.trusted`,
`event.advance`).

| Method | URL | Controller action | Route name |
|---|---|---|---|
| GET | `/discipleship` | `DiscipleshipController@index` | `discipleship.index` |
| GET | `/discipleship/programs/{program:slug}` | `DiscipleshipController@show` | `discipleship.programs.show` |
| GET | `/discipleship/programs/{program:slug}/enrollments/export` | `DiscipleshipController@exportCsv` | `discipleship.enrollments.export` |
| GET | `/discipleship/programs/{program:slug}/participant-search` | `DiscipleshipController@participantSearch` | `discipleship.participants.search` |
| POST | `/discipleship/programs/{program:slug}/batches` | `DiscipleshipBatchController@store` | `discipleship.batches.store` |
| GET | `/discipleship/batches/{batch}` | `DiscipleshipBatchController@show` | `discipleship.batches.show` |
| PUT | `/discipleship/batches/{batch}` | `DiscipleshipBatchController@update` | `discipleship.batches.update` |
| DELETE | `/discipleship/batches/{batch}` | `DiscipleshipBatchController@destroy` | `discipleship.batches.destroy` |
| POST | `/discipleship/programs/{program:slug}/enrollments` | `DiscipleshipEnrollmentController@store` | `discipleship.enrollments.store` |
| PUT | `/discipleship/enrollments/{enrollment}` | `DiscipleshipEnrollmentController@update` | `discipleship.enrollments.update` |
| DELETE | `/discipleship/enrollments/{enrollment}` | `DiscipleshipEnrollmentController@destroy` | `discipleship.enrollments.withdraw` |
| POST | `/discipleship/batches/{batch}/sessions` | `DiscipleshipSessionController@store` | `discipleship.sessions.store` |
| PUT | `/discipleship/sessions/{session}` | `DiscipleshipSessionController@update` | `discipleship.sessions.update` |
| DELETE | `/discipleship/sessions/{session}` | `DiscipleshipSessionController@destroy` | `discipleship.sessions.destroy` |
| POST | `/discipleship/batches/{batch}/attendances/bulk` | `DiscipleshipAttendanceController@bulkUpsert` | `discipleship.attendances.bulk` |
| DELETE | `/discipleship/attendances/{attendance}` | `DiscipleshipAttendanceController@destroy` | `discipleship.attendances.destroy` |

16 routes. `{program:slug}` binds by slug; `{batch}`,
`{enrollment}`, `{session}`, `{attendance}` bind by id — none of
those have a slug, and their names are not unique.

**Route declaration order matters.** The batch routes and
session routes are more specific than the program routes; they
sit first inside the group. Laravel matches in declaration
order.

---

## 6. Progress and completion

`DiscipleshipProgressService` — three methods, all pure
calculators. No writes. No side effects. No container binding;
call sites resolve via `app(DiscipleshipProgressService::class)`.

### `progress(DiscipleshipEnrollment): array`

Returns:

    [
      'attended' => int,
      'applicable' => int,
      'percentage' => float|null,
      'required_sessions' => int|null,
      'is_complete' => bool,
    ]

**Applicable** = sessions in the enrollment's batch where
`session_date` is between the enrollment's `enrolled_at` date
and today (both inclusive). No batch pointer → 0 applicable.
Sessions before enrollment do not count against the
participant; sessions in the future are not yet due.

**Attended** = attendance rows for this enrollment where the
session is in the applicable set and status is `present` or
`excused`. Excused counts as attended — pre-arranged absence
treated as a soft-present. Changing this policy is a one-line
edit inside `progress()`.

**Percentage** = `round(attended / applicable * 100, 1)`, or
null when applicable is 0.

**is_complete**:
- `required_sessions` set: `attended >= required`
- `required_sessions` null: `applicable > 0 AND attended === applicable`

### `batchSummary(DiscipleshipBatch): array`

One row per enrollment visible on the batch detail page (batch-
assigned plus program-level with a null batch pointer). Same
shape as `progress()`, with an added `enrollment_id` key.

**N+1 note:** each enrollment's progress runs its own queries.
A 20-person batch is ~40 queries. Acceptable at church scale. If
a batch ever exceeds ~100 enrollees, batch the applicable-
session lookup and the attended-count lookup by enrollment_id.

### `programOverview(DiscipleshipProgram): array`

Status counts, no per-enrollment progress:

    [
      'total', 'completed', 'in_progress', 'not_started',
      'withdrawn', 'on_hold', 'completion_rate',
    ]

`not_started` maps to the `enrolled` status — a participant who
is enrolled but has not been marked In Progress yet.
`completion_rate` = `round(completed / total * 100, 1)`, null
when total is 0.

---

## 7. Frontend surfaces

### Landing — `discipleship/index.tsx`

Six program cards in seeded order. Each card shows the program
icon (mapped by slug), name, description, and batch count.
Clicking a card navigates to the program detail page.

### Program detail — `discipleship/show.tsx`

Three sections:

**Header + overview + quick actions** (grid, 3 columns):

- Header: program name, description, batch and enrollment
  counts.
- Program Overview card: completion-rate donut plus four
  counters (total, completed, in progress, not started).
- Quick Actions card: view program details (dialog), manage
  batches (anchor scroll to `#batches`), add enrollment.

**Batches section.** One row per batch, with edit and delete
controls. Batch name links to the batch detail page.

**Enrollments section.** Filter bar, table, dialogs.

Filter bar: debounced search (300ms) across participant
name/email/contact, status filter chips with counts, batch
dropdown (all / unassigned / specific batch), "clear filters"
link when any is active, result count, Export CSV button.

Table columns: Participant, Contact, Batch, Status, Progress
(bar + attended/applicable + percentage), Enrolled At, Actions.

Four dialogs:

- **Add Enrollment** — live participant search (debounced fetch
  to the JSON endpoint), optional batch dropdown, optional
  enrollment date, notes. Already-enrolled participants render
  disabled in the result list.
- **Edit Enrollment** — status dropdown, batch dropdown, notes.
- **Withdraw** — confirm dialog explaining row-survives
  semantics.
- **Program Details** — read-only summary of description,
  counts, and completion criteria placeholder text.

### Batch detail — `discipleship/batch-show.tsx`

Three sections:

**Header.** Batch name, start date, session and enrollment
counts, Add Session button.

**Sessions section.** One row per session ordered by
`session_date` ascending. Each row shows the date, topic, and
notes. Edit and delete controls.

**Attendance section.** The matrix. Rows = enrollments (the
same set as `batchSummary`), columns = sessions (chronological),
cells = status dropdowns.

Cell state is held in local React state seeded from the server
payload. Changed cells mark themselves dirty; a lime ring
highlights pending edits. The Save Attendance button appears
only when dirty cells exist and fires one `POST bulk` with the
changed cells. A Progress column at the end of each row shows
the enrollment's progress bar.

The first column (Participant) is `sticky left-0` for
horizontal scroll. Cells are `min-w-[100px]`.

---

## 8. Design decisions

Each of these has a rationale that is not obvious from the code
alone. Changing any of them has consequences.

### Programs are seeded, not user-created

No create/edit/destroy for programs. The seeder is idempotent
via `firstOrCreate` on slug. Rationale: the six are a fixed
set the client confirmed. If a seventh is added later, it is a
code change — one row in the seeder — not a new admin screen.

### Enrollment is program-scoped with a nullable batch pointer

The task list said both "unique (participant, program, active)"
and "enrolled per batch." Resolved by taking both: total unique
on (participant_id, program_id) plus a nullable `batch_id` that
records which cohort the participant is in.

Total unique (not partial) because MySQL 8 does not support
filtered indexes. Withdrawn and on_hold are statuses, not
deletions. Re-enrolling resets the same row.

### Withdraw is a status change, not a row deletion

`DELETE /enrollments/{enrollment}` sets `status = withdrawn`.
Rationale: the total unique on (participant_id, program_id) is
the invariant. Deleting would allow a second row to be created
for the same participant+program, breaking that invariant and
losing the prior attempt's history.

### Attendance is keyed on the enrollment, not the participant

`enrollment_id` + `session_id`, not `participant_id` +
`session_id`. Rationale: attendance is a fact about an
enrollment's progress, not about the participant directly. A
participant in two programs has two independent attendance
trails.

### Excused counts as attended

Pre-arranged absence treated as a soft-present for progress
purposes. Matches how most church discipleship programs work.
The service computes the percentage either way from the same
data; changing the policy is a one-line edit.

### `recorded_by` uses `nullOnDelete`

Every other user-attribution FK in the codebase uses RESTRICT.
Attendance is the exception: the fact outlives the recorder's
account. Losing the attribution is acceptable; losing the
attendance row is not.

### Sessions have no `session_number`

Order is derived from `session_date`, with id as a tiebreak.
Same reasoning as batches: no artificial ordinal that has to be
kept in sync with reality.

### No unique on (batch_id, session_date)

Two sessions can happen on the same day. If duplicate-day
enforcement becomes a data-quality need, add it then.

### Bulk upsert for attendance, not per-cell PUT

A 20 × 20 matrix is 400 cells. Per-cell writes would be
hundreds of requests on a full re-edit. One POST with an array
of changed cells, one transaction.

### Cross-batch guard is strict

Every enrollment_id in a bulk attendance payload must belong to
the batch's program; every session_id must belong to the batch.
A single foreign id aborts the whole request with a 404 rather
than silently skipping. Rationale: a mixed payload indicates a
UI bug or a tampered request; partial success would hide the
problem.

### Program-level enrollments with a null batch pointer are
accepted in the matrix

A participant can be enrolled in a program before being assigned
to a batch. If they show up to a session, the operator can mark
them. This is why the enrollment schema has a nullable
batch_id.

### CSV is a direct anchor, not an Inertia visit

The export route returns `streamDownload`, which sets
`Content-Disposition: attachment`. An Inertia visit would
intercept the response and try to render it as a page. A plain
`<a href>` lets the browser handle the download natively.

### Counts on filter chips ignore the status filter

The chips show how many of each status match the current q and
batch scope. This gives the operator "I searched for X, here's
how they break down by status." Including the status filter
would make the active chip always show its own total, which is
useless.

### No enrollment status transition policy

All five statuses are currently legal from any state. Nothing
has asked for constraints. A policy class that logs "everything
is legal" logs nothing. If a specific rule is requested later
(e.g. "can't go from withdrawn straight to completed"), build
it then.

### No pagination on any list

Program detail enrollments and batch detail matrix load
everything. Fine at church scale. If a program exceeds ~500
enrollees or a batch exceeds ~100, add pagination.

---

## 9. Test coverage

**96 tests, 398 assertions** across seven files, all green at
Phase 5 closure. Full suite at closure: **791 passed, 3
skipped, 3,080 assertions.**

| File | Tests | Covers |
|---|---|---|
| `DiscipleshipProgramTest` | 6 | Landing render, seeded order, per-slug view, 404, seeder idempotency, auth |
| `DiscipleshipBatchTest` | 12 | Store, update, destroy, validation, duplicates allowed, cascade, ordering, auth |
| `DiscipleshipEnrollmentTest` | 24 | Store (with/without batch), duplicate prevention, cross-program batch rejection, status transitions, withdraw semantics, three-level cascade, participant-search endpoint, auth |
| `DiscipleshipSessionTest` | 13 | Store, update, destroy, validation, retroactive logging, same-day allowed, ordering, cascade, auth |
| `DiscipleshipAttendanceTest` | 15 | Bulk upsert, idempotency, update, cross-batch guards, program-level enrollment acceptance, empty cells, invalid status, destroy, cascade, auth |
| `DiscipleshipProgressTest` | 13 | Progress (present, excused, absent, mixed, window boundaries), required_sessions set/unset, batchSummary, programOverview |
| `DiscipleshipProgramReportTest` | 15 | Search (name/last/email), status filter, batch filter, unassigned, combined filters, counts scoping, CSV response and filters, auth |

**Four uncovered relations**, deliberately: `creator()` on
Batch, Enrollment, and Session; `recorder()` on Attendance. Not
called by any controller, service, or view. Testing them would
be testing `belongsTo`. If a future feature uses them, cover
that feature end to end.

---

## 10. What is not built

The client mockups (`docs/church-events/09-mockup-scope-
inventory.md`) show Discipleship with features this module does
not yet have. Not built:

- **Program selector strip at the top of the page.** Current
  landing navigates away; the mockup swaps the detail in place.
- **Attendance (X/Y) column on the program show page.** The
  progress bar shows the same information but not the mockup's
  two-column layout.
- **Batch Summary card.** Per-batch progress bars on the
  program show page.
- **Completion criteria editor.** `required_sessions` and
  `extra_requirements` exist on the model but no UI edits them.
  The Program Details dialog shows a placeholder text.
- **Full criteria display in the Program Details dialog.** The
  program payload does not carry `required_sessions` or
  `extra_requirements` at the top level — they arrive per
  enrollment via the progress sub-object.
- **Enrollment status transition rules.** All transitions legal.
- **Cell clearing on the attendance matrix.** The API supports
  it (DELETE /attendances/{attendance}), but the frontend does
  not call it.
- **Per-session attendance view.** The matrix is the only
  attendance surface.
- **Pagination on any list.**
- **Audit log surfacing.** `recorded_by` and `recorded_at` are
  stored but not displayed.

Two items flagged during Phase 5 as client decisions:

1. **Per-program required_sessions values.** All six seeded
   programs have null. The client decides the thresholds.
2. **Discipleship status "Not Started" from the mockup.** Was
   treated as a UI label for the initial state; the enum
   remains five values.

---

## 11. Open items

Known but not fixed. Recorded so a future session does not
rediscover them.

1. **`required_sessions` backfill.** Seeded programs have null.
   Requires a client decision per program.
2. **Program payload does not include criteria columns
   directly.** The Program Details dialog shows placeholder
   text. A small additive block can extend the payload.
3. **The four `creator()`/`recorder()` relations** are
   uncovered. Cosmetic.
4. **No pagination.** See §8.
5. **Cell clearing from the frontend.** API supports; UI does
   not yet call it.
6. **N+1 in `batchSummary()`.** See §6.

---

## 12. Cross-references

| Document | Purpose |
|---|---|
| `docs/church-events/07-scope-and-task-division.md` §3.6 | Phase 5 task breakdown (5A–5H) |
| `docs/church-events/09-mockup-scope-inventory.md` | What the client mockups show beyond this module |
| `docs/progress.md` 2026-10-16 entries | The eight sub-phase records (5A through 5H) |
| `docs/participant-identity.md` | Participant identity model |
| `docs/attendance-code.md` | Running-event attendance — structurally distinct from Discipleship attendance |
| `docs/architecture.md` | Whole-system overview |

---

## 13. Changelog

- **2026-10-16** — File created. Documents the state of the
  Discipleship module after Phase 5 closure at commit `7cd20a1`.
  Covers data model, workflow, progress semantics, routes,
  frontend, design decisions, test coverage, and open items.
  Eight sub-phases consolidated into one reference.
