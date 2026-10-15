# WDEMS -- Church Events -- Task Board

**Status:** Live operational document. Edited in place.

**Policy exception.** Every other file under `docs/church-events/`
is append-only (per P7). This file is not.

**Reference docs:** 01-08 plus `docs/dev-workflow/README.md`.
This file is a checklist over them, not a replacement.

---

## Status legend

    [ ]   not started
    [~]   in progress
    [x]   done
    [!]   blocked
    [-]   deferred / out of scope

---

## Current phase: Phase 2 -- General Events module

**Status:** Blocked by Q5 and Q6. See Blocked section.

**Deliverable:** the `events` table distinguishes running events
from church events. Church events can be created, listed, and
viewed without the running-event fields (distance, accessibility).

**Prerequisite:**

    [!] Q5 (mockup authority) answered
    [!] Q6 (workflow stages) answered

### Tasks

Full task list: doc 07 section 3.3. Not duplicated here.

---

## Future phases -- progress index

    [x] Phase 1   Foundation          COMPLETE -- see Done section
    [!] Phase 2   General Events      blocked by Q5, Q6
    [ ] Phase 3   Services module     doc 07 sec 3.4
    [ ] Phase 4   Fellowship module   doc 07 sec 3.5
    [ ] Phase 5   Discipleship module doc 07 sec 3.6
    [ ] Phase 6   Youth + Cell Group  doc 07 sec 3.7
    [ ] Phase 7   Cross-module        doc 07 sec 3.8
    [ ] Phase 8   Cross-cutting       doc 07 sec 3.9

---

## Blocked items

    Q4  Cell Group placement
        Blocks: Phase 6

    Q5  Mockup authority
        Blocks: Phase 2, 3, 4, 5, 6

    Q6  Workflow stages
        Blocks: Phase 2

    Q7  Non-English event names
        Blocks: Phase 3, possibly Phase 2

    Q8  Wizard draft-loss fix
        Blocks: Phase 2

    Q9  TrimStrings middleware
        Blocks: no phase, affects every text field

---

## Resolved items

    [x] Q1   Top-level modules            all six are top-level
    [x] Q2   Members is new entity        yes
    [x] Q2a  Members references           references participants
    [x] Q3   First-Timers is new entity   yes
    [x] Q3a  First-Timers references      references participants
    [x] Q3b  First-Timers lifecycle       hybrid (new table + unified attendance)

---

## Done

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
    [x] 1.10  npx tsc --noEmit (silent, no frontend changes)
    [x] 1.11  migrate:fresh --seed (clean)
    [x] 1.12  migrate:rollback --step=2 && migrate (clean)
    [x] 1.13  Phase 1 closure appended to doc 06

Test delta: 593 -> 609 passed (+16), 2279 -> 2307 assertions
(+28). Zero regressions.

---

## How to use this file

**Adding a task.** Under the relevant phase. Number sequentially.

**Completing a task.** Change `[ ]` to `[x]`. Do not delete.
Do not renumber.

**Moving a phase to Done.** When all tasks checked, move the
block to Done. Mark the phase in the index as `[x]`.

**When blocked.** Change `[ ]` to `[!]` and add to Blocked.

**When a task changes scope.** Add a new task below it. Mark
the old one `[-]` with a note: "superseded by N.N".

**Frequency.** After every work session.

