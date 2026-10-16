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

## Current phase: Phase 3 -- Services module

**Status:** READY TO START. Phase 2 backend shipped; Phase 2
frontend work deferred into this phase (see Done section).

**Prerequisite decisions (all resolved):**

    [x] Q1   Top-level modules
    [x] Q5   Mockup authority (approved as-is)
    [x] Q6   Workflow stages (four)
    [x] Q8   Wizard draft-loss (deferred)

**Blocker before Phase 3 tasks:**

    [!] Q7   Non-English event names
             Question: will WDEMS host Filipino event names?

### Tasks

Full task list: doc 07 section 3.4. Not duplicated here.

Phase 2 frontend work folded into Phase 3:
    - /events/create branch for church events
    - /events filter by kind
    - Event detail conditional sections
    - Status pills (Upcoming/Ongoing/Completed/Cancelled)
    - Church event creation tests

---

## Future phases -- progress index

    [x] Phase 1   Foundation          COMPLETE
    [x] Phase 2   General Events      COMPLETE (backend only)
    [~] Phase 3   Services module     ready to start; blocked on Q7
    [ ] Phase 4   Fellowship module   doc 07 sec 3.5
    [ ] Phase 5   Discipleship module doc 07 sec 3.6
    [!] Phase 6   Youth + Cell Group  blocked by Q4
    [ ] Phase 7   Cross-module        doc 07 sec 3.8
    [ ] Phase 8   Cross-cutting       doc 07 sec 3.9

---

## Blocked items

    Q4  Cell Group placement
        Blocks: Phase 6

    Q7  Non-English event names
        Blocks: Phase 3 (Services)
        Question: will WDEMS host Filipino event names?
        If yes: HumanNameQualityRules needs Unicode classes.

---

## Resolved items

    [x] Q1   Top-level modules            all six are top-level
    [x] Q2   Members is new entity        yes
    [x] Q2a  Members references           references participants
    [x] Q3   First-Timers is new entity   yes
    [x] Q3a  First-Timers references      references participants
    [x] Q3b  First-Timers lifecycle       hybrid
    [x] Q5   Mockup authority             approved as-is
    [x] Q6   Workflow stages              four
    [x] Q8   Wizard draft-loss fix        deferred
    [x] Q9   TrimStrings middleware       active

---

## Done

### Phase 2 -- General Events module (2026-10-16, backend only)

    [x] 2.0   Decide church-event status schema
              Chosen: separate church_status column (Option 1)
    [x] 2.1   Migration: add church-event columns to events
    [x] 2.2   Model: extend Event with church fields and helpers
    [x] 2.3   Validation: relax distance and accessibility rules
    [x] 2.4   Controller: create, edit, list, show branches
    [-] 2.5   Frontend: /events/create branch for church events
              DEFERRED to Phase 3
    [-] 2.6   Frontend: /events filter by kind
              DEFERRED to Phase 3
    [-] 2.7   Frontend: event detail conditional sections
              DEFERRED to Phase 3
    [-] 2.8   Frontend: status pills
              DEFERRED to Phase 3
    [-] 2.9   Tests: church event CRUD
              DEFERRED to Phase 3
    [-] 2.10  Run ./scripts/audit-design.sh
              N/A -- no frontend changes
    [x] 2.11  Full suite verification
              3 skipped, 609 passed (unchanged from Phase 1)
    [x] 2.12  migrate:fresh --seed verification
              38 migrations applied cleanly
    [x] 2.13  Phase 2 closure appended to doc 06

Test delta: 0 new tests. Existing 609 tests all pass. Phase 2
shipped without dedicated tests for church-event creation --
this gap is folded into Phase 3 testing work.

### Phase 1 -- Foundation (2026-10-16)

    [x] 1.1   Migration: create_members_table
    [x] 1.2   Model: Member
    [x] 1.3   Filtered test run (no regressions)
    [x] 1.4   Migration: create_first_timers_table
    [x] 1.5   Model: FirstTimer
    [x] 1.6   Participant relations: member(), firstTimers()
    [x] 1.7   MemberTest.php (7 tests)
    [x] 1.8   FirstTimerTest.php (9 tests)
    [x] 1.9   Full suite (3 skipped, 609 passed)
    [x] 1.10  npx tsc --noEmit (silent)
    [x] 1.11  migrate:fresh --seed (clean)
    [x] 1.12  migrate:rollback --step=2 && migrate (clean)
    [x] 1.13  Phase 1 closure appended to doc 06

Test delta: 593 -> 609 passed (+16), 2279 -> 2307 assertions
(+28). Zero regressions.

---

## How to use this file

**Adding a task.** Under the relevant phase. Number sequentially.

**Completing a task.** Change `[ ]` to `[x]`. Do not delete.

**Deferring a task.** Change `[ ]` to `[-]` with a one-line note
stating which phase inherits it.

**Moving a phase to Done.** When all tasks checked or deferred,
move the block. Mark the phase in the index.

**When blocked.** Change `[ ]` to `[!]` and add to Blocked.

