# WDEMS -- Church Events Feature Specifications and Acceptance Criteria

**Status:** Draft for review. Documentation-only. Feature
specifications below describe required behavior at the
requirement level. Where behavior depends on a schema decision
that is still pending, that dependency is named explicitly, and
candidate-specific acceptance criteria are separated from
requirement-level criteria.

**Audience:** Whoever executes documents 05-06 and whoever
implements the accepted features.

**Purpose:** Turn the confirmed and proposed requirements from
document 02 into behavior, user flows, validation, error states,
permissions, and acceptance criteria. Criteria are written so
they can be turned into automated tests without further design
work where the underlying decision is settled.

**Related:**

- `docs/church-events/01-current-system-audit.md`
- `docs/church-events/02-product-scope-and-requirements.md`
- `docs/church-events/03-domain-model-and-workflows.md`
- The Church Event Management System workflow specification
- The dashboard mockup

**Limitation notice:** Documentation-only. No code, migrations,
enums, or configuration were changed to produce this document.

---

## 0. Reading this document

### 0.1 Feature identifiers

Features are numbered `FS-NN`. Each feature has the same
structure:

- **Requirement source** -- which doc 02 tags it derives from
- **Blocked by** -- which of the ten decisions from doc 03
  section 16 are unresolved
- **Behavior** -- what the feature does, at the requirement
  level
- **User flows** -- the steps a user takes
- **Validation rules** -- what must be true for an action to
  succeed
- **Error states** -- what the user sees on failure
- **Permissions** -- who can do it
- **Acceptance criteria** -- testable statements
- **Not yet testable** -- criteria blocked by pending decisions

### 0.2 Acceptance criteria format

Each criterion is written as a plain assertion. Where a criterion
depends on a schema candidate, it is prefixed `[F2]`, `[D2]`,
etc., matching doc 03's candidate labels. Requirement-level
criteria have no prefix -- they hold regardless of candidate
selection.

Test-ready criteria are those that can be turned into a Pest test
immediately without further design. A criterion marked
`[BLOCKED: <decision>]` cannot be tested until that decision
resolves.

### 0.3 Constraints inherited from the scoping prompt

- No application source code is changed by this document.
- No migrations are proposed here. Schema comes from doc 03.
- No feature below is approved by being specified. Approval
  remains a product decision recorded in doc 06.

---

## 1. Scope of this document

### 1.1 Features specified at the requirement level

- FS-01: Event category and event type administration
- FS-02: Unified event creation and editing
- FS-03: Unified event listing and filtering
- FS-04: Church event detail view
- FS-05: Event proposal and approval
- FS-06: Recurring events and occurrences
- FS-07: Church event registration
- FS-08: Church event attendance
- FS-09: Enrollment and completion tracking
- FS-10: Subgroups
- FS-11: Budget and expenses
- FS-12: Church event reports
- FS-13: Authorization and permissions for church events
- FS-14: Dashboard and navigation extension

### 1.2 What is not specified here

- Exact schema. See doc 03.
- Exact UI layout. Mockup is a reference, not a spec.
- Implementation order. See doc 06.
- Test plan and compatibility matrix. See doc 05.

---

## 2. FS-01 -- Event category and event type administration

**Requirement source:** doc 02 CAT-01 (CONFIRMED), CAT-02
(PENDING), CAT-03 (PENDING), CAT-05 (PROPOSED).

**Blocked by:** Decision 1 (category/type modeling).

### 2.1 Behavior (requirement level)

- The system holds a list of event categories. The confirmed
  list is: Services, Fellowship, Discipleship, Youth, Kids
  Ministry, Young Adult, Worship & Prayer, Special Occasions,
  Church Administration.
- Each category holds one or more event types.
- A user creating a church event selects a category, then an
  event type within it.
- The event type determines which fields, checklists, and
  features are enabled for the event.

### 2.2 Behavior (candidate-specific)

- **F1 / F4:** Categories and types are PHP constants. Admin
  UI does not exist. Adding a type requires a code deploy.
- **F2:** Categories and types are DB rows. Admin UI exists.
  Running-event types (`CommunityRun`, `FunRun`) stay in the PHP
  enum and are not managed via the admin UI.
- **F3 / F5:** All types are DB rows, including running types.
  Admin UI manages everything.

### 2.3 User flows

**Flow 1 -- Administrator adds a category (F2/F3/F5 only):**

1. Administrator opens Categories page.
2. Clicks "Add Category".
3. Enters category name and sort order.
4. Saves.
5. Category appears in the list and in the event-creation
   category dropdown.

**Flow 2 -- Administrator adds an event type (F2/F3/F5 only):**

1. Administrator selects a category.
2. Clicks "Add Event Type".
3. Enters type name, optional feature configuration, and sort
   order.
4. Saves.
5. Type appears under the category and in the event-creation
   type dropdown when that category is selected.

**Flow 3 -- Administrator deactivates a category or type
(F2/F3/F5 only):**

1. Administrator opens the Categories page.
2. Selects a category or type.
3. Clicks "Deactivate".
4. Confirms.
5. Deactivated items no longer appear in the event-creation
   dropdown. Existing events with that category or type remain
   unchanged and display the deactivated name.

### 2.4 Validation rules

- Category name is required, unique within the active set,
  maximum 100 characters.
- Event type name is required, unique within its category,
  maximum 100 characters.
- Sort order is an integer, default 0.
- The "Other" option, when present, requires a custom name.
  Custom name validation: minimum 3 characters, maximum 100
  characters, passes `HumanNameQualityRules`
  (`app/Concerns/HumanNameQualityRules.php`).

### 2.5 Error states

| Condition                                  | Message                                                                                                                          |
| ------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------- |
| Duplicate category name                    | "A category with this name already exists."                                                                                      |
| Duplicate type name in category            | "This category already has a type with this name."                                                                               |
| Empty name                                 | "Name is required."                                                                                                              |
| Invalid sort order                         | "Sort order must be a number."                                                                                                   |
| Deactivating a category with active events | "This category has N active events. Deactivating it will hide it from new events but will not affect existing events. Continue?" |

### 2.6 Permissions

- **F1 / F4:** No admin UI exists. Not applicable.
- **F2 / F3 / F5:** Only `admin` role can manage categories and
  types. Staff cannot access the Categories page. Route is
  behind the existing `admin` middleware alias.

### 2.7 Acceptance criteria

**Requirement-level (testable once a candidate is chosen):**

- AC-01.1. The confirmed nine categories exist and are
  selectable when creating a church event.
- AC-01.2. Selecting a category filters the event-type dropdown
  to that category's types.
- AC-01.3. Every church event has both a category and an event
  type assigned.
- AC-01.4. The event type determines which additional fields
  are shown on the creation form. See FS-02.

**Candidate-specific (F2/F3/F5):**

- AC-01.5 [F2/F3/F5]. An admin can create a new category; it
  immediately appears in the event-creation dropdown.
- AC-01.6 [F2/F3/F5]. An admin can create a new event type
  under a category; it immediately appears in the type dropdown
  for that category.
- AC-01.7 [F2/F3/F5]. A staff user cannot access the Categories
  page.
- AC-01.8 [F2/F3/F5]. Deactivating a category hides it from the
  event-creation dropdown but does not affect existing events.
- AC-01.9 [F2/F3/F5]. Deactivated categories and types still
  render correctly on existing events (name displayed, no
  broken references).

**Candidate-specific (F1/F4):**

- AC-01.10 [F1/F4]. Adding a new category or type requires a
  code change and a deployment.
- AC-01.11 [F1/F4]. The "Other" option (if implemented) stores
  a free-text name on the event.

**Blocked:**

- AC-01.12 [BLOCKED: Decision 1]. The exact set of fields the
  event type controls (which features are enabled per type).

### 2.8 Not yet testable

- Feature configuration per event type. The workflow spec
  mentions "which fields, checklists, registration rules,
  attendance options, and reports are enabled" but does not
  enumerate what a configurable type looks like. Requires
  Decision 1 resolution.

---

## 3. FS-02 -- Unified event creation and editing

**Requirement source:** doc 02 EVT-01 (CONFIRMED), EVT-05
(PROPOSED).

**Blocked by:** Decision 1 (category/type), Decision 2
(lifecycle/status).

### 3.1 Behavior (requirement level)

- Running events and church events are created through the same
  entry point (`/events/create`).
- The user first selects whether the event is a running event or
  a church event.
- If running event: existing behavior (existing `EventType` enum
  selection).
- If church event: user selects category, then event type.
- The selected type determines the additional fields shown.

### 3.2 Behavior (running-event preservation)

The existing event-creation wizard (`resources/js/pages/events/
create.tsx` and its step components) must continue to work
unchanged for running events. No new fields are added to the
running-event path.

### 3.3 Church event fields

Common fields shared with running events:

- Event name
- Description
- Date, start time, end time
- Venue, venue address

Church-specific fields (PROPOSED, from workflow spec section
1.2):

- Category
- Event type
- Purpose and objectives
- Expected outcomes
- Target participant group
- Expected participant count
- Event head / organizer
- Assigned committees
- Registration / enrollment settings (conditional on FS-07)
- Attendance settings (conditional on FS-08)
- Budget (conditional on FS-11)

Fields NOT applicable to church events (must not be shown):

- `distance_value` and `distance_unit`
- The eight accessibility booleans
- `partners`
- `course_url` (removed from the codebase in commit 6173540)

### 3.4 User flows

**Flow 1 -- Create a running event:**

1. User visits `/events/create`.
2. Selects "Running Event".
3. Existing wizard proceeds unchanged.

**Flow 2 -- Create a church event:**

1. User visits `/events/create`.
2. Selects "Church Event".
3. Selects category from dropdown.
4. Selects event type from dropdown (filtered by category).
5. Enters event name, description, date, time, venue.
6. Enters purpose, objectives, target participants, expected
   count, event head.
7. Toggles registration on/off (if applicable to the type).
8. Toggles attendance on/off (if applicable to the type).
9. Saves as draft or submits proposal (depending on Decision 2).

### 3.5 Validation rules

- `event_name`: existing `eventNameRules()` (min 3, max 100,
  `HumanNameQualityRules`).
- `category`: required for church events, must exist.
- `event_type`: required for church events, must belong to the
  selected category.
- `event_date`: existing rule
  (`after_or_equal:today`, `before:+2 years`).
- `start_time`: existing rule (required, `date_format:H:i`).
- `end_time`: existing rule (nullable, `after:start_time`,
  minimum duration 60 minutes when present).
- `venue`: existing rule (min 3, max 255).
- `venue_address`: existing rule (min 5, max 500).
- Purpose, objectives, target participants, event head: optional
  for draft, required for proposal submission (per workflow spec
  Stage 1).
- Expected participant count: integer, minimum 1, maximum 9999.

### 3.6 Error states

| Condition                          | Message                                                  |
| ---------------------------------- | -------------------------------------------------------- |
| No category selected               | "Please select a category."                              |
| No type selected                   | "Please select an event type."                           |
| Type does not belong to category   | "The selected type does not belong to this category."    |
| Invalid date                       | Existing messages from `eventMessages()`                 |
| Invalid time range                 | Existing messages from `eventMessages()`                 |
| Required field missing on proposal | "This field is required before submitting the proposal." |

### 3.7 Permissions

- Any authenticated user (`staff` or `admin`) can create events.
- Per doc 01 section 1.8, no ownership check exists today.
  Whether church events introduce one is Decision 10.

### 3.8 Acceptance criteria

**Requirement-level:**

- AC-02.1. `/events/create` offers a choice between running and
  church event.
- AC-02.2. Selecting "Running Event" enters the existing wizard
  unchanged. All existing running-event creation tests still
  pass.
- AC-02.3. Selecting "Church Event" shows the category dropdown.
- AC-02.4. Selecting a category filters the event-type dropdown
  to that category's types.
- AC-02.5. A church event created through this flow has a null
  `distance_value`, `distance_unit`, and null accessibility
  flags.
- AC-02.6. `canEdit()` on the created church event returns the
  expected value for its status (behavior depends on Decision
  2).
- AC-02.7. The created church event appears in `/events`.

**Running-event preservation:**

- AC-02.8. Every existing event-creation test in
  `tests/Feature/Events/` still passes.
- AC-02.9. Existing validation rules
  (`app/Concerns/EventValidationRules.php::eventRules()`) still
  reject invalid running events.
- AC-02.10. Existing wizard step components render unchanged for
  running events.

**Blocked:**

- AC-02.11 [BLOCKED: Decision 2]. Whether saving a church event
  produces status `Draft` or `Proposed`.
- AC-02.12 [BLOCKED: Decision 4]. Whether submission triggers an
  approval workflow.

---

## 4. FS-03 -- Unified event listing and filtering

**Requirement source:** doc 02 NAV-01, NAV-02 (CONFIRMED).

### 4.1 Behavior

- `/events` lists all events regardless of kind.
- Each row displays category and event type for church events,
  and running type for running events.
- Filtering options include: kind (running/church), category,
  status, date range, organizer.

### 4.2 User flows

1. User visits `/events`.
2. Sees all events. Can filter by kind, category, status, date.
3. Clicking a row opens the event detail (FS-04).

### 4.3 Validation rules

Filter values must be from the allowed set. Invalid filter
values fall back to "all".

### 4.4 Error states

No user-facing errors. Invalid filter values are ignored.

### 4.5 Permissions

Any authenticated user sees the same list today (doc 01 ISSUE-001
and ISSUE-002). Whether this changes is Decision 10.

### 4.6 Acceptance criteria

- AC-03.1. `/events` shows both running and church events.
- AC-03.2. Each row shows a readable category and type.
- AC-03.3. Filtering by kind shows only running or only church
  events.
- AC-03.4. Filtering by category shows only that category's
  events.
- AC-03.5. Filtering by status shows only events in that status.
- AC-03.6. Filter state persists in the URL query string (for
  shareable links).
- AC-03.7. Empty state renders when no events match.

---

## 5. FS-04 -- Church event detail view

**Requirement source:** doc 02 EVT-01 (CONFIRMED), NAV-05
(PROPOSED).

### 5.1 Behavior

- `/events/{event}` renders the detail page.
- For running events: unchanged.
- For church events: shows category, type, purpose, objectives,
  target participants, event head, assigned committees, and
  stage-appropriate actions.
- Running-only sections (distance, accessibility) do not render
  for church events.

### 5.2 User flows

1. User opens a church event.
2. Sees event summary: name, category, type, dates, venue,
   organizer, status.
3. Sees stage-appropriate actions (e.g., Submit Proposal,
   Mark Ready, Start Event, End Event, Mark Completed).
4. Sees related records: registrations, attendance, documents.

### 5.3 Validation rules

Not applicable to viewing.

### 5.4 Error states

- Event not found: 404 (existing behavior).
- Event is draft and user lacks permission: existing
  `canEdit()` gate applies.

### 5.5 Permissions

Viewing is allowed to any authenticated user today. Edit
permission depends on Decision 10.

### 5.6 Acceptance criteria

- AC-04.1. Church event detail renders without crashing.
- AC-04.2. `distanceLabel()` returns null and the distance row
  is hidden.
- AC-04.3. Accessibility flags are hidden for church events.
- AC-04.4. Stage-appropriate actions are shown (depends on
  Decision 2).
- AC-04.5. Running-event detail page renders unchanged.

---

## 6. FS-05 -- Event proposal and approval

**Requirement source:** doc 02 APR-01 through APR-05 (PENDING),
PND-02, PND-04.

**Blocked by:** Decision 2, Decision 4.

### 6.1 Behavior (candidate-level)

- **If Decision 2 resolves to A2/A3 (stage concept):** a
  church event moves through stages. Approval is a stage
  transition. The event's `status` remains `Draft` or the
  equivalent running status.
- **If Decision 2 resolves to A1 (extend EventStatus):**
  approval is a status change (`Under Review` -> `Approved`).

### 6.2 User flows

**Flow 1 -- Submit a proposal:**

1. Organizer creates a church event, saves as draft.
2. Reviews event details.
3. Clicks "Submit Proposal".
4. Event moves to a pending-review state.

**Flow 2 -- Approve a proposal (approver only):**

1. Approver opens the pending event.
2. Reviews details.
3. Clicks "Approve" or "Request Revision" or "Reject".
4. Event moves to the corresponding state.

**Flow 3 -- Revise a proposal:**

1. Organizer receives "Revision Required".
2. Edits event details.
3. Resubmits.

### 6.3 Validation rules

- Approval requires the approver's identity (per Decision 4).
- Revision requires a reason (per workflow spec).
- Rejection requires a reason (per workflow spec).

### 6.4 Error states

| Condition                           | Message                                             |
| ----------------------------------- | --------------------------------------------------- |
| Non-approver attempts approval      | 403                                                 |
| Approving an already-approved event | "This event is already approved."                   |
| Revision without reason             | "Please provide a reason for the revision request." |
| Rejection without reason            | "Please provide a reason for rejection."            |

### 6.5 Permissions

Depends on Decision 4 (C1/C2/C3/C4). No permission is
presupposed.

### 6.6 Acceptance criteria

**Requirement-level:**

- AC-05.1. A church event in the proposal state can be
  submitted for review.
- AC-05.2. The submit action is available only to the creator
  or an admin (subject to Decision 4).
- AC-05.3. The submit action moves the event out of the draft
  state.
- AC-05.4. Revision and rejection require a reason.
- AC-05.5. The reason is recorded and displayed.

**Candidate-specific:**

- AC-05.6 [A1]. Approval changes `EventStatus`.
- AC-05.7 [A2/A3]. Approval changes a stage field or stage
  record; `EventStatus` is unchanged.
- AC-05.8 [C1]. Only users with the `approver` role can
  approve.
- AC-05.9 [C2]. Only admins can approve.
- AC-05.10 [C3]. Only the event's assigned approver or an
  admin can approve.
- AC-05.11 [C4]. Approval is a checklist item; no separate
  state exists.

**Blocked:**

- AC-05.12 [BLOCKED: Decision 4]. The exact set of actors who
  may approve.

---

## 7. FS-06 -- Recurring events and occurrences

**Requirement source:** doc 02 EVT-05, EVT-06, REC-01 through
REC-04, PND-03, PND-10.

**Blocked by:** Decision 3.

### 7.1 Behavior (candidate-level)

- **If D1:** no occurrences. Each date is a separate event.
- **If D2/D3/D4:** a recurring event has a schedule. Occurrences
  are generated from the schedule. Attendance attaches to
  occurrences.

### 7.2 User flows

**Flow 1 -- Create a recurring event (D2/D3/D4):**

1. User creates a church event.
2. Selects "Recurring".
3. Sets schedule (e.g., weekly, day, time).
4. Sets end condition (date, number of occurrences, or none).
5. Saves.
6. System generates occurrence rows.

**Flow 2 -- View occurrences:**

1. User opens a recurring event.
2. Sees a list of occurrences with dates and statuses.
3. Clicks an occurrence to see attendance for that date.

**Flow 3 -- Cancel an individual occurrence (D2/D3/D4):**

1. User opens the occurrence.
2. Clicks "Cancel this occurrence".
3. Provides a reason.
4. Occurrence is marked cancelled; the series continues.

### 7.3 Validation rules

- Schedule definition required if recurring.
- End date must be after start date.
- Number of occurrences must be positive if that end condition
  is chosen.
- Individual occurrence cancellation requires a reason.

### 7.4 Error states

| Condition                         | Message                                      |
| --------------------------------- | -------------------------------------------- |
| Invalid schedule                  | "The recurrence pattern is not valid."       |
| End before start                  | "End date must be after start date."         |
| Cancelling a past occurrence      | "This occurrence has already occurred."      |
| Duplicate occurrence on same date | "An occurrence already exists on this date." |

### 7.5 Permissions

Event creator or admin. See Decision 10.

### 7.6 Acceptance criteria

- AC-06.1 [D2/D3/D4]. Creating a recurring event generates the
  expected occurrences.
- AC-06.2 [D2/D3/D4]. Each occurrence has an independent status,
  date, and attendance.
- AC-06.3 [D2/D3/D4]. Cancelling one occurrence does not affect
  the series.
- AC-06.4 [D2/D3/D4]. Reports show both per-occurrence and
  aggregate figures.
- AC-06.5 [D1]. Recurring requires manually creating each event.
- AC-06.6 [BLOCKED: Decision 3]. Whether recurring is in scope
  for the first release.

---

## 8. FS-07 -- Church event registration

**Requirement source:** doc 02 REG-01 (CONFIRMED), REG-02,
REG-03, REG-04, REG-05 (PENDING).

**Blocked by:** Decision 3 (attendance entity), Decision 6
(enrollment), Decision 8 (attendance identity).

### 8.1 Behavior

- Registration availability is per event type (doc 02 REG-04).
- If registration is enabled, the event has a public
  registration URL (reuse `/r/{slug}` if applicable).
- If registration is disabled, no public surface exists; the
  event uses attendance-only (e.g., a Sunday service).

### 8.2 User flows

**Flow 1 -- Enable registration on a church event:**

1. Organizer toggles registration on.
2. Configures common fields (reuse existing
   `registration_common_field_requirements`).
3. Publishes the registration URL.
4. Participants register through the existing flow or a
   simplified flow (per Decision 8).

**Flow 2 -- Register a participant:**
Existing public registration flow, or a new flow.

### 8.3 Validation rules

If reusing the existing flow: unchanged.
If a new flow: TBD pending Decision 8.

### 8.4 Error states

Existing errors apply if reused.

### 8.5 Permissions

Public registration is anonymous in the existing model.
Organizer actions require authentication.

### 8.6 Acceptance criteria

- AC-07.1. A church event can have registration enabled or
  disabled independently.
- AC-07.2. If enabled, participants can register through a
  public URL.
- AC-07.3. Registered participants appear in the event's
  participant list.
- AC-07.4. If disabled, no public registration URL exists for
  the event.
- AC-07.5 [BLOCKED: Decision 8]. Whether contact verification
  is required.
- AC-07.6 [BLOCKED: Decision 8]. Whether registration reuses
  the existing `/r/{slug}` flow.
- AC-07.7 [BLOCKED: Decision 6]. Whether enrollment is a
  separate concept from registration.

---

## 9. FS-08 -- Church event attendance

**Requirement source:** doc 02 ATT-01 (CONFIRMED), ATT-02
through ATT-06 (PENDING/PROPOSED).

**Blocked by:** Decision 3 (occurrences), Decision 8 (identity).

### 9.1 Behavior (candidate-level)

- **If AT1:** attendance attaches to registrations. Requires
  every attendee to have a registration.
- **If AT2/AT3:** attendance attaches to occurrences.
- **If AT4:** count-only, no per-person records.

### 9.2 User flows

**Flow 1 -- Record attendance for a service (AT2/AT3):**

1. Staff opens the service for a specific date (occurrence).
2. Sees a list of expected attendees (church members, or
   registered participants).
3. Marks each person present or absent.
4. Saves.

**Flow 2 -- Record attendance with walk-ins:**

1. Staff adds a walk-in by name (or by participant lookup).
2. Marks them present.
3. Saves.

### 9.3 Validation rules

- One attendance record per participant per occurrence (per
  workflow spec section 2.2).
- Duplicate attendance for the same participant and occurrence
  is rejected.
- If a church member list exists, attendance requires the
  member to be on the list (unless walk-ins are enabled).

### 9.4 Error states

| Condition                 | Message                                             |
| ------------------------- | --------------------------------------------------- |
| Duplicate attendance      | "Attendance already recorded for this participant." |
| Unknown participant       | "This person is not on the expected list."          |
| No occurrence selected    | "Please select an occurrence."                      |
| Attendance already closed | "Attendance for this occurrence is closed."         |

### 9.5 Permissions

Any authenticated staff or admin. Per Decision 10.

### 9.6 Acceptance criteria

**Requirement-level:**

- AC-08.1. Attendance for a service can be recorded by
  date/occurrence.
- AC-08.2. A participant appears at most once per occurrence.
- AC-08.3. Attendance history for a participant is viewable.
- AC-08.4. Attendance can be corrected within a defined window
  (existing running-event window logic applies if reused).

**Candidate-specific:**

- AC-08.5 [AT2/AT3]. Each occurrence has an independent
  attendance count.
- AC-08.6 [AT3]. Church attendance is separate from running
  attendance in the data model.
- AC-08.7 [AT4]. Attendance is count-only; no participant-level
  records.

**Blocked:**

- AC-08.8 [BLOCKED: Decision 3]. Whether occurrences exist.
- AC-08.9 [BLOCKED: Decision 8]. Whether attendees are
  identified by `participants`, members, or counts.

---

## 10. FS-09 -- Enrollment and completion tracking

**Requirement source:** doc 02 REG-05 (PENDING), PND-08.

**Blocked by:** Decision 6.

### 10.1 Behavior (candidate-level)

- **If EN1:** `Registration` extended with enrollment statuses.
- **If EN2:** new `program_enrollments` table.
- **If EN3:** enrollment plus per-session attendance.

### 10.2 User flows

**Flow 1 -- Enroll a participant:**

1. Organizer opens a Discipleship event.
2. Clicks "Enroll Participant".
3. Selects an existing participant or creates one.
4. Enrollment record created with status "Enrolled".

**Flow 2 -- Track progress:**

1. After each session, staff records attendance.
2. Enrollment status updates based on attendance count against
   required sessions.

**Flow 3 -- Mark completion:**

1. Program reaches completion criteria.
2. Organizer marks participant "Completed".
3. Completion date recorded.

### 10.3 Validation rules

- Completion criteria must be defined before enrollment (per
  workflow spec section 3.3).
- Participant cannot be enrolled twice in the same program.
- Completion status is one of: Enrolled, In Progress, Completed,
  Withdrawn, Incomplete.

### 10.4 Error states

| Condition                   | Message                                        |
| --------------------------- | ---------------------------------------------- |
| Duplicate enrollment        | "This participant is already enrolled."        |
| Missing completion criteria | "Define completion criteria before enrolling." |
| Invalid status transition   | "Cannot transition from {from} to {to}."       |

### 10.5 Permissions

Any authenticated staff or admin. Per Decision 10.

### 10.6 Acceptance criteria

- AC-09.1 [EN1/EN2/EN3]. Participants can be enrolled.
- AC-09.2 [EN1/EN2/EN3]. Progress is tracked per participant.
- AC-09.3 [EN1/EN2/EN3]. Completion status is recorded
  independently of the parent event's completion.
- AC-09.4 [EN1/EN2/EN3]. A completed event may still have
  participants "In Progress" if the program spans multiple
  cohorts.
- AC-09.5 [BLOCKED: Decision 6]. Whether enrollment is distinct
  from registration.

---

## 11. FS-10 -- Subgroups

**Requirement source:** doc 02 CAT-04 (PENDING), PND-09.

**Blocked by:** Decision 7.

### 11.1 Behavior (candidate-level)

- **If SG1:** a text field on the event.
- **If SG2:** a `subgroups` table referenced by the event.
- **If SG3:** polymorphic event tags.

### 11.2 User flows

**Flow 1 -- Assign a subgroup (SG1 or SG2):**

1. Organizer selects a subgroup for the event.
2. The subgroup is stored and displayed.

**Flow 2 -- Manage subgroups (SG2, admin only):**

1. Admin opens the Subgroups page (if it exists).
2. Adds, renames, deactivates subgroups within a category.

### 11.3 Validation rules

- If SG1: free text, max 100 chars.
- If SG2: must exist, must belong to the event's category.
- If SG3: any string.

### 11.4 Error states

| Condition                      | Message                                                   |
| ------------------------------ | --------------------------------------------------------- |
| Subgroup not in category (SG2) | "The selected subgroup does not belong to this category." |
| Empty subgroup (SG1)           | "Please enter a subgroup."                                |

### 11.5 Permissions

Determined by Decision 7 implementation.

### 11.6 Acceptance criteria

- AC-10.1. A church event can have a subgroup assigned.
- AC-10.2. Reports can filter or group by subgroup.
- AC-10.3 [SG2]. Subgroups are administrator-managed.

---

## 12. FS-11 -- Budget and expenses

**Requirement source:** doc 02 BUD-01 through BUD-03 (PENDING),
PND-05.

**Blocked by:** Decision 5.

### 12.1 Behavior (candidate-level)

- **If E1:** a budget amount and notes on the event, plus a PDF
  upload for reconciliation.
- **If E2:** line items plus a computed total.
- **If E3:** line items, receipts, reconciliation status,
  approval.

### 12.2 User flows

**Flow 1 -- Enter budget (E1/E2/E3):**

1. Organizer opens the event's Budget tab.
2. Enters amount and notes.
3. Saves.

**Flow 2 -- Submit liquidation (E1/E2/E3):**

1. After the event, organizer opens Budget tab.
2. Uploads the reconciliation document.
3. Records actual expenses (per candidate).

### 12.3 Validation rules

- Amount: numeric, minimum 0, maximum 9999999.99.
- Notes: max 2000 chars.
- Line items (E2/E3): category required, amount numeric.

### 12.4 Error states

| Condition            | Message                                     |
| -------------------- | ------------------------------------------- |
| Negative amount      | "Amount cannot be negative."                |
| Attachment too large | "File exceeds the maximum size of {limit}." |
| Invalid file type    | "Only PDF, JPG, or PNG files are accepted." |

### 12.5 Permissions

Organizer or admin. See Decision 10.

### 12.6 Acceptance criteria

- AC-11.1. A church event can have a budget recorded.
- AC-11.2. Actual expenses can be recorded.
- AC-11.3. Reconciliation document can be uploaded.
- AC-11.4 [E2/E3]. Line items sum to a computed total.
- AC-11.5 [BLOCKED: Decision 5]. Which tier is in scope.

---

## 13. FS-12 -- Church event reports

**Requirement source:** doc 02 REP-01 through REP-04 (PENDING),
PND-06.

**Blocked by:** Decision 3, Decision 9.

### 13.1 Behavior

- Church events have reports analogous to running events.
- Reports include: attendance totals, attendance history per
  participant, comparison across service types, missing-
  occurrence reports, per-group summaries.

### 13.2 User flows

1. User opens a church event.
2. Clicks "View Report".
3. Sees summary, cross-event comparison, participant details
   (mirroring the running-event report structure).

### 13.3 Validation rules

- Reports are viewable only when the event is in a reportable
  state (analogous to `ReportController::assertReportable()`).

### 13.4 Error states

- Non-reportable state: 404 (mirroring existing behavior).

### 13.5 Permissions

Any authenticated user today. Whether this changes is Decision 10.

### 13.6 Acceptance criteria

- AC-12.1. Reports render for church events in reportable
  states.
- AC-12.2. Summary figures match underlying data.
- AC-12.3. Cross-event comparison scopes correctly (by category
  for church events).
- AC-12.4. CSV export produces a valid file.
- AC-12.5 [BLOCKED: Decision 9]. Whether reports extend
  `ReportService` or a new service.

---

## 14. FS-13 -- Authorization and permissions

**Requirement source:** doc 02 USR-02, USR-03 (PENDING), NFR-04
(PROPOSED).

**Blocked by:** Decision 10.

### 14.1 Behavior

- Authorization is enforced on all event, registration, and
  report actions.
- The policy layer (if introduced) covers both running and
  church events consistently, or only church events, depending
  on Decision 10.

### 14.2 User flows

Standard flows. No new user-facing UI.

### 14.3 Validation rules

Every controller action that mutates or reads an event,
registration, or report must pass an authorization check.

### 14.4 Error states

Unauthorized access returns 403. This is already how
`AdminMiddleware` behaves.

### 14.5 Permissions

Defined by Decision 10.

### 14.6 Acceptance criteria

- AC-13.1 [OWN1/OWN2]. Unauthorized users receive 403 for
  actions they are not permitted to perform.
- AC-13.2 [OWN1/OWN2]. Authorized users can perform permitted
  actions.
- AC-13.3 [OWN1]. Running-event authorization also changes.
- AC-13.4 [OWN2]. Running-event authorization unchanged.
- AC-13.5 [BLOCKED: Decision 10]. Which ownership model
  applies.

---

## 15. FS-14 -- Dashboard and navigation extension

**Requirement source:** doc 02 NAV-01 through NAV-10.

**Blocked by:** Decision 1 (Categories entry), Decision 2
(status pills).

### 15.1 Behavior

- Sidebar gains a Categories entry (if Decision 1 resolves to
  F2/F3/F5).
- Dashboard stat widgets aggregate across all event kinds
  (pending Decision on exact scope).
- The Church Events Categories panel (from mockup) shows
  categories with per-category counts.
- Quick actions link to Create Event, Manage Registrations,
  Record Attendance, View Reports.

### 15.2 User flows

1. User logs in.
2. Dashboard shows stats, upcoming events, recent events,
   calendar.
3. Sidebar shows Dashboard, Events, Registration, Attendance,
   and (if F2/F3/F5) Categories.

### 15.3 Validation rules

Not applicable to rendering.

### 15.4 Error states

Not applicable.

### 15.5 Permissions

Sidebar visibility per Decision 10 and NAV-08.

### 15.6 Acceptance criteria

- AC-14.1. Dashboard renders with the unified layout.
- AC-14.2. Stats aggregate correctly (scope per Decision 2 of
  doc 02's PENDING items).
- AC-14.3 [F2/F3/F5]. Categories entry visible to admins.
- AC-14.4. Existing running-event users see no regression in
  the dashboard.
- AC-14.5. Quick actions route to the correct pages.

---

## 16. Running-event preservation criteria

These criteria apply regardless of which candidates are chosen
for the ten decisions. They must pass after every implementation
phase.

- PRES-AC-01. Every existing test in `tests/Feature/Events/`
  passes.
- PRES-AC-02. Every existing test in `tests/Feature/Public/`
  passes.
- PRES-AC-03. Every existing test in `tests/Feature/Auth/`
  passes.
- PRES-AC-04. Every existing test in `tests/Feature/Participants/`
  passes.
- PRES-AC-05. Every existing test in `tests/Feature/Settings/`
  passes.
- PRES-AC-06. Existing migrations run without error on a fresh
  database.
- PRES-AC-07. `php artisan route:list` shows all existing routes
  unchanged.
- PRES-AC-08. The running-event public registration flow works
  end-to-end.
- PRES-AC-09. The running-event attendance flow works
  end-to-end.
- PRES-AC-10. The running-event report renders correctly.
- PRES-AC-11. Fun Run behavior is unchanged. (Pending doc 01
  section B verification.)

---

## 17. Criteria ready to test now

Criteria that can be turned into Pest tests immediately without
any decision:

- PRES-AC-01 through PRES-AC-11 (existing behavior must not
  break).
- AC-02.1, AC-02.2 (running path unchanged; church path adds a
  branch).
- AC-03.1 through AC-03.7 (listing).
- AC-04.1 through AC-04.5 (detail view).
- AC-14.1, AC-14.4 (dashboard unchanged).

Everything else is either candidate-specific or blocked by a
pending decision.

---

## 18. Criteria blocked by pending decisions

For traceability, the criteria that cannot be tested until each
decision resolves.

| Decision | Blocking criteria                                         |
| -------- | --------------------------------------------------------- |
| 1        | AC-01.5 through AC-01.12, AC-14.3                         |
| 2        | AC-02.11, AC-02.12, AC-04.4, AC-05.6, AC-05.7, AC-14.2    |
| 3        | AC-06.1 through AC-06.6, AC-08.5 through AC-08.9, AC-12.5 |
| 4        | AC-02.12, AC-05.1 through AC-05.12                        |
| 5        | AC-11.1 through AC-11.5                                   |
| 6        | AC-07.7, AC-09.1 through AC-09.5                          |
| 7        | AC-10.1 through AC-10.3                                   |
| 8        | AC-07.5, AC-07.6, AC-08.9                                 |
| 9        | AC-12.5                                                   |
| 10       | AC-13.1 through AC-13.5                                   |

---

## 19. Cross-cutting concerns

### 19.1 Validation consistency

Every new validation rule that duplicates an existing rule (e.g.,
name quality, phone quality) must reuse the existing trait.
Doc 01 flags `HumanNameQualityRules` and
`ContactAndAddressQualityRules` as shared.

### 19.2 Rate limiters

Any new public endpoint must have a rate limiter registered in
`AppServiceProvider`. Any new authenticated write endpoint
should have one unless justified.

### 19.3 Error message conventions

Existing conventions:

- Validation errors flash on the field key.
- Authorization failures abort with 403.
- Missing resources abort with 404.
- Transport failures return generic messages; never leak
  provider details.

### 19.4 Logging

Log lines must not include PII, OTP codes, tokens, or contact
values. Follow the pattern in
`RegistrationVerificationService::publicAction()`.

### 19.5 Read-only state

Completed events (running) become read-only. Church events
should follow the same convention. See NFR-05 (PROPOSED).

---

## 20. What this document does not decide

- It does not select a candidate for any of the ten decisions.
- It does not propose a schema.
- It does not add or modify code, migrations, or config.
- It does not approve any feature.

---

## 21. Changelog

- **2026-10-16** -- File created. Feature specifications and
  acceptance criteria recorded for fourteen features. Test-ready
  criteria separated from blocked criteria. No selection made;
  no code, migrations, or configuration changed.
