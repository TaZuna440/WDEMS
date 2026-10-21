# WDEMS -- Church Events Domain Model and Workflows

**Status:** Draft for review. Documentation-only. No schema
decision in this document is selected. Every "Alternative" is a
candidate for a decision that is still pending.

**Audience:** Whoever executes documents 04-06 of the Church
Events documentation set, and whoever approves the eventual
implementation.

**Purpose:** Map the design space for extending the WDEMS domain
model to support Church Events. Establish what exists today,
what could change, what the consequences of each change are, and
which decisions block which implementation phases.

**Related:**

- `docs/church-events/01-current-system-audit.md` -- verified
  findings about the current system
- `docs/church-events/02-product-scope-and-requirements.md` --
  product scope and the ten blocking decisions
- The Church Event Management System workflow specification
- The dashboard mockup

**Limitation notice (repeated from doc 01):** The auditor has no
filesystem access. Every claim in section 1 is drawn from files
pasted during the audit session. Claims marked PENDING-DATA
require a command to be run by the developer.

---

## 0. Reading this document

Tags follow document 02:

| Tag              | Meaning                                                                                           |
| ---------------- | ------------------------------------------------------------------------------------------------- |
| **CURRENT**      | Describes an existing implementation. Verified or inferred, per doc 01.                           |
| **CANDIDATE**    | One of several alternatives for a pending decision. Not a recommendation.                         |
| **BLOCKED**      | Cannot be settled until a specific product decision is made.                                      |
| **CONSTRAINT**   | A rule that limits the design space. Derived from the existing system or from the scoping prompt. |
| **PENDING-DATA** | Requires a command to be run; the auditor cannot verify this from pasted files.                   |

This document does not select. Where the audit (doc 01) made a
provisional recommendation, that recommendation was withdrawn by
the audit's review-response block. Nothing here re-introduces it.

---

## 1. The current domain model

### 1.1 Verified shape (CURRENT)

The WDEMS domain today has exactly two event categories and one
lifecycle. The verified shape from `01-current-system-audit.md`:

    Event
      id, created_by -> User
      event_type  -> EventType enum (CommunityRun | FunRun)
      status      -> EventStatus enum (Draft | RegistrationOpen |
                     RegistrationClosed | Ongoing | Completed |
                     Cancelled)
      event_name, description
      event_date, start_time, end_time
      distance_value, distance_unit
      venue, venue_address, venue_map_url,
      venue_latitude, venue_longitude
      partners (JSON)
      eight accessibility booleans
      registration_start, registration_end
      registration_form_saved_at, registration_slug
      registration_common_field_requirements (JSON)

    Registration (event_id, participant_id, ...)
      UNIQUE (event_id, participant_id)

    Participant (first_name, last_name, contact_number,
                 contact_number_normalized, email, age, address)
      UNIQUE email (non-null)
      UNIQUE contact_number_normalized (non-null)

    Attendance (registration_id, attendance_status,
                attendance_time, recorded_by, notes, verified_at)
      FK registration_id -> Registration

    RegistrationField       (event_id, label, field_type, ...)
    RegistrationFieldResponse (registration_id, registration_field_id,
                               value)

    RegistrationVerificationChallenge
      (event_id, token_hash, payload, per-channel OTP state)

### 1.2 What is NOT in the current model (CURRENT)

- No `event_categories` table.
- No `event_types` table.
- No `event_occurrences` table.
- No `budget` or `expense` columns.
- No approval columns (`approver_id`, `requires_approval`,
  `approved_at`, `approval_notes`).
- No `parent_event_id`, `series_id`, `recurrence_rule`, or any
  recurrence field on `events`.
- No `event_attachments` table.
- No `members` table (church members).
- No policies. No authorization beyond `User::isAdmin()`.

### 1.3 Load-bearing invariants (CONSTRAINT)

These must not be broken by any church-event extension:

- `EventStatus::allowedTransitions()` is the sole gate for
  running-event status changes.
- `EventWorkflow::transition()` is the sole write path for
  running-event status changes.
- `Attendance.registration_id` is the FK. Running-event
  attendance is per-registration, not per-event.
- `Participant.resolveFrom()` is the identity resolver for
  attendance walk-ins and bulk paper intake. It is not on the
  public path anymore (public uses
  `RegistrationVerificationService::finalize()`).
- `Registration (event_id, participant_id)` is UNIQUE. One
  registration per participant per event.
- `participants.email` is UNIQUE on non-null values.
- `participants.contact_number_normalized` is UNIQUE on non-null
  values.
- Existing migrations are append-only.

### 1.4 Load-bearing consumers of `EventType` (PENDING-DATA)

The audit identified these consumers by reading source files:

- `app/Models/Event.php::isCommunityRun()`
- `app/Models/Event.php::isFunRun()`
- `app/Concerns/EventValidationRules.php::eventRules()` -- uses
  `Rule::enum(EventType::class)`
- `app/Services/Reporting/ReportService.php::cross_event()` --
  scopes on `event_type`
- Frontend types in `resources/js/pages/events/*`

The audit could not verify this list is complete. `PENDING-DATA`:
grep for `EventType::` across `app/`, `resources/js/`,
`database/`, `routes/`, and `tests/` is required before any
decision on category/type modeling.

### 1.5 Load-bearing consumers of `EventStatus` (PENDING-DATA)

The audit identified these by reading source files:

- `EventWorkflow::transition()`
- `EventWorkflow` convenience methods
  (`openRegistration`, `closeRegistration`, `start`, `complete`,
  `cancel`)
- `AdvanceEventStatus` middleware
- `Event::canEdit`, `canEditRegistrationForm`,
  `canOpenRegistration`, `canCloseRegistration`, `canFinalize`,
  `canRecordAttendance`, `canRecordAttendanceNow`,
  `canViewAttendance`
- `DashboardController::buildActionItems()`
- `EventController::index`, `show`
- `AttendanceController::index`
- `RegistrationController::index`
- `RegistrationMonitorController::index`, `show`
- `ReportController::assertReportable()`
- `EventDeletionService::delete()`
- Frontend status-pill rendering on multiple pages

Same `PENDING-DATA`: a full grep is required before deciding
whether to extend `EventStatus`.

---

## 2. Decision 1: Event category and event type modeling

**Blocks:** NA. **Blocked by:** product decisions on whether
categories and types are administrator-managed.
**Product scope reference:** doc 02 CAT-02, CAT-03, PND-01.
**Audit reference:** audit section F.

### 2.1 Current (CURRENT)

`EventType` is a two-case PHP enum. `Event.event_type` casts to
it. Validation uses `Rule::enum(EventType::class)`.

### 2.2 Candidates

**F1 -- Extend the `EventType` enum with church cases.**
Add `ChurchService`, `Fellowship`, etc. to the enum. No DB
tables.

- Satisfies: unified type lookup, type-safe `match`, single
  source of truth.
- Fails: workflow spec section 4.1 requirement that dropdown
  options be administrator-configurable. Adding a type requires
  a code deploy.
- Cost: low. Blast radius: every `match` and every consumer of
  `EventType`.

**F2 -- Hybrid.** Keep `EventType` enum for running events. Add
`event_categories` and `event_types` DB tables for church
events. `events` gains nullable `event_category_id` and
`event_type_id`.

- Satisfies: administrator-managed church types. Running path
  untouched at the enum level.
- Cost: two parallel type systems. Validation must branch on
  which one applies. Frontend must render types from two
  sources.
- Open question under F2: what does `events.event_type` hold for
  a church row? Options: a sentinel case like
  `EventType::Church` (requires extending the enum anyway),
  `NULL` (requires making the column nullable and the cast
  nullable-tolerant), or leaving the column populated with an
  arbitrary running value (unacceptable).
- Open question under F2: are `event_category_id` and
  `event_type_id` both required for church rows, or is
  `event_category_id` implied by `event_type_id`?

**F3 -- Everything in DB tables.** Deprecate `EventType`. Seed
`Community Run` and `Fun Run` as rows in `event_types`.

- Satisfies: single system, uniform administrator management.
- Cost: high. Every reference to `EventType` changes:
  `isCommunityRun()`/`isFunRun()` become name lookups;
  `Rule::enum()` becomes `exists:event_types,id`; frontend types
  regenerate; all tests touching `EventType` are updated.
- Blast radius: larger than any other candidate.

**F4 -- Enum for all types, no DB tables, no admin UI.**
Add church types to the enum but do not implement the mockup's
"Add Category" flow.

- Satisfies: simplest change.
- Fails: contradicts workflow spec section 4.1.
- Blast radius: same as F1.

**F5 -- Polymorphic types.** A single `event_types` table with a
`kind` column (`running` | `church`). `EventType` enum is
deleted; `Community Run` and `Fun Run` become seeded rows with
`kind = 'running'`; church types are seeded rows with
`kind = 'church'`. Categories are a separate table referenced
only by `kind = 'church'` rows.

- Satisfies: one system, category support for church only.
- Cost: same as F3 for the running side, plus a new
  `category_id` nullable foreign key.

### 2.3 Consequences table

| Candidate | Admin-configurable types | Running path untouched | Migration added                                 | Frontend rework |
| --------- | ------------------------ | ---------------------- | ----------------------------------------------- | --------------- |
| F1        | No                       | Yes                    | None                                            | Small           |
| F2        | Yes                      | Yes                    | events migration, event_categories, event_types | Medium          |
| F3        | Yes                      | No                     | event_types, event_categories, seed migration   | Large           |
| F4        | No                       | Yes                    | None                                            | Small           |
| F5        | Yes                      | Partially              | event_types, event_categories, data migration   | Large           |

### 2.4 Blocking questions

1. Does the client require administrator-managed categories and
   types, or is a code-level list acceptable for the first
   release?
2. If administrator-managed, is the running side also
   administrator-managed, or only the church side?
3. What does `events.event_type` hold for a church row under any
   hybrid candidate?

---

## 3. Decision 2: Event status and lifecycle

**Blocks:** Any status-dependent feature.
**Blocked by:** product decision on whether the ten-stage
workflow is a state machine or a checklist.
**Product scope reference:** doc 02 EVT-02 through EVT-04,
PND-02.
**Audit reference:** audit section A.

### 3.1 Current (CURRENT)

`EventStatus` has six cases with linear transitions. Every
running-event guard is a method on `Event` that reads
`$this->status`.

### 3.2 Candidates

**A1 -- Extend `EventStatus`.** Add church states to the enum.

- Satisfies: one status machine.
- Cost: every consumer of `EventStatus` re-read. Running-event
  tests may break. `EventWorkflow::transition()` grows
  branches on category.

**A2 -- New `ChurchEventStatus` enum.** `Event.status` stores a
string; the cast is category-dependent. Add a `church_status`
column OR reuse `status` and cast based on category.

- Satisfies: running path untouched.
- Cost: two status systems in the UI and in reports.
  `Event::status` becomes polymorphic in intent even if it
  stores one string.
- Open question under A2: if `status` is reused, is the cast in
  `Event::casts()` conditional? Laravel does not support
  conditional casts directly; the model must override
  `getAttribute` or use a custom cast class.

**A3 -- Separate `workflow_stage` concept.** Keep
`EventStatus` for running events. Church stages are a separate
field or side table (`church_event_stages`) that is orthogonal
to `status`.

- Satisfies: the status pill system stays simple and matches
  the mockup.
- Cost: two concepts (status and stage). Approval is not a
  status transition; it is a stage transition. Reports must
  reconcile the two.

**A4 -- Extend `EventStatus` but restrict church events to a
subset.** Church events never leave the six running states.
Stages are pure UI.

- Satisfies: no schema change to status.
- Fails: cannot represent `Proposed`, `Under Review`, or
  `Rejected` as real states. Approval becomes a boolean or a
  separate field.

### 3.3 Consequences table

| Candidate | Running status untouched | Ten stages representable as state | Migration added             | Frontend rework |
| --------- | ------------------------ | --------------------------------- | --------------------------- | --------------- |
| A1        | No                       | Yes                               | None (enum change)          | Medium          |
| A2        | Yes                      | Yes                               | Possibly a column           | Medium          |
| A3        | Yes                      | Only as stage field               | Yes (stage table or column) | Medium          |
| A4        | Yes                      | No                                | None                        | Small           |

### 3.4 Blocking questions

1. Is the ten-stage workflow an approved requirement or a
   discovery artifact?
2. Do low-ceremony categories (Services, Church Administration)
   skip stages?
3. What do the mockup's four display labels (`Upcoming`,
   `Open Registration`, `Ongoing`, `Completed`) represent --
   derived display states or actual values?
4. Does approval gate preparation (as the spec says), or is it
   advisory?

---

## 4. Decision 3: Occurrence model

**Blocks:** Attendance, reports, deletion cascade.
**Blocked by:** product decision on how attendance is recorded.
**Product scope reference:** doc 02 EVT-05, EVT-06, REC-01
through REC-04, ATT-02 through ATT-05, PND-03, PND-10.
**Audit reference:** audit section D.

### 4.1 Current (CURRENT)

No occurrence concept exists. `events` has one date, one start
time, one end time. `AdvanceEventStatus` fires on the single
event row.

### 4.2 Candidates

**D1 -- No occurrences.** Each service date is a separate
`Event` row, created manually.

- Satisfies: no schema change. Running path fully untouched.
- Fails: workflow spec requirement that a schedule create
  occurrences. No series-level reporting. Mass creation is
  tedious.

**D2 -- `event_occurrences` table with shared `Attendance`.**
New table:
`id, event_id, occurrence_date, label, start_time, end_time,
status, notes`. Attendance FK question: does the existing
`Attendance` model gain a nullable `occurrence_id`, or does the
model attach to `Occurrence` for church events?

- Satisfies: matches the workflow spec. Series reporting
  possible.
- Cost: new table, new model, new relation on `Event`, new
  write path. `Attendance` becomes dual-purpose or must
  branch.

**D3 -- `event_occurrences` plus `occurrence_attendances`.**
Church attendance is its own table.

- Satisfies: full separation. Running attendance untouched.
- Cost: two attendance tables. Reports must union them.

**D4 -- `event_occurrences` plus nullable `occurrence_id` on
the existing `Attendance`.**

- Satisfies: one attendance table.
- Cost: every existing query on `Attendance` must filter
  (or acknowledge) the nullable column. High risk.

### 4.3 Consequence: what does attendance attach to?

This is the sub-decision under D. Four options:

- **Registrations** (running-event model). Requires every
  church attendee to have a registration. Services with no
  registration cannot use this.
- **Church members.** Requires a `members` table and a
  `member_id` FK on attendance. No such table exists.
- **Occurrences.** Requires the D2/D3/D4 model. Attendance
  attaches to `(occurrence_id, participant_id)` or
  `(occurrence_id, member_id)`.
- **Count only.** No per-person record. Aggregates stored on
  the occurrence row (`attendance_count`).

The answer determines the schema.

### 4.4 Consequence: identity of attendees

For a service with attendance against a member list, is the
attendee identified by:

- `participants.id` (reuses the running-event identity model;
  requires every attendee to be a Participant)
- `members.id` (new table; no existing FK)
- Unnamed (count-only)
- A mix (member list plus guest count)

Doc 02 ATT-04 flags this. No answer is presupposed.

### 4.5 Consequence: recurring schedule generation

If recurring is in scope, how are future occurrences created?

- **Eager.** On event creation, generate N occurrences up to a
  horizon (e.g., 12 weeks).
- **Lazy.** On first access, generate missing occurrences up to
  a rolling horizon.
- **Manual.** Organizer creates each occurrence explicitly.
- **Scheduled.** A Laravel scheduled command generates
  occurrences daily.

The choice affects jobs, migrations, and testing.

### 4.6 Blocking questions

1. Is recurring in scope for the first release?
2. What is attendance recorded against?
3. Are attendees identified by `participants.id`, by a member
   record, or by count only?
4. If a recurring schedule exists, how are future occurrences
   generated?

---

## 5. Decision 4: Approval workflow

**Blocks:** Any approval feature. Also interacts with
authorization (doc 01 section 1.8).
**Blocked by:** product decision on approver identity and audit
requirements.
**Product scope reference:** doc 02 APR-01 through APR-05,
PND-04.
**Audit reference:** audit section C.

### 5.1 Current (CURRENT)

No approval concept. `User::isAdmin()` is the only role check.
No policies. `events.created_by` exists and is set but nothing
reads it.

### 5.2 Candidates

**C1 -- New `approver` role.** Third role alongside `admin` and
`staff`.

- Cost: role management UI. Modifies `User::$fillable` (for
  `role`), which is currently `['name', 'email', 'password',
'role']`.
- Blast radius: every place that checks `isAdmin()` may need a
  parallel check.

**C2 -- `admin` role approves.** No new role. Approval actions
gated on `isAdmin()`.

- Cost: low.
- Fails: cannot support multiple distinct approvers (e.g., a
  pastor who is not the system admin).

**C3 -- Per-event approver.** Nullable `approver_id` on
`events`.

- Cost: medium. Requires a UI to select the approver at
  proposal time. Approval gate: `auth()->id() ===
$event->approver_id || isAdmin()`.
- Satisfies: flexibility without a new role.

**C4 -- Approval deferred.** No formal approval. Stage 2's
"approval" is a checklist item.

- Cost: minimal.
- Fails: if the client requires an audit trail of who approved
  what.

### 5.3 Sub-decision: audit trail

If approval is implemented, do approval actions write to an
`event_approval_log` table or to the existing audit conventions
(`Log::info`)?

- A log table supports reporting ("show me all approved
  proposals in Q1").
- `Log::info` is not queryable from the UI.

No `event_approval_log` exists today.

### 5.4 Blocking questions

1. Is approval in scope for the first release?
2. Who approves? A single admin, a specific named user per
   event, a role?
3. Can the creator approve their own proposal?
4. Is rejection terminal, or can a rejected proposal be
   revised and resubmitted?
5. What is the audit-trail requirement?
6. Does the Church Events authorization change expand to cover
   running events too? (This reopens doc 01's ISSUE-004.)

---

## 6. Decision 5: Budget and expenses

**Blocks:** Any financial feature.
**Blocked by:** product decision on minimum financial
requirement.
**Product scope reference:** doc 02 BUD-01 through BUD-03,
PND-05.
**Audit reference:** audit section E.

### 6.1 Current (CURRENT)

No budget or expense concept in any file inspected.

### 6.2 Candidates

**E1 -- Tier 1.** `events.budget_amount` (decimal, nullable) and
`events.budget_notes` (text, nullable). Reconciliation is a PDF
upload via a generic attachments table.

**E2 -- Tier 2.** A `budget_line_items` table:
`event_id, category, description, amount, notes`. Total is
computed. Reconciliation is still a PDF upload.

**E3 -- Tier 3.** Line items plus per-receipt attachments, per-
item reconciliation status, and an approval workflow for each
expense.

### 6.3 Consequence: attachments

Any budget tier requires an attachments mechanism. Options:

- **A single `event_attachments` table** shared by budget,
  minutes, photos, and documents. Columns:
  `id, event_id, category, filename, mime, size, path,
uploaded_by, uploaded_at`.
- **A generic `attachments` table** with
  `attachable_type`/`attachable_id` (polymorphic). Supports
  attachments on any model, not just events.
- **A per-feature table** (e.g., `budget_files`,
  `meeting_minutes`). Most tables, least shared logic.

No attachments table exists today.

### 6.4 Blocking questions

1. Is budget and expense tracking in scope for the first
   release?
2. At which tier?
3. If attachments are needed, one table or many?
4. What file types and size limits are acceptable?
5. Where are uploaded files stored (local, S3, other)?
   `config/filesystems.php` was not inspected.

---

## 7. Decision 6: Enrollment and completion

**Blocks:** Discipleship and structured-program features.
**Blocked by:** product decision on whether enrollment is
distinct from registration.
**Product scope reference:** doc 02 REG-05, PND-08.

### 7.1 Current (CURRENT)

`Registration` is the only per-person enrollment concept.
`RegistrationStatus` has three cases: `Pending`, `Confirmed`,
`Cancelled`. No completion state. No per-participant
progression.

### 7.2 Candidates

**EN1 -- Reuse `Registration` for enrollment.** Add new values
to `RegistrationStatus` (`Enrolled`, `InProgress`, `Completed`,
`Withdrawn`, `Incomplete`). Add a nullable `completed_at`
column.

- Cost: low. `RegistrationStatus` is currently used by running-
  event registration; adding church states to it broadens the
  enum's meaning.
- Risk: running-event queries that assume `Confirmed` is the
  only meaningful state may need to branch.

**EN2 -- A `program_enrollments` table.** Distinct from
`Registration`. Columns:
`id, event_id, participant_id, status, enrolled_at,
completed_at, notes`. Unique on `(event_id, participant_id)`.

- Cost: medium. Two enrollment concepts.
- Benefit: running-event registration semantics unchanged.

**EN3 -- A `program_enrollments` table with per-session
attendance.** EN2 plus a `program_session_attendance` table or
reuse of `occurrence_attendances`.

- Cost: higher. Full separation.
- Benefit: matches the workflow spec exactly.

### 7.3 Blocking questions

1. Is enrollment distinct from registration, or the same
   concept?
2. If distinct, does it require per-session attendance and
   completion tracking in the first release?
3. Does completion gate certificate issuance, or is it purely
   a record?

---

## 8. Decision 7: Subgroups

**Blocks:** Fellowship, Youth, Kids, Young Adult category
features.
**Blocked by:** product decision on whether subgroups are a
first-class concept.
**Product scope reference:** doc 02 CAT-04, PND-09.

### 8.1 Current (CURRENT)

No subgroup concept. `Event` has no subgroup columns or
relations.

### 8.2 Candidates

**SG1 -- A per-event text field.** `events.subgroup` (string,
nullable). Simple. Not queryable as a normalized dimension.

**SG2 -- A `subgroups` table.** `id, category_id, name,
parent_id`. Categories reference their allowed subgroups.
Events reference a subgroup.

- Cost: medium. Matches the three-level structure (Kids
  Ministry > Outreaches > Wawang Pulo). Requires a UI to
  manage subgroups.

**SG3 -- A polymorphic tags table.** `event_tags (event_id,
tag)`. Free-form.

- Cost: low. Loses the taxonomy structure.

### 8.3 Blocking questions

1. Are subgroups a first-class concept or a per-event label?
2. If first-class, is the taxonomy fixed (seeded) or
   administrator-managed?
3. Do reports need to filter or group by subgroup?

---

## 9. Decision 8: Attendance entity and identity

This overlaps with Decision 3 (occurrences) but is listed
separately because it can be answered independently.

### 9.1 Current (CURRENT)

`Attendance.registration_id` is the FK. Church attendance
against non-registrations cannot use this table.

### 9.2 Candidates

**AT1 -- Reuse `Attendance` with `registration_id` only.**
Requires every church attendee to have a `Registration` row.

**AT2 -- Reuse `Attendance` with a nullable
`occurrence_id`.** Running events populate
`registration_id`; church events populate `occurrence_id`.
`Attendance` becomes dual-purpose.

**AT3 -- New `occurrence_attendances` table.** Separate from
running attendance.

- Columns candidate: `id, occurrence_id, participant_id,
attendance_status, recorded_at, recorded_by, notes,
verified_at`.
- Unique on `(occurrence_id, participant_id)`.

**AT4 -- Count-only on occurrences.** `occurrences.present_count`
and `occurrences.absent_count`. No per-person records.

### 9.3 Blocking questions

1. Per-person attendance or count-only?
2. If per-person, is the participant required to be in
   `participants`, or can an attendee be a name only?
3. Is `verified_at` semantics reused, or is church attendance
   unverified by nature?

---

## 10. Decision 9: Reports and aggregation

**Blocks:** Anything that needs new report surfaces.
**Blocked by:** product decision on what reports are required.
**Product scope reference:** doc 02 REP-01 through REP-04,
PND-06.
**Audit reference:** audit section 1.7, section 2.

### 10.1 Current (CURRENT)

`ReportService::build(Event)` returns four sections: `event`,
`summary`, `cross_event`, `participants`. All scoped by
`event_type`. Reachable only when the event is `Ongoing` or
`Completed`.

### 10.2 Candidates

**RP1 -- Extend `ReportService` with category-aware branching.**
Same service, more branches. `cross_event` scopes on category
for church events, on `event_type` for running events.

**RP2 -- A new `ChurchReportService`.** Parallel service.
`ReportController` gains church-specific actions.

**RP3 -- A new `ChurchReportController` and view.** Fully
separate reporting surface.

### 10.3 Blocking questions

1. Which reports are required for the first release?
2. Do church reports reuse the existing report page or a new
   page?
3. Is cross-event comparison required for church events (the
   running report has it; the workflow spec is silent on
   whether church reports need it)?

---

## 11. Decision 10: Event ownership and authorization

**Blocks:** Approval, deletion, editing, monitor actions.
**Blocked by:** product decision on whether ownership gates
actions.
**Product scope reference:** doc 02 USR-02, USR-03.
**Audit reference:** audit section 1.8, audit finding 9.

### 11.1 Current (CURRENT)

No policy exists for any model. `EventRequest::authorize()`
returns `true`. `ensureEditableRegistration()` and
`ensureManageable()` check event status only, not user
identity.

### 11.2 Candidates

**OWN1 -- Introduce a policy layer.** `EventPolicy` and
`RegistrationPolicy`. `Gate::authorize()` in controllers.

- Satisfies: proper authorization for both running and church
  events.
- Cost: touches many controllers. Changes behavior: today, any
  staff can edit any event.

**OWN2 -- Church events only.** Policies for church events;
running events stay as they are.

- Satisfies: narrower change.
- Fails: introduces inconsistency the codebase would then
  carry.

**OWN3 -- Deferred.** No authorization change in the first
release. Documented as a known gap.

### 11.3 Blocking questions

1. Should staff be able to edit events they did not create?
2. Should event creators be able to approve their own
   proposals?
3. Should any change to running-event authorization be part
   of this scope, or is it out?

---

## 12. Cross-cutting impact map

Every candidate above has consequences that reach outside the
model. This section names them.

### 12.1 Migrations required, by candidate

| Candidate  | New migrations                                                                      |
| ---------- | ----------------------------------------------------------------------------------- |
| F1, F4     | None                                                                                |
| F2         | `event_categories`, `event_types`, `add_category_type_to_events`                    |
| F3, F5     | `event_types`, `event_categories`, `seed_types`, `deprecate_event_type_enum`        |
| A1         | None (enum change requires no migration)                                            |
| A2         | Possibly `add_church_status_to_events`                                              |
| A3         | `church_event_stages` (side table) or `add_workflow_stage_to_events`                |
| A4         | None                                                                                |
| D1         | None                                                                                |
| D2         | `event_occurrences`, `add_occurrence_id_to_attendance`                              |
| D3         | `event_occurrences`, `occurrence_attendances`                                       |
| D4         | `event_occurrences`, `add_occurrence_id_to_attendance`                              |
| C1         | `add_approver_role_column` or reuse `role`                                          |
| C2         | None                                                                                |
| C3         | `add_approver_id_to_events`                                                         |
| C4         | None                                                                                |
| E1         | `add_budget_to_events`, `event_attachments`                                         |
| E2         | `budget_line_items`, `event_attachments`                                            |
| E3         | `budget_line_items`, `budget_receipts`, `event_attachments`, reconciliation columns |
| EN1        | None (enum change) plus `add_completed_at_to_registrations`                         |
| EN2, EN3   | `program_enrollments` (and `program_session_attendance`)                            |
| SG1        | `add_subgroup_to_events`                                                            |
| SG2        | `subgroups`, `add_subgroup_id_to_events`                                            |
| SG3        | `event_tags`                                                                        |
| AT1        | None                                                                                |
| AT2, AT4   | `add_occurrence_id_to_attendance`                                                   |
| AT3        | `occurrence_attendances`                                                            |
| RP1        | None                                                                                |
| RP2, RP3   | None                                                                                |
| OWN1, OWN2 | None                                                                                |

### 12.2 Services and controllers affected

Whichever candidates are chosen, some of these will need
modification:

- `EventController::index`, `show`, `create`, `store`, `edit`,
  `update` -- any new event fields.
- `EventWorkflow` -- if status changes.
- `EventDeletionService` -- if new child tables are added
  (cascade coverage).
- `AdvanceEventStatus` -- if occurrence model exists
  (auto-advance per occurrence? per parent?).
- `ReportService` -- if reports extend.
- `AttendanceController::show`, `mark`, `bulkMark`, `walkIn`,
  `bulkWalkIn` -- if attendance model changes.
- `RegistrationController::index` -- if events list changes
  shape.
- `DashboardController` -- if stats change or role-aware logic
  added.
- `RegistrationMonitorController` -- if monitor needs to show
  church events.
- `RegistrationVerificationService` -- if church registration
  reuses it.
- `AppServiceProvider` -- rate limiter additions.
- `bootstrap/app.php` -- middleware alias additions.
- `routes/web.php`, `routes/settings.php` -- new route groups.
- `resources/js/app.tsx` -- layout cases.
- `resources/js/components/app-sidebar.tsx` -- navigation.

### 12.3 Tables that MUST NOT change (CONSTRAINT)

- `users` -- apart from additive columns if a new role is
  introduced.
- `participants` -- apart from potentially being reused by
  church code.
- `registrations`, `registration_field_responses`,
  `registration_fields` -- running-event shape.
- `attendances` -- unless Decision 3 explicitly extends it.
- `registration_verification_challenges` -- public intake is
  stable.
- The base users/cache/jobs migrations -- append-only.

---

## 13. Design-principle constraints

Derived from the scoping prompt, the audit, and existing
conventions. These limit the design space regardless of which
candidates are selected.

**DP-01.** Preserve existing running-event behavior unless an
approved requirement says otherwise.

**DP-02.** Prefer the smallest change that satisfies a
confirmed requirement.

**DP-03.** Reuse existing functionality where it satisfies the
requirement.

**DP-04.** Do not edit existing migrations.

**DP-05.** Do not add a new role unless the role is confirmed
by a product decision.

**DP-06.** Do not add a table unless its requirement is
confirmed.

**DP-07.** Do not introduce polymorphic foreign keys (e.g.,
`attachable_type`/`attachable_id`) unless the polymorphism is
genuinely needed. Doc 01 section 4.2 flags `Attendance` becoming
polymorphic as a risk.

**DP-08.** Do not introduce a second status enum, stage system,
or type system unless the first one is proven insufficient.

**DP-09.** Each new table must have an explicit cascade policy
in `EventDeletionService`.

**DP-10.** Each new field must have a documented fillable and
cast entry. Doc 01 flags model-fillable drift as a recurring
cause of silent bugs.

---

## 14. What this document does not decide

- It does not select a candidate for any decision.
- It does not propose a schema.
- It does not add or modify code, migrations, or config.
- It does not answer the ten blocking questions in section 13
  of doc 02.

---

## 15. Cross-references

- `docs/church-events/01-current-system-audit.md` -- verified
  current state
- `docs/church-events/02-product-scope-and-requirements.md` --
  product scope and blockers
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
  -- to be written; requires decisions 1-10 to be at least
  partially made
- `docs/church-events/05-compatibility-and-test-plan.md` -- to
  be written; scope depends on selected candidates
- `docs/church-events/06-open-questions-and-implementation-roadmap.md`
  -- to be written; consumes the ten decisions as blockers

---

## 16. Decision summary

For quick reference, the ten decisions and their current state:

| #   | Decision                          | Blocking product question                   | Documents affected |
| --- | --------------------------------- | ------------------------------------------- | ------------------ |
| 1   | Category and event type modeling  | Administrator-managed or code-level?        | 04, 05, 06         |
| 2   | Event status and lifecycle        | Ten-stage machine or checklist?             | 04, 05, 06         |
| 3   | Occurrence model                  | Is recurring in scope? Attendance per what? | 04, 05, 06         |
| 4   | Approval workflow                 | Who approves, with what audit?              | 04, 05, 06         |
| 5   | Budget and expenses               | In scope? Which tier?                       | 04, 05, 06         |
| 6   | Enrollment and completion         | Distinct from registration?                 | 04, 05, 06         |
| 7   | Subgroups                         | First-class or label?                       | 04, 05, 06         |
| 8   | Attendance entity and identity    | Per-person or count?                        | 04, 05, 06         |
| 9   | Reports and aggregation           | Which reports, which page?                  | 04, 05, 06         |
| 10  | Event ownership and authorization | Policy change scoped to church, or all?     | 04, 05, 06         |

Each row's answers determine which candidates survive to doc 04.

---

## 17. Changelog

- **2026-10-16** -- File created. Domain model candidates mapped
  for ten pending decisions. No selection made. No code,
  migrations, or configuration changed.
