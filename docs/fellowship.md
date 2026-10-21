# WDEMS — Fellowship Module

**Audience:** Whoever implements, modifies, or reasons about the
Fellowship module — the six types, event creation, group
management, or the attendance roster.

**Purpose:** Reference for the shipped state of the module.
Every claim describes code that exists on `main` as of commit
`bb14b50` (2026-10-16). Not a spec, not a plan.

**Status:** Live. Phase 4 shipped end to end. The progress
entries in `docs/progress.md` are the chronological record;
this file is the consolidated reference.

**Related:**

- `docs/church-events/07-scope-and-task-division.md` §3.5 — the
  task breakdown this module implements
- `docs/church-events/09-mockup-scope-inventory.md` — what the
  client mockups show beyond this module
- `docs/progress.md` — the Phase 4 entries (2026-10-16)
- `docs/discipleship.md` — the parallel church-events module;
  structurally similar, documented with more depth because it
  has more layers
- `docs/participant-identity.md` — the Participant identity
  model every attendance row is anchored to

---

## 1. The 60-second overview

Fellowship is the church-gathering workstream. Six fixed types
(Family, Church, Pastors, Leaders, Women's, Men's) each contain
gatherings — events with a name, date, and optional details.
Attendance is recorded per gathering, with optional assignment
of each participant to a group within that gathering.

The module has two levels:

    Fellowship type → Event → Group (optional)

- A **type** is one of six fixed values. Types are an enum, not
  a database table. No UI creates or edits them.
- An **event** is one gathering of one type — a named occurrence
  on a date. Events carry description, venue, facilitator, and
  notes.
- A **group** is a sub-unit within one event (Parents, Youth,
  Children). Optional. Group names are chosen by the organizer,
  not fixed.

Attendance is recorded directly on the event:

- An **attendance row** says "this participant was present (or
  not) at this event, optionally assigned to this group."
  Keyed on (event, participant) — one row per participant per
  event.

**Fellowship does not have an enrollment concept.** Unlike
Discipleship, a participant is not "enrolled" in anything. They
simply appear at an event when attendance is recorded. If they
attend ten Family Fellowships over a year, that is ten separate
attendance rows with no linking enrollment record.

---

## 2. Where things live

### Database tables

| Table                    | Purpose                                                         |
| ------------------------ | --------------------------------------------------------------- |
| `fellowship_events`      | One gathering, FK to created_by user                            |
| `fellowship_groups`      | Event-specific groups, FK to event                              |
| `fellowship_attendances` | Participant × event, FK to event + participant + optional group |

### Models

| Model                  | File                                  |
| ---------------------- | ------------------------------------- |
| `FellowshipEvent`      | `app/Models/FellowshipEvent.php`      |
| `FellowshipGroup`      | `app/Models/FellowshipGroup.php`      |
| `FellowshipAttendance` | `app/Models/FellowshipAttendance.php` |

The `Participant` model carries a `fellowshipAttendances()`
relation.

### Enums

| Enum             | Values                                         |
| ---------------- | ---------------------------------------------- |
| `FellowshipType` | family, church, pastors, leaders, womens, mens |

`FellowshipType` has a `label()` method and a `values()` helper.

**Attendance status is not an enum.** It is stored as a 16-char
string. The allowed set (`present`, `absent`, `excused`) is
validated by `Rule::in(...)` inside the FormRequest. Rationale:
the allowed set may grow; a schema change is not needed to add
a fourth status.

### Controllers

| Controller                       | Actions                             |
| -------------------------------- | ----------------------------------- |
| `FellowshipController`           | index, show, store, update, destroy |
| `FellowshipGroupController`      | store, update, destroy              |
| `FellowshipAttendanceController` | show, store, mark, destroy          |

### FormRequests

All under `app/Http/Requests/Fellowship/`:

- `StoreFellowshipEventRequest`
- `UpdateFellowshipEventRequest`
- `StoreFellowshipGroupRequest`
- `UpdateFellowshipGroupRequest`
- `AddFellowshipAttendanceRequest`
- `MarkFellowshipAttendanceRequest`

### Frontend

| Page                | File                                           |
| ------------------- | ---------------------------------------------- |
| Landing             | `resources/js/pages/fellowship/index.tsx`      |
| Per-type event list | `resources/js/pages/fellowship/show.tsx`       |
| Attendance          | `resources/js/pages/fellowship/attendance.tsx` |

Sidebar entry in `app-sidebar.tsx` — `HeartHandshake` icon,
between Services and Events.

### Tests

`tests/Feature/Fellowship/`:

- `FellowshipControllerTest.php` — 16 tests
- `FellowshipGroupControllerTest.php` — 11 tests
- `FellowshipAttendanceControllerTest.php` — 18 tests

**45 tests, 190 assertions** at Phase 4 closure.

---

## 3. The data model

### Event

Columns: id, fellowship_type (32 chars, enum-backed), name
(255), description (nullable text), event_date, start_time
(nullable), end_time (nullable), venue (nullable), facilitator
(nullable), notes (nullable text), created_by (FK, RESTRICT),
timestamps.

Index on (fellowship_type, event_date) — the per-type listing
sorts chronologically by this index.

**No slug column.** Fellowship events have no public URL; they
are internal records only. Routes bind events by id.

**No unique on (fellowship_type, name).** Two Family Fellowships
can both be named "Monthly Gathering". If duplicate names become
a data-quality problem, add the constraint then.

### Group

Columns: id, fellowship_event_id (FK, CASCADE), name (100),
display_order (integer, default 0), timestamps.

**UNIQUE on (fellowship_event_id, name).** An event cannot have
two groups with the same name. Enforced at the database level
and at the FormRequest level (for a friendly 422 rather than a
QueryException).

`display_order` controls rendering order. Lower numbers appear
first. Tie-broken by id.

### Attendance

Columns: id, fellowship_event_id (FK, CASCADE), participant_id
(FK, CASCADE), fellowship_group_id (FK, SET NULL, nullable),
attendance_status (16 chars), recorded_by (FK, SET NULL,
nullable), recorded_at (nullable timestamp), notes (nullable
text), timestamps.

**UNIQUE on (fellowship_event_id, participant_id).** A
participant cannot be marked twice for the same event. A second
attempt returns a friendly 422 rather than a QueryException.

**`fellowship_group_id` is nullable.** A participant may be
marked present without a group. Set to NULL on group delete via
`nullOnDelete` — attendance rows survive group deletion.

**`recorded_by` uses `nullOnDelete`.** Same convention as
Discipleship attendance: the fact outlives the recorder's
account.

---

## 4. The workflow

### Type lifecycle

None. Six types exist as an enum. If the client adds a seventh
type, it is a code change: one case in `FellowshipType`, one
case in `label()`, one entry in the WHERE-IN constraint on the
`{type}` route parameter, one entry in the seeded icon map on
the frontend.

### Event lifecycle

Events are created, renamed, updated, and deleted by staff.
Any authenticated user can perform any action; there is no
per-event ownership check.

- **Create:** `POST /fellowship/{type}/events`
- **Update:** `PUT /fellowship/events/{event}` — name,
  description, date, times, venue, facilitator, notes. The
  `fellowship_type` is immutable.
- **Delete:** `DELETE /fellowship/events/{event}` — cascades to
  the event's groups and attendance rows.

### Group lifecycle

Groups are created, renamed, reordered, and deleted on the
event's attendance page. Each group belongs to exactly one
event.

- **Create:** `POST /fellowship/events/{event}/groups`
- **Update:** `PUT /fellowship/events/{event}/groups/{group}`
- **Delete:** `DELETE /fellowship/events/{event}/groups/{group}`
  — attendance rows assigned to the group survive with a NULL
  group pointer.

Cross-event guard: the group's `fellowship_event_id` must match
the route's `{event}`. A mismatch aborts with 404.

### Attendance lifecycle

- **Show:** `GET /fellowship/events/{event}/attendance` — the
  roster plus the event's groups.
- **Add:** `POST /fellowship/events/{event}/attendance` — takes
  `participant_id` or new-participant fields. Optional
  `fellowship_group_id`. Initial status is always `present`.
- **Mark:** `PUT /fellowship/events/{event}/attendance/{attendance}`
  — updates status, group pointer, or notes. Requires status;
  accepts optional group.
- **Remove:** `DELETE /fellowship/events/{event}/attendance/{attendance}`
  — deletes the row. Does **not** delete the underlying
  participant. Same semantics as Discipleship attendance
  destroy.

Note: removing an attendance row **deletes it**, unlike
Discipleship enrollment withdraw (which sets a status). The
distinction is deliberate. Enrollment is a durable commitment —
withdraw is a state change. Attendance is a fact about one
event — removing it is legitimately "no record."

### Cross-event guards

Every row-level action on attendance and groups asserts that
the row's event matches the route's event. Prevents URL
tampering across events.

---

## 5. Routes

All under the `/fellowship` prefix, in the main authenticated
group (`auth`, `verified.or.admin`, `device.trusted`,
`event.advance`).

| Method | URL                                                  | Controller action                        | Route name                      |
| ------ | ---------------------------------------------------- | ---------------------------------------- | ------------------------------- |
| GET    | `/fellowship`                                        | `FellowshipController@index`             | `fellowship.index`              |
| GET    | `/fellowship/{type}`                                 | `FellowshipController@show`              | `fellowship.show`               |
| POST   | `/fellowship/{type}/events`                          | `FellowshipController@store`             | `fellowship.events.store`       |
| PUT    | `/fellowship/events/{event}`                         | `FellowshipController@update`            | `fellowship.events.update`      |
| DELETE | `/fellowship/events/{event}`                         | `FellowshipController@destroy`           | `fellowship.events.destroy`     |
| POST   | `/fellowship/events/{event}/groups`                  | `FellowshipGroupController@store`        | `fellowship.groups.store`       |
| PUT    | `/fellowship/events/{event}/groups/{group}`          | `FellowshipGroupController@update`       | `fellowship.groups.update`      |
| DELETE | `/fellowship/events/{event}/groups/{group}`          | `FellowshipGroupController@destroy`      | `fellowship.groups.destroy`     |
| GET    | `/fellowship/events/{event}/attendance`              | `FellowshipAttendanceController@show`    | `fellowship.attendance.show`    |
| POST   | `/fellowship/events/{event}/attendance`              | `FellowshipAttendanceController@store`   | `fellowship.attendance.store`   |
| PUT    | `/fellowship/events/{event}/attendance/{attendance}` | `FellowshipAttendanceController@mark`    | `fellowship.attendance.mark`    |
| DELETE | `/fellowship/events/{event}/attendance/{attendance}` | `FellowshipAttendanceController@destroy` | `fellowship.attendance.destroy` |

12 routes.

**`{type}` is constrained** via `whereIn` to the six enum
values. A URL like `/fellowship/not-a-real-type` 404s before
the controller runs.

**Route declaration order matters.** The `events/...` routes
are more specific than `{type}`; they sit first inside the
group. The `{type}` constraint prevents `/fellowship/events`
from matching the show route — "events" is not a valid type.

**`{event}`, `{group}`, `{attendance}` bind by id.** None have
slugs, none have unique names. Laravel resolves by controller
type-hint, not by parameter name — the `{event}` parameter in
these routes binds to `FellowshipEvent`, distinct from the
running-event module's `{event}` parameter which binds to
`Event`. The two live under different path prefixes and never
collide.

---

## 6. Frontend surfaces

### Landing — `fellowship/index.tsx`

Six type cards in enum order. Each card shows a type-specific
icon, the type label, and a count of events with the latest
event date. Clicking a card navigates to the per-type view.

Icons:

| Type    | Icon        |
| ------- | ----------- |
| Family  | `Home`      |
| Church  | `Church`    |
| Pastors | `BookOpen`  |
| Leaders | `Award`     |
| Women's | `UserRound` |
| Men's   | `UserRound` |

Women's and Men's deliberately share `UserRound` — distinct
icons for a gender pair would imply a ranking the data does not
carry.

### Per-type view — `fellowship/show.tsx`

Two sections:

**Header.** Type label, event count, Add Gathering button.

**Event list.** One card per event, ordered by `event_date`
descending (most recent first). Each card shows the event name,
description, date, times, venue, facilitator, and attendance
count. The event name is clickable to the attendance page.

Each card has edit (pencil) and delete (trash) controls. Edit
opens `EventFormDialog` in edit mode; delete opens a confirm
dialog.

**Create** and **Edit** share the same `EventFormDialog`
component. One form, one `useForm` hook, mode-driven.

### Attendance — `fellowship/attendance.tsx`

Three sections:

**Header.** Event name, date, times, venue, facilitator,
created by.

**Groups panel.** A list of group chips showing name and
attendance count. Each chip has edit and delete controls. Add
Group button.

**Roster.** Add Participant button, search field, table.

Table columns: Status, Name, Age, Contact, Group, Actions.

- **Status** is a plain `<select>` (not the Radix Select)
  showing present / absent / excused with color-coded borders.
  Changing status fires one PUT.
- **Group** is a Radix Select showing "No group" plus all
  event groups. Changing fires one PUT that carries both the
  current status and the new group — one request, no
  field-nulling.
- **Actions** has a delete button that opens a confirm dialog.

Two dialogs:

- **Add Participant** — first name, last name, age, birthday,
  contact number, address, optional group. Uses `firstOrCreate`
  on phone-normalized match: if a participant with the same
  normalized phone exists, reuse them.
- **Group Form** — name, display order. Same dialog for create
  and edit.

### Empty states

Three distinct cases on the attendance page: no groups (a
one-line italic note, not a full empty state), no roster (full
empty state with Add Participant button), no search matches
(full empty state with different message).

---

## 7. Design decisions

Each of these has a rationale that is not obvious from the code
alone.

### Type is an enum, not a database table

Six fixed values. Consistency with `ServiceType` (Phase 3). If
the client later wants admin-configurable Fellowship types, the
change is an additive migration that moves the six values into
a `fellowship_types` table.

### No enrollment layer

A participant simply appears at an event when attendance is
recorded. There is no "committed to this Fellowship" state.
Rationale: Fellowship gatherings are one-off events, not
courses. Attendance is a factual record, not a status.

### Groups are a first-class resource with their own routes

Not lazily created from attendance rows. Rationale: the mockup
shows the organizer setting up groups (Parents, Youth,
Children) before the event. A first-class resource supports
that flow.

The alternative — groups created on first mention in an
attendance row — would leave the groups panel empty until the
first mark. Worse UX for the setup phase.

### `fellowship_group_id` is nullable with `nullOnDelete`

A participant may be marked present without a group. If a group
is deleted, its attendance rows survive with a NULL group
pointer. Rationale: attendance is a fact; the group assignment
is metadata. Losing the assignment is acceptable; losing the
attendance is not.

### Attendance status is a string, not an enum

The allowed set (`present`, `absent`, `excused`) may grow.
Storing as a string means adding a fourth status is a
FormRequest rule change, not a schema migration.

Contrast with `DiscipleshipEnrollmentStatus` and
`DiscipleshipAttendanceStatus` — those are enums because the
set is stable and every consumer needs the labels.

### One PUT for status + group changes

`FellowshipAttendanceController::mark()` accepts
`attendance_status` (required) and `fellowship_group_id`
(optional). When the operator changes only one field, the
frontend sends the other unchanged so the server never receives
a partial payload that would null the untouched field.

### Cross-event guards are strict

Every row-level action asserts the row's event matches the
route's event. A mismatch aborts with 404. Rationale: URL
tampering across events is not a legitimate use case.

### Attendance destroy deletes the row, unlike enrollment

withdraw

Enrollment withdraw sets a status; attendance destroy removes
the row. Rationale: enrollment is a durable commitment,
attendance is a fact about one event. Removing attendance is
legitimately "no record for this event"; withdrawing
enrollment is "still enrolled, but withdrawn from pursuing."

### No bulk attendance operations

Unlike Discipleship's matrix, Fellowship attendance is one
PUT per participant. Rationale: a Fellowship gathering is
typically 10–40 people, edited one at a time as people arrive.
The Discipleship matrix has a batch-of-sessions × batch-of-
participants shape that makes bulk worth it. Fellowship does
not.

### No progress or completion tracking

Fellowship events have no sessions, no completion criteria, no
progress percentage. A gathering happened or it did not. If the
client wants multi-event Fellowship journeys tracked (e.g.
"attended 8 of 12 Men's Fellowships this year"), that is a
query over attendance rows, not a stored state.

---

## 8. What is not built

The client mockups (`docs/church-events/09-mockup-scope-
inventory.md`) show Fellowship at the sidebar level but no
detail page. No features are missing relative to a specification
— Fellowship was built from the task list in doc 07 §3.5 and
that list is complete.

Two things the module could plausibly grow into:

1. **Cross-event attendance summaries.** "How many Family
   Fellowships has this participant attended this year?" is
   currently a manual query. A report page could surface it.
2. **Recurring Fellowship schedules.** Currently every event is
   created individually. Services has a recurring generator;
   Fellowship does not. Not requested.

Neither is in a spec.

---

## 9. Open items

Known but not fixed. Recorded so a future session does not
rediscover them.

1. **No `creator()` / `recorder()` coverage in tests.** Same
   four uncovered relations as Discipleship: `FellowshipEvent::creator()`,
   `FellowshipGroup::` (no creator — groups have no
   `created_by`), `FellowshipAttendance::recorder()`. Not
   called by any controller, service, or view. Cosmetic.

    Actually `FellowshipGroup` has no `created_by` column —
    groups are event-scoped, not user-attributed. Only Event
    and Attendance have user FKs.

2. **No `attendance_count` on the attendance page header.**
   The per-type view shows counts; the attendance page shows
   only the roster.

3. **No CSV export.** Services and Discipleship have exports;
   Fellowship does not. Not requested.

4. **No pagination.** Fine at church scale.

5. **No filter or search on the roster.** The roster is small;
   the browser's find function is sufficient.

---

## 10. Cross-references

| Document                                                | Purpose                                                     |
| ------------------------------------------------------- | ----------------------------------------------------------- |
| `docs/church-events/07-scope-and-task-division.md` §3.5 | Phase 4 task breakdown                                      |
| `docs/church-events/09-mockup-scope-inventory.md`       | What the client mockups show beyond this module             |
| `docs/progress.md` 2026-10-16 Phase 4 entries           | The sub-phase records                                       |
| `docs/discipleship.md`                                  | The parallel church-events module — more layers, more depth |
| `docs/participant-identity.md`                          | Participant identity model                                  |
| `docs/architecture.md`                                  | Whole-system overview                                       |

---

## 11. Changelog

- **2026-10-16** — File created. Documents the state of the
  Fellowship module after Phase 4 closure and Phase 5 completion
  at commit `bb14b50`. Covers data model, workflow, routes,
  frontend, design decisions, and open items.
