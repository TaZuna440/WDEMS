# WDEMS — Attendance Encoding and Reporting

**Audience:** Whoever implements, modifies, or reasons about the
attendance encoding surface, the event finalization flow, or the
end-of-event report.

**Purpose:** Specification for three interlocking features:
an encoding workspace, a finalize-event action, and a
participant-centric report. This is a spec — nothing here describes
current code except where explicitly named as "existing."

**Status:** Spec. No code exists yet for any of the three features.

**Related:**
- `docs/attendance-redesign.md` — the three-mode attendance workspace
  this supersedes for day-of operations
- `docs/registration.md` — the participant registration flow
- `docs/participant-identity.md` — the identity model that makes
  per-participant reporting possible
- `docs/issues-inventory.md` — BUG-005 (unwired workflow transitions)
  and GAP-002 (participant history) are closed in part by this plan

---

## 1. The operational reality

A Philippine community run has three distinct moments:

**Before the event.** Organizers publish a public registration link.
Participants register online. The registration count grows.
Registration closes the day before.

**On event day.** Volunteers sit at a table. Pre-registered
participants arrive and sign a paper attendance sheet. Walk-ins
arrive and fill a paper registration form identical to the online
one. Two paper artifacts accumulate by end of day.

**After the event.** Staff transcribe both papers into the system.
The pre-registered list needs attendance marks. The walk-ins need
full registration records. Only when both are entered does the
system's data match reality.

The current system handles the "before" moment well. It handles the
"on event day" moment adequately via the batch walk-in table. It
does not have a purpose-built surface for the "after the event"
moment — the transcription phase, which is the largest block of
work.

## 2. The three features

| Feature | Purpose | Pain it solves |
|---|---|---|
| **A — Encoding workspace** | A fast grid for entering both paper artifacts | Excel is slow; the current batch table does one type of paper at a time |
| **B — Finalize event** | A password-gated close after which the event is immutable | Events currently sit at `registration_closed` forever; no terminal action |
| **C — End-of-event report** | A single page showing the event's outcome and participant history | No consolidated view of attendance vs registration; no cross-event view |

The features build on each other: **B** produces the terminal state
that **C** reports on. **A** produces the clean data that **C**
reports. Building them together keeps the data model coherent.

Recommended build order: **B → A → C**. B is smallest and closes an
existing gap. A is the daily-pain fix. C is the payoff, and it needs
both B's state and A's data to be meaningful.

## 3. Feature A — Encoding workspace

### 3.1 What it is

An extension of the existing `/events/{event}/attendance` page. The
page gains an **Encoding mode**, the default for events in an
encodeable state. Encoding mode has **two tabs**: Attendance and
Walk-ins.

The three existing modes (Mark, Confirm, Add) are **removed**. Every
action they performed is covered by the two tabs. Keeping both would
create two ways to do the same thing and force the user to remember
which mode handles which paper.

### 3.2 Layout

    ┌─────────────────────────────────────────────────────────────┐
    │ Event name                                [ ONGOING ]        │
    │ 2026-11-02 · BGC · 10 KM                                     │
    │                                                              │
    │ 142 registered · 38 present · 12 walk-ins                    │
    ├─────────────────────────────────────────────────────────────┤
    │ [ Attendance (142) ] [ Walk-ins (0) ]                        │
    ├─────────────────────────────────────────────────────────────┤
    │ Search [_________________]   Filter: [All ▾]                 │
    │                                                              │
    │ ☐  Maria Santos          09171234567  [ Present ▾ ] [⋯]     │
    │ ☐  Jose Rizal            jose@ex.com  [ Present ▾ ] [⋯]     │
    │ ☐  Andres Bonifacio      09207654321  [ Present ▾ ] [⋯]     │
    │ ...                                                          │
    ├─────────────────────────────────────────────────────────────┤
    │ 50 of 142 · [< Prev] [1] [2] [3] [Next >]                    │
    └─────────────────────────────────────────────────────────────┘

**Attendance tab** — one row per pre-registered participant. Columns:

| Column | Content |
|---|---|
| Checkbox | For bulk actions (see §3.4) |
| Name | First + last |
| Contact | Email if present, else phone |
| Status | Dropdown: Present / Absent / Late / Excused / Unmarked |
| ⋯ | Row actions (edit participant, view history, delete) |

**Walk-ins tab** — one row per walk-in registration. New walk-ins
are added via an "Add walk-in" button that opens a form identical to
the public registration form. Existing walk-ins are listed with the
same columns as the Attendance tab, minus the status dropdown
(walk-ins are marked present at creation by default).

### 3.3 Interaction model

Keyboard-first. The grid behaves like a spreadsheet:

- **Tab** moves to the next cell
- **Enter** saves the row and moves to the next row, same column
- **Shift+Tab** moves backward
- **Escape** cancels the current cell edit

Every row saves on blur. There is no "Submit all" button. This is
deliberate — a crash mid-session loses at most one in-flight row.

Status changes on the Attendance tab fire immediately. A change from
Unmarked → Present issues a POST and updates the row in place.

### 3.4 Bulk operations

The Attendance tab retains Confirm mode's bulk selection:

- Checkbox column visible at the left
- "Select all visible" checkbox in the header
- A sticky toolbar appears when any row is selected: "N selected · Mark as [status ▾]"
- The toolbar has a "Mark all matching filter" shortcut when a search
  or filter is active

Bulk marks use the existing `POST /events/{event}/attendance/bulk-mark`
endpoint.

### 3.5 Offline behavior

**Tier 1 — Resilient tab.** No offline queue, no service worker, no
localStorage persistence of unsaved rows.

What this means in practice:

- If the network drops for 30 seconds, in-flight POSTs retry on
  failure. The user sees a subtle "Reconnecting…" indicator.
- If the laptop sleeps or the browser closes, unsaved rows are lost.
- If the page is refreshed, unsaved rows are lost.
- Any row already POSTed is safe.

**Why Tier 1 and not higher.** True offline (Tier 3, service worker
+ IndexedDB) is 3–6 weeks of additional work. The venue for most
community runs has some phone signal. The encoding session happens
after the event when the organizer is back at a table with wifi.

If a specific venue proves to need true offline, that is a separate
feature, not an extension of this one.

### 3.6 Fields

Walk-in rows in the Walk-ins tab render **the same fields as the
public online form**. Six common fields plus the event's custom
fields (all eight types).

There is no separate schema, no separate validation, no separate
custom field set. The walk-in path reuses:

- `WalkInRegistrationRequest` for validation
- The custom field rendering used by `walk-in-dialog.tsx`
- The same `Registration` + `Participant` + `RegistrationFieldResponse`
  write path

A walk-in encoded here is indistinguishable from a walk-in submitted
by the participant directly — except `source` is `walk_in` vs
`paper`, and `recorded_by` is the operator.

### 3.7 Route and guard

**Route:** `/events/{event}/attendance` (unchanged)

**Guard:** `Event::canEncode()`

Returns true when:
- The event status is `ongoing`
- The attendance window is open (already enforced by
  `canRecordAttendanceNow()`)

Returns false when:
- The event is `draft`, `registration_open`, or `registration_closed`
- The event is `completed`
- The event is `cancelled`

When `canEncode()` is false, the page renders in read-only mode:
tabs are visible, data is visible, all editing is disabled, and a
banner explains why:

> Encoding is available on event day. The event starts 2026-11-02 at 05:00.

**Admin override.** `?force_open=1` bypasses the guard for admins,
matching the existing pattern on `mark`, `walkIn`, and `bulkWalkIn`.

### 3.8 What this replaces

The three current modes on the attendance page are removed:

| Removed | Reason |
|---|---|
| Mark mode | Covered by Attendance tab's inline status dropdown |
| Confirm mode | Covered by Attendance tab's bulk selection |
| Add mode | Covered by Walk-ins tab |

The `walk-in-dialog.tsx` component is retained — the Walk-ins tab's
"Add walk-in" button opens it. The `bulk-walk-in-table.tsx` component
is removed; its function is absorbed by the Walk-ins tab.

The page becomes **one mode with two tabs**, plus read-only rendering
when the event is locked.

---

## 4. Feature B — Finalize event

### 4.1 What it is

A terminal action that moves an event from `ongoing` to `completed`
and locks the event's data. The action is password-gated, permanent,
and available to both admin and staff roles.

### 4.2 The status chain

    draft → registration_open → registration_closed → ongoing → completed

                       (cancelled reachable from any non-terminal state)

No shortcuts. Each transition has one trigger:

| Transition | Trigger | Who |
|---|---|---|
| `draft → registration_open` | "Open registration" button | Admin or staff |
| `registration_open → registration_closed` | "Close registration" button | Admin or staff |
| `registration_closed → ongoing` | Auto — middleware fires when the event's `start_time` arrives | System |
| `ongoing → completed` | "Finalize event" button, password-gated | Admin or staff |
| `* → cancelled` | "Cancel event" button, password-gated | Admin only |

### 4.3 The auto-advance mechanism

A new middleware `AdvanceEventStatus` runs on every authenticated
request in the main route group:

    auth, verified.or.admin, device.trusted

It checks `$request->route('event')`. If an `Event` is bound and
- the event's status is `registration_closed`
- `event_date + start_time` is in the past

then the middleware flips the event to `ongoing` and writes a log
entry: `event.status.auto_advanced` with `event_id`, `from`, `to`,
`fired_at`.

**Why middleware and not a scheduler.** Zero infrastructure. Fails
safely — if nobody visits the app, nothing flips, which is fine
because nobody is watching. The organizer will open the app on event
day to use the encoding page, and the flip happens then.

**Trade-off, documented.** An event whose date has passed but was
never visited stays `registration_closed` until someone opens a page
that binds it. This is not a bug. It is the cost of zero
infrastructure. If this proves insufficient, a scheduled task is a
follow-up, not part of this feature.

### 4.4 The Finalize button

**Where it appears:** the Workflow Actions panel on
`events/show.tsx`, next to the existing Open Registration and Close
Registration buttons.

**When it appears:** only when `event.status === EventStatus::Ongoing`.

**Button label:** "Finalize event"

**Visual weight:** destructive — red or amber, not the primary blue.
This is a one-way action.

### 4.5 The confirmation flow

Clicking Finalize opens a modal:

    ┌──────────────────────────────────────────────────────┐
    │  Finalize this event?                          [ X ] │
    ├──────────────────────────────────────────────────────┤
    │                                                       │
    │  This marks the event as complete. After this:        │
    │                                                       │
    │    · Registrations become read-only                   │
    │    · Attendance marks are frozen                      │
    │    · Walk-in entry is disabled                        │
    │    · Encoding is read-only                            │
    │                                                       │
    │  This cannot be undone.                               │
    │                                                       │
    │  Enter your password to confirm:                      │
    │  [____________________________________]               │
    │                                                       │
    │              [ Cancel ]  [ Finalize event ]           │
    └──────────────────────────────────────────────────────┘

**Password re-entry.** The modal contains a password field. The form
submits to a route protected by Laravel's built-in
`RequirePassword` middleware (`Illuminate\Auth\Middleware\RequirePassword`).

`RequirePassword` is already used on `/settings/security`,
`/settings/security/two-factor`, and `/settings/security/devices`.
This feature reuses it — no new middleware.

**Session behavior.** `RequirePassword` allows a 3-hour grace window
by default (`config('auth.password_timeout')`). Within that window,
the user is not re-prompted. After it expires, they are. If the user
has just changed their security settings, they will not need to
re-enter the password. If they have not touched settings in hours,
they will.

This matches the existing behavior on every other destructive
settings action.

### 4.6 What locks after finalize

When the event reaches `completed`, the following surfaces reject
further writes:

| Surface | Enforcement |
|---|---|
| Attendance mark | `AttendanceController::mark` — window gate (already rejects) |
| Single walk-in | `AttendanceController::walkIn` — window gate (already rejects) |
| Bulk walk-in | `AttendanceController::bulkWalkIn` — window gate (already rejects) |
| Bulk mark | `AttendanceController::bulkMark` — window gate (already rejects) |
| Monitor actions (edit/flag/note) | `RegistrationMonitorController::ensureEditableRegistration` (already rejects) |
| Registration deletion | `RegistrationDeletionController::ensureManageable` (already rejects) |
| Event edit | `EventController::edit` / `::update` — `canEdit()` already excludes `completed` |
| Registration form builder | `RegistrationFormController` — already rejects non-`draft` |
| **Encoding page** | **New** — `Event::canEncode()` returns false |
| **Event deletion** | **Unchanged** — admin may still delete a completed event |

Every lock except the encoding page already exists. The one new guard
is `Event::canEncode()`.

### 4.7 Why password-gated instead of a second confirm click

A misclick on a text-only confirm is easy. A misclick on a password
field is impossible — you must know the password to proceed. This
matches the user's stated requirement: "security in order to avoid
organizers mistakes and foolproof."

### 4.8 Reversibility

**None.** Once `completed`, the event cannot be moved back to
`ongoing`. The only ways to change its data are:

1. Admin deletes the event (`EventDeletionController::destroy` —
   already allows any status)
2. Admin directly edits the database (out of scope)

If a correction is needed after finalization, the answer is: reopen
via direct DB edit or delete and recreate. This is a deliberate
trade-off for simplicity. A "reopen for editing" feature is out of
scope for v1.

---

## 5. Feature C — End-of-event report

### 5.1 What it is

A single page, `/events/{event}/report`, with three stacked sections:

1. **This event summary** — how did this event go?
2. **Cross-event by type** — how do events of this type compare?
3. **Participants in this event** — who showed up, and what is their
   history?

The page is interactive: sortable tables, filterable, expandable
rows. A "Print summary" button triggers `window.print()` with a
print-optimized stylesheet.

### 5.2 Route and guard

**Route:** `GET /events/{event}/report`, name `events.report`

**Guard:** reachable when the event status is `ongoing` or
`completed`. Not reachable for `draft`, `registration_open`,
`registration_closed`, or `cancelled` — those states do not have a
meaningful "how did it go" answer.

**Entry points:**
- A "View report" link on the event show page (visible when status
  is `ongoing` or `completed`)
- After a successful finalize, the redirect target is the report
  page, not the event show page

### 5.3 Section 1 — This event summary

Top of the page. Cards or a small table:

| Metric | Meaning |
|---|---|
| Registered | Count of registrations with `source = form` |
| Attended | Count of those marked Present or Late |
| Walk-ins | Count of registrations with `source = walk_in` or `paper` |
| Total attendance | Attended + Walk-ins marked present |
| Show rate | Attended / Registered, as a percentage |
| No-shows | Registered − Attended |

Below the cards, a small line chart or simple table showing the
distribution:

- Present: N
- Late: N
- Excused: N
- Absent: N
- Unmarked: N

**Why this shape.** The organizer's first question post-event is
always "did we match registration this time?" The cards answer it in
one glance.

### 5.4 Section 2 — Cross-event by type

A table of every event of the same `event_type` as the current event,
all-time, sorted by date descending.

| Column | Content |
|---|---|
| Event | Name |
| Date | `event_date` |
| Registered | Count at finalize |
| Attended | Count Present + Late |
| Show rate | Attended / Registered |
| Δ vs avg | Show rate − type's average show rate |

Above the table, a summary strip:

    Community Runs: 12 · Avg show rate: 68% · Best: Feb Run (84%) · Worst: Jun Run (51%)

**Sortable.** Every column is a sort header. The default sort is
date descending. The current event's row is highlighted.

**Scope: same `event_type`, all time.** A participant attending both
community runs and fun runs is counted in each type separately. This
matches the user's requirement: "if the same participant is on the
fun run it's a different counting."

### 5.5 Section 3 — Participants in this event

A table of every participant with a registration for this event,
across all sources (form, walk_in, paper).

| Column | Content |
|---|---|
| Name | Full name |
| Contact | Email or phone |
| Attendance | Status at this event (Present / Late / etc.) |
| Total appearances | Count of this participant's registrations across **events of this type** where they were marked Present or Late |
| Last appearance | Date of the most recent prior event they attended |

**Expandable rows.** Clicking a row expands a nested list of every
prior event this participant attended, in this type, date descending.

**Scope: participants who have a registration for the current event.**
Not "all participants ever." A participant who has never touched this
event does not appear. This keeps the table finite — a matured
system may have thousands of participants, but only a few hundred
per event.

**Sortable.** Every column is a sort header. Default sort is
attendance status (Present first), then name.

**Export.** A "Download CSV" button produces the same data with the
expanded history flattened into extra columns.

### 5.6 Print layout

Triggered by a "Print summary" button. Uses `@media print` on the
same page — no separate route, no duplicate data assembly.

**Print stylesheet rules:**
- Hide the app shell (sidebar, header, tabs)
- Hide interactive controls (buttons, checkboxes, sort indicators)
- Hide the CSV download button
- Expand all collapsed rows in Section 3
- Use black text on white
- Include a header line: event name, date, venue, and print timestamp
- Page-break hints between the three sections

**Why CSS-only and not a separate route.** A separate print route
duplicates the controller logic and the component. Two surfaces drift.
CSS-only keeps one source of truth.

### 5.7 What this report does NOT include

- **No "loyalty" badges.** The user's decision: total appearances
  shown as a plain count, no rank, no title. The number speaks for
  itself.
- **No predictive analytics.** No "likely to attend" scores.
- **No cross-type aggregation.** Community runs and fun runs are
  counted separately by design.
- **No export to formats other than CSV.** PDF is out of scope —
  `window.print()` handles the PDF case via the browser.

### 5.8 Data assembly

The controller action `ReportController::show` builds three payloads:

1. **Summary** — count queries against `registrations` and
   `attendances` scoped to this event
2. **Cross-event** — every event of the same type, with per-event
   aggregate counts (a single query with joins, aggregated)
3. **Participants** — every participant with a registration for this
   event, joined with their attendance count across events of the
   same type, plus their last prior attendance date

Performance is fine up to thousands of events. If a deployment ever
reaches tens of thousands, the cross-event query becomes a
materialized summary table. Not needed for v1.

---

## 6. Cross-cutting concerns

### 6.1 The auto-advance middleware

**File:** `app/Http/Middleware/AdvanceEventStatus.php` (new)

**Registered:** `bootstrap/app.php` alias `event.advance`, applied to
the main route group:

    auth, verified.or.admin, device.trusted, event.advance

**Behavior:**

1. Read `$event = $request->route('event')`. If null, pass through.
2. If `$event->status !== EventStatus::RegistrationClosed`, pass
   through.
3. Compute `$startAt = $event->event_date + $event->start_time` (in
   `config('app.timezone')`).
4. If `$startAt` is null or `$startAt > now()`, pass through.
5. Otherwise, transition: `$event->status = EventStatus::Ongoing;
   $event->save();` and log `event.status.auto_advanced`.
6. Pass through.

**Idempotent.** A second request in the same state does nothing —
the status check on step 2 fails and the middleware exits early.

**Order matters.** `event.advance` runs *after* `auth` and
`device.trusted` — an unauthenticated request never flips a status.
This is deliberate: auto-advance is a side effect of app usage, not
of the public internet hitting a route.

### 6.2 The `canEncode()` guard

**Where:** `app/Models/Event.php`

    public function canEncode(): bool
    {
        return $this->status === EventStatus::Ongoing
            && $this->canRecordAttendanceNow();
    }

**Consumed by:** `AttendanceController::show`, to decide whether the
page renders in editable mode or read-only mode.

**Admin override.** `?force_open=1` bypasses `canEncode()` for admins,
matching the existing pattern in `mark`, `walkIn`, and `bulkWalkIn`.
The override is not persisted.

### 6.3 Where the existing `force_open` pattern extends

The `force_open` query parameter already bypasses:

- `canRecordAttendanceNow()` on mark
- `canRecordAttendanceNow()` on walkIn
- `canRecordAttendanceNow()` on bulkMark
- `canRecordAttendanceNow()` on bulkWalkIn

It will also bypass:

- `canViewAttendance()` on show (so an admin can prepare the page
  before event day)
- `canEncode()` on show (so an admin can encode outside the window)

**Guard rail.** `force_open` requires an authenticated admin. Staff
passing `force_open=1` are ignored. Verified by
`AttendanceForceOpenTest`.

### 6.4 The finalize route

    POST /events/{event}/finalize

**Middleware:** `auth`, `verified.or.admin`, `device.trusted`,
`RequirePassword`

**Controller:** `EventController::finalize`

**Behavior:**

1. Abort 403 if the event's status is not `Ongoing`
2. Transition to `Completed` via `EventWorkflow::complete()`
3. Log `event.finalized` with actor, event ID, timestamp
4. Redirect to `events.report` (the new report page)

**Idempotent?** No. A second POST returns 403 because the status is
no longer `Ongoing`. This is intentional — the button disappears
after the first click.

### 6.5 The report route

    GET /events/{event}/report

**Middleware:** `auth`, `verified.or.admin`, `device.trusted`

**Controller:** `ReportController::show` (new)

**Guard:** abort 404 if the event's status is not `Ongoing` or
`Completed`.

**Payload structure:**

    {
      event: { id, name, date, venue, type, status },
      summary: {
        registered, attended, walk_ins, total_attendance,
        show_rate, no_shows,
        status_breakdown: { present, late, excused, absent, unmarked },
      },
      cross_event: {
        type_label, average_show_rate, best, worst, total_events,
        rows: [{ id, name, date, registered, attended, show_rate, delta }],
      },
      participants: [
        {
          id, full_name, contact, attendance_status,
          total_appearances, last_appearance,
          history: [{ event_name, event_date, status }],
        },
      ],
      type_counts: {
        // For the current event's type, so the header can say
        // "Community Runs: 12" without a second query.
      },
    }

### 6.6 What `event_type` scoping means

Every query that feeds the cross-event section and the participant
`total_appearances` column is scoped by `event_type`. A participant's
history in **community runs** excludes their **fun run** attendance.

Concretely:

    SELECT COUNT(*) FROM registrations r
    JOIN events e ON e.id = r.event_id
    JOIN attendances a ON a.registration_id = r.id
    WHERE r.participant_id = ?
      AND e.event_type = 'community_run'
      AND a.attendance_status IN ('present', 'late')

That is the query behind `total_appearances`.

### 6.7 Removing the three attendance modes

The current `attendance.tsx` implements three modes via a state
variable and a mode toggle. This spec removes the toggle and the
mode-based branching.

**What changes:**

| File | Change |
|---|---|
| `resources/js/pages/events/attendance.tsx` | Becomes the encoding workspace. Two tabs. No mode toggle. |
| `resources/js/components/bulk-mark-toolbar.tsx` | Retained — folded into the Attendance tab |
| `resources/js/components/walk-in-dialog.tsx` | Retained — opened from the Walk-ins tab |
| `resources/js/components/bulk-walk-in-table.tsx` | Removed — replaced by the Walk-ins tab |
| `resources/js/components/attendance-filter-chips.tsx` | Retained — reused in the Attendance tab |

**What does NOT change:**

- The controller (`AttendanceController`) — same endpoints
- The routes — same paths
- The rate limiters
- The window gates

This is a frontend refactor, not a backend change.

### 6.8 Naming

The user's term is "encoding." The page title uses that word.
Internally, code and routes may use either "encode" or "encoding"
consistently. Suggested: `Encoding` as the user-facing term,
`encode` in route names (`events.attendance.encode`), and the page
component named `EncodingWorkspace` to distinguish from the
`AttendanceController`.

---

## 7. Open items

Recorded so they are not lost. Each needs a decision before its
feature is implemented.

### 7.1 Auto-advance and multi-day events

The current design assumes a single `event_date` and a single
`start_time`. Multi-day events do not exist in the schema. If they
ever do, `AdvanceEventStatus` needs a new rule.

### 7.2 Reset-finalize is not built

Once completed, an event cannot be reopened. If a real deployment
needs a "reopen for 24 hours" override, that is a separate feature
and needs its own password gate.

### 7.3 Cross-event report scope on multi-type organizers

Currently `cross_event` shows all events of the same type. If two
organizers share the installation and both run community runs, the
table shows both organizers' events. Not a v1 concern — WDEMS is
single-organization — but worth flagging.

### 7.4 Participant history from the report

Section 3 links each participant row to their full history within
the event type. Clicking the name does not (yet) open a dedicated
participant page. That page is GAP-002 in the issues inventory.

### 7.5 Encoding page — what about last-minute registrations?

A participant who registers online five minutes before the event
starts appears in the Attendance tab as a new row. The tab refreshes
on load. There is no live push. If they register *during* the event,
the operator needs to reload the tab. This is acceptable for v1.

### 7.6 Report on an ongoing event

The report is reachable when the status is `ongoing`. The numbers at
that point are partial — the event is still happening. The page
should render a small banner:

> Live — numbers will change until the event is finalized.

### 7.7 CSV export of the report

The report's third section has a "Download CSV" button. The export
flattens the expanded history into extra columns. The exact column
shape is decided during implementation, not here.

### 7.8 Print stylesheet on browsers other than Chrome

The `@media print` CSS is tested on Chrome. Firefox and Safari
support the same directives but render pagination differently. No
guarantee of identical output — document the tested browser when
this ships.

---

## 8. What this spec does NOT cover

- **The printable attendance sheet.** The user confirmed this is out
  of scope. WDEMS will not generate the paper the volunteers carry.
  The organizer brings their own.
- **CSV paste of walk-ins.** The user confirmed this is not needed.
  The encoding workspace is the input mechanism.
- **A true offline PWA.** Tier 1 is the chosen level. Tiers 2 and 3
  are separate features.
- **Participant-facing surfaces.** The report is for organizers and
  staff. Participants do not log in.
- **Emailing the report.** No email delivery. The organizer prints
  or downloads.
- **Scheduled report generation.** No cron job. The report is
  computed on request.

---

## 9. Build order and dependency graph

    B (finalize) ──► C (report) ──► ??? (future analytics)
      │
      └──► A (encoding) ──► C

- **B** is standalone. It adds the middleware, the route, the button,
  the modal. Once B lands, events can be finalized.
- **A** depends on nothing from B, but the `canEncode()` guard is
  only meaningful once `ongoing` is reachable — which requires B's
  middleware. So A after B.
- **C** depends on both. Cross-event stats need finalized events. The
  participant section needs clean attendance data, which A produces.

**Estimate:**

| Feature | Blocks | Sessions |
|---|---|---|
| B | 1 | 1 |
| A | 1 | 2–3 |
| C | 1 | 2 |

Total: 5–6 sessions.

---

## 10. Glossary

| Term | Meaning |
|---|---|
| **Encoding** | The act of transcribing a paper artifact (attendance sheet or walk-in registration form) into WDEMS |
| **Attendance sheet** | The paper pre-registered participants sign on arrival. Not generated by WDEMS. |
| **Registration paper** | The paper walk-ins fill on arrival. Mirrors the online form. Not generated by WDEMS. |
| **Show rate** | Attended / Registered, as a percentage |
| **Attended** | A registration whose attendance status is Present or Late |
| **Walk-in** | A registration with `source = walk_in` or `source = paper` |
| **Type** | The `event_type` value: `community_run` or `fun_run` |
| **Total appearances** | A participant's count of Present + Late attendances across events of a single type |
| **Force open** | The admin-only `?force_open=1` bypass that skips time-window gates |
| **RequirePassword** | Laravel middleware forcing password re-entry; 3-hour grace window |

---

## 11. Changelog

- **2026-10-13** — File created. Three features specified: encoding
  workspace, finalize event, end-of-event report. Auto-advance
  middleware designed. Print-layout via CSS-only. Lock semantics
  enumerated. Build order B → A → C with 5–6 session estimate.
  Eight open items flagged. No code.

---

## Corrections (2026-10-13)

Append-only corrections based on the source review. Where this section
disagrees with the earlier spec body, this section takes precedence.

### SPEC-GAP-1 — Auto-advance must use EventWorkflow

Section 6.1 directly assigns `$event->status`, bypassing the workflow
service. The architecture requires every transition to use
`App\Services\EventWorkflow`, which rejects illegal transitions.

Correction to step 5. Import `App\Services\EventWorkflow` and call:

    app(EventWorkflow::class)->start($event);

Keep the existing middleware checks and `event.status.auto_advanced`
logging. Change only the transition mechanism.

---

### SPEC-GAP-2 — Report aggregation belongs in a service

Section 5.8 assigns three aggregate payloads to
`ReportController::show`. The architecture rule in `architecture.md`
requires multi-step domain operations to live behind a service.

Correction. Introduce `app/Services/Reporting/ReportService.php` with
`build(Event $event): array`. It assembles the summary, cross-event,
and participant payloads. The controller calls the service and
returns the Inertia response; report aggregation does not live in
the controller.

---

### SPEC-GAP-3 — Rate-limit row encoding

Section 3.3 saves each row on blur. The current mark route has no
route-level throttle, while the provider defines eight limiters and
`implementation-notes.md` documents only five.

When this feature ships, register `attendance-encode-row`, keyed by
user ID and event, with a limit of 60 requests per minute. Apply
`throttle:attendance-encode-row` to
`POST /events/{event}/attendance/{registration}/mark`.

Update the limiter inventory to nine entries when the new limiter
is implemented. Do not describe the limiter as active before then.

---

---

## Pointer: the attendance code shipped (2026-10-14)

Feature A's encoding-workspace half — the per-registration
attendance code, the code lookup on the workspace, and the
verified-mark semantics — shipped on 2026-10-14 in commit
`d05c7e7`. See `docs/attendance-code.md` for the reference.

That new doc covers:

- The code format, generation, storage, and delivery
- The lookup at the table (normalization, precedence, empty state)
- The `attendances.verified_at` column and its semantics
- Live and deferred encoding modes for single and bulk walk-ins
- The `CarbonInterface` lesson from the bulk controller

Features B (finalize event) and C (end-of-event report) remain
unbuilt. When Feature C ships, its summary section should use
`verified_at` to distinguish code-verified attendance from staff
override.

---

## Pointer: finalize event shipped (2026-10-14)

Feature B — the finalize-event transition from Ongoing to
Completed — shipped on 2026-10-14. Section 4 above describes the
design; this note records what landed.

**What shipped:**

- `AdvanceEventStatus` middleware. Auto-advances registration_closed
  to ongoing once the event's start moment has passed. Runs on the
  main authenticated group after auth and device.trusted.
- `Event::canFinalize()` — status must be Ongoing.
- `EventController::finalize()` — checks the guard, calls
  `EventWorkflow::complete()`, logs `event.finalized`.
- Route `POST /events/{event}/finalize`, wrapped in Laravel's
  `RequirePassword` middleware.
- Finalize Event button and confirmation modal on `events/show.tsx`.

**Deferred:**

- The redirect target after finalize is `events.show`, not
  `events.report`. When Feature C ships, that becomes
  `events.report`. One line to change in
  `EventController::finalize()`.

**Corrections to Section 4.6 ("What locks after finalize"):**

- The encoding page's `canEncode()` guard mentioned in Section 3.7
  does not exist. The equivalent in shipped code is
  `canRecordAttendanceNow()` for the mark and walk-in endpoints, and
  `canViewAttendance()` for page rendering. All four write endpoints
  reject Completed; the page renders read-only.
- Section 6.1's auto-advance behavior specified calling
  `EventWorkflow` rather than assigning status directly (SPEC-GAP-1).
  Shipped as specified.
- Section 4.4's modal description specified a password field in the
  modal. Not shipped — the route-level `RequirePassword` middleware
  handles re-authentication. The modal explains consequences only.

**Unchanged from the plan:**

- The trade-off in Section 4.3 stands. An event whose date has passed
  but was never visited stays RegistrationClosed until someone opens
  a page that binds it. Zero infrastructure, safe failure.
- Reversibility (Section 4.8). Once Completed, the event cannot be
  moved back. The only paths to change data are admin event deletion
  or a direct database edit.

---

## Feature C shipped (2026-10-15)

Feature C — the end-of-event report — shipped in commit `95c79a5`.

### What shipped

| Deliverable | Status |
|---|---|
| `app/Http/Controllers/ReportController.php` | ✅ |
| `app/Services/Reporting/ReportService.php` | ✅ |
| Route `GET /events/{event}/report` (`events.report`) | ✅ |
| Route `GET /events/{event}/report/export` (`events.report.export`) | ✅ |
| `resources/js/pages/events/report.tsx` | ✅ |
| View Report button on the event show page (ongoing / completed) | ✅ |
| `tests/Feature/Events/ReportControllerTest.php` | ✅ |
| `tests/Feature/Events/ReportServiceTest.php` | ✅ |

### Corrections to the Feature B status block

The "Deferred" bullet reads:

> The redirect target after finalize is `events.show`, not
> `events.report`. When Feature C ships, that becomes
> `events.report`. One line to change in
> `EventController::finalize()`.

That line changed. `EventController::finalize()` now redirects to
`events.report`. The comment in the controller was updated to
match.

### Reachability

`events.report` and `events.report.export` sit in the main
authenticated group. The controller 404s when the event status is
not `ongoing` or `completed`. The View Report button on
`events/show.tsx` mirrors the controller guard, so the link never
leads to a 404.

### CSV export

`events.report.export` is a separate route so the browser downloads
the file directly via `Content-Disposition: attachment` rather
than an Inertia visit. Same pattern as
`RegistrationMonitorController::exportCsv()`. No rate limiter — the
route is inside the authenticated group, matching the monitor's
export.

### Verification

    tests/Feature/Events/ReportControllerTest.php
    tests/Feature/Events/ReportServiceTest.php

Both suites pass. Not exercised by hand this session — Feature C
was committed from pre-existing WIP.

### Documentation note

The two new files and three test files were not read during the
session that committed them. The commit message discloses this per
P10. A future session touching the report should cat both files
before proposing changes.
