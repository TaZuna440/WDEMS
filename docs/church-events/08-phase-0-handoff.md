# WDEMS -- Church Events Phase 0 Handoff

**Purpose:** A single-page review document for the client. Lists
the six developer-side verifications completed, the nine product
decisions still owed, the current state of the code, and the
recommended next action. Fill in the answers at the bottom and
return to the developer.

**Audience:** The client (Church Events stakeholder) and the
developer.

**How to use:** Read sections 1-3. Answer the nine questions in
section 4. Sign section 6. Return to the developer.

**Reference docs (for anyone who needs deeper context):**
- `docs/church-events/01-current-system-audit.md` -- current state
- `docs/church-events/02-product-scope-and-requirements.md` -- scope
- `docs/church-events/03-domain-model-and-workflows.md` -- decisions
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
- `docs/church-events/05-compatibility-and-test-plan.md`
- `docs/church-events/06-open-questions-and-implementation-roadmap.md`
- `docs/church-events/07-scope-and-task-division.md`

---

## 1. Where we are

The Church Events extension has completed its documentation
phase. Six documents describe what exists, what the client wants,
what the architectural options are, and how the work divides into
phases.

**No code has been written.** No migrations, models, controllers,
tests, or configuration have been changed. The running-event
system (Community Run and Fun Run) is fully intact.

**Next step:** answer the nine product decisions in section 4.
Once answered, Phase 1 (Foundation) can begin.

---

## 2. Developer-side verifications -- all complete

Six verifications were required before any implementation could
begin. All six are finished.

| # | Verification | Result |
|---|---|---|
| 6.1 | Baseline test run | 3 skipped, 593 passed (2,279 assertions), 19.51s |
| 6.2 | Enum consumer inventory | EventType: 5 production files. EventStatus: 14 production files. |
| 6.3 | Payment grep | Zero matches. Fun Run has no payment code. |
| 6.4 | Migration status | 36 migrations applied, head at Batch 15 (2026-10-15). |
| 6.5 | Test infrastructure | Pest 5, `RefreshDatabase` bound to Feature suite. P11 confirmed as live hazard. |
| 6.6 | Documentation read | 8 of 8 files read. 14 new findings recorded in doc 06. |

**What the verifications confirmed:**

- The baseline is healthy. Every subsequent phase must preserve
  it.
- Fun Run has no payment code. The earlier concern that it might
  need to be dropped does not match the codebase.
- The running-event system has not been modified. It works.
- The Church Events work is fully additive -- no existing behavior
  needs to change unless a decision explicitly requires it.

**What the verifications uncovered:**

- The wizard has no conditional-step mechanism. Building a
  church-events creation flow through the existing wizard requires
  a structural change. This affects the Phase 2 estimate.
- Two dead fillable columns exist on the `events` table
  (`course_url`, `quiet_space_available`). They are harmless but
  will sit alongside new church columns.
- Non-ASCII event names are rejected by the current validation
  rules. This will affect Filipino event names directly.
- The wizard draft-loss bug (EC-08) will repeat in any new
  wizard-like flow unless it is fixed first.

---

## 3. Current code state

**What has not changed since Phase 0 began:**

    app/               (no files touched)
    bootstrap/         (no files touched)
    config/            (no files touched)
    database/          (no migrations added or modified)
    routes/            (no routes added or modified)
    resources/         (no frontend files touched)
    tests/             (no tests added or modified)

**Test suite status:**

    3 skipped, 593 passed (2279 assertions), 19.51s

**Documentation status:**

    docs/church-events/01-current-system-audit.md
    docs/church-events/02-product-scope-and-requirements.md
    docs/church-events/03-domain-model-and-workflows.md
    docs/church-events/04-feature-specifications-and-acceptance-criteria.md
    docs/church-events/05-compatibility-and-test-plan.md
    docs/church-events/06-open-questions-and-implementation-roadmap.md
    docs/church-events/07-scope-and-task-division.md
    docs/church-events/08-phase-0-handoff.md  (this file)

**Running events:**

    Community Run -- fully working, unchanged
    Fun Run       -- fully working, unchanged, no payment code

---

## 4. Nine product decisions owed

Each question below has:

- **Question** -- what needs deciding
- **Why it matters** -- what it blocks
- **Answer** -- a line for the client to fill in

---

### Q1. Top-level modules

**Question.** Do Services, Fellowship, Discipleship, Youth,
Members, and First-Timers all become top-level modules in the
sidebar, or do some live under others?

**Why it matters.** This determines the information architecture.
The mockups show them as peers; the earlier workflow spec
described them as categories under a single "Church Events"
parent.

**Answer:**

___

___

---

### Q2. Members entity

**Question.** Is "Members" a new entity (a new database table), or
an extension of the existing `participants` table?

**Why it matters.** Members appear in the Services attendance
mockup, the Discipleship enrollment mockup, and the Fellowship
mockup. Every module needs to know what a "member" is.

**Answer:**

___

___

---

### Q3. First-Timers entity

**Question.** Is "First-Timers" a new entity, or a flag/relation
on the existing participant record?

**Why it matters.** First-Timers have their own attendance track
with distinct fields (Invited By). The Services mockup shows
separate Members and First-Timers tabs. Whether they are a new
table or a subset of members affects every attendance query.

**Answer:**

___

___

---

### Q4. Cell Group placement

**Question.** Is "Cell Group" a top-level module, or a sub-module
under Youth?

**Why it matters.** The Youth mockup shows Cell Group in the
sidebar as a peer of Youth, but it also appears in some lists
under Youth activities.

**Answer:**

___

___

---

### Q5. Mockup authority

**Question.** Are the mockups (Services, Fellowship, Discipleship,
Youth) approved designs, or proposals still open to change?

**Why it matters.** If they are approved, the frontend work must
match them. If they are proposals, the developer has flexibility
in layout choices.

**Answer:**

___

___

---

### Q6. Workflow stages

**Question.** Is the ten-stage workflow from the original Church
Event Management specification still in scope, or has it been
replaced by simpler statuses (Upcoming / Ongoing / Completed /
Cancelled)?

**Why it matters.** The dashboard mockup shows only four
statuses. The original spec describes ten. If ten stages are
required, the implementation is much larger and every module
needs its own stage machine.

**Answer:**

___

___

---

### Q7. Non-English event names

**Question.** Will WDEMS host event names in languages other than
English (e.g. Filipino: "Pasko sa Nayon", "Binyag ni Hesus")?

**Why it matters.** The current `HumanNameQualityRules` trait uses
ASCII-only regexes. Non-Latin characters are rejected. If
non-English names are required, the trait needs Unicode character
classes, and the client-side mirror must change in lockstep. This
is a **Phase 2 blocker** if the answer is yes.

**Answer:**

___

___

---

### Q8. Wizard draft-loss fix

**Question.** Should the existing wizard draft-loss bug (the
localStorage snapshot is cleared before the server responds, so a
rejected submission loses the draft) be fixed **before** Church
Events reuses the pattern?

**Why it matters.** Services, Fellowship, and Discipleship will
all need wizard-like creation flows with draft persistence. If
the pattern is not fixed first, all three inherit the same bug.
The fix is small (move the clear call into a success callback).

**Answer:**

___

___

---

### Q9. Text input trimming

**Question.** Is `TrimStrings` middleware active on the web route
group? If not, should text-input trimming be applied globally
before Church Events adds new text fields?

**Why it matters.** The current event fields (event_name, venue,
description) store values as typed, including trailing whitespace.
Church event fields (facilitator, ministry name, notes) will have
the same exposure. A global fix is cleaner than per-field fixes.

**Answer:**

___

___

---

## 5. Recommended next action

**For the client:**

1. Answer the nine questions in section 4.
2. Sign section 6.
3. Return this document to the developer.

**For the developer (after receiving answers):**

1. Record the answers in `docs/church-events/06-open-questions-and-implementation-roadmap.md`
   as an append-only closure block.
2. Update `docs/church-events/02-product-scope-and-requirements.md`
   to move resolved PENDING items to CONFIRMED or PROPOSED status.
3. Update `docs/church-events/07-scope-and-task-division.md` if any
   phase scope changes.
4. Begin Phase 1 (Foundation -- Members, First-Timers, shared
   identity).

**Estimated time to Phase 1 start:** same day as answered
questions are received, assuming the developer has capacity.

---

## 6. Sign-off

**Client:**

Name:                              Date:

Signature (or initial):            ___

**Developer:**

Name:                              Date:

Signature (or initial):            ___

**Notes:** (any additional comments, caveats, or deferrals)

___

___

___

___

---

## 7. What happens if some questions are left blank

If a question is left blank:

- The corresponding phase is deferred until an answer arrives.
- Phases that do not depend on the unanswered question proceed.
- The developer will record the deferral in doc 06 and re-ask
  the question at the next handoff.

If a question is answered "later, decide during implementation":

- The developer will pick the smaller option (least invasive,
  easiest to reverse) and document it as a provisional choice.
- The choice remains open to revision until the phase ships.

**No phase begins until Q1, Q2, and Q3 are answered.** These
three determine the foundational data model. Everything else can
be deferred individually.

---

## 8. Context for the reviewer

**What the client approved in the initial scope (2026-10-16 morning):**

- Extend WDEMS to support Church Events.
- Preserve Community Run and Fun Run.
- Unified dashboard and navigation.
- Reuse existing WDEMS functionality.
- Phased, incremental approach.

**What changed after that approval:**

- The client provided a 13-page workflow specification.
- The client provided seven mockups (Services, Fellowship,
  Discipleship, Youth, dashboard).
- The client provided a "What changed" note that split Services
  and Fellowship into distinct workflows.
- The client provided a full Discipleship feature specification.

**How the documentation responded:**

Docs 02-06 describe the initial scope. Doc 07 records the scope
revision. This document (08) captures the current state and the
nine open questions.

**What has not changed:**

- The running-event system is fully intact.
- No code has been written or modified.
- All six developer-side verifications are complete.
- The recommendation remains: phased, incremental, additive.

