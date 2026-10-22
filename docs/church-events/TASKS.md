# WDEMS -- Church Events -- Task Board

**Status:** Live operational document. Edited in place.

**Policy exception.** Every other file under `docs/church-events/`
is append-only (per P7). This file is not.

**Reference docs:** `docs/specs/Church_Events_Update.md` (authority),
Amendments B-D; `docs/church-events/10-client-decision-log.md`;
`docs/church-events/11-shared-workflow-spec-2026-10-21.md`; and
`docs/spec-implementation-plan-2026-10-22.md` (cross-project index).

**Last reviewed:** 2026-10-22. The previous Phase 6/Q1-Q20 blocker was stale:
the client decision log records Q1-Q21 as answered. Church Events Amendments
B-D override older draft plans where they conflict.

---

## Status legend

    [ ]   not started
    [~]   in progress
    [x]   done
    [!]   blocked
    [-]   deferred / out of scope

---

## Current phase: Church shared workflow acceptance + specialist modules

**Status:** Shared workflow implemented locally; browser/release acceptance
and specialist workflows remain.

The working Church foundation includes generic Church Events, Services,
Fellowship, Discipleship, registration/HOLD review, Services/Fellowship bridge,
monitoring attendance, and participant history. The five generic category
pages and four Youth submodule pages remain Coming Soon placeholders; this pass
changes only Church-specific form input examples, not page architecture.
Specialist workflow implementation and manual acceptance remain pending.

---

## Future phases -- progress index

    [x] Phase 1   Foundation              COMPLETE
    [x] Phase 2   General Events          COMPLETE
    [x] Phase 3   Services module         COMPLETE
    [x] Phase 4   Fellowship module       COMPLETE (2026-10-16)
    [x] Phase 5   Discipleship module     COMPLETE (2026-10-16)
    [x] Phase 6 foundation  Youth hub + Youth Service route implemented
    [~] Phase 6 specialist Youth modules and remaining category workflows
    [~] Church registration/bridge/attendance/history manual acceptance
    [ ] Cross-module approved scope and specialist ministry vertical slices
    [ ] Cross-cutting security/reliability and release acceptance

The task board distinguishes the agreed shared Church workflow from specialist
module work. Do not gate all work on the old mockup question set. Cell Group was
removed by Q18; participant payments were removed by Q10/Q11; Q21 retained the
Admin/Staff role model and rejected approval workflow.

---

## Priority 0 — shared Church workflow acceptance

    [x] Generic Church event create/edit/list and category filtering
    [x] Church registration Add/Edit/Delete, registration codes and HOLD review
    [x] Public Church link uses email OTP/reCAPTCHA and event-scoped registrations
    [x] Services/Fellowship registration bridge and legacy mutation guards
    [x] Church monitoring attendance, typed code/search/filter, event summary
    [x] D-11 Church participant history; bridge de-duplication and pool isolation
    [x] B.3 scheduling date picker/server limits for generic and legacy Church dates
    [ ] Browser acceptance: staff Add/Edit/Delete, public link lifecycle and
        held resolution, Service/Fellowship bridge, attendance lookup/filters,
        summary invariant and participant-history pool isolation
    [ ] Fresh install/upgrade/recovery check for the already-applied bridge
        migration; do not rename or reorder an applied migration
    [ ] Keep legacy Services/Fellowship Add paths until bridge acceptance and
        user sign-off

Automated reference: `docs/spec-implementation-plan-2026-10-22.md`, Phase B;
Church authority: `docs/specs/Church_Events_Update.md`, Amendment D.

---

## Priority 1 — implement placeholder Church modules

The category and Youth submodule pages remain placeholders. Church input
placeholder copy changes do not implement these modules. Add persistent
activity/submodule tagging before claiming Youth submodules have independent
event lists; currently no activity key exists.

    [ ] Youth Camp — participant context, guardian/minor requirements, per-day
        attendance if approved; no payment tracking (Q10/Q11).
    [ ] Huddle — group, leader, schedule and attendance workflow.
    [ ] Campus Ministry — grade/year groupings and approved outreach details.
    [ ] Timothy Class — structured class/session workflow; reconcile against
        the existing Discipleship program model instead of duplicating progress.
    [ ] Kids Service and Vacation Bible School — approved session/age/teacher
        data and attendance; dynamic Outreach locations per Q8.
    [ ] Young Adult Service/Seminar and Partner → GRC → Care Group/Fellowship
        hierarchy per Q4; use shared Church attendance/registration safely.
    [ ] Worship & Prayer — Outdoor Prayer Meeting, Worship Night, Prayer &
        Fasting using Church workflows, never Running-specific flows.
    [ ] Special Occasions — Anniversary, Thanksgiving, Pastor’s Appreciation,
        Dedication, and SOL levels 1–3; support multiple named roles and
        generated documents as approved. No payment/budget-approval workflow.
    [ ] Church Administration — Church Planning and Ministry Meeting; editable
        ministries per Q9, Gantt timeline per Q7, no invented approval chain.
    [-] Cell Group — removed by client decision Q18.
    [-] Per-participant payment tracking / gateway — removed by Q10/Q11.

Each module slice requires its own data ownership, routes, validation, UI,
isolation/authorization tests and acceptance checklist. Preserve Church pool
boundaries and do not create global participants for descriptive honoree,
guardian, sponsor or role names unless they register for attendance.

---

## Deferred items

    First-timer promotion UI + endpoint
        Deferred from Phase 3. Per doc 06 D2, promotion is manual.
        Originally folded into Phase 4; not built. Dedicated
        Members/First-Timers pages are approved by Q17 but remain
        separate UI scope; promotion stays manual unless approved.

    docs/services.md subsystem reference
        Deferred documentation follow-up; Q1 answered that Services stays.
        No longer blocked on sidebar scope.

    Discipleship required_sessions backfill
        All six seeded programs have null required_sessions.
        Completion falls back to 100% of applicable sessions.
        Per-program thresholds are a client decision.

    Discipleship cell-clearing on the attendance matrix
        The DELETE endpoint supports it. The frontend does not
        call it. Small additive block when useful.

---

## Current decision/acceptance blockers

The former Q1-Q20 blocker is closed; these are the remaining specific gates:

    [!] Discipleship Q14 data migration
        Client approved Present/Absent only and all sessions count toward
        applicability. Confirm handling of historical Excused rows before
        migrating; see docs/known-issues.md ISSUE-025.

    [!] Church role/ownership policy and outside-event-day write rules
        Deferred by Amendment B.7. Do not add new policy behavior until the
        owner decision is recorded. Related: ISSUE-004 and ISSUE-015.

    [!] Specialist activity/submodule identity
        Generic category and Youth pages now use the shared workflow. A
        persistent activity discriminator is needed before Youth submodules
        can have independent lists or tailored guardian/attendance fields.
        Track in docs/spec-implementation-plan-2026-10-22.md.

    [~] Browser/release acceptance
        Exercise Church registration, bridge, attendance, history and new
        category pages; verify the applied bridge migration on fresh install,
        upgrade and recovery. Do not retire legacy Add paths before approval.

    Latest automated verification: Church category workflow — 5 tests / 110
    assertions; full suite — 980 passed / 3 skipped / 4,320 assertions. These do
    not substitute for the browser and migration acceptance listed above.

---

## Resolved items

    [x] Q1   Top-level modules            all six are top-level (pre-mockup)
    [x] Q2   Members is new entity        yes
    [x] Q2a  Members references           references participants
    [x] Q3   First-Timers is new entity   yes
    [x] Q3a  First-Timers references      references participants
    [x] Q3b  First-Timers lifecycle       hybrid
    [x] Q4   Cell Group placement         -- removed from scope by Q18
    [x] Q5   Mockup authority             approved as-is
    [x] Q6   Workflow stages              four
    [x] Q7   Non-English event names      yes (Unicode-aware rules)
    [x] Q8   Wizard draft-loss fix        deferred
    [x] Q9   TrimStrings middleware       active

**Numbering note.** The Q1-Q9 above are the original doc-06
questions. The Q1-Q20 in `docs/church-events/09-mockup-scope-
inventory.md` and `docs/church-events/10-client-decision-
log.md` are a separate, larger set. The collision is
intentional: the original Q set was closed before the mockups
arrived. When reading either file, check the doc heading to
know which Q set is meant.

---

## Done

### Phase 5 -- Discipleship module (2026-10-16)

    [x] 5A.1   Migration: discipleship_programs
    [x] 5A.2   Model: DiscipleshipProgram
    [x] 5A.3   Seeder: DiscipleshipProgramSeeder (six programs)
    [x] 5A.4   Controller: DiscipleshipController (index, show)
    [x] 5A.5   Frontend: discipleship/index.tsx
    [x] 5A.6   Tests: DiscipleshipProgramTest

    [x] 5B.1   Migration: discipleship_batches
    [x] 5B.2   Model: DiscipleshipBatch
    [x] 5B.3   Controller: DiscipleshipBatchController (store,
               update, destroy)
    [x] 5B.4   Routes: 3 batch endpoints
    [x] 5B.5   Frontend: show.tsx (batch list + create/edit)
    [x] 5B.6   Tests: DiscipleshipBatchTest

    [x] 5C.1   Migration: discipleship_enrollments
    [x] 5C.2   Enum: DiscipleshipEnrollmentStatus
    [x] 5C.3   Model: DiscipleshipEnrollment
    [x] 5C.4   Controller: DiscipleshipEnrollmentController
               (store, update, destroy = withdraw)
    [x] 5C.5   JSON endpoint: participant-search
    [x] 5C.6   Routes: 4 enrollment endpoints + 1 JSON search
    [x] 5C.7   Frontend: enrollment table + three dialogs
    [x] 5C.8   Tests: DiscipleshipEnrollmentTest

    [x] 5D.1   Migration: discipleship_sessions
    [x] 5D.2   Model: DiscipleshipSession
    [x] 5D.3   Controller: DiscipleshipSessionController
    [x] 5D.4   Controller: DiscipleshipBatchController::show
    [x] 5D.5   Routes: 4 session endpoints
    [x] 5D.6   Frontend: discipleship/batch-show.tsx
    [x] 5D.7   Tests: DiscipleshipSessionTest

    [x] 5E.1   Migration: discipleship_attendances
    [x] 5E.2   Enum: DiscipleshipAttendanceStatus
    [x] 5E.3   Model: DiscipleshipAttendance
    [x] 5E.4   Controller: DiscipleshipAttendanceController
               (bulkUpsert, destroy)
    [x] 5E.5   Routes: 2 attendance endpoints
    [x] 5E.6   Frontend: attendance matrix on batch-show.tsx
    [x] 5E.7   Tests: DiscipleshipAttendanceTest

    [x] 5F.1   Migration: required_sessions + extra_requirements
    [x] 5F.2   Service: DiscipleshipProgressService (progress,
               batchSummary, programOverview)
    [x] 5F.3   Controller: show payloads gain progress + overview
    [x] 5F.4   Frontend: overview card + progress columns
    [x] 5F.5   Tests: DiscipleshipProgressTest

    [x] 5G.1   Controller: search + filter params on show()
    [x] 5G.2   Controller: exportCsv()
    [x] 5G.3   Routes: 1 export endpoint
    [x] 5G.4   Frontend: filter bar + CSV + Quick Actions
    [x] 5G.5   Tests: DiscipleshipProgramReportTest

    [x] 5H.1   Coverage audit
    [x] 5H.2   Closure entry in docs/progress.md
    [x] 5H.3   Subsystem reference: docs/discipleship.md

Test delta: 705 -> 791 passed (+86), 2245 -> 3080 assertions
(+835). Zero regressions.

### Phase 4 -- Fellowship module (2026-10-16)

    [x] 4.1    Migration: fellowship_events
    [x] 4.2    Migration: fellowship_groups
    [x] 4.3    Migration: fellowship_attendances
    [x] 4.4    Enum: FellowshipType (six values)
    [x] 4.5    Model: FellowshipEvent
    [x] 4.6    Model: FellowshipGroup
    [x] 4.7    Model: FellowshipAttendance
    [x] 4.8    Participant relation: fellowshipAttendances
    [x] 4.9    Controller: FellowshipController (index, show,
               store, update, destroy)
    [x] 4.10   Controller: FellowshipGroupController (store,
               update, destroy)
    [x] 4.11   Controller: FellowshipAttendanceController
               (show, store, mark, destroy)
    [x] 4.12   FormRequests: 6 Fellowship FormRequests
    [x] 4.13   Routes: 12 Fellowship endpoints
    [x] 4.14   Frontend: fellowship/index.tsx (landing)
    [x] 4.15   Frontend: fellowship/show.tsx (event list)
    [x] 4.16   Frontend: fellowship/attendance.tsx (roster)
    [x] 4.17   Sidebar: Fellowship entry
    [x] 4.18   Tests: FellowshipControllerTest (16)
    [x] 4.19   Tests: FellowshipGroupControllerTest (11)
    [x] 4.20   Tests: FellowshipAttendanceControllerTest (18)
    [x] 4.21   Subsystem reference: docs/fellowship.md

Test delta: 695 -> 750 passed (+55), 2682 -> 2866 assertions
(+184). Zero regressions.

### Phase 3 -- Services module (2026-10-16)

    [x] 3.1   Migration: add birthday to participants
    [x] 3.2   Migration: create service_occurrences
    [x] 3.3   Migration: create service_attendance_members
    [x] 3.4   Migration: create service_attendance_first_timers
    [x] 3.5   Enum: ServiceType
    [x] 3.6   Model: ServiceOccurrence
    [x] 3.7   Model: ServiceAttendanceMember
    [x] 3.8   Model: ServiceAttendanceFirstTimer
    [x] 3.9   Participant relations: serviceAttendanceMembers,
              serviceAttendanceFirstTimers
    [x] 3.10  Controller: ServiceController (index, show,
              storeOccurrence, storeRecurring, destroyOccurrence)
    [x] 3.11  Controller: ServiceAttendanceController (show, store,
              markMember, markFirstTimer, destroyMember,
              destroyFirstTimer)
    [x] 3.12  FormRequests: StoreServiceOccurrence, StoreRecurring,
              AddServiceAttendance, MarkServiceAttendance
    [x] 3.13  Routes: 11 Services endpoints
    [x] 3.14  Rate limiter: service-recurring-batch
    [x] 3.15  Frontend: services/index.tsx (landing)
    [x] 3.16  Frontend: services/show.tsx (per-type list)
    [x] 3.17  Frontend: services/attendance.tsx (two tracks)
    [x] 3.18  Sidebar: Services entry
    [x] 3.19  Tests: ServiceControllerTest (16 tests)
    [x] 3.20  Tests: ServiceAttendanceControllerTest (17 tests)
    [-] 3.21  First-timer promotion UI + endpoint
              DEFERRED -- see Deferred items

Test delta: 617 -> 650 passed (+33), 2346 -> 2492 assertions
(+146). Zero regressions.

### Phase 2 -- General Events module (2026-10-16)

    [x] Backend: church event schema, ChurchEventStatus enum,
              conditional validation, controller branching
    [x] Frontend: kind filter, category sub-line, upcoming pill
    [x] Fix: distance_unit nullable + ChurchEventTest
    [x] Docs: Phases 2 closure + frontend closure

### Phase 1 -- Foundation (2026-10-16)

    [x] members + first_timers tables, models, relations, tests
    [x] 16 tests

---

## How to use this file

**Adding a task.** Under the relevant phase.

**Completing a task.** Change `[ ]` to `[x]`.

**Deferring a task.** Change `[ ]` to `[-]` with a note.

**Moving a phase to Done.** When all tasks checked or deferred,
move the block.

**Blocking a phase.** Change `[ ]` to `[!]` in the index and
add the blocker to the Blocked items section.

**Adding a client question.** Add to the Blocked items section
with the phase it gates. Full question text and rationale go in
`docs/church-events/10-client-decision-log.md`.

---

## Changelog

- **2026-10-16** — File rewritten. Phase 3 done block expanded
  from a summary to full task list. Phase 4 and Phase 5 done
  blocks added. Current phase moved from 4 to 6. Blocked items
  expanded from one (Q4) to a twenty-question set. Deferred
  items reorganized. Cross-references updated to include
  `docs/church-events/10-client-decision-log.md`.
