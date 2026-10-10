# WDEMS — Attendance Redesign

**Audience:** Whoever implements the three-mode attendance workspace and
the time-based attendance window.

**Purpose:** Specification and phased plan for the changes that follow
FIX-026 (attendance search/filter/pagination) and FIX-027 (contact and
address quality rules). Captures the operational reality of Philippine
community runs and fun runs: walk-ins arrive in bursts, pre-registered
participants sign a physical paper sheet, and the paper form carries
the same fields as the online form.

**Status:** Planned. Phases A/B/C not started. This document is the
authoritative spec; each phase ships independently.

**Related:**
- `docs/registration.md` §7 (attendance)
- `docs/participant-identity.md` §6 (identity resolution)
- `docs/participant-identity-and-monitoring-plan.md` (identity plan)
- `docs/fixes.md` FIX-026 (attendance search/filter/pagination)
- `docs/fixes.md` FIX-027 (contact and address quality rules)
- `docs/implementation-notes.md` §6 (`(event_id, participant_id)` UNIQUE)

---

## 1. The problem

The walk-in flow assumes one operator entering one person at a time, in
real time, at the moment of arrival. Philippine community run
operations don't work that way.

Three distinct operational gaps:

**1.1 Bulk walk-in entry.** Fifty walk-ins arrive at 04:00. The current
dialog handles one person, then closes. Staff would need fifty
dialog opens, each with a full form. Untenable.

**1.2 Pre-registered confirmation from paper.** One hundred
pre-registered participants show up and sign a paper attendance sheet.
The system has one hundred rows, none marked. Staff goes down the paper
sheet and taps Present for each. With pagination at 50 and one POST per
row, the reconciliation is slow and error-prone.

**1.3 Custom field capture on walk-in.** The paper walk-in form has
the same shape as the online form, including any custom fields the
organizer configured (shirt size, blood type if added, guardian name if
added). The current walk-in dialog renders only the three toggleable
common fields plus name and age. Custom field answers are silently
dropped even if the client sends them.

The at-least-one identity rule and the quality rules from FIX-027
already protect the identity and format layers. What is missing is the
operational layer: batch entry, batch confirmation, and per-event
custom field rendering on the walk-in path.

## 2. Design principle: one page, three modes

The attendance page becomes a workspace with a mode toggle. All three
modes share the same search, filter chips, pagination, row primitive,
and event payload. Only the row interaction and the primary action
change.

    Attendance — {event}

    [ Mark ] [ Confirm ] [ Add ]        <- mode toggle

    Search  [__________________]

    [ All (42) ] [ Unmarked (10) ] [ Present (32) ] ...

    ☐  Row    Row    Row    Row
    ☐  Row    Row    Row    Row
    ...

- **Mark mode** — current behavior. Per-row Present button and ⋯ menu.
  No multi-select. Kept as the default.
- **Confirm mode** — checkbox column, "Mark N selected as present"
  toolbar, "Mark all visible" shortcut. Bulk mark against existing
  registrations.
- **Add mode** — two entry points: the existing single dialog and a new
  multi-row table. Custom fields render in both.

Mode is not persisted across sessions. Default is Mark.

Device layout: desktop renders the mode toggle as a tab bar. Mobile
renders it as a dropdown select. Same content, different chrome.

## 3. The attendance window change

The current gate is `Event::canRecordAttendanceToday()`, day-granular:
`event_date <= today` and status in a specific list. This allows an
event starting at 06:00 to be marked from 00:00 — six hours early.

Replace with `Event::canRecordAttendanceNow()`, time-granular relative
to the event's start time:

    Returns false if status not in [registration_closed, ongoing]
    Returns false if now() < (event_date + start_time) - 1 hour
    Returns true otherwise

Fallback: if `start_time` is null, use `event_date 00:00`. Same
effective behavior as today for events without a start time. Log a
warning if the event is on its event day and has no start time — that
is almost certainly an oversight.

Closing: marking closes when status leaves `ongoing`. Completed events
stay viewable (view-only) — same as today.

**Admin override.** A "Force open" toggle on the attendance page,
visible to admins only. When on, the window check is bypassed for that
request. Not persisted. Re-evaluated on every page load. Rationale:
flexible without a schema change, and staff never see the toggle.

Both `mark()` and `walkIn()` switch to the new gate. `canViewAttendance()`
is unchanged — it still governs read access.

## 4. Rule inventory — locked decisions

The following decisions were made before writing this plan. Each is
binding for the implementation. Changing any of them changes the
build.

| # | Rule | Value |
|---|---|---|
| D1 | When batch entry is available | Same gate as single walk-in — `canRecordAttendanceNow()` |
| D2 | Source of paper-transcribed registration | `paper` for new people; existing pre-reg keeps `form` |
| D3 | Does batch create pre-reg confirmations | Yes — Confirm mode is bulk mark, not new registration |
| D4 | Custom fields on walk-in | Required per event, per `RegistrationField.is_required` |
| D5 | Walk-in one mode or two | Two entry points in Add mode: dialog and batch table |
| D6 | Batch row errors | Per-row accept/reject — one bad row does not fail the batch |
| D7 | `recorded_by` for bulk | The batch submitter (single user) |
| D8 | Can batch mix walk-in and confirm | No — modes separated |
| D9 | Paper signature capture | Deferred to ISSUE-010 (legal layer) |
| D10 | "Mark all visible" scope | Current filter and search — WYSIWYG |
| E1 | Rate limiter for batch | `walk-in-batch` — 10/min/user. Single walk-in keeps no limiter |
| E2 | Two devices same batch | UNIQUE catches it; UI shows "already registered" per row |
| E3 | Optimistic vs sequential | Sequential with progress indicator |
| E4 | Idempotency | Client UUID per row, server dedupes within 60s |
| F1 | `paper` source | Live value |
| F2 | Post-hoc custom responses | No — deferred to Phase 8 (monitor actions) |
| F3 | First-write-wins on batch | Yes, same as single walk-in |
| F4 | Edit participants from batch | No |
| G1 | Device layouts | Tabs on desktop, dropdown on mobile |
| G2 | Keyboard | Enter = next row, Tab = next field, Ctrl/Cmd+Enter = submit |
| G3 | Where batch lives | Same attendance page, mode toggle at top |
| G4 | Mode toggle placement | Top toolbar, above search |
| G5 | Undo | Not in v1 — re-mark to correct |

## 5. Data model impact

No new tables. No new columns on `participants` or `registrations`.

**`paper` source value.** Already exists in the schema (nullable string
with values `form`, `walk_in`, `paper`). Phase C becomes the first
writer of `paper`. `sourceLabel()` in `attendance.tsx` already handles
the case.

**`attendances` unchanged.** No new columns. The existing `recorded_by`
and `attendance_time` cover batch operations. Bulk mark uses
`updateOrCreate` (idempotent) per registration.

**`registration_field_responses` unchanged.** Phase A writes rows that
Phase 4 deferred. The model and table already exist. The write shape
mirrors `PublicRegistrationController::store()` — string values,
arrays JSON-encoded.

## 6. Phase A — window and custom fields on the walk-in dialog

**Goal.** Change the gate. Render custom fields in the walk-in dialog.
Write `RegistrationFieldResponse` rows from the walk-in controller.

**Scope.**

- `Event::canRecordAttendanceNow()` added. `canRecordAttendanceToday()`
  removed (only two call sites, both in `AttendanceController`).
- `AttendanceController::show` and `walkIn` call the new method.
- Admin force-open toggle: a query string parameter that, when present
  and the user is an admin, bypasses the window check. No persistence.
- `WalkInRegistrationRequest` gains `responses.*` validation rules
  mirroring `PublicRegistrationRequest::withValidator()` Pass 1 + 2.
- `AttendanceController::walkIn` writes `RegistrationFieldResponse`
  rows inside the existing transaction.
- `WalkInDialog` renders the event's custom fields, all eight types.
  `useForm` state extends with a `responses` map.
- Client-side validation for walk-in responses reuses
  `registration-field-validation.ts` where possible.

**Files touched.**

    app/Models/Event.php                                    (window change)
    app/Http/Controllers/AttendanceController.php           (gate + write)
    app/Http/Requests/WalkInRegistrationRequest.php         (responses rules)
    resources/js/components/walk-in-dialog.tsx              (field render)
    resources/js/pages/events/attendance.tsx                (pass fields prop)

**Files added.**

    None.

**Tests.**

    tests/Feature/Events/AttendanceWindowTest.php           (new, ~8 tests)
    tests/Feature/Events/WalkInCustomFieldsTest.php         (new, ~10 tests)

Window tests cover: opens 1h before start, closed before that, null
start_time fallback, admin force-open, status gate unchanged, completed
viewable but not markable.

Custom field tests cover: each of the eight types round-trips through
the walk-in endpoint; required custom field missing rejects; invalid
choice rejects; array values JSON-encoded; `source = 'walk_in'`
preserved.

**Verify.**

    php artisan test tests/Feature/Events/AttendanceWindowTest.php
    php artisan test tests/Feature/Events/WalkInCustomFieldsTest.php
    php artisan test
    npx tsc --noEmit

**Commit.**

One commit. `feat(attendance): time-based window and custom fields on walk-in`.

## 7. Phase B — Confirm mode

**Goal.** Bulk mark pre-registered participants as present from a paper
sheet.

**Scope.**

- Checkbox column on the attendance table, visible only in Confirm
  mode.
- Toolbar showing "N selected" and a "Mark selected present" button.
- "Mark all visible" shortcut that respects the current filter and
  search (D10).
- One new POST endpoint: `POST /events/{event}/attendance/bulk-mark`.
  Accepts `registration_ids: int[]`.
- All-or-nothing at the request level: if any ID belongs to another
  event, reject the whole batch. Individual mark writes use
  `updateOrCreate` and are idempotent.
- Confirmation dialog before the bulk write. Lists count.
- After success, refresh counts and clear selection.

**Files touched.**

    app/Http/Controllers/AttendanceController.php           (bulkMark)
    routes/web.php                                          (new route)
    resources/js/pages/events/attendance.tsx                (mode + checkbox)
    resources/js/components/attendance-filter-chips.tsx     (no change)

**Files added.**

    app/Http/Requests/BulkMarkAttendanceRequest.php         (validate IDs)
    resources/js/components/bulk-mark-toolbar.tsx           (selection UI)

**Tests.**

    tests/Feature/Events/AttendanceBulkMarkTest.php         (new, ~10 tests)

Covers: single ID, multiple IDs, IDs from another event rejected,
empty selection rejected, unmarked → present updates, present stays
present (idempotent), late → present overwrites, filter counts update
after bulk mark, rate limit not hit at 5 IDs, concurrency safe (two
simultaneous requests mark the same ID without error).

**Verify.**

    php artisan test tests/Feature/Events/AttendanceBulkMarkTest.php
    php artisan test
    npx tsc --noEmit

**Commit.**

One commit. `feat(attendance): confirm mode with bulk mark`.

## 8. Phase C — Add mode batch table

**Goal.** Multi-row walk-in entry with per-row validation and
per-row errors.

**Scope.**

- New surface inside Add mode: a multi-row table.
- Each row: name fields, age, per-event common fields, custom fields,
  `client_uuid`.
- Add-row button, remove-row button, paste-from-spreadsheet (deferred
  to a follow-up).
- Per-row validation runs before submit. Rows that fail show inline
  errors and do not submit.
- One new POST endpoint: `POST /events/{event}/attendance/bulk-walk-in`.
  Accepts `rows: array` where each row has `client_uuid`.
- Server processes each row independently in a transaction. Failed
  rows return their errors; successful rows persist.
- Rate limiter `walk-in-batch` — 10/min/user.
- Idempotency: server records processed `client_uuid` values in cache
  for 60s. A retry within the window is a no-op for already-processed
  rows.
- `source = 'paper'` for rows submitted here. Single-dialog walk-in
  stays `source = 'walk_in'`.
- Sequential submission with progress bar.

**Files touched.**

    app/Http/Controllers/AttendanceController.php           (bulkWalkIn)
    app/Http/Requests/WalkInRegistrationRequest.php         (reuse for single-row validation)
    app/Providers/AppServiceProvider.php                    (rate limiter)
    routes/web.php                                          (new route)
    resources/js/pages/events/attendance.tsx                (mode: batch)

**Files added.**

    app/Http/Requests/BulkWalkInRegistrationRequest.php     (validate rows[])
    app/Concerns/WalkInFieldValidationRules.php             (shared single + batch)
    resources/js/components/bulk-walk-in-table.tsx         (multi-row surface)
    resources/js/lib/attendance-bulk-validation.ts          (client mirror)

**Tests.**

    tests/Feature/Events/AttendanceBulkWalkInTest.php       (new, ~15 tests)
    tests/Feature/Events/AttendanceBulkWalkInRateLimitTest.php (new, ~3 tests)

Covers: one row, many rows, per-row errors with mixed success, all
rows fail, `source = 'paper'` stamped, idempotency UUID retry, rate
limiter hit at 11th request in a minute, custom fields persisted,
duplicate identity within the same batch, duplicate against an existing
registration rejected per row, cross-event isolation.

**Verify.**

    php artisan test tests/Feature/Events/AttendanceBulkWalkInTest.php
    php artisan test tests/Feature/Events/AttendanceBulkWalkInRateLimitTest.php
    php artisan test
    npx tsc --noEmit
    npm run build

**Commit.**

One commit. `feat(attendance): add mode with batch walk-in table`.

## 9. What this plan does not do

- No paper signature capture. Deferred to ISSUE-010.
- No CSV upload. Paste-from-spreadsheet is a follow-up to Phase C, not
  part of it.
- No editing of existing participant records from the batch surface.
  Phase 8 of the identity plan owns that.
- No multi-event batch operations. One event at a time.
- No offline mode. A network failure during a batch prompts retry.
- No undo. Corrections re-mark.
- No per-attendance-row attribution beyond `recorded_by`. One operator
  per batch is the mental model.

## 10. Interactions with existing subsystems

**Public registration form.** Unaffected. The `responses` map is
already the shape the public form uses. Phase A makes the walk-in
dialog render the same field types with the same validation.

**Participant identity.** Unchanged. `resolveFrom` is called from
both single and batch paths with the same arguments. First-write-wins
holds.

**Attendance search.** Unchanged. Phase B reuses the current filter
and search as the scope for "mark all visible." Phase C's batch table
is a separate surface, not affected by search.

**Event lifecycle.** The new window gate is stricter relative to
`start_time` but looser relative to the whole day. Events without a
`start_time` behave identically to today. Events with a start time
open one hour before, not at midnight.

**Phase 4 (attendance rebuild, FIX-026).** All 21 tests in
`AttendanceTest.php` must continue to pass. The `canRecordAttendanceToday`
method is renamed; any test asserting against it is updated to
`canRecordAttendanceNow`. Behavior change is limited to the opening
time.

**ISSUE-010 (legal layer).** This plan does not attempt to close it.
The paper form's wet signature is not captured. Recorded as an open
item.

## 11. Open items

- **Paste-from-spreadsheet.** Deferred. May become Phase D or a
  follow-up inside Phase C.
- **Event without `start_time` on its event day.** Falls back to
  midnight. Is a warning log the right shape, or should this be a
  validation error at event creation? Not decided here.
- **Multi-operator batches.** One operator per batch. Multiple staff
  submitting simultaneously is safe (UNIQUE backstop) but the UI does
  not surface "another operator is mid-batch." Not decided.
- **Paper as a first-class source value.** `paper` becomes live in
  Phase C. The `sourceLabel()` mapping already exists. Reporting on
  `paper` versus `walk_in` may be a future analytics concern.
- **Admin force-open auditing.** Bypassing the window is currently
  silent. Should it write an audit log entry? Not decided.

## 12. Verification at phase close

Each phase ends with the same full-suite check as FIX-027:

    php artisan test
    npx tsc --noEmit
    npm run build

Full-suite baseline at the time of this writing: 3 skipped, 399
passed, 1127 assertions. Each phase adds tests; the skipped count
should remain 3 (SecurityTest 2FA feature).

## 13. Changelog

- **2026-10-10** — File created. Three-phase plan. Window change,
  three-mode workspace, D1-D10 and E1-E4 and F1-F4 and G1-G5 locked.
  Phase A ships first.

---

## Phase A complete (2026-10-10)

**Status:** Shipped. Two commits, not one.

### Deviation from the plan

Section 6 of this doc described Phase A as a single commit. It
shipped as two:

- `3f8cafa` — time-based window. Added
  `Event::canRecordAttendanceNow()`, removed
  `Event::canRecordAttendanceToday()`, swapped three call sites in
  `AttendanceController`, extended `AttendanceDateGateTest` with
  seven new time-window tests.
- This commit — custom fields on walk-in.

Reason for the split: the window change and the custom field change
are independent. Two commits mean either half can be reverted
independently if it misbehaves in production. The plan's "one
commit" was a convenience assumption, not a correctness requirement.

### What shipped

**Window change:**

- `Event::canRecordAttendanceNow()` — time-based gate, opens one
  hour before `start_time`.
- `Event::attendanceWindowOpensAt()` — helper. Falls back to
  midnight when `start_time` is null.
- `Event::ATTENDANCE_WINDOW_MINUTES_BEFORE = 60` — the single knob
  for the whole policy.
- `Event::canRecordAttendanceToday()` removed entirely.

**Custom fields on walk-in:**

- `WalkInRegistrationRequest` extended with `responses` rules and
  a two-pass `withValidator` (custom field ownership + required
  check), mirroring `PublicRegistrationRequest`.
- `AttendanceController::walkIn` writes `RegistrationFieldResponse`
  rows inside the existing transaction.
- `AttendanceController::show` adds a `registration_fields` prop to
  the `event` payload.
- `walk-in-dialog.tsx` renders all eight field types.
- `attendance.tsx` passes the fields through.

### What did NOT ship

**Admin force-open toggle.** Section 3 of this doc described a
"Force open" query-string parameter that bypasses the window for
admins. Not built in Phase A. Deferred to a small follow-up commit
because:

1. It needs a controller-layer change plus a UI affordance.
2. The window change works correctly without it.
3. The bypass is an escape hatch, not a blocker.

A follow-up commit will add:
- Admin-only `?force_open=1` handling in `AttendanceController::show`
  and `AttendanceController::mark`.
- A small toggle button in the attendance page header, visible only
  when the user is an admin.
- Tests for the bypass path.

### Verification

    php artisan test tests/Feature/Events/AttendanceDateGateTest.php  (20 passed)
    php artisan test tests/Feature/Events/WalkInCustomFieldsTest.php  (12 passed)
    php artisan test                                                   (418 passed)
    npx tsc --noEmit                                                  (silent)

### Cross-references

- Commits: `3f8cafa` (window), this commit (custom fields).
- FIX-027 — adjacent quality rules that already landed.
- `docs/progress.md` — dated progress entry for this session.

---

## Admin force-open toggle (2026-10-10)

Closes the one item Phase A deferred. See the "Phase A complete"
section above for context.

### What shipped

**Server side:**

- `AttendanceController::show()` computes `$forceOpen` from the
  query string and the authenticated user's role. `is_admin` and
  `force_open` are both passed to the page as props.
- `AttendanceController::mark()` and `AttendanceController::walkIn()`
  read `force_open` from the query string and skip the window gate
  when both conditions hold: user is admin, and the parameter is
  present.
- `Event::canRecordAttendanceNow()` is **not modified**. The model
  method remains the strict truth. The bypass is a controller-layer
  concern, not a model-layer one.

**Client side:**

- `attendance.tsx` renders a "Force open" / "Force close" toggle in
  the header, visible only when `is_admin && (!can_mark || force_open)`.
  The `!can_mark` half hides the toggle when the window is naturally
  open. The `force_open` half keeps the button visible once the
  admin has activated it, so they have a way back to the normal
  state.
- An amber banner appears at the top of the page when `force_open`
  is true, informing the admin that the override is active.
- `sharedQuery()` rebuilds the query string on every partial reload
  — search, pagination, filter chips — so the bypass survives
  navigation within the page.
- `mark()` and the walk-in POST both carry `force_open=1` when the
  toggle is active.
- `walk-in-dialog.tsx` accepts an optional `forceOpen` prop and
  appends `?force_open=1` to the POST URL when true.

### Not persisted

The bypass lives entirely in the query string. Reload the page
without the parameter and the toggle is off. No session state, no
cache entry, no database row. This is deliberate — the bypass is
an escape hatch, not a mode.

### Not audited

Bypassing the window is not logged. Recorded as an open item in
section 11. A future session can add audit logging if the
compliance need arises.

### Tests

    tests/Feature/Events/AttendanceForceOpenTest.php  (new, 9 tests)

Covers:
- admin without param — can_mark false, is_admin true, force_open false
- admin with param — can_mark true, force_open true
- admin with param — mark succeeds on a completed event
- admin with param — walk-in succeeds on a completed event
- staff with param — can_mark still false (bypass does not apply)
- staff with param — mark forbidden on a completed event
- staff with param — walk-in forbidden on a completed event
- is_admin prop is true for admin
- is_admin prop is false for staff

### Verification

    php artisan test tests/Feature/Events/AttendanceForceOpenTest.php  (9 passed)
    php artisan test                                                   (427 passed)
    npx tsc --noEmit                                                  (silent)

### Cross-references

- Section 3 — the window change and the force-open design
- Section 6 — Phase A scope, where the toggle was first deferred

---

## Phase B complete (2026-10-10)

**Status:** Shipped. One commit (`b736370`).

### What shipped

**Server side:**

- New `App\Http\Requests\BulkMarkAttendanceRequest`. Two accepted
  shapes, mutually exclusive:
  - `registration_ids: int[]` — explicit list, all-or-nothing
    (any ID belonging to another event rejects the whole batch
    with 422).
  - `mark_all_visible: true` + `filter` + `q` — marks every
    registration matching the current filter and search. Scope is
    the search result, not the current page (D10).

- `AttendanceController::bulkMark()` — new action. Idempotent via
  `updateOrCreate`. Same status / time / recorded_by shape as the
  single-row `mark()`.

- Two new private helpers on the controller:
  - `resolveBulkMarkIds()` — normalizes the two request shapes into
    a flat list of event-scoped registration IDs.
  - `buildFilteredRegistrationIds()` — rebuilds the search + filter
    clause the `show()` page uses. Duplicated from the `$baseQuery`
    closure for now; Phase C will consolidate once the batch
    walk-in surface also needs it.

- New route: `POST events/{event}/attendance/bulk-mark`
  (`events.attendance.bulk-mark`).

- No rate limiter. Same reasoning as single-row mark — trusted
  staff, and the single-row endpoint has none either.

**Client side:**

- New `resources/js/components/bulk-mark-toolbar.tsx`. Displays
  selection count, matching count, and the two action buttons
  ("Mark selected", "Mark all visible").

- `resources/js/pages/events/attendance.tsx` extended with a mode
  toggle (Mark / Confirm), a checkbox column visible only in
  Confirm mode, page-level selection state, and a confirmation
  dialog before the write.

- Selection is cleared on every `rows` change (pagination, filter,
  search). Local state, not URL-persisted.

- The toolbar's "Mark all visible" button reads
  `counts[filter]` — the same counter the filter chips show — as
  its scope preview. What the chip says is what gets marked.

### Not shipped

- Add mode (batch walk-in table) — that is Phase C.
- Rate limiter on the bulk endpoint — deliberately deferred. See
  the docblock on `bulkMark()`.

### Deviations from the plan

**D10 interpretation.** The plan said "Mark all visible" respects
the current filter and search. This shipped with the server
interpreting the scope as "everything matching the filter+search,"
not "the 50 rows currently on this page." Rationale: the
paper-sheet reconciliation workflow needs the whole filter scope,
and pagination is a view concern, not a marking concern.

### Tests

    tests/Feature/Events/AttendanceBulkMarkTest.php  (new, 12 tests)

Covers: explicit list (single, multiple, idempotent rerun),
cross-event rejection with 422, empty list rejection, mark-all-
visible scoped to filter and search, cross-event isolation for
the mark-all shape, both-shapes-at-once rejection, neither-shape
rejection, forbidden when window closed, admin force-open bypass
on a completed event.

### Verification

    php artisan test tests/Feature/Events/AttendanceBulkMarkTest.php  (12 passed)
    php artisan test                                                   (439 passed)
    npx tsc --noEmit                                                  (silent)

### Cross-references

- Section 7 — Phase B scope
- FIX-026 — original attendance page this builds on
- Phase A complete — the window + custom fields work this depends on

---

## Phase C complete (2026-10-10)

**Status:** Shipped. One commit.

### What shipped

**Server side:**

- New `App\Concerns\WalkInFieldValidationRules` trait. Extracted
  `isEmpty()` and `validateResponseType()` from
  `WalkInRegistrationRequest`. `validateResponseType()` takes the
  error key as a parameter so single-row (`responses.{id}`) and bulk
  (`rows.{i}.responses.{id}`) callers share one implementation.

- New `App\Http\Requests\BulkWalkInRegistrationRequest`. Accepts
  `rows: array` (min 1, max 100). Each row validated for common
  fields, name quality, phone/address quality, and custom field
  ownership + format. Error keys under `rows.{i}.*`.

- New `walk-in-batch` rate limiter in `AppServiceProvider` —
  10/min per user+event. Compound key matches the OTP limiter
  pattern.

- `AttendanceController::bulkWalkIn()` — new action. Iterates the
  rows, runs business checks (identity presence, already-registered,
  duplicate-within-batch) per row, persists each success in its own
  transaction. Per-row failures reported as `rows.{i}.identity`
  errors alongside the successful writes.

- Two new private helpers: `persistBulkWalkInRow()` and
  `bulkWalkInSummary()`.

- `source = 'paper'` for every bulk-registered row — the operator is
  transcribing physical forms, not registering a live walk-up.

- Idempotency: `client_uuid` cached for 60 seconds after successful
  persist. A resubmitted row with the same UUID is a no-op. Failed
  rows do not cache, so they retry naturally.

- New route: `POST events/{event}/attendance/bulk-walk-in`
  (`events.attendance.bulk-walk-in`), throttled by `walk-in-batch`.

**Client side:**

- New `resources/js/lib/attendance-bulk-validation.ts`. Wraps
  `validateRegistrationForm()` from the public form, namespaces
  errors under `rows.{i}.*`. Exports `makeBulkWalkInRow()` for
  generating fresh rows with a client UUID.

- New `resources/js/components/bulk-walk-in-table.tsx`. Multi-row
  entry surface. Each row renders the six common fields plus the
  event's custom fields (all eight types). Per-row error feedback
  via `InputError`. Left border marks rows with errors. Add/remove
  controls.

- `attendance.tsx` gains a third mode, `'batch'`. Batch mode hides
  the list/filter/search and renders the table + Save button. On
  success, rows reset to a single empty one. On server per-row
  errors, rows stay so the operator can fix and retry.

### Deviations from the plan

**Single test file.** The plan listed two files
(`AttendanceBulkWalkInTest.php` and
`AttendanceBulkWalkInRateLimitTest.php`). Shipped as one file — the
rate limit test needs the same helpers (event, staff, row, field)
and splitting would have duplicated them.

**Error key for identity.** The plan used the phrase
`rows.{i}.identity` for the per-row business errors. Shipped exactly
that — the same key the single walk-in dialog uses for the
email-or-phone rule, namespaced by row.

**Rate limit key.** The plan said "10/min/user." Shipped as
`user_id|event_id` to match the compound OTP limiter pattern. One
operator working two events simultaneously does not share a rate
budget.

### Not shipped

**Paste-from-spreadsheet.** Deferred as planned. Users transcribe
manually today. Phase D (or a follow-up inside C) can add paste
support once the manual entry has been used on a real event day.

### Tests

    tests/Feature/Events/AttendanceBulkWalkInTest.php  (new, 16 tests)

Covers: single row, multiple rows, source stamp, custom field
responses per row, idempotency, per-row rejection with partial
success (no identity, already registered, duplicate within batch),
shape validation (empty, oversize, missing UUID, duplicate UUID,
cross-event field), window gate, admin force-open, rate limit at
the 11th request.

### Verification

    php artisan test tests/Feature/Events/AttendanceBulkWalkInTest.php  (16 passed)
    php artisan test                                                   (455 passed)
    npx tsc --noEmit                                                  (silent)

### Cross-references

- Section 8 — Phase C scope
- Phase A complete — window + custom fields on the single dialog
- Phase B complete — Confirm mode, which established the mode
  toggle this phase extends
