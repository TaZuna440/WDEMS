# WDEMS -- Church Events Scope Revision and Task Division

**Status:** Draft for review. Documentation-only. This document
records the scope shift that occurred after the initial five
documents were written, divides the work into workstreams and
phases, and lists the client decisions and developer
verifications that gate implementation.

**Audience:** The developer and the client, for planning and
sequencing.

**Purpose:** Reconcile the current client intent (as expressed
in the workflow spec, the mockups, the "What changed" note, and
the Discipleship feature spec) with the earlier documentation
set (docs 01-06). Establish the top-level module inventory.
Divide the work into phases. Name what blocks each phase.

**Related:**

- `docs/church-events/01-current-system-audit.md`
- `docs/church-events/02-product-scope-and-requirements.md`
- `docs/church-events/03-domain-model-and-workflows.md`
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
- `docs/church-events/05-compatibility-and-test-plan.md`
- `docs/church-events/06-open-questions-and-implementation-roadmap.md`
- Client mockups: Services, Fellowship, Discipleship, Youth
- Client notes: "What changed", "Feature Discipleship Management
  System"

**Limitation notice:** The auditor has no filesystem access. No
code, migrations, enums, tests, or configuration were changed to
produce this document. Every phase below is a proposal, not an
approval.

---

## 0. Purpose and relationship to docs 01-06

Docs 01-06 were written on 2026-10-16 morning, based on the
Church Event Management workflow specification and a single
dashboard mockup. They assumed:

- One parent module ("Church Events").
- Nine categories under that module.
- One five-stage workflow with conditional fields.
- Two event kinds total (running, church).

The client has since provided additional inputs. These inputs
contradict the "one module, one workflow" premise:

- The "What changed" note splits Services and Fellowship into
  distinct workflows.
- The Discipleship feature spec describes a program + batch +
  session + enrollment + progress domain that is not an event.
- The Youth mockup shows five distinct sub-modules.
- The Services and Fellowship mockups show top-level sidebar
  entries with their own navigation.
- Members and First-Timers appear as first-class concepts in
  the sidebar.

Docs 01-06 are therefore a correct snapshot of an earlier scope,
not a correct description of the current one. This document
records the delta. It does not rewrite docs 01-06 (append-only
convention, P7).

---

## 1. What the current client intent describes

### 1.1 Top-level module inventory

Eleven modules are identified across the mockups, the "What
changed" note, and the Discipleship spec.

| #   | Module                                  | Confirmed by                                      | Docs 01-06 coverage           |
| --- | --------------------------------------- | ------------------------------------------------- | ----------------------------- |
| M1  | Members                                 | Sidebar mockups (Discipleship, Youth, Fellowship) | None                          |
| M2  | First-Timers                            | Sidebar mockups + "What changed"                  | None                          |
| M3  | Services                                | Services mockups                                  | As "category" only            |
| M4  | Fellowship                              | Fellowship mockups + Tagalog note                 | As "category" only            |
| M5  | Discipleship                            | Full spec (A-G) + mockup                          | As "category" only            |
| M6  | Youth                                   | Youth mockup set                                  | As "category" only            |
| M7  | Cell Group                              | Sidebar in Youth mockup                           | None                          |
| M8  | Events (general, unified)               | Both                                              | Overlapping (EventController) |
| M9  | Attendance (cross-module)               | Sidebar dropdown                                  | Partial                       |
| M10 | Reports (cross-module)                  | Sidebar dropdown                                  | Partial                       |
| M11 | Running events (Community Run, Fun Run) | Existing WDEMS                                    | Full                          |

Net-new modules: M1, M2, M7, and the sub-modules under M6.
Reworked from "category" to "module": M3, M4, M5, M6.
Extensions or cross-cutting: M8, M9, M10.

### 1.2 The four distinct workflows

The mockups and specs describe four workflows, not one:

**Services workflow:**

- Parent: service type (Morning, Midweek, Youth, Other).
- Children: occurrences (one per service date).
- Each occurrence carries two attendance tracks:
  Members and First-Timers.
- Attendance fields differ per track.
- Members: Name, Age, Address, Birthday, Contact Number,
  Cell Leader.
- First-Timers: Name, Age, Address, Birthday, Contact Number,
  Invited By.

**Fellowship workflow:**

- Parent: fellowship type (Family, Church, Pastors, Leaders,
  Womens, Mens).
- Children: fellowship events (one per gathering).
- Each event defines its own participant groups (Parents,
  Youth, Children, Other).
- Participants are registered, assigned to a group, marked
  present or absent.
- Group totals and overall event totals are computed.

**Discipleship workflow:**

- Parent: program (Life Class, Encounter God Retreat,
  School of Leaders 1-3, Cell Convergence).
- Children: batches per program.
- Grandchildren: sessions per batch.
- Enrollment records link participants to programs and batches.
- Attendance per session.
- Progress per participant (attended / applicable / percentage).
- Completion per participant, independent of parent event
  completion.

**Youth workflow:**

- Parent: youth ministry.
- Children: five sub-modules.
- Youth Camp: registration, payment tracking, per-day
  attendance, guardian contact for minors.
- Youth Service: schedule + per-occurrence attendance.
- Huddle: small group + attendance.
- Campus Ministry: high school (Grade 7-12) and college
  (1st-4th year) subgroups.
- Timothy Class: enrollment + progress.

### 1.3 Concepts that do not exist in docs 01-06

These are new domain concepts, confirmed by the mockups:

- Members as a first-class entity with a sidebar entry.
- First-Timers as a first-class entity with a sidebar entry
  and its own attendance track.
- Batches (Discipleship-specific).
- Program sessions (Discipleship-specific, distinct from
  Service occurrences).
- Youth sub-modules with their own mockups.
- Cell Group as a module or sub-module.
- Per-session progress percentage (Discipleship).
- Attendance tracking with payment status (Youth Camp).

### 1.4 Where docs 01-06 still apply

- Running-event preservation (PRES-01 through PRES-04) --
  unchanged.
- Participant identity model -- reused, extended.
- Migration safety rules (doc 05 section 6) -- apply.
- Preservation baseline (doc 05 section 2) -- applies.
- Risk register (doc 05 section 8) -- applies.

### 1.5 What this document does not do

- It does not select a schema for Members, First-Timers,
  Services, Fellowship, Discipleship, Youth, or Cell Group.
- It does not approve any phase.
- It does not modify docs 01-06.

---

## 2. Division of work

The work can be divided along four axes.

### 2.1 Axis A -- by module (vertical slices)

Good for parallel work, client demos, incremental delivery.

    Workstream 1: Members + First-Timers      (M1, M2)
    Workstream 2: Services                    (M3)
    Workstream 3: Fellowship                  (M4)
    Workstream 4: Discipleship                (M5)
    Workstream 5: Youth + sub-modules         (M6, M7)
    Workstream 6: General Events              (M8)
    Workstream 7: Cross-module                (M9, M10)
    Workstream 8: Preservation                (M11)

### 2.2 Axis B -- by layer (horizontal)

Good for estimating and sequencing infrastructure.

    Layer 1: Database migrations
    Layer 2: Models and enums
    Layer 3: FormRequests and validation
    Layer 4: Services (business logic)
    Layer 5: Controllers and routes
    Layer 6: React pages and components
    Layer 7: Tests

### 2.3 Axis C -- by phase (temporal, dependency-ordered)

Good for actual scheduling. Used as the organizing principle in
section 3.

### 2.4 Axis D -- by decision dependency

Good for knowing what can proceed now versus what waits on
client input.

---

## 3. Phases

Each phase is independently shippable, independently
revertible, and preserves the running-event suite as a hard gate.

### 3.1 Phase 0 -- Client sign-off and verification

**Blocks:** everything.

**Tasks:**

| #    | Task                                                                                      | Owner     | Blocks  |
| ---- | ----------------------------------------------------------------------------------------- | --------- | ------- |
| 0.1  | Confirm the four-workflow architecture supersedes the "one workflow" premise.             | Client    | Phase 1 |
| 0.2  | Confirm the eleven-module inventory (section 1.1) is complete and correct.                | Client    | Phase 1 |
| 0.3  | Confirm Members is a first-class entity (not just `participants`).                        | Client    | Phase 1 |
| 0.4  | Confirm First-Timers is a first-class entity with its own attendance track.               | Client    | Phase 1 |
| 0.5  | Confirm Cell Group placement (top-level or under Youth).                                  | Client    | Phase 6 |
| 0.6  | Run `php artisan test` and record baseline pass count.                                    | Developer | Phase 1 |
| 0.7  | Run the payment grep (doc 06 section 6.3).                                                | Developer | Phase 1 |
| 0.8  | Run `php artisan migrate:status`.                                                         | Developer | Phase 1 |
| 0.9  | Read the eight uninspected docs (doc 06 section 6.6).                                     | Developer | Phase 1 |
| 0.10 | Confirm no production deployments (doc 06 PQ-04).                                         | Client    | Phase 1 |
| 0.11 | Confirm whether the ten-stage workflow is still in scope or replaced by simpler statuses. | Client    | Phase 2 |

**Output:** signed-off module inventory and verified baseline.

### 3.2 Phase 1 -- Foundation (shared identity)

**Blocks:** every module phase.

**Rationale:** Members, First-Timers, and Participant identity
are prerequisites for Services, Fellowship, and Discipleship
attendance. Nothing else can be built without deciding who is
being tracked.

| #    | Task                                                                                   | Layer   | Depends on |
| ---- | -------------------------------------------------------------------------------------- | ------- | ---------- |
| 1.1  | Decide: is `members` a new table or a role on `participants`?                          | Design  | 0.3        |
| 1.2  | Decide: is `first_timers` a new table or a flag + relation on `participants`?          | Design  | 0.4        |
| 1.3  | Decide: is `participants` extended or is a new `church_members` table added alongside? | Design  | 0.3        |
| 1.4  | Migration: create `members` (or chosen shape).                                         | DB      | 1.1        |
| 1.5  | Migration: create `first_timers` (or chosen shape).                                    | DB      | 1.2        |
| 1.6  | Migration: link existing identity to the new shape.                                    | DB      | 1.3        |
| 1.7  | Model: `Member` or extend `Participant`.                                               | Model   | 1.4        |
| 1.8  | Model: `FirstTimer`.                                                                   | Model   | 1.5        |
| 1.9  | Identity service: extend `Participant::resolveFrom` or add `Member::resolveFrom`.      | Service | 1.7        |
| 1.10 | First-Timer linking service (search existing, confirm link).                           | Service | 1.8        |
| 1.11 | Cascade: extend `EventDeletionService` for new tables.                                 | Service | 1.4, 1.5   |
| 1.12 | Tests: identity resolution, first-timer linking, cascade.                              | Test    | 1.7-1.11   |
| 1.13 | Preservation: full suite.                                                              | Verify  | all        |

**Definition of done:** a member and a first-timer can be
created, linked, and retrieved. Running events unaffected.

### 3.3 Phase 2 -- General Events module (M8)

**Blocks:** nothing critical; provides the layout and routing
skeleton every other module extends.

| #    | Task                                                                                             | Layer      | Depends on |
| ---- | ------------------------------------------------------------------------------------------------ | ---------- | ---------- |
| 2.1  | Decide: does a general event use the existing `Event` table with a new category, or a new table? | Design     | 0.1, 0.11  |
| 2.2  | Migration: event kind distinction.                                                               | DB         | 2.1        |
| 2.3  | Enum: extend `EventType` or introduce `ChurchEventCategory`.                                     | Model      | 2.1        |
| 2.4  | Validation: relax distance and accessibility rules for church events.                            | Request    | 2.2        |
| 2.5  | Controller: creation, edit, list, show.                                                          | Controller | 2.2        |
| 2.6  | Frontend: `/events/create` branch for church events.                                             | UI         | 2.5        |
| 2.7  | Frontend: `/events` filter by kind.                                                              | UI         | 2.5        |
| 2.8  | Frontend: event detail conditional sections.                                                     | UI         | 2.5        |
| 2.9  | Frontend: status pills (Upcoming / Ongoing / Completed / Cancelled).                             | UI         | 2.5        |
| 2.10 | Tests: create, list, filter, detail, statuses.                                                   | Test       | 2.5-2.9    |
| 2.11 | Preservation: full suite.                                                                        | Verify     | all        |

**Definition of done:** a general church event can be created,
listed, edited, deleted, and viewed end-to-end. Running events
unaffected.

### 3.4 Phase 3 -- Services module (M3)

**Rationale for third:** the Services mockups are the most
complete, and Services is the client's stated priority
("prio attendance only").

| #    | Task                                                                                           | Layer      | Depends on |
| ---- | ---------------------------------------------------------------------------------------------- | ---------- | ---------- |
| 3.1  | Migration: `service_types`.                                                                    | DB         | Phase 1    |
| 3.2  | Migration: `service_occurrences`.                                                              | DB         | 3.1        |
| 3.3  | Migration: `service_attendance_members` (or extend `attendances`).                             | DB         | 3.2        |
| 3.4  | Migration: `service_attendance_first_timers`.                                                  | DB         | 3.2        |
| 3.5  | Models: ServiceType, ServiceOccurrence, ServiceAttendance.                                     | Model      | 3.1-3.4    |
| 3.6  | Relation: ServiceType hasMany ServiceOccurrence.                                               | Model      | 3.5        |
| 3.7  | Relation: ServiceOccurrence hasMany ServiceAttendance.                                         | Model      | 3.5        |
| 3.8  | Cascade: extend EventDeletionService.                                                          | Service    | 3.1-3.4    |
| 3.9  | Validation: occurrence date, service time.                                                     | Request    | 3.5        |
| 3.10 | Controller: Services landing (list service types).                                             | Controller | 3.5        |
| 3.11 | Controller: selected service (list occurrences).                                               | Controller | 3.5        |
| 3.12 | Controller: create occurrence.                                                                 | Controller | 3.5        |
| 3.13 | Controller: open Members attendance for an occurrence.                                         | Controller | 3.5        |
| 3.14 | Controller: open First-Timers attendance for an occurrence.                                    | Controller | 3.5        |
| 3.15 | Controller: mark attendance, add row, delete row, search.                                      | Controller | 3.5        |
| 3.16 | Frontend: `/services` landing page (service cards).                                            | UI         | 3.10       |
| 3.17 | Frontend: selected service view (occurrence list).                                             | UI         | 3.11       |
| 3.18 | Frontend: add attendance record form.                                                          | UI         | 3.12       |
| 3.19 | Frontend: Members attendance table.                                                            | UI         | 3.13       |
| 3.20 | Frontend: First-Timers attendance table with "Apply to Existing WDEMS" action.                 | UI         | 3.14       |
| 3.21 | Frontend: sidebar entry for Services.                                                          | UI         | 3.10       |
| 3.22 | Report: attendance per service date, per service type.                                         | Report     | 3.5        |
| 3.23 | Report: attendance history per participant.                                                    | Report     | 3.5        |
| 3.24 | Tests: create occurrence, mark attendance, first-timer linking, duplicate prevention, cascade. | Test       | 3.5-3.22   |
| 3.25 | Preservation: full suite.                                                                      | Verify     | all        |

**Definition of done:** organizer can create a Morning Service
occurrence, open Members and First-Timers attendance, mark
attendees, link a first-timer to an existing profile, and see
service-specific reports.

### 3.5 Phase 4 -- Fellowship module (M4)

**Rationale for fourth:** can proceed in parallel with Phase 3
if capacity allows.

| #    | Task                                                                            | Layer      | Depends on |
| ---- | ------------------------------------------------------------------------------- | ---------- | ---------- |
| 4.1  | Migration: `fellowship_types`.                                                  | DB         | Phase 1    |
| 4.2  | Migration: `fellowship_events`.                                                 | DB         | 4.1        |
| 4.3  | Migration: `fellowship_groups` (per-event).                                     | DB         | 4.2        |
| 4.4  | Migration: `fellowship_attendance`.                                             | DB         | 4.3        |
| 4.5  | Models: FellowshipType, FellowshipEvent, FellowshipGroup, FellowshipAttendance. | Model      | 4.1-4.4    |
| 4.6  | Cascade: extend EventDeletionService.                                           | Service    | 4.1-4.4    |
| 4.7  | Validation: type selection, group assignment, participant registration.         | Request    | 4.5        |
| 4.8  | Controller: Fellowship landing (type selector).                                 | Controller | 4.5        |
| 4.9  | Controller: per-type event list with filters.                                   | Controller | 4.5        |
| 4.10 | Controller: create/edit/delete Fellowship event.                                | Controller | 4.5        |
| 4.11 | Controller: create/edit/delete groups within an event.                          | Controller | 4.5        |
| 4.12 | Controller: assign participants to groups.                                      | Controller | 4.5        |
| 4.13 | Controller: mark attendance.                                                    | Controller | 4.5        |
| 4.14 | Controller: group totals and overall totals.                                    | Controller | 4.5        |
| 4.15 | Frontend: `/fellowship` page with type selector sidebar.                        | UI         | 4.8        |
| 4.16 | Frontend: event list per type.                                                  | UI         | 4.9        |
| 4.17 | Frontend: event detail page with groups section.                                | UI         | 4.10       |
| 4.18 | Frontend: attendance summary per group.                                         | UI         | 4.14       |
| 4.19 | Frontend: sidebar entry for Fellowship.                                         | UI         | 4.8        |
| 4.20 | Report: attendance per group, per event, per type.                              | Report     | 4.5        |
| 4.21 | Tests: create event, group assignment, attendance, group totals, cascade.       | Test       | 4.5-4.20   |
| 4.22 | Preservation: full suite.                                                       | Verify     | all        |

**Definition of done:** organizer can create a Family Fellowship
event, define Parents / Youth / Children groups, register
participants, assign them to groups, mark attendance, and see
group totals.

### 3.6 Phase 5 -- Discipleship module (M5)

**Rationale for fifth:** the largest single workstream. The
feature spec (A-G) splits into six sub-phases.

#### 5A -- Programs

| #    | Task                                                                                                                         |
| ---- | ---------------------------------------------------------------------------------------------------------------------------- |
| 5A.1 | Migration: `discipleship_programs` (six seeded: Life Class, Encounter God Retreat, School of Leaders 1-3, Cell Convergence). |
| 5A.2 | Model: DiscipleshipProgram.                                                                                                  |
| 5A.3 | Controller: list programs, show program detail.                                                                              |
| 5A.4 | Frontend: Discipleship landing with program cards.                                                                           |

#### 5B -- Batches

| #    | Task                                                              |
| ---- | ----------------------------------------------------------------- |
| 5B.1 | Migration: `discipleship_batches` (program_id, name, start_date). |
| 5B.2 | Model: DiscipleshipBatch.                                         |
| 5B.3 | Controller: CRUD batches.                                         |
| 5B.4 | Frontend: batch list per program, batch creation form.            |

#### 5C -- Enrollment

| #    | Task                                                                    |
| ---- | ----------------------------------------------------------------------- |
| 5C.1 | Migration: `discipleship_enrollments`.                                  |
| 5C.2 | Model: DiscipleshipEnrollment.                                          |
| 5C.3 | Duplicate prevention: unique (participant, program, active) constraint. |
| 5C.4 | Controller: enroll, edit enrollment, withdraw.                          |
| 5C.5 | Frontend: Add Enrollment button + dialog (link to existing member).     |

#### 5D -- Sessions

| #    | Task                                                        |
| ---- | ----------------------------------------------------------- |
| 5D.1 | Migration: `discipleship_sessions` (batch_id, date, topic). |
| 5D.2 | Model: DiscipleshipSession.                                 |
| 5D.3 | Controller: CRUD sessions.                                  |
| 5D.4 | Frontend: session list per batch.                           |

#### 5E -- Session attendance

| #    | Task                                                                      |
| ---- | ------------------------------------------------------------------------- |
| 5E.1 | Migration: `discipleship_attendance` (enrollment_id, session_id, status). |
| 5E.2 | Unique (enrollment, session) constraint.                                  |
| 5E.3 | Model: DiscipleshipAttendance.                                            |
| 5E.4 | Controller: attendance sheet per session.                                 |
| 5E.5 | Frontend: attendance table with Present / Absent / Excused.               |

#### 5F -- Progress and completion

| #    | Task                                                                          |
| ---- | ----------------------------------------------------------------------------- |
| 5F.1 | Progress service: attended / applicable / percentage / remaining.             |
| 5F.2 | Completion criteria per program (required sessions + extra requirements).     |
| 5F.3 | Migration: `discipleship_completions` or status column on enrollment.         |
| 5F.4 | Status transitions: Enrolled / In Progress / Completed / Withdrawn / On Hold. |
| 5F.5 | Frontend: progress bar per participant.                                       |
| 5F.6 | Frontend: completion status badge.                                            |

#### 5G -- Dashboard and reports

| #    | Task                                                                          |
| ---- | ----------------------------------------------------------------------------- |
| 5G.1 | Program overview stats (Total Enrolled, Completed, In Progress, Not Started). |
| 5G.2 | Batch summary (enrolled per batch, percentage).                               |
| 5G.3 | Completion rate donut.                                                        |
| 5G.4 | Participant list with search, batch filter, status filter.                    |
| 5G.5 | Export CSV.                                                                   |
| 5G.6 | Frontend: full dashboard layout.                                              |
| 5G.7 | Frontend: Quick Actions panel.                                                |
| 5G.8 | Sidebar entry for Discipleship.                                               |

#### 5H -- Tests and preservation

| #    | Task                                                                   |
| ---- | ---------------------------------------------------------------------- |
| 5H.1 | Enrollment tests (create, duplicate prevention, status transitions).   |
| 5H.2 | Session attendance tests (duplicate prevention, progress calculation). |
| 5H.3 | Completion tests (criteria enforcement).                               |
| 5H.4 | Cascade tests.                                                         |
| 5H.5 | Full suite preservation.                                               |

**Definition of done:** organizer can create a School of
Leaders 1 batch, enroll participants, record per-session
attendance, watch progress bars update, mark completions, and
export a report.

### 3.7 Phase 6 -- Youth module (M6) and Cell Group (M7)

**Rationale for sixth:** the mockups are complete but the module
depends on Foundation only.

#### 6A -- Youth landing

| #    | Task                                             |
| ---- | ------------------------------------------------ |
| 6A.1 | Frontend: `/youth` landing page with five cards. |
| 6A.2 | Sidebar entry with sub-nav.                      |

#### 6B -- Youth Camp

| #    | Task                                                     |
| ---- | -------------------------------------------------------- |
| 6B.1 | Migration: `youth_camps` + `youth_camp_participants`.    |
| 6B.2 | Model + Controller + UI.                                 |
| 6B.3 | Payment tracking (Paid / Partial / Not Paid per mockup). |
| 6B.4 | Guardian contact for minors.                             |

#### 6C -- Youth Service

| #    | Task                                                               |
| ---- | ------------------------------------------------------------------ |
| 6C.1 | Migration: `youth_service_schedules` + `youth_service_attendance`. |
| 6C.2 | Reuses the occurrence pattern from Services.                       |

#### 6D -- Huddle

| #    | Task                                                   |
| ---- | ------------------------------------------------------ |
| 6D.1 | Migration: `huddles` + `huddle_attendance`.            |
| 6D.2 | Group name, leader, day and time, members, attendance. |

#### 6E -- Campus Ministry

| #    | Task                                                           |
| ---- | -------------------------------------------------------------- |
| 6E.1 | Migration: `campus_ministry_students` (level, year).           |
| 6E.2 | High School (Grade 7-12) and College (1st-4th year) subgroups. |

#### 6F -- Timothy Class

| #    | Task                                        |
| ---- | ------------------------------------------- |
| 6F.1 | Migration: `timothy_class_enrollments`.     |
| 6F.2 | Reuses the Discipleship enrollment pattern. |

#### 6G -- Cell Group

| #    | Task                                                                       |
| ---- | -------------------------------------------------------------------------- |
| 6G.1 | Confirm with client: top-level module or Youth sub-module.                 |
| 6G.2 | Migration: `cell_groups` + `cell_group_members` + `cell_group_attendance`. |
| 6G.3 | Full module per confirmation.                                              |

**Definition of done:** each Youth sub-module works end-to-end
with its own attendance and reports. Cell Group works if
confirmed as a module.

### 3.8 Phase 7 -- Cross-module Attendance and Reports

**Rationale for seventh:** improves usability after Phases 3-6.

| #   | Task                                                                                      |
| --- | ----------------------------------------------------------------------------------------- |
| 7.1 | Decide: does `/attendance` become a hub across modules, or does each module have its own? |
| 7.2 | Decide: does `/reports` become a hub, or per-module?                                      |
| 7.3 | Attendance hub: unified list with filters.                                                |
| 7.4 | Reports hub: unified reports with filters.                                                |
| 7.5 | Sidebar dropdowns for Attendance and Reports.                                             |

### 3.9 Phase 8 -- Cross-cutting concerns

**Rationale:** runs alongside all phases.

| #   | Task                                          | When              |
| --- | --------------------------------------------- | ----------------- |
| 8.1 | RBAC: policy for each new model.              | From Phase 1      |
| 8.2 | Rate limiters for new endpoints.              | Each module phase |
| 8.3 | Design system extension (new components).     | Each module phase |
| 8.4 | Preserve all existing running-event behavior. | Every phase       |
| 8.5 | Update docs per P7 (append-only corrections). | Each phase        |

---

## 4. What is blocked by client decisions

| Phase   | Blocked by                                                                |
| ------- | ------------------------------------------------------------------------- |
| Phase 0 | Nothing (can start now)                                                   |
| Phase 1 | 0.3 (Members entity), 0.4 (First-Timers entity)                           |
| Phase 2 | 0.1 (four-workflow model), 0.2 (module inventory), 0.11 (workflow stages) |
| Phase 3 | 0.1, 0.2                                                                  |
| Phase 4 | 0.1, 0.2                                                                  |
| Phase 5 | 0.1, 0.2                                                                  |
| Phase 6 | 0.1, 0.2, 0.5 (Cell Group placement)                                      |
| Phase 7 | 7.1, 7.2                                                                  |
| Phase 8 | Runs continuously                                                         |

---

## 5. Client decisions owed

Six items must be answered before Phase 1 starts.

1. Do Services, Fellowship, Discipleship, Youth, Members,
   First-Timers all become top-level modules?
2. Is Members a new entity or an extension of `participants`?
3. Is First-Timers a new entity or a flag on `participants`?
4. Is Cell Group top-level or under Youth?
5. Are the mockups approved designs or proposals?
6. Is the ten-stage workflow from the original spec still in
   scope, or has it been replaced by simpler statuses
   (Upcoming / Ongoing / Completed / Cancelled)?

---

## 6. Developer verifications owed

Four items must be completed before Phase 1 starts.

1. Baseline `php artisan test` output.
2. Payment grep (doc 06 section 6.3).
3. `php artisan migrate:status` output.
4. The eight uninspected docs read (doc 06 section 6.6).

---

## 7. What this document does not do

- It does not select a schema.
- It does not approve any phase.
- It does not modify docs 01-06.
- It does not begin implementation.
- It does not predict effort beyond the sub-phase breakdown.

---

## 8. Cross-references

- `docs/church-events/01-current-system-audit.md`
- `docs/church-events/02-product-scope-and-requirements.md`
- `docs/church-events/03-domain-model-and-workflows.md`
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
- `docs/church-events/05-compatibility-and-test-plan.md`
- `docs/church-events/06-open-questions-and-implementation-roadmap.md`
- `docs/dev-workflow/README.md`

---

## 9. Changelog

- **2026-10-16** -- File created. Records the scope revision
  that produced a four-workflow, eleven-module architecture
  after docs 01-06 were written. Divides work into Phase 0
  through Phase 8 with sub-phase task tables. Lists client
  decisions and developer verifications that gate Phase 1. No
  code, migrations, enums, tests, or configuration changed.
