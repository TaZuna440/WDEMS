# WDEMS -- Church Events -- Task Board

**Status:** Live operational document. Edited in place.

**Policy exception.** Every other file under `docs/church-events/`
is append-only (per P7). This file is not.

**Reference docs:** 01-08 plus `docs/dev-workflow/README.md`.

---

## Status legend

    [ ]   not started
    [~]   in progress
    [x]   done
    [!]   blocked
    [-]   deferred / out of scope

---

## Current phase: Phase 4 -- Fellowship module

**Status:** Ready to start. Phase 3 complete.

**Blocker:** None. Q4 (Cell Group placement) blocks Phase 6
only.

### Tasks

Full task list: doc 07 section 3.5. Not duplicated here.

First-timer promotion (deferred from Phase 3) folds in here.

---

## Future phases -- progress index

    [x] Phase 1   Foundation          COMPLETE
    [x] Phase 2   General Events      COMPLETE
    [x] Phase 3   Services module     COMPLETE (first-timer promotion deferred)
    [~] Phase 4   Fellowship module   READY
    [ ] Phase 5   Discipleship module doc 07 sec 3.6
    [!] Phase 6   Youth + Cell Group  blocked by Q4
    [ ] Phase 7   Cross-module        doc 07 sec 3.8
    [ ] Phase 8   Cross-cutting       doc 07 sec 3.9

---

## Blocked items

    Q4  Cell Group placement
        Blocks: Phase 6
        Question: top-level module or Youth sub-module?

---

## Deferred items

    First-timer promotion UI + endpoint
        Deferred from Phase 3. Per doc 06 D2, promotion is manual
        only. The mechanism is folded into Phase 4 (Fellowship),
        which needs the same pattern for shared members.

---

## Resolved items

    [x] Q1   Top-level modules            all six are top-level
    [x] Q2   Members is new entity        yes
    [x] Q2a  Members references           references participants
    [x] Q3   First-Timers is new entity   yes
    [x] Q3a  First-Timers references      references participants
    [x] Q3b  First-Timers lifecycle       hybrid
    [x] Q4   Cell Group placement         (still blocking Phase 6)
    [x] Q5   Mockup authority             approved as-is
    [x] Q6   Workflow stages              four
    [x] Q7   Non-English event names      yes (Unicode-aware rules)
    [x] Q8   Wizard draft-loss fix        deferred
    [x] Q9   TrimStrings middleware       active

---

## Done

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
    [x] 3.10  Controller: ServiceController (index, show, storeOccurrence,
              storeRecurring, destroyOccurrence)
    [x] 3.11  Controller: ServiceAttendanceController (show, store,
              markMember, markFirstTimer, destroyMember,
              destroyFirstTimer)
    [x] 3.12  FormRequests: StoreServiceOccurrence, StoreRecurring,
              AddServiceAttendance, MarkServiceAttendance
    [x] 3.13  Routes: 11 Services endpoints
    [x] 3.14  Rate limiter: service-recurring-batch
    [x] 3.15  Frontend: services/index.tsx (landing)
    [x] 3.16  Frontend: services/show.tsx (per-type occurrence list)
    [x] 3.17  Frontend: services/attendance.tsx (two tracks)
    [x] 3.18  Sidebar: Services entry
    [x] 3.19  Tests: ServiceControllerTest (16 tests)
    [x] 3.20  Tests: ServiceAttendanceControllerTest (17 tests)
    [-] 3.21  First-timer promotion UI + endpoint
              DEFERRED to Phase 4

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

