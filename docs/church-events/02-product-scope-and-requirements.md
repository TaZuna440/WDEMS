# WDEMS -- Church Events Product Scope and Requirements

**Status:** Draft for review. Documentation-only. Requirements are
tagged per the reading guide below. Nothing here is an approved
implementation decision until it is marked CONFIRMED.

**Audience:** Whoever executes documents 03-06 of the Church
Events documentation set, and whoever approves the eventual
implementation.

**Purpose:** Establish what the Church Events feature is for, who
it is for, what is in scope for the first release, what is out of
scope, and which requirements remain unresolved. Documents 03-06
derive from this one.

**Related:**

- `docs/church-events/01-current-system-audit.md` -- what exists
  in WDEMS today
- `docs/AI-CONTEXT.md` -- system-wide constraints
- The Church Event Management System workflow specification
  (client document, 13 pages)
- The dashboard mockup (client-provided image)

---

## 0. Reading this document

Every requirement below carries one of four tags.

| Tag           | Meaning                                                                                                                                                                                     |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **CONFIRMED** | A directive from the Church Events scoping prompt. It is a settled instruction, not a proposal.                                                                                             |
| **PROPOSED**  | From the workflow specification, the mockup, or a related client document. Not yet approved as a requirement. Included here so it is not lost and can be approved or rejected deliberately. |
| **PENDING**   | A decision that has not been made. Listed so it blocks the right things and is not accidentally answered by an implementation choice.                                                       |
| **OUT**       | Explicitly excluded from the current scope. Recorded so it is not re-proposed.                                                                                                              |

Nothing in this document is an implementation decision. Where a
requirement is CONFIRMED, document 04 may turn it into feature
specification. Where it is PENDING, document 06 records it as a
blocker and the feature is deferred until answered.

---

## 1. Product objectives

**OBJ-01 (CONFIRMED).** Extend WDEMS to support Church Events as
a first-class event category alongside the existing running-event
categories.

**OBJ-02 (CONFIRMED).** WDEMS must serve a dual audience: the
running-event organizers it serves today, and the church
organizers who need Church Events support. Church Events "should
also be useful to the people who create and manage Community Run
events."

**OBJ-03 (CONFIRMED).** This is an extension of the existing
WDEMS, not a separate application and not a complete rewrite.

**OBJ-04 (CONFIRMED).** Reuse existing WDEMS functionality
whenever it satisfies the new requirements.

**OBJ-05 (PROPOSED, from workflow spec section 1.0).** Church
Events is a parent module. Services, Fellowship, Discipleship,
Youth, Kids Ministry, Young Adult, Worship & Prayer, Special
Occasions, and Church Administration are categories within it, not
separate event-management systems.

**OBJ-06 (PROPOSED, from workflow spec section 10).** All
categories follow one five-stage workflow with category-specific
fields and features enabled per event type. New activities can be
added as configurable event types without building separate
workflows.

---

## 2. Intended users

The scoping prompt does not define user roles. The mockup shows a
single identity: "Charish -- Admin / Event Organizer." The
workflow specification refers to "authorized leadership or
designated approvers" without naming them.

**USR-01 (CONFIRMED).** Existing WDEMS users (admin and staff)
remain users of the extended system.

**USR-02 (PENDING).** Are church roles distinct from running-event
roles, or is the same `admin` / `staff` split reused?

**USR-03 (PENDING).** Is there a distinct "approver" role or
capability for church-event proposals, and if so, how is it
granted?

**USR-04 (PENDING).** Are participants of church events
(attendees, members, volunteers) users of WDEMS, or records only?
The running-event model treats participants as records, not users.
Whether that carries over is unconfirmed.

**USR-05 (PROPOSED, from workflow spec section 2.2).** Sensitive
participant information, especially children's and guardians'
contact details, must be role-restricted. What roles exist and how
restriction is implemented is PENDING.

---

## 3. Transition from Personal Events to Church Events

The scoping prompt states: "Church Events replaces Personal Events
in the new product scope."

**TR-01 (PENDING).** No `PersonalEvent` type, category, or
concept exists in the WDEMS source code. The audit
(`01-current-system-audit.md` section 5, item 7) flagged this.
What "Personal Events" referred to is unresolved. Possibilities:

- A planned feature that was never built.
- A working name for what later became Church Events.
- Something currently in the code under a different label.

**TR-02 (CONFIRMED).** Whatever Personal Events was, its place in
the product scope is now taken by Church Events. No other
interpretation is needed to proceed.

**TR-03 (PENDING).** If Personal Events exists anywhere as
documentation, a roadmap, or a backlog item, that material should
be superseded rather than left in place. The audit found no such
material in `docs/`, but a repository-wide search was not run.

---

## 4. Confirmed in-scope capabilities

These are the only capabilities confirmed by the scoping prompt.

**CAP-01 (CONFIRMED).** Church Events as a new event category or
set of categories.

**CAP-02 (CONFIRMED).** A unified dashboard and shared navigation
spanning Events, Registrations, Attendance, and Reports.
Community Run, Fun Run, and Church Events appear in the same
surfaces.

**CAP-03 (CONFIRMED).** Preservation of existing Community Run
functionality. Any change to running-event behavior requires an
explicitly approved requirement.

**CAP-04 (CONFIRMED).** Preservation of existing Fun Run
functionality. The scoping prompt says: "Do not remove its
existing functionality or assume its payment behavior is
changing."

**CAP-05 (CONFIRMED).** Reuse of existing WDEMS functionality
where it satisfies new requirements.

**CAP-06 (CONFIRMED, from scoping prompt section 1.7).** Church
Events may require additional categories, workflows, occurrence
handling, attendance capabilities, or reports. Each must be
verified before proposing implementation.

**CAP-07 (CONFIRMED, from scoping prompt section 1.8).** Not
every proposed feature must be implemented in the first release.
Phasing is expected and acceptable.

---

## 5. Confirmed exclusions

**EXC-01 (OUT).** Replacing the existing WDEMS architecture.

**EXC-02 (OUT).** Removing Community Run.

**EXC-03 (OUT).** Removing Fun Run, or changing its payment
behavior without an approved requirement.

**EXC-04 (OUT).** Building a second application or a separate
event-management system for church activities.

**EXC-05 (OUT, from scoping prompt section 1).** Personal Events
as a scope item -- it is replaced by Church Events.

**EXC-06 (OUT, from scoping prompt section 6).** Implementing any
feature in this documentation phase. Documents 01-06 are
documentation only.

---

## 6. Pending capabilities

Capabilities proposed in the workflow specification or shown in
the mockup, but not yet approved as requirements. Each requires a
product decision.

**PND-01 (PENDING).** Event categories and event types as
database-managed entities with an administrator UI. The mockup
shows a Categories sidebar entry and an "Add Category" button. The
workflow spec says dropdown options must be "configurable by an
authorized administrator." Whether this is approved for the first
release is unresolved.

**PND-02 (PENDING).** A multi-stage church event workflow
(Draft / Proposed / Under Review / Approved / Revision Required /
Rejected / In Preparation / Ready / Ongoing / Event Ended / Under
Evaluation / Completed). Whether these are states in a state
machine, or a UI checklist separate from status, is unresolved.
See audit section A.

**PND-03 (PENDING).** Recurring events with per-occurrence
attendance. The workflow spec says every service date needs its
own occurrence. Whether the first release supports recurrence, and
how attendance attaches to occurrences, is unresolved. See audit
section D.

**PND-04 (PENDING).** An approval workflow for church-event
proposals. Who approves, under what conditions, with what audit
trail, is unresolved. See audit section C.

**PND-05 (PENDING).** Budget and expense tracking. The workflow
spec mentions budget estimate and expense reconciliation. Whether
this is in scope for the first release, and at which tier, is
unresolved. See audit section E.

**PND-06 (PENDING).** Church-specific reports (service-specific
attendance, attendance per group or subgroup, enrollment and
completion tracking). Whether these extend the existing
`ReportController` or require a new reporting surface is
unresolved.

**PND-07 (PENDING).** File attachments (photos, minutes,
documents, reconciliation files). The workflow spec mentions
uploads in Stages 3 and 5. Whether a generic attachments table is
added and what file types or size limits apply is unresolved.

**PND-08 (PENDING).** Enrollment and completion tracking for
structured programs (School of Leaders, Life Class, Encounter God
Retreat, Timothy Class, VBS). The workflow spec describes per-
participant statuses: Enrolled, In Progress, Completed,
Withdrawn, Incomplete. Whether this ships in the first release is
unresolved.

**PND-09 (PENDING).** Group and subgroup tracking (Fellowship
type, Youth school year, Kids outreach location, Young Adult care
group). Whether subgroups are a first-class concept or a per-event
field is unresolved.

**PND-10 (PENDING).** Cancellation handling for individual
occurrences of a recurring event, separate from cancelling the
whole schedule. See audit section D.

---

## 7. Unified dashboard and navigation requirements

**NAV-01 (CONFIRMED).** One dashboard. Community Run, Fun Run,
and Church Events appear in the same surfaces.

**NAV-02 (CONFIRMED).** Shared navigation for Events,
Registrations, Attendance, and Reports.

**NAV-03 (PROPOSED, from mockup).** A Categories navigation entry
for administrator-managed event categories.

**NAV-04 (PROPOSED, from mockup).** Quick actions on the
dashboard: Create Event, Manage Registrations, Record Attendance,
View Reports.

**NAV-05 (PROPOSED, from mockup).** Dashboard widgets: Total
Events, Total Registrations, Total Attendance, Active Categories;
Upcoming Events list; Church Events Categories panel with per-
category counts; Recent Events list; Event Statistics chart;
Calendar widget.

**NAV-06 (PENDING).** Whether the four top-line dashboard stats
(Total Events, Total Registrations, Total Attendance, Active
Categories) are computed across all event kinds, only Church
Events, or are configurable.

**NAV-07 (PENDING).** Whether the Event Statistics chart (which
the mockup shows segmented by Community Run, Fun Run, Church
Events) compares a chosen time range across all three, or shows a
different default.

**NAV-08 (PENDING).** Whether the Categories sidebar entry is
visible to all users or admin-only.

**NAV-09 (PENDING).** Whether existing sidebar items
(Dashboard, Events, Registration, Attendance) are extended or
re-labelled when Church Events ships.

**NAV-10 (PENDING).** Whether registration for Church Events
reuses the `/registrations` page, or a Church Events page
supersedes it for church events.

---

## 8. Preservation of Community Run and Fun Run

**PRES-01 (CONFIRMED).** Existing running-event behavior must not
change without an explicitly approved requirement.

**PRES-02 (CONFIRMED).** Community Run remains an event
category.

**PRES-03 (CONFIRMED).** Fun Run remains in scope. Do not assume
its payment behavior is changing.

**PRES-04 (CONFIRMED, from scoping prompt section 2).** The
following must remain working after the extension:

- Existing event CRUD for running events
- Existing registration flow for running events
- Existing attendance flow for running events
- Existing reports for running events
- Existing deletion (with OTP) for running events
- Existing authentication and 2FA
- Existing participant identity model

**PRES-05 (PENDING).** Whether any current running-event test
will need updating when Church Events ships. The audit could not
answer this -- test files were not inspected.

**PRES-06 (PENDING).** The "Fun Run payments" question. The audit
found no payment-related code in inspected files. Whether payment
behavior was intended but never built, or was always out of scope,
is unresolved. See audit section B.

---

## 9. Functional requirements

Requirements are grouped by area. Each area lists confirmed,
proposed, and pending items.

### 9.1 Event categories and event types

**CAT-01 (CONFIRMED).** Church Events introduces categories
(Services, Fellowship, Discipleship, Youth, Kids Ministry, Young
Adult, Worship & Prayer, Special Occasions, Church
Administration). The client list is the source of truth.

**CAT-02 (PENDING).** Whether categories and types are stored in
database tables (administrator-managed) or fixed in code.

**CAT-03 (PENDING).** Whether the category list is closed or an
administrator can add, rename, or deactivate categories and
types.

**CAT-04 (PENDING).** Whether a Church Events category may
contain sub-categories (e.g., Kids Ministry > Outreaches >
Wawang Pulo) or whether subgroup data is a per-event field.

**CAT-05 (PROPOSED).** An "Other" option on dropdowns that, when
selected, requires a custom name. From workflow spec section 4.1.

### 9.2 Event creation and lifecycle

**EVT-01 (CONFIRMED).** Unified event creation and listing. Both
running and church events are created through the same entry
points.

**EVT-02 (PROPOSED).** Five-stage lifecycle shared by all church
categories:

1. Proposal
2. Planning & Approval
3. Preparation
4. Execution
5. Evaluation & Completion

**EVT-03 (PENDING).** Whether the five stages are a state machine
or a checklist. See audit section A.

**EVT-04 (PENDING).** Whether low-ceremony categories (Services,
Church Administration) skip stages or run through all five.

**EVT-05 (PROPOSED).** One-time, recurring, or multi-session
events. From workflow spec section 2, Stage 1.

**EVT-06 (PENDING).** Whether recurring events are in scope for
the first release. See PND-03.

### 9.3 Registration

**REG-01 (CONFIRMED).** Reuse existing registration and
participant identity handling where it satisfies new
requirements.

**REG-02 (PENDING).** Whether church-event registration reuses
the existing public form builder, the participant identity model,
and the verification challenge flow, or uses a simpler
registration shape.

**REG-03 (PENDING).** Whether contact verification (email OTP,
Firebase phone) is required for church-event registration. The
audit inferred that most church activities do not need it, but
this is not confirmed.

**REG-04 (PROPOSED).** Registration is category- and type-
dependent. Camps, seminars, retreats, and classes register;
services do not.

**REG-05 (PENDING).** Whether enrollment for structured programs
(School of Leaders, Life Class, Timothy Class, VBS) is modeled as
`Registration` or as a distinct concept.

### 9.4 Attendance

**ATT-01 (CONFIRMED).** Reuse existing attendance recording and
staff lookup where it satisfies new requirements.

**ATT-02 (PENDING).** The entity attendance is recorded against.
The running-event `Attendance` model attaches to a
`Registration` row. Whether church attendance attaches to
registrations, to a church member list, to occurrences, or to
some combination is unresolved. See audit section D.

**ATT-03 (PROPOSED).** Attendance per occurrence or per session.
The workflow spec says each service date and each session must
have its own attendance record.

**ATT-04 (PENDING).** Whether walk-ins, unnamed attendees, or
count-only attendance is needed for services.

**ATT-05 (PROPOSED).** Duplicate-attendance prevention by unique
participant-plus-occurrence. From workflow spec section 2.2.

**ATT-06 (PROPOSED).** Category-specific attendance features:

- Services -- recurring attendance, history per participant
- Fellowship -- attendance by group
- Discipleship -- attendance per session, progress
- Youth -- attendance per day or session for camps

### 9.5 Reports

**REP-01 (CONFIRMED).** Reuse existing reports and aggregation
logic where it satisfies new requirements.

**REP-02 (PENDING).** Whether church reports extend
`ReportService` or require a new service.

**REP-03 (PROPOSED).** Attendance count per service date,
attendance history per participant, comparison across service
types, missing-occurrence reports.

**REP-04 (PROPOSED).** Reports filterable by category, type,
date, status, group, and participant.

### 9.6 Approval

**APR-01 (PENDING).** Whether approval is implemented in the
first release.

**APR-02 (PENDING).** Who approves.

**APR-03 (PENDING).** Whether the event creator may approve their
own proposal.

**APR-04 (PENDING).** Rejection and revision behavior.

**APR-05 (PENDING).** Audit-trail requirements.

### 9.7 Recurring events and occurrences

**REC-01 (PENDING).** Whether recurring events are in scope for
the first release.

**REC-02 (PENDING).** How occurrences are modeled and stored.

**REC-03 (PENDING).** Whether individual occurrences can be
cancelled or rescheduled independently.

**REC-04 (PROPOSED).** Reports must show both per-occurrence
results and aggregate results for the whole schedule.

### 9.8 Budget and expenses

**BUD-01 (PENDING).** Whether budget and expense tracking is in
scope for the first release.

**BUD-02 (PENDING).** If in scope, at which tier (see audit
section E).

**BUD-03 (PROPOSED).** Budget estimate in Stage 2, actual
expenses and liquidation in Stage 5.

---

## 10. Non-functional requirements

**NFR-01 (CONFIRMED, from existing WDEMS conventions).** Changes
must preserve existing running-event data.

**NFR-02 (CONFIRMED).** Existing migrations must not be edited.
New schema changes use new migrations.

**NFR-03 (CONFIRMED).** Existing tests must continue to pass or
must be explicitly updated with the change documented.

**NFR-04 (PROPOSED).** Sensitive records (children, guardians,
finance, leadership meetings) must be access-controlled. Specific
policy shape is PENDING.

**NFR-05 (PROPOSED).** Completed events should be read-only for
ordinary users. Corrections require permission and an audit
entry.

**NFR-06 (PROPOSED).** Cancellation must record reason, actor,
and timestamp.

**NFR-07 (PROPOSED).** All status transitions must be auditable.

**NFR-08 (PENDING).** Performance targets for church-event
listing, attendance entry, and reporting are not specified.

**NFR-09 (PENDING).** Data retention requirements for church
records are not specified.

**NFR-10 (PENDING).** Whether the church-event module is exposed
in the public `/r/{slug}` registration surface, or only to
authenticated staff, is not specified.

---

## 11. Assumptions requiring approval

Listed so each can be confirmed or rejected before document 04
turns them into feature specs.

**ASM-01.** The nine categories named in the workflow spec are
the approved first-release list. If more or fewer are wanted,
the list is wrong.

**ASM-02.** Church events are represented in the same `events`
table as running events, distinguished by category or type,
rather than a new table.

**ASM-03.** The existing `Participant` identity model (email or
normalized PH phone) is reusable for church-event participants.

**ASM-04.** The existing user roles (`admin`, `staff`) are
sufficient for the first release, and no new role is required.

**ASM-05.** The dashboard stat widgets shown in the mockup
describe the eventual state, not necessarily the first release.

**ASM-06.** The mockup's visual design (colors, layout, sidebar
structure) follows the existing WDEMS design system.

**ASM-07.** Both Community Run and Fun Run remain full-scope for
the foreseeable future, not deprecated.

**ASM-08.** Any church-event feature that is not CONFIRMED is
deferred to a future release.

**ASM-09.** No production deployments are affected by schema
changes. Confirmation required.

**ASM-10.** The workflow specification's 13 pages are the
authoritative description of the client's intent, and the mockup
is the authoritative visualization. Where they disagree (e.g.,
ten-state workflow vs. four display labels), the resolution is a
product decision. See audit section A.

---

## 12. Cross-references

- `docs/church-events/01-current-system-audit.md` -- what exists
- `docs/church-events/03-domain-model-and-workflows.md` -- to be
  written; derives schema decisions from this document's
  PENDING items
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
  -- to be written; turns CONFIRMED items into feature specs
- `docs/church-events/05-compatibility-and-test-plan.md` -- to be
  written; derives preservation requirements from section 8
- `docs/church-events/06-open-questions-and-implementation-roadmap.md`
  -- to be written; consumes this document's PENDING items as the
  blocker list

---

## 13. Summary of unresolved decisions blocking document 03

Every item below must be resolved before the domain model can be
finalized.

1. Whether the workflow specification is an approved requirement
   or a discovery artifact. (Blocks EVT-03, EVT-04, PND-02.)
2. Whether categories and types are database-managed or fixed in
   code. (Blocks CAT-02, CAT-03, PND-01.)
3. The entity attendance is recorded against. (Blocks ATT-02,
   ATT-03, ATT-04.)
4. Whether recurring events are in scope. (Blocks EVT-06, REC-01
   through REC-04, PND-03.)
5. The minimum financial requirement, if any. (Blocks BUD-01,
   BUD-02, PND-05.)
6. Who approves, under what conditions, with what audit trail.
   (Blocks APR-01 through APR-05, PND-04.)
7. Whether Fun Run payment behavior exists or was planned.
   (Blocks PRES-06.)
8. Whether contact verification is required for church-event
   registration. (Blocks REG-03.)
9. Whether enrollment is distinct from registration. (Blocks
   REG-05, PND-08.)
10. Whether subgroups are a first-class concept. (Blocks CAT-04,
    PND-09.)

---

## 14. Changelog

- **2026-10-16** -- File created. Product scope recorded from the
  scoping prompt (CONFIRMED items), from the workflow spec and
  mockup (PROPOSED items), and as open questions (PENDING items).
  Ten unresolved decisions carried into document 03.
