# WDEMS -- Church Events Open Questions and Implementation Roadmap

**Status:** Draft for review. Documentation-only. This is the
final document in the `docs/church-events/` set. It records the
open questions that block implementation and proposes a phased
roadmap, ordered smallest-safe-first. No phase is approved by
being listed here. No code, migration, enum, test, or
configuration was changed to produce this document.

**Audience:** The developer and the client, for the product
review that decides which phases proceed.

**Purpose:** Consolidate the decisions from documents 01-05 into
a single report. Satisfy the final report requirements of the
Church Events scoping prompt. Present the smallest safe first
change and the dependency chain that follows.

**Related:**
- `docs/church-events/01-current-system-audit.md`
- `docs/church-events/02-product-scope-and-requirements.md`
- `docs/church-events/03-domain-model-and-workflows.md`
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
- `docs/church-events/05-compatibility-and-test-plan.md`
- `docs/dev-workflow/README.md`

**Limitation notice:** The auditor has no filesystem access.
Section 6 of this document lists the PENDING-DATA items that
must be resolved before implementation begins. Every verification
needs to be run by the developer.

---

## 0. Reading this document

### 0.1 Structure

This document has two halves.

**Part A (sections 1-5)** is the report. It summarizes what was
found, what can be reused, what must change, what is risky, and
what is unresolved.

**Part B (sections 6-11)** is the roadmap. It lists the
prerequisite verifications, the ten product decisions, the
proposed phases, the definition of done for each phase, and the
compliance confirmation.

### 0.2 Tags

| Tag | Meaning |
|---|---|
| **VERIFIED** | A finding supported by reading a file during the audit. |
| **PENDING-DATA** | A finding that requires a command to be run. |
| **PENDING-PRODUCT** | A decision that requires client input. |
| **PROPOSED** | A suggestion, not an approval. |
| **CONFIRMED** | A directive from the scoping prompt. |

### 0.3 What this document does not do

- It does not select candidates from doc 03.
- It does not approve phases.
- It does not propose schemas or code.
- It does not run any verification.

---

# Part A -- Report

## 1. Documentation files created

Five files were created in `docs/church-events/` during the
documentation phase:

| File | Purpose | Lines (approx) |
|---|---|---|
| `01-current-system-audit.md` | Verified current state, inferred findings, unverified claims, impact map, and the A-F decision analyses. | ~1,445 |
| `02-product-scope-and-requirements.md` | Product objectives, users, in-scope, out-of-scope, and tagged requirements with confirmed/proposed/pending/out markers. | ~613 |
| `03-domain-model-and-workflows.md` | Domain model candidates for ten pending decisions, with consequences, migration requirements, and blocking questions per decision. | ~976 |
| `04-feature-specifications-and-acceptance-criteria.md` | Fourteen feature specs with behavior, flows, validation, errors, permissions, and acceptance criteria. Criteria separated into test-ready and blocked. | ~1,218 |
| `05-compatibility-and-test-plan.md` | Preservation baseline, test categories per feature, migration safety analysis, integration scenarios, risks, rollbacks, and traceability. | ~850 |

**Updated:** None. No existing documentation file was modified.

**Not created:** The six-file set was scoped by the Church
Events documentation prompt. All six were produced; the six
referenced above are 01 through 05 plus this document (06).

---

## 2. Most important verified findings

These are the findings from doc 01 section 1 that matter most
for the Church Events decision. Each is a fact about the current
system, not a proposal.

### 2.1 The event model is running-event-specific

`app/Models/Event.php` carries roughly thirty fillable fields,
most of which are running-event concepts: `distance_value`,
`distance_unit`, eight accessibility booleans, `partners`,
`course_url` (removed from the codebase in commit 6173540).
None of these apply to church events.

The `event_date`, `start_time`, `end_time` triad is single-
occurrence. There is no `parent_event_id`, `series_id`, or
`recurrence_rule`. There is no occurrence concept.

### 2.2 Every event guard branches on status

`Event::canEdit`, `canEditRegistrationForm`,
`canOpenRegistration`, `canCloseRegistration`, `canFinalize`,
`canRecordAttendance`, `canRecordAttendanceNow`, and
`canViewAttendance` all read `$this->status`. Extending
`EventStatus` with church states forces every one of those
methods to be re-read and every consumer of those methods to be
re-tested.

### 2.3 Attendance attaches to registrations, not events

`app/Models/Attendance.php` has `registration_id` as its
foreign key. There is no `occurrence_id`. Church services that
do not have registrations cannot use this table without a
schema change.

### 2.4 No occurrence support exists

Recurring events, multi-session programs, and per-occurrence
attendance are all net-new. This is the largest schema addition
in the proposed scope.

### 2.5 No approval workflow exists

No approver role, no approval table, no approval queue, no
approval audit trail. The workflow spec's Stage 2 (Planning and
Approval) is entirely new.

### 2.6 No category or type tables exist

`EventType` is a two-case PHP enum. The mockup's "Add Category"
button and Categories sidebar entry have no backend support.
Whether they are approved requirements is unresolved (doc 02
PND-01).

### 2.7 No authorization policy exists at all

`EventRequest::authorize()` returns `true`.
`RegistrationMonitorController::ensureEditableRegistration()`
and `RegistrationDeletionController::ensureManageable()` check
event status only, not user identity. ISSUE-004 in
`docs/known-issues.md` describes this for event edit; the audit
found it applies to every event, registration, attendance,
deletion, and report action.

### 2.8 No payment code was found

Every file inspected was free of payment references. The grep
has not been run, so this is not definitive. See section 6.3.

### 2.9 The public registration flow is specialized

`RegistrationVerificationService` handles email OTP, Firebase
phone verification, encrypted challenge payloads, cookie-scoped
auth, and idempotent finalize. Church services and most church
activities do not need any of this. Reusing it for church
events that do not need verification would be incorrect.

### 2.10 The docs corpus is disciplined

Fifteen-plus authoritative docs with an append-only correction
pattern. Church Events documentation has followed this pattern
in docs 01-06.

---

## 3. Existing features that can be reused

These are the existing WDEMS capabilities that satisfy a Church
Events requirement without modification, or with only additive
change.

### 3.1 Fully reusable (no change)

| Feature | Where | Reused by |
|---|---|---|
| Authentication (login, Fortify, session) | `config/fortify.php`, `FortifyServiceProvider` | All users |
| Email 2FA and trusted devices | `EmailTwoFactorService`, `EnsureDeviceIsTrusted` | All users |
| Email verification gate | `EnsureEmailIsVerifiedOrAdmin`, `MustVerifyEmail` | All users |
| User model and roles | `User`, `isAdmin()`, `homePath()` | All users |
| Participant identity resolution | `Participant::resolveFrom`, `normalizeContactNumber` | Church event attendees |
| Name quality rules | `HumanNameQualityRules` | Church event fields |
| Contact and address quality rules | `ContactAndAddressQualityRules` | Church event fields |
| OTP helper | `OtpCode` | Any future OTP path |
| Inertia + React plumbing | `app.tsx`, layouts, hooks | New pages |
| Design system | `docs/design-system.md`, `resources/css/app.css` | New UI |
| Wizard pattern | `resources/js/components/wizard.tsx` | New creation flows |
| Pagination component | `resources/js/components/pagination.tsx` | New list views |
| Confirm dialog pattern | `use-confirm-dialog` hook | New destructive actions |
| Rate limiter pattern | `AppServiceProvider::configureRateLimiters` | New endpoints |

### 3.2 Reusable with additive change

| Feature | Where | What is added |
|---|---|---|
| Event model | `Event` | New nullable columns for church fields, new relations |
| Event creation entry | `EventController::store` | A branch for church events |
| Event listing | `EventController::index` | A kind filter, category display |
| Event detail | `EventController::show`, `events/show.tsx` | Conditional sections for church vs running |
| Event deletion | `EventDeletionService` | Cascade coverage for new child tables |
| Reports | `ReportService`, `ReportController` | Category-aware scoping, possibly new sections |
| Sidebar | `app-sidebar.tsx` | New entries if Category management is approved |
| Dashboard | `DashboardController`, `dashboard.tsx` | Stats scope decision, new widget layout |

### 3.3 Cannot be reused as-is

| Feature | Where | Why not |
|---|---|---|
| Attendance storage | `Attendance`, `AttendanceController` | FK is `registration_id`; church attendance may need occurrences |
| Public registration flow | `PublicRegistrationController`, `RegistrationVerificationService` | Contact verification is not needed for all church activities |
| `EventStatus` state machine | `EventStatus`, `EventWorkflow` | Six running states do not match the ten-stage church workflow |

---

## 4. Components likely to require changes

Grouped by likelihood and phase dependency.

### 4.1 Must change for any Church Events implementation

- `app/Models/Event.php` -- new fillable and cast entries, new
  relations, new guards.
- `app/Http/Controllers/EventController.php` -- creation, edit,
  show, index must handle church events.
- `app/Concerns/EventValidationRules.php` -- distance and
  accessibility rules must not apply to church events.
- `routes/web.php` -- new routes.
- `resources/js/pages/events/create.tsx` and `edit.tsx` --
  branching on event kind.
- `resources/js/pages/events/index.tsx` -- filter and display
  changes.
- `resources/js/pages/events/show.tsx` -- conditional sections.

### 4.2 Changes conditional on decisions

| Component | Conditional on |
|---|---|
| `app/Enums/EventType.php` | Decision 1 (category/type modeling) |
| `app/Enums/EventStatus.php` | Decision 2 (status and lifecycle) |
| `app/Services/EventWorkflow.php` | Decision 2 |
| `app/Http/Middleware/AdvanceEventStatus.php` | Decision 3 (occurrences) |
| `app/Models/Attendance.php` | Decision 3, Decision 8 |
| `app/Http/Controllers/AttendanceController.php` | Decision 3, Decision 8 |
| `app/Models/User.php` | Decision 4 (approval) |
| `app/Services/Reporting/ReportService.php` | Decision 9 (reports) |
| `app/Http/Requests/EventRequest.php` | Decision 1, Decision 10 |
| `app/Services/EventDeletion/EventDeletionService.php` | Any new table |
| `resources/js/app.tsx` | New page families |
| `resources/js/components/app-sidebar.tsx` | Decision 1 (Categories entry) |
| `app/Providers/AppServiceProvider.php` | New rate limiters |
| New policies | Decision 10 |

### 4.3 Must not change without an approved requirement

- Existing `EventStatus` cases.
- `EventWorkflow::transition()` semantics for running events.
- `EnsureRegistrationIsOpen`, `AdvanceEventStatus` running
  behavior.
- `RegistrationVerificationService` (unless church registration
  reuses it).
- The public `/r/{slug}` flow.
- `Participant::resolveFrom` semantics.
- Existing migrations.
- Existing tests, unless a phase explicitly updates them.

---

## 5. Highest-risk architectural decisions

Ranked by impact if chosen incorrectly and cost to reverse.

### 5.1 Decision 2 (status and lifecycle) -- highest risk

Extending `EventStatus` touches ten-plus consumers across
controllers, middleware, services, and frontend. If it breaks
running-event behavior, the rollback is nontrivial. If it is
deferred and the church workflow later needs ten real states,
the deferred work costs more than making the choice now.

### 5.2 Decision 3 (occurrences and attendance) -- highest cost

The occurrence model is net-new schema, new relations, new
write paths, new cascade coverage, and new report scoping. It
interacts with Decision 8 (attendance identity) and Decision 9
(reports). Getting it wrong means reworking the largest new
subsystem.

### 5.3 Decision 1 (category and type modeling) -- highest coupling

Adding church types to the existing enum couples the running
and church paths at the schema and validation level. Keeping
them separate requires a hybrid system that must be maintained
in parallel. Moving everything to DB tables is the most
invasive of the options.

### 5.4 Decision 10 (authorization) -- highest scope creep

Introducing a policy layer for church events exposes that
running events have none. Fixing one without the other leaves
an inconsistency. Fixing both expands scope beyond Church
Events.

### 5.5 Decision 5 (budget) -- highest ambiguity

Three tiers, ranging from "one column" to "a full expense
subsystem." Choosing wrong either under-delivers or
massively over-scopes.

### 5.6 Decisions 4, 6, 7, 8, 9 -- medium risk

Each is a net-new subsystem with its own design space. None
touches existing running-event behavior if scoped narrowly.

---

## 6. Prerequisite verifications (PENDING-DATA)

These must be run before any implementation phase begins. Each
requires the developer, not the auditor.

### 6.1 Baseline test run

    php artisan test
    npx tsc --noEmit
    npm run build

Record the pass count, skip count, and duration. Every phase
compares against this baseline.

### 6.2 Enum consumer inventory

    grep -rn "EventType::" app/ resources/js/ database/ routes/ tests/
    grep -rn "EventStatus::" app/ resources/js/ database/ routes/ tests/

Confirm the audit's consumer lists (doc 01 sections 1.4, 1.5)
are complete before deciding whether to extend either enum.

### 6.3 Payment behavior check

    grep -rn -i "payment\|stripe\|paymongo\|checkout\|paid_at\|amount_paid" \
      app/ resources/js/ database/ routes/ tests/

If empty: Fun Run has no payment behavior; the "out of scope
because of payments" concern is moot. If non-empty: those code
paths must be preserved and documented.

### 6.4 Migration state

    php artisan migrate:status

Confirm which migrations have been applied to the development
database, and confirm the production state if any.

### 6.5 Test file inspection

    cat tests/Pest.php
    find tests -type f -name "*.php" | sort

Confirm the test layout. Establish which tests are the
preservation gate.

### 6.6 Missing documentation read

These files exist in the tree but were not inspected:

    docs/implementation-notes.md
    docs/design-system.md
    docs/event-creation.md
    docs/event-creation-wizard.md
    docs/event-creation-issues.md
    docs/event-deletion.md
    docs/development-log.md
    docs/demo-email.md

At least four are likely to contain constraints relevant to
Church Events. Read them before Phase 1.

---

## 7. Unresolved product decisions

These are the PENDING-PRODUCT items. Each blocks specific
phases.

### 7.1 The ten decisions from doc 03 section 16

| # | Decision | Blocks |
|---|---|---|
| 1 | Category and event type modeling | Phase 1, Phase 2 |
| 2 | Event status and lifecycle | Phase 2, Phase 3, Phase 5 |
| 3 | Occurrence model | Phase 4 |
| 4 | Approval workflow | Phase 5 |
| 5 | Budget and expenses | Phase 7 |
| 6 | Enrollment and completion | Phase 6 |
| 7 | Subgroups | Phase 8 |
| 8 | Attendance entity and identity | Phase 4 |
| 9 | Reports and aggregation | Phase 9 |
| 10 | Event ownership and authorization | Phase 10 |

### 7.2 Additional product questions surfaced by docs 02-05

- **PQ-01.** Is the Church Events workflow specification (13
  pages) an approved requirement or a discovery artifact?
- **PQ-02.** Is the mockup authoritative for product scope or
  exploratory?
- **PQ-03.** Does Personal Events exist as a prior concept, and
  if so, what supersedes it?
- **PQ-04.** Are there any production deployments whose data
  must be preserved during schema changes?
- **PQ-05.** Are there specific performance or retention
  targets for church records?
- **PQ-06.** Which of the nine church categories are
  prioritized for the first release?
- **PQ-07.** Should the Category management UI be visible to
  staff, admins only, or a new role?
- **PQ-08.** Does the church have existing member records
  elsewhere that would need migration into WDEMS?
- **PQ-09.** Are there any existing documents or policies the
  Church Events feature must comply with (privacy, records
  retention, leadership approval)?
- **PQ-10.** What is the release timeline, and is there a hard
  deadline that constrains the phase order?

---

# Part B -- Roadmap

## 8. Recommended implementation phases

Phases are ordered smallest-safe-first. Each phase is
independently shippable, independently revertible, and
preserves the running-event suite as a hard gate.

The phase numbering assumes the decisions from doc 03 section
16 are at least partially resolved. If a decision remains
pending, the corresponding phase is deferred and the phases
before it proceed.

### 8.1 Phase 0 -- Prerequisite verifications

**Depends on:** none.

**Blocks:** every phase.

**Deliverable:** the six verifications in section 6, run and
recorded.

**Changes:** none to code.

**Definition of done:**

- Baseline test count recorded.
- Enum consumer lists confirmed.
- Payment grep run.
- Migration status known.
- Test layout confirmed.
- Missing docs read.

**Risk:** low. This phase is inspection only.

### 8.2 Phase 1 -- Event kind distinction (smallest safe change)

**Depends on:** Decision 1 at least partially resolved.

**Blocks:** every subsequent phase.

**Deliverable:** the `events` table can distinguish running
events from church events. The distinction mechanism is the
chosen candidate for Decision 1 (F1, F2, F3, F4, or F5).

**Why it is smallest:** every subsequent phase adds church-
specific behavior gated on this distinction. Building the
distinction first lets later phases be additive.

**Expected changes:**

- One migration (varies by candidate).
- One model change to `Event` (new column or relation).
- One validation change to `EventValidationRules` so church
  events do not require running-event fields.
- No controller or frontend changes beyond the minimum needed
  to satisfy the schema.

**Preservation gate:** the full running-event suite must pass
unchanged.

**Definition of done:**

- The migration runs cleanly on a fresh database.
- The migration rolls back cleanly.
- Running-event tests pass unchanged.
- One new test asserts that a church event can be created at
  the schema level without running-event fields.
- Doc 06 records the phase.

**Risk:** medium. The migration may touch `event_type`,
`EventType`, or `EventStatus`. See doc 05 RISK-01 and RISK-03.

### 8.3 Phase 2 -- Church event creation and listing

**Depends on:** Phase 1, Decision 1, and one of Decision 2's
candidates.

**Deliverable:** a church event can be created, listed, and
viewed.

**Expected changes:**

- `EventController::create`, `store`, `show`, `index`.
- `EventRequest` validation for church-specific fields.
- Frontend: `events/create.tsx` branching on kind.
- Frontend: `events/index.tsx` filter and display.
- Frontend: `events/show.tsx` conditional sections.

**Preservation gate:** running-event tests pass; running-event
creation flow works manually.

**Definition of done:**

- A church event can be created end-to-end.
- A church event appears on `/events` and on the dashboard.
- Running events are unaffected.
- Doc 04 AC-02.1 through AC-02.10 pass.
- Doc 04 AC-03.1 through AC-03.7 pass.
- Doc 04 AC-04.1 through AC-04.5 pass.
- Doc 06 records the phase.

**Risk:** medium. Frontend changes to shared pages can
regress running-event layouts. See doc 05 RISK-07.

### 8.4 Phase 3 -- Categories and event types (if approved)

**Depends on:** Phase 2, Decision 1 (if DB-managed).

**Deliverable:** administrator-managed categories and types,
if the mockup's requirement is confirmed.

**If Decision 1 chose F1 or F4:** this phase is skipped. The
types are code-level.

**Expected changes:**

- `event_categories` and `event_types` tables.
- Admin CRUD controller and pages.
- Sidebar entry for Categories.
- Validation changes to `EventRequest` to check type belongs
  to category.

**Preservation gate:** running-event tests pass.

**Definition of done:**

- An admin can create a category and a type.
- Types filter by category in the creation dropdown.
- Deactivating a type hides it from creation but preserves
  existing events.
- Doc 04 AC-01.5 through AC-01.11 pass.
- Doc 06 records the phase.

**Risk:** medium. Adds admin UI surface and a new sidebar
entry. See doc 05 RISK-01 and RISK-07.

### 8.5 Phase 4 -- Occurrences and church attendance

**Depends on:** Phase 2, Decision 3, Decision 8.

**Deliverable:** recurring church events with per-occurrence
attendance, if recurring is in scope.

**If Decision 3 chose D1:** this phase is skipped.

**Expected changes:**

- `event_occurrences` table.
- Attendance schema (D2, D3, or D4 candidate).
- Attendance controller branches for occurrence-based mode.
- Occurrence generation logic.
- Frontend: occurrence list view, attendance entry for an
  occurrence.

**Preservation gate:** running-event attendance unchanged;
running-event tests pass.

**Definition of done:**

- A recurring event generates occurrences.
- Attendance can be recorded per occurrence.
- Cancelling one occurrence does not affect the series.
- Doc 04 AC-06.1 through AC-06.6 pass.
- Doc 04 AC-08.1 through AC-08.9 pass.
- Doc 06 records the phase.

**Risk:** high. This is the largest schema addition and
interacts with Decisions 3, 8, and 9.

### 8.6 Phase 5 -- Approval workflow (if approved)

**Depends on:** Phase 2, Decision 2, Decision 4.

**Deliverable:** the proposal and approval flow, if in scope.

**If Decision 4 chose C4 or the workflow spec is not
approved:** this phase is skipped.

**Expected changes:**

- Approval columns or table (per Decision 4 candidate).
- Approval controller actions and routes.
- Frontend: submit proposal, approve, revise, reject flows.
- Reason capture for revisions and rejections.

**Preservation gate:** running-event tests pass; running
events are not affected by the approval flow.

**Definition of done:**

- A proposal can be submitted, approved, revised, rejected.
- Reasons are required and displayed.
- Doc 04 AC-05.1 through AC-05.12 pass (candidate-conditional
  criteria pass for the selected candidate).
- Doc 06 records the phase.

**Risk:** medium. Approval is a new state concept and may
interact with Decision 2.

### 8.7 Phase 6 -- Enrollment and completion (if approved)

**Depends on:** Phase 4, Decision 6.

**Deliverable:** structured-program enrollment and completion
tracking, if in scope.

**Expected changes:**

- Either extend `RegistrationStatus` or add
  `program_enrollments` table.
- Enrollment controller actions.
- Session attendance (if EN3).
- Completion status tracking.
- Frontend: enroll participant, progress view.

**Preservation gate:** running-event registration tests pass.

**Definition of done:**

- Participants can be enrolled.
- Progress is tracked.
- Completion is recorded independently of the parent event.
- Doc 04 AC-09.1 through AC-09.5 pass.
- Doc 06 records the phase.

**Risk:** medium.

### 8.8 Phase 7 -- Budget and expenses (if approved)

**Depends on:** Phase 2, Decision 5.

**Deliverable:** budget tracking, if in scope.

**Expected changes:** vary widely by tier. E1 is small; E3 is
large.

**Preservation gate:** running-event tests pass.

**Definition of done:**

- A budget can be recorded.
- Reconciliation document can be attached (if Tier 1+).
- Line items compute correctly (if Tier 2+).
- Doc 04 AC-11.1 through AC-11.5 pass for the chosen tier.
- Doc 06 records the phase.

**Risk:** medium for E1, high for E3.

### 8.9 Phase 8 -- Subgroups (if approved)

**Depends on:** Phase 3, Decision 7.

**Deliverable:** subgroup assignment and reporting.

**Expected changes:** small if SG1; medium if SG2.

**Preservation gate:** running-event tests pass.

**Definition of done:**

- Subgroups can be assigned.
- Reports can filter by subgroup.
- Doc 04 AC-10.1 through AC-10.3 pass.
- Doc 06 records the phase.

**Risk:** low.

### 8.10 Phase 9 -- Church event reports

**Depends on:** Phase 4, Decision 9.

**Deliverable:** the report surface for church events.

**Expected changes:**

- Extend `ReportService` or add `ChurchReportService`.
- New report pages or extended report pages.
- CSV export.

**Preservation gate:** running-event report tests pass.

**Definition of done:**

- Church event reports render.
- Figures match underlying data.
- CSV export works.
- Doc 04 AC-12.1 through AC-12.5 pass.
- Doc 06 records the phase.

**Risk:** medium.

### 8.11 Phase 10 -- Authorization policy (if approved)

**Depends on:** Decision 10.

**Deliverable:** a policy layer for events and registrations.

**Expected changes:** broad if OWN1 (all events); narrow if
OWN2 (church only).

**Preservation gate:** running-event tests pass. If OWN1 is
chosen, running-event authorization behavior changes and the
change is documented.

**Definition of done:**

- Unauthorized users receive 403.
- Authorized users can perform permitted actions.
- Doc 04 AC-13.1 through AC-13.5 pass.
- Doc 06 records the phase.

**Risk:** high if OWN1 is chosen (expands scope). See doc 05
RISK-06.

### 8.12 Phase 11 -- Dashboard and navigation finalization

**Depends on:** Phases 1-10 as applicable.

**Deliverable:** the unified dashboard matching the mockup.

**Expected changes:**

- Dashboard stats and widgets.
- Sidebar final layout.
- Church Events Categories panel.
- Event Statistics chart.

**Preservation gate:** running-event dashboard unchanged.

**Definition of done:**

- Dashboard renders with the final layout.
- All stats aggregate correctly.
- Doc 04 AC-14.1 through AC-14.5 pass.
- Doc 06 records the phase.

**Risk:** low.

### 8.13 Recommended phase order summary

    Phase 0  -- Prerequisite verifications (inspection only)
    Phase 1  -- Event kind distinction
    Phase 2  -- Church event creation and listing
    Phase 3  -- Categories and types (if approved)
    Phase 4  -- Occurrences and church attendance
    Phase 5  -- Approval workflow (if approved)
    Phase 6  -- Enrollment and completion (if approved)
    Phase 7  -- Budget and expenses (if approved)
    Phase 8  -- Subgroups (if approved)
    Phase 9  -- Church event reports
    Phase 10 -- Authorization policy (if approved)
    Phase 11 -- Dashboard and navigation finalization

Phases 3, 5, 6, 7, 8, and 10 are conditional. If a decision
defers a feature, the phase is skipped. The remaining phases
can proceed independently of that decision.

### 8.14 Dependency graph

    Phase 0
      |
      v
    Phase 1 ---> Phase 2 ---> Phase 3
                   |           |
                   |           v
                   |         Phase 8
                   v
                 Phase 4 ---> Phase 6
                   |           |
                   |           v
                   |         Phase 9
                   v
                 Phase 5
                   |
                   v
                 Phase 7

    Phase 10 and Phase 11 apply across all phases.

No phase beyond Phase 0 can begin until Phase 1 is complete.
Phase 2 must complete before any feature phase.

### 8.15 Smallest safe first change

**Phase 0 is inspection only.** It changes no code.

**Phase 1 is the smallest code change.** It adds the ability
to distinguish church events from running events in the data
model. If Decision 1 resolves to a candidate that requires
new tables (F2/F3/F5), Phase 1 adds those tables. If Decision
1 resolves to F1/F4, Phase 1 adds enum cases. Either way, Phase
1 is one migration (or one enum change) plus the minimum model
and validation adjustments.

Phase 1 delivers:

- The ability to create a church event at the schema level.
- No user-facing behavior change.
- No change to running-event behavior.

Once Phase 1 ships and the running-event suite confirms no
regression, Phase 2 can begin with confidence.

---

## 9. Definition of done for each phase

The reusable template is defined in doc 05 section 10. This
section is the applied version for each phase.

Every phase must satisfy:

**Pre-phase:**

- All prerequisite decisions resolved (see section 7.1).
- All prerequisite verifications run (see section 6).
- Phase scope documented.

**During-phase:**

- Pint passes on new PHP files.
- PHPStan passes.
- `npx tsc --noEmit` is clean.
- Every migration is reversible.
- Every new controller action has a 403 test and a success
  test.
- Every new endpoint has a rate limiter or a documented reason.
- Every new table is added to cascade coverage.

**Post-phase:**

- `php artisan test` -- all green.
- `npx tsc --noEmit` -- clean.
- `npm run build` -- succeeds.
- `php artisan migrate:fresh --seed` -- succeeds.
- `php artisan migrate:rollback --step=N && php artisan migrate`
  -- succeeds.
- Running-event preservation scenarios verified.
- Phase-specific acceptance criteria verified.
- Doc 06 phase record appended.

---

## 10. Final compliance confirmation

**No application source code was modified.** No file under
`app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`resources/js/`, `resources/css/`, `resources/views/`, or
`tests/` was created or changed during the documentation phase.

**No database migrations were added or modified.** No file
under `database/migrations/` was created or changed.

**No enums were modified.** No file under `app/Enums/` was
changed.

**No configuration was changed.** `.env`, `.env.example`,
`composer.json`, `package.json`, `vite.config.ts`, `tsconfig.json`,
`phpunit.xml`, `pint.json`, `phpstan.neon`, and every config
file under `config/` were left unchanged.

**No dependencies were installed.** `composer.lock` and
`package-lock.json` were not updated.

**No tests were added or modified.** No file under `tests/` was
created or changed.

**No commands that modify state were run.** The only commands
run were the heredocs that created the six documentation files
and their verification commands (`wc`, `grep`, `file`).

**Six files were created:**

    docs/church-events/01-current-system-audit.md
    docs/church-events/02-product-scope-and-requirements.md
    docs/church-events/03-domain-model-and-workflows.md
    docs/church-events/04-feature-specifications-and-acceptance-criteria.md
    docs/church-events/05-compatibility-and-test-plan.md
    docs/church-events/06-open-questions-and-implementation-roadmap.md

**One file was appended (append-only, no rewrite):**

    docs/church-events/01-current-system-audit.md
      -- a "Review response (2026-10-16)" block was appended
         after the initial audit to record that the A-F
         recommendations were withdrawn and no alternative was
         selected.

**No existing documentation was modified.** Every other file
under `docs/` was left unchanged.

---

## 11. What happens next

The documentation phase ends with this document. No further
documentation is produced until the client review.

**The developer's next steps:**

1. Run the six verifications in section 6.
2. Read the eight missing documentation files (section 6.6).
3. Present this document and doc 02 section 13 to the client.
4. Obtain decisions on the ten items in section 7.1.
5. Obtain answers on the ten additional questions in section
   7.2.
6. Confirm the phase order in section 8.
7. Begin Phase 0 when ready.

**The client's decision points:**

Each of the ten decisions in section 7.1 is a product choice.
The consequences of each candidate are documented in doc 03.
The acceptance criteria for each candidate are in doc 04. The
tests for each candidate are in doc 05.

**No implementation phase begins until Phase 0 is complete and
the client has resolved at least Decisions 1 and 2.**

---

## 12. Changelog

- **2026-10-16** -- File created. Final report and roadmap of
  the Church Events documentation set. Ten product decisions
  and ten additional questions recorded. Eleven phases proposed
  with definition of done. Compliance confirmation recorded.
  No code, migrations, enums, tests, or configuration changed.


---

## Phase 0 verification results (2026-10-16)

Append-only. Section 6 of this document listed six verifications
required before Phase 1 begins. Four are now complete. This
block records the results and updates the status of each.

### 6.1 Baseline test run -- COMPLETE

Command:

    php artisan test

Result:

    Tests:    3 skipped, 593 passed (2279 assertions)
    Duration: 19.51s

The three skipped tests are in
`tests/Feature/Settings/SecurityTest` and skip because Fortify's
`two-factor-authentication` feature is disabled. This matches the
expected skip documented in `docs/authentication.md` Trap #6.

**Preservation baseline for every subsequent phase:** 3 skipped,
593 passed. Any phase that changes this number requires an
explicit append to this document explaining the delta.

**Notable coverage confirmed by the run:**

- `Tests\Feature\Events\` -- 28 test files, all passing.
- `Tests\Feature\Public\` -- 7 test files, all passing.
- `Tests\Feature\Auth\` -- 8 test files, all passing.
- `Tests\Feature\Participants\` -- 2 test files, all passing.
- `Tests\Feature\Settings\` -- 2 test files, 3 skipped.
- `Tests\Unit\Services\` -- 2 test files, all passing.
- `Tests\Integration\RegistrationVerificationConcurrencyTest`
  did not run in the default suite. This is consistent with the
  concurrency test's documented requirement for a real MySQL
  database (see `docs/registration-contact-verification.md`).

### 6.2 Enum consumer inventory -- NOT RUN

The two greps for `EventType::` and `EventStatus::` were not
included in the outputs provided. This verification remains
open.

### 6.3 Payment grep -- COMPLETE

Command:

    grep -rn -i "payment\|stripe\|paymongo\|checkout\|paid_at\|amount_paid" \
      app/ resources/js/ database/ routes/ tests/

Result: **no matches.**

**Finding:** Fun Run has no payment behavior in the current
codebase. The concern raised in earlier conversation that "Fun
Run might be out of scope because of payments" does not match
the implementation. The scoping prompt's directive stands: Fun
Run remains in scope; its behavior is unchanged.

**Effect on doc 01 Sec. 1.10:** the audit's "not a definitive
negative" caveat is resolved. The finding is now definitive.

**Effect on doc 01 section B:** the B2-then-B1 alternative is
settled. There is no payment code to preserve.

**Effect on doc 05 Sec. 5.1:** "Current state (PENDING-DATA)" is
resolved. Section 5.2's "if the grep is empty" branch applies:
no payment tests are required.

### 6.4 Migration state -- COMPLETE

Command:

    php artisan migrate:status

Result: 36 migrations, all `Ran`. Batches 1 through 15.

**Observations:**

- The migration history matches the audit's file listing.
- `2026_09_16_160308_create_event_options_table` and
  `2026_09_16_160335_create_registration_options_table` show as
  `Ran` even though both tables were dropped by
  `2026_09_25_180100_drop_event_options_scaffolding`. This is
  correct Laravel behavior -- the migration history records
  execution, not current schema state.
- The most recent migration is
  `2026_10_15_100000_add_code_acknowledged_at_to_registration_verification_challenges_table`
  (Batch 15), consistent with the code_acknowledged_at feature
  shipped on 2026-10-15.
- No migrations show as pending. The development database is at
  the head of the migration chain.

### 6.5 Test file inspection -- NOT RUN

`cat tests/Pest.php` was not included in the outputs provided.
This verification remains open.

### 6.6 Missing documentation read -- PARTIAL

Command:

    ls -la docs/implementation-notes.md docs/design-system.md \
           docs/event-creation.md docs/event-creation-wizard.md \
           docs/event-creation-issues.md docs/event-deletion.md \
           docs/development-log.md docs/demo-email.md

Result: all eight files exist. Sizes confirmed:

| File | Bytes | Last modified |
|---|---|---|
| `docs/implementation-notes.md` | 15,880 | 2026-10-14 |
| `docs/design-system.md` | 13,472 | 2026-10-15 |
| `docs/event-creation.md` | 33,082 | 2026-09-25 |
| `docs/event-creation-wizard.md` | 33,288 | 2026-10-15 |
| `docs/event-creation-issues.md` | 30,807 | 2026-09-25 |
| `docs/event-deletion.md` | 22,334 | 2026-09-25 |
| `docs/development-log.md` | 10,719 | 2026-09-25 |
| `docs/demo-email.md` | 10,236 | 2026-09-25 |

The files have not been read. This verification remains open.

**Observation:** `implementation-notes.md` and `design-system.md`
have modification dates close to the shipping of recent features
(2026-10-14 and 2026-10-15 respectively), suggesting they were
updated alongside the Church Events documentation effort or the
attendance code / finalize features. Their content is likely the
most current and most directly relevant to Phase 1 decisions.

### Updated Phase 0 status

| Verification | Status |
|---|---|
| 6.1 Baseline test run | COMPLETE |
| 6.2 Enum consumer inventory | OPEN |
| 6.3 Payment grep | COMPLETE |
| 6.4 Migration state | COMPLETE |
| 6.5 Test file inspection | OPEN |
| 6.6 Missing documentation read | PARTIAL (files confirmed to exist, not read) |

**Two of six complete. Two partial or open. Three items remain
before Phase 1 begins.**

### Updated decision statuses

The following items in section 7 of this document change status
based on the verification results.

- **Decision 7.2 (Fun Run payments).** Resolved. No payment code
  exists. Fun Run is a free event with the same behavior as
  Community Run in every inspected code path. The client may
  still decide to make Fun Run paid in the future; nothing in
  the current codebase prevents that.
- **PQ-04 (production deployments).** Not yet resolved by these
  commands. The migration status shows the dev database, not
  any production database. Requires client confirmation.

### What is still required before Phase 1

**Developer side (two open commands):**

    grep -rn "EventType::" app/ resources/js/ database/ routes/ tests/
    grep -rn "EventStatus::" app/ resources/js/ database/ routes/ tests/
    cat tests/Pest.php

**Developer side (two open reads):**

- Read the eight documentation files confirmed to exist in 6.6.

**Client side (unchanged):**

- The six product decisions from doc 07 section 5.

### Effect on doc 06 section 8 (roadmap)

Phase 0 is partially complete. Section 8.1's definition of done
is partially satisfied. Phases 1 through 11 remain blocked on
Phase 0 completion plus the client decisions in doc 07 section
5.

No phase has been approved. No code, migration, enum, test, or
configuration has been changed.


---

## Phase 0 verification results -- closure (2026-10-16)

Append-only. Section 6.2 and section 6.5 are now complete. This
block records the results, closes the remaining developer-side
Phase 0 verifications, and updates the effect on downstream
documents.

### 6.2 Enum consumer inventory -- COMPLETE

Commands:

    grep -rn "EventType::" app/ resources/js/ database/ routes/ tests/
    grep -rn "EventStatus::" app/ resources/js/ database/ routes/ tests/

#### EventType consumers (complete list)

| File | Line | Usage |
|---|---|---|
| `app/Http/Controllers/EventController.php` | 110 | `EventType::cases()` for the wizard dropdown |
| `app/Concerns/EventValidationRules.php` | 62 | `Rule::enum(EventType::class)` |
| `app/Models/Event.php` | 86 | `event_type` cast |
| `app/Models/Event.php` | 358 | `isCommunityRun()` |
| `app/Models/Event.php` | 363 | `isFunRun()` |
| `tests/Feature/Events/EventWorkflowTest.php` | 27 | Test fixture |

**Total: 5 production locations, 1 test fixture.**

**Effect on doc 01 section 1.4.** The audit's list is confirmed
complete. No additional consumers exist.

**Effect on doc 03 section 2.2.** The consequences table for
candidate F1/F4 overstates the cost. Extending `EventType`
touches five production files, all of which are simple `match`
or `Rule::enum` updates. F1 and F4 are materially cheaper than
doc 03 estimated.

#### EventStatus consumers (complete list)

Production files:

| File | Approx. hits |
|---|---|
| `app/Models/Event.php` | 15 |
| `app/Http/Controllers/DashboardController.php` | 10 |
| `app/Http/Controllers/RegistrationMonitorController.php` | 9 |
| `app/Services/EventWorkflow.php` | 8 |
| `app/Http/Controllers/RegistrationController.php` | 6 |
| `app/Http/Controllers/EventController.php` | 4 |
| `app/Services/Reporting/ReportService.php` | 4 |
| `app/Http/Controllers/AttendanceController.php` | 3 |
| `app/Http/Middleware/AdvanceEventStatus.php` | 3 |
| `app/Http/Controllers/RegistrationDeletionController.php` | 2 |
| `app/Http/Controllers/ReportController.php` | 2 |
| `app/Concerns/EventValidationRules.php` | 2 |
| `app/Http/Middleware/EnsureRegistrationIsOpen.php` | 1 |
| `app/Services/RegistrationVerification/RegistrationVerificationService.php` | 1 |

Test files:

| File | Note |
|---|---|
| `tests/Feature/Events/EventWorkflowTest.php` | Many, plus two hard-shape assertions |
| `tests/Feature/Events/EventFinalizeTest.php` | 8 |
| `tests/Feature/Events/EventAutoAdvanceTest.php` | 10 |

**Total: 14 production files, 3 test files.**

**Two consumers were not in the audit's list** (doc 01 section
1.5):

- `app/Concerns/EventValidationRules.php` lines 331-332:
  `validateEventNameUnique()` excludes `EventStatus::Completed`
  and `EventStatus::Cancelled` from the duplicate-name check.
- `app/Http/Controllers/RegistrationController.php` lines
  29-57: the queue page filters events by `Draft`,
  `RegistrationOpen`, and `RegistrationClosed`.

The audit's list is therefore incomplete. Downstream documents
that depend on it must be updated.

**Effect on doc 03 section 3.2.** The consequences table for
A1 (extend `EventStatus`) is confirmed as the most invasive
candidate. A2 and A3 remain safer.

#### Hard test gate discovered

`tests/Feature/Events/EventWorkflowTest.php` contains three
assertions that pin the exact shape of `EventStatus`:

    line 39: expect(EventStatus::values())->not->toContain('configured');
    line 43: expect(EventStatus::tryFrom('configured'))->toBeNull();
    line 47: expect(EventStatus::cases())->toHaveCount(6);

If Decision 2 resolves to A1 or A4 (extend `EventStatus`),
**line 47 will fail.** Editing it requires an explicit reason
recorded per doc 05 section 2.5. This is the only test edit
this audit can predict with certainty.

### 6.5 Test file inspection -- COMPLETE

Command:

    cat tests/Pest.php

Findings:

- `RefreshDatabase` is bound to the entire `Feature` suite:

      pest()->extend(TestCase::class)
          ->use(RefreshDatabase::class)
          ->in('Feature');

- No custom test helpers exist beyond a placeholder
  `something()` function.
- The `toBeOne` expectation extension is a Laravel starter-kit
  leftover with no usage in the suite.

**Effect on doc 05 section 6.7.** The P11 concern is confirmed
as a live hazard. Any new migration file dropped into
`database/migrations/` runs on every Feature test immediately
after save.

### Updated Phase 0 status -- final

| Verification | Status |
|---|---|
| 6.1 Baseline test run | COMPLETE |
| 6.2 Enum consumer inventory | COMPLETE |
| 6.3 Payment grep | COMPLETE |
| 6.4 Migration state | COMPLETE |
| 6.5 Test file inspection | COMPLETE |
| 6.6 Missing documentation read | OPEN (files confirmed to exist; contents not read) |

**Five of six developer-side verifications complete.** Only 6.6
remains: reading the eight documentation files.

### Corrections to earlier documents

Append-only. These corrections supersede specific claims in docs
01-05.

**Doc 01 section 1.5.** The EventStatus consumer list is
incomplete. Add:
- `app/Concerns/EventValidationRules.php` (duplicate-name check).
- `app/Http/Controllers/RegistrationController.php` (queue page).

**Doc 01 section 1.4.** The EventType consumer list is
confirmed complete. No correction needed; the audit's
qualification ("could not verify this list is complete") is now
removed.

**Doc 03 section 2.2.** The F1 and F4 cost estimates are too
high. Extending `EventType` touches five production files, not
the broader surface the doc implied.

**Doc 05 section 2.5.** A new acceptable reason for editing an
existing test is confirmed:
`tests/Feature/Events/EventWorkflowTest.php` line 47
(`toHaveCount(6)`) must be updated if Decision 2 extends
`EventStatus`.

**Doc 05 section 6.7.** P11 is confirmed as a live hazard, not
a theoretical one. No change to the rules; the warning is
amplified.

### What is still required before Phase 1

**Developer side:**

- Read the eight documentation files confirmed to exist in
  section 6.6.

**Client side:**

- The six product decisions from doc 07 section 5.

### Effect on doc 06 section 8 (roadmap)

Phase 0 is complete on the developer side except for doc reads.
No phase has been approved. No code, migration, enum, test, or
configuration has been changed.


## Encoding note

The Phase 0 verification results block added on 2026-10-16
contains non-ASCII characters that rendered as mojibake in the
terminal. The content is correct; the characters are not. This
is a paste-channel artifact, not a content error. The block is
otherwise readable.


---

## Phase 0.6 partial closure -- documentation reads (2026-10-16)

Append-only. Section 6.6 of this document listed eight
documentation files as uninspected. Four have now been read.
This block records findings relevant to Church Events and
corrects earlier documents where the newly-read files
contradict them.

### 6.6 status -- partial

| File | Bytes | Read |
|---|---|---|
| docs/implementation-notes.md | 15,880 | Yes |
| docs/design-system.md | 13,472 | Yes |
| docs/event-creation-wizard.md | 33,288 | Yes |
| docs/event-creation.md | 33,082 | Yes |
| docs/event-creation-issues.md | 30,807 | No |
| docs/event-deletion.md | 22,334 | No |
| docs/development-log.md | 10,719 | No |
| docs/demo-email.md | 10,236 | No |

**Four of eight complete.** The four remaining are lower priority
for Phase 1: `event-creation-issues.md` is a backlog file,
`event-deletion.md` covers a subsystem the Church Events work will
extend but not modify, `development-log.md` is narrative, and
`demo-email.md` is operational.

### Findings relevant to Church Events

#### F-1. The `events` table carries preserved-but-unused columns

From `event-creation-wizard.md` corrections (2026-10-15) and the
`Event` model:

| Column | Status |
|---|---|
| `faq` | Column preserved on schema. Not in `Event::$fillable`. Never editable via UI. Seeded once on create via `EventController::defaultFaq()`. |
| `course_url` | Column preserved on schema. **Still in `Event::$fillable`.** Removed from wizard UI, validation, controller payload, and public form in commit 6173540. |
| `quiet_space_available` | Column preserved on schema. **Still in `Event::$fillable`.** Removed from the accessibility grid in commit 6173540. The grid now has 8 flags, not 9. |
| `rsvp_required` | Column dropped by migration 2026_09_25_170000. Not in fillable. Not in UI. Fully removed. |

**Two dead fillable entries exist today** (`course_url`,
`quiet_space_available`). Mass-assignment can set them; no code
path does. This is a pre-existing inconsistency, not introduced
by Church Events, but it will complicate any migration that adds
new columns to `events`.

**Effect on doc 03 section 12.** The migration matrix does not
account for the presence of dead columns. A future migration
that adds church-event fields will sit alongside them. Whether
to clean them up first is a decision worth making explicitly.

#### F-2. The wizard is structurally running-event-shaped

Every step component in the wizard has running-event-specific
fields hard-coded:

| Step | Fields | Church-relevant? |
|---|---|---|
| Basics | `event_type`, `event_name`, `description`, accessibility flags | Partially |
| Schedule | `event_date`, `start_time`, `end_time`, `distance_value`, `distance_unit` | Distance is running-only |
| Venue | `venue`, `venue_address`, `venue_latitude`, `venue_longitude` | Coordinates are running-specific |
| Extras | `partners` | Running-event concept |

**The wizard shell does not support conditional steps.** The
`WizardStepConfig` type has `fields` and `requiredFields` but no
`visibleWhen` or `skipWhen` mechanism. Adding a branch requires
either:

1. Extending `WizardStepConfig` with a conditional field.
2. Rendering church events through a separate wizard.
3. Using the existing wizard for both, with the step components
   conditionally hiding running-only inputs.

**Effect on doc 07 section 3.3.** The estimate for Phase 2
("`/events/create` branch for church events") is too optimistic.
The wizard has no built-in branching. This is a substantive
frontend refactor, not a minor addition.

#### F-3. `event_type` is locked in edit mode, backend and frontend

From `event-creation.md` Trap #2 and `event-creation-wizard.md`
section 9.2:

- The edit page passes a single-element `eventTypes` array.
- The `Select` is disabled via an `isEdit` prop.
- `EventController::update()` does not include `event_type` in
  its update array.

**Effect on doc 02 section 9.2.** EVT-01's "unified event
creation" cannot reuse the wizard for church events without
either unlocking `event_type` or introducing a parallel path.
Neither is trivial.

#### F-4. The design system imposes specific UI rules

`design-system.md` establishes:

- Four-layer model (base gradient / image overlay / glass
  surfaces / content).
- Three glass tiers (A: chrome, B: content panels, C: rows).
- Mode contract (what flips between light and dark).
- Semantic color table.
- **Warning rule: yellow in dark mode, navy (`#13253B`) in light
  mode. Every yellow utility must have a `dark:` override.**
- `text-on-image` utility for text over the raw image only.
- Six anti-patterns.
- Enforcement script: `scripts/audit-design.sh`.

**Effect on docs 04 and 07.** Every new church-events UI
(Services landing, Fellowship list, Discipleship dashboard,
Youth module) must comply. The audit script must run before
commit on any UI change. `npx tsc --noEmit` alone is not
sufficient for UI PRs.

**Specific compliance obligations for the Church Events work:**

- The Services landing page (five service cards) -- Tier B card
  surfaces, Tier C rows.
- The Fellowship type selector sidebar -- Tier A.
- The Discipleship program overview stats -- Tier B.
- Every status pill -- check the warning rule.
- The Categories admin UI (if built) -- Tier B for the table,
  Tier C for rows.

#### F-5. Implementation notes reveals additional invariants

`implementation-notes.md` documents seven non-obvious behaviors.
Three are directly relevant:

**Entry 1 -- Wizard error merge order.** Client errors win over
server errors on key collision. Commit 3ef048e fixed this. Any
new wizard branch (church events) must not swap the spread
order.

**Entry 2 -- `HumanNameQualityRules` shared across three fields.**
`event_name`, `partners.*.name`, and registration form builder
`label`. If church events add new free-text fields with similar
quality requirements, they must reuse this trait, not duplicate
it.

**Entry 5 -- Rate limiter inventory is now 11, not 5.** The
original entry listed 5. The 2026-10-14 correction records the
current state: 8 in `configureRateLimiters()`, 3 in
`configureRegistrationVerificationLimits()`. Any new church-events
endpoint must register in the appropriate method.

#### F-6. Stale references in wizard documentation

Both `event-creation-wizard.md` and `event-creation.md` reference
things that no longer exist in the codebase:

| Reference in doc | Actual state |
|---|---|
| `EventOptionController` (both docs) | File does not exist in the tree |
| `EventOption` model (both docs) | Deleted in registration Phase 2 |
| `app/Http/Controllers/EventOptionController.php` | Not in tree |
| "Event Options" flow (both docs, Sec. 7.1) | Removed |
| `rsvp_required` field (both docs) | Dropped by migration 2026_09_25_170000 |
| `EventStatus` "Seven states" (`event-creation.md` Sec. 2) | Six states. Configured removed. |
| `canConfigure()` (both docs) | Renamed to `canEditRegistrationForm()` |
| "9 accessibility flags" (both docs) | 8 flags after quiet_space_available removal |
| `course_url` in Schedule step (both docs) | Removed in commit 6173540 |

**Note.** The corrections blocks at the bottom of each doc do
address some of these (course_url removal, quiet_space removal,
AccessibilityGrid move). But they do not address the removal of
`EventOptionController` and `EventOption`, the removal of
`rsvp_required`, the `EventStatus` count, or the `canConfigure()`
rename. These are live inaccuracies.

**Effect on doc 06 section 6.6.** The remaining four unread docs
may contain similar stale references. When they are read, the
same correction process applies.

### Corrections to earlier documents from this phase

**Doc 01 section 1.1.** The `events` table also carries
`course_url` and `quiet_space_available` as preserved-but-unused
columns. `rsvp_required` was dropped, not preserved. The audit
did not distinguish these cases.

**Doc 01 section 1.5.** `EventStatus` has six cases, not seven.
The Configured case was removed in Phase 1 of the registration
plan (commit ec88543). Any doc still describing seven cases is
stale.

**Doc 02 section 9.2 (EVT-01).** "Unified event creation and
listing" is confirmed as a requirement, but the implementation
path is more complex than the doc implied. The wizard does not
support conditional step rendering. See F-2.

**Doc 03 section 12.** The migration matrix does not account for
dead columns on `events`. See F-1.

**Doc 05 section 10.3.** The post-phase checklist must add:

    ./scripts/audit-design.sh

before the `npx tsc --noEmit` step for any UI phase.

**Doc 07 section 3.3.** The Phase 2 estimate for
`/events/create` branch is too optimistic. See F-2.

### Updated Phase 0 status

| Verification | Status |
|---|---|
| 6.1 Baseline test run | COMPLETE |
| 6.2 Enum consumer inventory | COMPLETE |
| 6.3 Payment grep | COMPLETE |
| 6.4 Migration state | COMPLETE |
| 6.5 Test file inspection | COMPLETE |
| 6.6 Missing documentation read | PARTIAL (4 of 8) |

**Five of six complete. One partial.** The remaining four
documents are lower priority and can be read during Phase 1 or
Phase 2 as their subsystems come into scope.

### What is still required before Phase 1

**Developer side:**

- Optionally read the remaining four docs (`event-creation-issues.md`,
  `event-deletion.md`, `development-log.md`, `demo-email.md`).
- The four are not blockers for Phase 1. Only `event-creation-issues.md`
  might surface constraints, and it is a backlog file.

**Client side:**

- The six product decisions from doc 07 section 5.

### New findings for doc 07

Two new blockers or risks surfaced by this phase:

**N-1 (blocker for Phase 2).** The wizard has no conditional step
mechanism. Building a church-events creation flow through the
existing wizard requires extending `WizardStepConfig` or
introducing a parallel wizard. This changes the Phase 2 scope.

**N-2 (housekeeping decision).** Two dead fillable entries exist
on the `Event` model (`course_url`, `quiet_space_available`).
Whether to clean them up before Phase 2 or leave them for a
future cleanup is a decision.

Both are worth surfacing to the client alongside the six
decisions already owed.

### No code, migration, enum, test, or configuration was changed

This phase read four documentation files and appended this
record. No production code was touched.


---

## Phase 0 closure -- final documentation reads (2026-10-16)

Append-only. Section 6.6 is now complete. This block records the
findings from the final four documentation files and formally
closes Phase 0 on the developer side.

### 6.6 status -- COMPLETE

| File | Bytes | Read |
|---|---|---|
| docs/implementation-notes.md | 15,880 | Yes |
| docs/design-system.md | 13,472 | Yes |
| docs/event-creation-wizard.md | 33,288 | Yes |
| docs/event-creation.md | 33,082 | Yes |
| docs/event-creation-issues.md | 30,807 | Yes |
| docs/event-deletion.md | 22,334 | Yes |
| docs/development-log.md | 10,719 | Yes |
| docs/demo-email.md | 10,236 | Yes |

**Eight of eight complete.** Phase 0 developer side is finished.

### Findings relevant to Church Events

#### F-7. The wizard draft-loss pattern will repeat

`event-creation-issues.md` EC-08 records a real defect:

- `wizard.tsx::handleSubmit()` calls `clear()` on the localStorage
  snapshot BEFORE the server responds.
- If the server rejects with a validation error, the draft is gone.
- The user has to re-enter everything.

**Effect on Church Events.** Services, Fellowship, and Discipleship
will all need wizard-like creation flows with draft persistence.
If they copy the wizard pattern verbatim, they inherit this bug.
Doc 04's feature specs for these modules must include a
`clear-on-success-only` requirement, or the pattern must be fixed
first.

**Recommendation.** The fix to `wizard.tsx` should land before
Church Events reuses the pattern. This is a small, isolated change
(move `clear()` into an `onSuccess` callback) and prevents three
future modules from inheriting the same defect.

#### F-8. Text inputs are not trimmed

`event-creation-issues.md` EC-07 records that text inputs are
stored as typed, with no trailing whitespace trimming.

**Effect on Church Events.** Every church event field
(event_name, venue, description, facilitator, notes) has the same
exposure. If `TrimStrings` middleware is active in
`bootstrap/app.php`, the issue is moot. If not, the same EC-07
decision applies.

**Verification required.** Grep `bootstrap/app.php` for
`TrimStrings` in the `web` middleware group. If absent, EC-07 is
live and will affect all new church fields.

#### F-9. Non-ASCII name rules will affect Filipino event names

`event-creation-issues.md` EC-10 records that `HumanNameQualityRules`
uses ASCII-only regexes. Names like "Angstrom Run" are rejected.

**Effect on Church Events.** Church events will routinely use
Filipino names: "Pasko sa Nayon", "Binyag ni Hesus", "Panalangin
para sa Bayan". If these contain no ASCII vowel in a word the
regex processes, they may be rejected.

**Recommendation.** Before Phase 2, decide the answer to EC-10's
product question: will WDEMS host non-English event names? If yes,
the regexes need `\p{L}` Unicode character classes, and the change
must be mirrored to `humanNameQualityError()` in the client.

This is a Phase 2 blocker if the answer is yes. It is a Phase 2
non-issue if the answer is no.

#### F-10. The deletion doc is stale but the concept is not

`event-deletion.md` Sec. 5 lists the ordered delete as six steps:

    1. Collect registration IDs
    2. Delete attendances
    3. Delete registration_options       <- table dropped
    4. Delete registrations
    5. Delete event_options              <- table dropped
    6. Delete the event

Steps 3 and 5 reference tables dropped in registration Phase 2.
The doc is stale.

**However**, the underlying principle holds: `EventDeletionService`
uses explicit ordering (rather than relying on cascade) as a
safety measure that survives schema changes. The current
implementation (verified in doc 01) uses four steps:

    1. Collect registration IDs
    2. Delete attendances
    3. Delete registrations
    4. Delete the event

**Effect on Church Events.** Every new child table (service
occurrences, fellowship events, discipleship enrollments, etc.)
must be added to this ordering. Doc 05 Sec. 4.6 already requires
cascade tests per new table; this confirms the implementation
pattern.

**Also stale.** `event-deletion.md` Sec. 9 Trap #7 still describes
the RESTRICT constraint on `registration_options.event_option_id`
as the reason for the ordering. That constraint no longer exists.
The ordering is retained as a design choice, not a constraint.

#### F-11. No test for deletion transaction failure

`event-deletion.md` Sec. 11 item 6 records:

> No test for transaction failure. If
> `EventDeletionService::delete()` throws mid-transaction, the
> rollback behavior is not covered by any test.

**Effect on Church Events.** When the service is extended to
cascade through new tables, the failure path becomes more complex.
A test for rollback behavior should be added alongside any
cascade extension.

#### F-12. Deliberate wizard design decisions to preserve

`event-creation.md` Trap list documents eleven things that must
not be changed. Two are directly relevant to Church Events:

**Trap #6 -- HumanNameQualityRules regexes.** Five rules that
catch keyboard mashing. Any church-events field that reuses the
trait must not simplify or replace these rules.

**Trap #2 -- `event_type` is locked after creation.** Frontend
disabled, backend ignores the update. Adding a church event type
does not change this. If a church event ever needs a type change,
it is a separate feature.

#### F-13. The partner validation gap will repeat

`event-creation-issues.md` EC-12 records no test coverage for
partner duplicate and quality rules. FIX-018 and FIX-020 shipped
without matching tests.

**Effect on Church Events.** The same pattern will apply to any
new validation rule in church-events work: rules get written,
tests do not get written, coverage silently degrades. Doc 05
Sec. 3.x lists test categories for each feature, but the categories
are aspirational unless they are enforced per phase.

**Recommendation.** Every Church Events phase with new validation
rules must include tests for those rules in the same commit. This
should be a hard gate in doc 06's phase checklist, not a "should".

#### F-14. Implementation notes confirms shared-trait discipline

`implementation-notes.md` Entry 2 records that
`HumanNameQualityRules` is shared across three fields
(`event_name`, `partners.*.name`, registration form label). The
trait is the single source of truth for keyboard-mashing
detection.

**Effect on Church Events.** Any new free-text field in a church
event (facilitator name, ministry name, etc.) must reuse this
trait, not duplicate its regexes. Duplication would produce
inconsistent behavior across the codebase.

`implementation-notes.md` Entry 5 also confirms the rate limiter
inventory is now 11 (not 5 as originally written). Any new
church-events endpoint must register a limiter in
`AppServiceProvider`.

### Corrections to earlier documents from this phase

**Doc 01.** No corrections needed. The audit did not read these
four files, so it could not have contained errors about them.

**Doc 05 Sec. 4.6.** The cascade coverage requirement is now
confirmed as the correct pattern. The doc's list of new tables
per candidate remains accurate, but the implementation must
follow the ordered-delete pattern (not rely on cascade alone).

**Doc 05 Sec. 6.2.** The `EventDeletionService` extension for new
tables now has a specific test requirement: rollback behavior on
transaction failure. Add this to the cascade test coverage list.

**Doc 06 Sec. 7.2.** Add three new product questions to the list:

- **PQ-11.** Will WDEMS host non-English event names? This
  determines whether `HumanNameQualityRules` needs Unicode
  character classes.
- **PQ-12.** Should the wizard draft-loss bug (EC-08) be fixed
  before Church Events reuses the pattern, or deferred?
- **PQ-13.** Is `TrimStrings` middleware active? If not, should
  EC-07's fix be applied globally before Church Events adds new
  text fields?

### Phase 0 status -- final

| Verification | Status |
|---|---|
| 6.1 Baseline test run | COMPLETE |
| 6.2 Enum consumer inventory | COMPLETE |
| 6.3 Payment grep | COMPLETE |
| 6.4 Migration state | COMPLETE |
| 6.5 Test file inspection | COMPLETE |
| 6.6 Missing documentation read | COMPLETE |

**All six developer-side verifications are complete.** Phase 0 is
finished on the developer side.

### What remains before Phase 1

**Client side only.** The six product decisions from doc 07 Sec.
5, plus the three additional questions added above (PQ-11, PQ-12,
PQ-13).

Once those nine answers arrive, Phase 1 can begin.

### Summary of new findings from Phase 0

Across the six Phase 0 verifications, the following new facts
about the codebase emerged that earlier documents did not record:

| # | Finding | Source | Effect |
|---|---|---|---|
| F-1 | Dead fillable columns exist | Wizard doc | Migration matrix needs adjustment |
| F-2 | Wizard has no conditional steps | Wizard doc | Phase 2 estimate too optimistic |
| F-3 | `event_type` locked in edit | Wizard doc + code ref | Unified creation needs new design |
| F-4 | Design system has 6 anti-patterns and an audit script | Design system | Every UI PR must run `./scripts/audit-design.sh` |
| F-5 | Rate limiter inventory is 11, not 5 | Implementation notes | New endpoints register in correct method |
| F-6 | Wizard docs reference 6 removed symbols | Wizard docs | Live inaccuracies in docs |
| F-7 | Draft-loss bug pattern will repeat | EC issues | Fix wizard before reuse |
| F-8 | No trimming on text inputs | EC issues | Verify TrimStrings middleware |
| F-9 | Non-ASCII names rejected | EC issues | Directly affects Filipino event names |
| F-10 | Deletion doc is stale but concept holds | Deletion doc | Cascade pattern is per-new-table |
| F-11 | No transaction failure test | Deletion doc | Add to cascade test list |
| F-12 | Two wizard traps must be preserved | EC code ref | Do not change `event_name` regexes, do not unlock `event_type` |
| F-13 | Validation without tests is a pattern | EC issues | Enforce tests per phase |
| F-14 | Shared traits are the discipline | Implementation notes | Reuse `HumanNameQualityRules` |

### No code, migration, enum, test, or configuration was changed

This phase read four documentation files and appended this
record. No production code was touched.


---

## Client answers -- Q1, Q2, Q3 (2026-10-16)

Append-only. The client has answered the first three product
questions. These were the Phase 1 blockers (see doc 07 section
4). This block records the answers, resolves the corresponding
statuses, and surfaces three new sub-decisions that Phase 1 must
address before any migration can be written.

### Q1 -- Top-level modules: YES

The client confirms that all six modules become top-level:

    Services
    Fellowship
    Discipleship
    Youth
    Members
    First-Timers

Plus the existing:

    Events (general, unified -- includes running events)
    Attendance (cross-module)
    Reports (cross-module)
    Dashboard
    Settings

**Status:** RESOLVED.

**Effect:** Supersedes OBJ-05 and OBJ-06 in doc 02 (the "nine
categories under one parent" framing). The sidebar structure in
the client mockups is confirmed as the target information
architecture.

**Doc 07 section 3.7 (Phase 6) impact:** Cell Group placement
remains open (Q4). Everything else in Phase 6 is confirmed.

### Q2 -- Members is a new entity: YES

The client confirms that `members` is a new database table, not
an extension of `participants`.

**Status:** RESOLVED for the "new table" question. One
sub-decision remains.

**Sub-decision Q2a.** Does `members` reference `participants`
via a foreign key, or is it a fully independent table?

Two candidate shapes:

**Shape 1 -- `members` references `participants`.**
`members` becomes a table with `participant_id` (foreign key,
unique), plus church-specific fields (membership_date, cell
group, ministry, cell leader, etc.). The `participants` table
continues to own the identity keys (email, normalized phone).
Members are participants with a membership record.

- Benefit: reuses the identity model (`resolveFrom`,
  `normalizeContactNumber`, unique constraints). No duplicate
  identity resolution logic.
- Cost: `members` cannot hold a person who has never been a
  participant of any event. First-time walk-ins would need a
  `participants` row created first.
- Fit with the mockups: high. Members appear as people who
  attend services; services generate participant records.

**Shape 2 -- `members` is fully independent.**
`members` becomes a new identity table with its own email and
normalized phone columns, unique constraints, and resolution
logic. It does not reference `participants`.

- Benefit: church membership is a distinct concept; a member
  is a member even if they have never attended a WDEMS event.
- Cost: duplicates the identity model. Two tables with the same
  purpose (identify a person by email or phone). Cross-module
  reporting must union them. First-Timer linking (Q3) becomes
  ambiguous across two identity tables.
- Fit with the mockups: medium. The mockups show Members and
  Participants as separate sidebar entries, but the entry for
  Participants is "Members" while event attendance also uses
  participants. The distinction is unclear.

**Recommendation: Shape 1.** The identity model in
`Participant` is sophisticated and well-tested (see
`ParticipantIdentityConstraintsTest`,
`ParticipantIdentityResolverTest`). Reimplementing it in a
separate table would produce two systems for the same problem.
Church members are also attendees of church events, so a
`members.participant_id` foreign key is natural.

**However.** This decision is the client's. Both shapes are
defensible; the recommendation is a preference, not a
requirement.

### Q3 -- First-Timers is a new entity: YES

The client confirms that `first_timers` is a new database table.

**Status:** RESOLVED for the "new table" question. Two
sub-decisions remain.

**Sub-decision Q3a.** Does `first_timers` reference
`participants` (like Q2a Shape 1), or is it independent?

Same trade-off as Q2a. Recommendation: reference
`participants`. A first-timer is a participant on their first
visit.

**Sub-decision Q3b.** What happens to a first-timer over time?

The mockups show a First-Timers tab with its own attendance
track. The workflow spec says:

    First-Timer attends -> Profile created -> Returns at a
    later service -> Organizer searches existing profile ->
    Organizer confirms link -> Existing profile is reused ->
    Attendance history continues

This describes a *link and merge* flow. A first-timer record
becomes a permanent member record after the second or third
attendance, at the organizer's discretion.

Two candidate designs:

**Design A -- First-Timers is a persistent state.**
A first-timer stays a first-timer forever, or until an organizer
manually promotes them. The `first_timers` table holds a
`promoted_at` column. Attendance entries reference either
`members` or `first_timers`.

- Benefit: simple. First-timer status is explicit.
- Cost: two attendance tables. Cross-module reporting must
  union them.

**Design B -- First-Timers is a temporary state that lives on
`members`.** Every attendee is a member row. `members` has an
`is_first_timer` flag or a `first_attended_at` timestamp. The
First-Timers tab is a view (filter) on `members`, not a
separate table.

- Benefit: one attendance table. Cross-module reporting is
  simple.
- Cost: contradicts the client's "First-Timers is a new entity"
  answer, unless the new entity is a side record (e.g.,
  `first_timer_intake` storing Invited By and first-visit
  details) while membership status lives on `members`.

**Recommendation: a hybrid.** Create `first_timers` as a new
table (matching the client's answer). It references
`participants` and stores first-visit details (`invited_by`,
`first_service_date`, `follow_up_notes`). Attendance for both
Members and First-Timers attaches to the same underlying
`participants` row via a `service_attendance` table. The
First-Timers tab in the UI is a filtered view of service
attendance for participants who have a `first_timers` row and
who are not yet promoted to `members`.

This preserves the client's "new entity" answer, avoids a
second identity model, and keeps attendance unified.

**However.** This design is a proposal, not a decision. The
client should confirm before Phase 3 (Services) begins.

### Summary of resolved vs. open

| Question | Status |
|---|---|
| Q1 -- Top-level modules | RESOLVED |
| Q2 -- Members is new entity | RESOLVED (sub-decision Q2a open) |
| Q3 -- First-Timers is new entity | RESOLVED (sub-decisions Q3a, Q3b open) |
| Q4 -- Cell Group placement | OPEN |
| Q5 -- Mockup authority | OPEN |
| Q6 -- Workflow stages | OPEN |
| Q7 -- Non-English event names | OPEN |
| Q8 -- Wizard draft-loss fix | OPEN |
| Q9 -- TrimStrings middleware | OPEN |

**Phase 1 blockers cleared.** Phase 2 blocked only by Q5 and Q6.

### Sub-decisions needed before Phase 1 code

Three sub-decisions must be answered before any migration is
written:

1. **Q2a.** Does `members` reference `participants` or stand
   alone?
2. **Q3a.** Does `first_timers` reference `participants` or
   stand alone?
3. **Q3b.** Is First-Timers a persistent state or a temporary
   status that promotes to membership?

Recommendations are provided above but are not decisions.

### Effect on doc 07 section 3.2 (Phase 1)

Phase 1's blockers are resolved. Phase 1 can begin once the
three sub-decisions above are answered. The task list in doc 07
section 3.2 remains accurate; the recommendations above narrow
the design space.

### No code, migration, enum, test, or configuration was changed

This block records three client answers. No production code was
touched.


---

## Sub-decision answers -- Q2a, Q3a, Q3b (2026-10-16)

Append-only. The client has accepted the recommendations
recorded in the previous block. All three Phase 1 sub-decisions
are now resolved. This block records the accepted answers, states
the schema direction they produce, and restates Phase 1's scope
for confirmation before any migration is written.

### Q2a -- Members references participants

**Accepted:** Shape 1.

`members` is a new table with a foreign key to `participants`.
The `participants` table continues to own identity (email,
normalized phone, name, age, address). The `members` table
carries church-specific fields on top.

**Working schema sketch (subject to implementation review):**

    members
      id                          bigint PK
      participant_id              FK -> participants, UNIQUE
      membership_status           string (active, inactive, transferred)
      membership_date             date, nullable
      cell_group                  string, nullable
      ministry                    string, nullable
      cell_leader                 string, nullable
      joined_via                  string, nullable
      notes                       text, nullable
      created_at, updated_at

The `UNIQUE` on `participant_id` ensures a participant can hold
at most one member record. This is the natural reading of "one
person, one membership."

### Q3a -- First-Timers references participants

**Accepted:** Same pattern as Q2a.

`first_timers` is a new table with a foreign key to
`participants`.

**Working schema sketch (subject to implementation review):**

    first_timers
      id                          bigint PK
      participant_id              FK -> participants
      first_service_date          date, nullable
      invited_by                  string, nullable
      follow_up_notes             text, nullable
      promoted_at                 timestamp, nullable
      promoted_to_member_id       FK -> members, nullable
      created_at, updated_at

The `participant_id` is NOT unique. A first-timer who lapses and
returns later can accumulate multiple first-timer records if the
church wants that history. The promotion columns track when a
first-timer became a member.

### Q3b -- First-Timers is a hybrid state

**Accepted:** Hybrid design.

`first_timers` is a new table (client's answer to Q3). It
references `participants` (Q3a). It stores first-visit details.
Attendance for both Members and First-Timers attaches to the
same underlying `participants` row via a unified
`service_attendance` table.

**The First-Timers tab in the UI is a filtered view**, not a
separate data store:

- Show participants who have a `first_timers` row where
  `promoted_at IS NULL`.
- Hide participants who have been promoted.

**Promotion.** When an organizer confirms a first-timer is now
a member:

1. A `members` row is created for the same `participant_id`.
2. The `first_timers` row is updated with `promoted_at = now()`
   and `promoted_to_member_id = <new members.id>`.
3. The first-timer's historical attendance is preserved (it
   already lives on `participants`, so nothing moves).
4. The First-Timers tab no longer shows the participant.

**The first_timers row is never deleted.** It becomes a
historical record of the participant's first-visit details.
This matches the project's append-only ethos (P7) and preserves
the "Invited By" data even after promotion.

### What these three answers produce

A single identity model shared across running events, church
services, fellowship, discipleship, and youth:

    participants
      identity: email, normalized phone, name, age, address
       |
       +-- registrations          (running-event registration)
       +-- members                (church membership, one per person)
       +-- first_timers           (first-visit records, zero or more)
       +-- service_attendance     (Members + First-Timers attendance)
       +-- fellowship_attendance  (assigned per event group)
       +-- discipleship_enrollments (per program + batch)
       +-- youth_*_attendance     (per sub-module)

**No table besides `participants` holds an identity key.** Every
church-event attendance, enrollment, or membership attaches to
`participants.id`. Cross-module reporting (e.g., "how many
services has this person attended across all ministries?") is a
join through `participants.id`, not a union across identity
tables.

### Updated resolution status

| Question | Status |
|---|---|
| Q1 -- Top-level modules | RESOLVED |
| Q2 -- Members is new entity | RESOLVED |
| Q2a -- Members references participants | RESOLVED |
| Q3 -- First-Timers is new entity | RESOLVED |
| Q3a -- First-Timers references participants | RESOLVED |
| Q3b -- First-Timers is hybrid | RESOLVED |
| Q4 -- Cell Group placement | OPEN |
| Q5 -- Mockup authority | OPEN |
| Q6 -- Workflow stages | OPEN |
| Q7 -- Non-English event names | OPEN |
| Q8 -- Wizard draft-loss fix | OPEN |
| Q9 -- TrimStrings middleware | OPEN |

**All Phase 1 blockers cleared.** Phase 2 blocked only by Q5 and
Q6. Phase 3 (Services) requires Q7 (non-ASCII names) resolved.

### Phase 1 restated

Phase 1 (Foundation) is the first code phase. Its scope is
unchanged from doc 07 section 3.2. It builds:

1. Migration: `create_members_table`
2. Migration: `create_first_timers_table`
3. Model: `Member`
4. Model: `FirstTimer`
5. Relations: `Participant hasOne Member`,
   `Participant hasMany FirstTimer`
6. Service: member resolution helpers (if needed)
7. Cascade: `EventDeletionService` unaffected (these tables do
   not descend from `Event`)
8. Tests: member creation, first-timer linking, promotion, unique
   constraint on `members.participant_id`, cascade behavior if
   participant is deleted

**Phase 1 does not build:**

- Services, Fellowship, Discipleship, Youth UI (Phases 3-6)
- Attendance tables (deferred to their respective phases)
- Promotion UI (deferred to Phase 3, where Services attendance
  creates the trigger)

**Files touched by Phase 1 (predicted):**

    database/migrations/XXXX_XX_XX_XXXXXX_create_members_table.php          (new)
    database/migrations/XXXX_XX_XX_XXXXXX_create_first_timers_table.php     (new)
    app/Models/Member.php                                                   (new)
    app/Models/FirstTimer.php                                               (new)
    app/Models/Participant.php                                              (relations added)
    tests/Feature/Participants/MemberTest.php                               (new)
    tests/Feature/Participants/FirstTimerTest.php                           (new)

**Preservation gate:** the full test suite (593 passed, 3
skipped) must still pass after Phase 1 lands. Any phase that
changes the number requires an append to this document
explaining the delta.

**Risks for Phase 1:**

- P11: `RefreshDatabase` runs every migration in the directory on
  every Feature test. The migration files must not be saved to
  disk until the models and relations they support are ready.
  Write the migration and the model in the same change block.
- The `members.participant_id` UNIQUE constraint could conflict
  with existing data if any `participants` row is pre-linked.
  Phase 0 confirmed no such data exists in the dev database.
- No production data exists (PQ-04 unconfirmed, but PQ-04 was
  answered for dev only). If production exists, backfill becomes
  a consideration.

### Next action

Phase 1 can begin. Before the first migration is written, the
developer confirms:

1. **Begin Phase 1?** Yes / No.
2. **Migration timestamps.** Suggest using the current date
   (2026-10-16) at a plausible time. The developer chooses the
   exact times.
3. **Model naming.** `Member` and `FirstTimer` (singular, Pascal
   case) match Laravel conventions. Confirm or override.
4. **Any local uncommitted changes** that should be committed
   before Phase 1 begins.

Once confirmed, the first block is the `members` migration plus
the `Member` model in the same heredoc pair, followed by
verification. Per P4, one block per message.

### No code, migration, enum, test, or configuration was changed

This block records three sub-decision answers. No production
code was touched.


---

## Client answers -- Q5, Q6, Q8 (2026-10-16)

Append-only. Three more product decisions resolved. This block
records the answers, updates the resolution status, and states
which phases unblock.

### Q5 -- Mockup authority: APPROVED AS-IS

The client confirms that the mockups (Services, Fellowship,
Discipleship, Youth, dashboard) are approved designs. The
frontend work builds to match them.

**Status:** RESOLVED.

**Effect:** the developer has no flexibility to adjust layout,
component structure, or visual hierarchy beyond what the
mockups show. Any deviation requires client approval.

**Effect on Phase 2:** the General Events module UI must match
the dashboard mockup's event list layout.

**Effect on Phases 3-6:** Services, Fellowship, Discipleship,
and Youth UIs build to their respective mockups without
redesign.

**Design system implication:** the mockups follow the existing
WDEMS design system (glass tiers, warning color rule, sidebar
structure). `scripts/audit-design.sh` must pass on every UI
commit in these phases.

### Q6 -- Workflow stages: FOUR

The client confirms that the ten-stage workflow from the
original Church Event Management specification is not the
target. The four statuses from the dashboard mockup are:

    Upcoming
    Ongoing
    Completed
    Cancelled

**Status:** RESOLVED.

**Effect:** supersedes the ten-stage chain described in
`docs/church-events/02-product-scope-and-requirements.md`
PND-02 and the Church Event Management workflow specification
Stages 1-5.

**Effect on Decision 2 (doc 03 section 3):** the A1/A2/A3/A4
alternatives are all resolved to "A4-like": church event status
is a simple four-value machine, not a ten-stage state machine
and not a parallel church status enum.

**Effect on Phase 2:** the `events` table needs to distinguish
running-event statuses from church-event statuses. Because
running events use six statuses (`Draft`, `RegistrationOpen`,
`RegistrationClosed`, `Ongoing`, `Completed`, `Cancelled`) and
church events use four (`Upcoming`, `Ongoing`, `Completed`,
`Cancelled`), the simplest design is a separate column on
`events` for church status, or a new table. The choice is a
Phase 2 design decision, not a new client question.

**Not resolved by this answer:**

- Whether the four statuses are automatic (derived from dates
  and completion) or manually set by the organizer.
- Whether `Cancelled` requires a reason.
- Whether `Completed` triggers any side effects (e.g., locking
  the event from further edits, similar to the running-event
  `finalize` flow).

These are implementation details to be decided during Phase 2,
not client questions.

### Q8 -- Wizard draft-loss fix: DEFERRED

The client accepts that the wizard's draft-loss bug (EC-08)
stays in place. Services, Fellowship, and Discipleship flows
that reuse the wizard pattern will inherit the same behavior.

**Status:** RESOLVED as deferred.

**Effect:** no fix to `wizard.tsx` before Phase 2. The bug stays
documented in `docs/event-creation-issues.md` EC-08.

**Risk accepted:** a rejected submission on a church-event
creation flow will lose the draft. The user must re-enter data.
This is a known annoyance, not a blocker.

### Q9 -- TrimStrings middleware: verification pending

The grep returned no matches in `bootstrap/app.php`. This is
inconclusive -- Laravel 11+ defines `TrimStrings` in the default
global middleware stack, not in the app's `web` group.

**Status:** OPEN pending one command (see the closure block for
Q9 when it lands).

**Verification command:**

    php artisan tinker --execute="
    \$kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    \$r = new ReflectionClass(\$kernel);
    \$p = \$r->getProperty('middleware');
    \$p->setAccessible(true);
    foreach (\$p->getValue(\$kernel) as \$m) {
        echo (is_string(\$m) ? \$m : get_class(\$m)) . PHP_EOL;
    }
    "

If `Illuminate\Foundation\Http\Middleware\TrimStrings` appears
in the output, the middleware is active and EC-07 is already
solved. If not, Phase 2 adds it.

### Updated resolution status

| Question | Status |
|---|---|
| Q1 -- Top-level modules | RESOLVED |
| Q2, Q2a -- Members entity | RESOLVED |
| Q3, Q3a, Q3b -- First-Timers | RESOLVED |
| Q4 -- Cell Group placement | OPEN (blocks Phase 6) |
| Q5 -- Mockup authority | RESOLVED (approved as-is) |
| Q6 -- Workflow stages | RESOLVED (four stages) |
| Q7 -- Non-English event names | OPEN (blocks Phase 3) |
| Q8 -- Wizard draft-loss fix | RESOLVED (deferred) |
| Q9 -- TrimStrings middleware | OPEN (verification pending) |

### Phase status after these answers

| Phase | Status | Blocker |
|---|---|---|
| Phase 2 -- General Events | UNBLOCKED | none |
| Phase 3 -- Services | Blocked by Q7 | non-ASCII names |
| Phase 4 -- Fellowship | UNBLOCKED | none |
| Phase 5 -- Discipleship | UNBLOCKED | none |
| Phase 6 -- Youth + Cell Group | Blocked by Q4 | Cell Group placement |
| Phase 7 -- Cross-module | UNBLOCKED | none |
| Phase 8 -- Cross-cutting | UNBLOCKED | none |

**Phase 2 can begin immediately.** Phase 3 is the next
sequential blocker, via Q7.

### No code, migration, enum, test, or configuration was changed

This block records three client answers. No production code was
touched.


---

## Q9 closure -- TrimStrings is active (2026-10-16)

Append-only. The Q9 verification command returned the full global
middleware stack. Result:

    Illuminate\Foundation\Http\Middleware\TrimStrings
    Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull

**Status:** RESOLVED.

**Finding:** `TrimStrings` is active on all routes by default in
Laravel 11+. Text inputs are already trimmed before validation
runs. EC-07 (documented in `docs/event-creation-issues.md`) is
not a live bug -- it was only ever a hypothesis.

**Effect on Church Events:** every new text field in Services,
Fellowship, Discipleship, and Youth automatically inherits
trimming. No per-field work needed.

**Effect on the earlier audits:** the audit's F-8 finding (from
the Phase 0.6 documentation reads) was conditional -- "if
TrimStrings is active, the issue is moot." It is active. F-8 is
resolved as a non-issue.

**Side note.** `ConvertEmptyStringsToNull` is also active. This
means an empty form field arrives as `null` on the server, not
`''`. The existing validation rules already account for this
(they use `nullable` and `required` interchangeably as needed).
No action required.

### Updated resolution status

| Question | Status |
|---|---|
| Q1 -- Top-level modules | RESOLVED |
| Q2, Q2a -- Members entity | RESOLVED |
| Q3, Q3a, Q3b -- First-Timers | RESOLVED |
| Q4 -- Cell Group placement | OPEN (blocks Phase 6) |
| Q5 -- Mockup authority | RESOLVED |
| Q6 -- Workflow stages | RESOLVED (four) |
| Q7 -- Non-English event names | OPEN (blocks Phase 3) |
| Q8 -- Wizard draft-loss fix | RESOLVED (deferred) |
| Q9 -- TrimStrings middleware | RESOLVED (active) |

**Seven of nine decisions resolved.** Q4 and Q7 remain.

### Phase status

| Phase | Status | Blocker |
|---|---|---|
| Phase 2 -- General Events | UNBLOCKED | none |
| Phase 3 -- Services | Blocked by Q7 | non-ASCII names |
| Phase 4 -- Fellowship | UNBLOCKED | none |
| Phase 5 -- Discipleship | UNBLOCKED | none |
| Phase 6 -- Youth + Cell Group | Blocked by Q4 | Cell Group placement |
| Phase 7 -- Cross-module | UNBLOCKED | none |
| Phase 8 -- Cross-cutting | UNBLOCKED | none |


---

## Phase 2 closure -- General Events backend (2026-10-16)

Append-only. Phase 2 landed as a backend-only unit. This block
records what shipped, what was deferred, and the effect on later
phases.

### What shipped

One new enum:

    app/Enums/ChurchEventStatus.php

One extended enum:

    app/Enums/EventType.php    (added Church case + runningTypes())

One migration:

    database/migrations/2026_10_16_110000_add_church_event_fields_to_events_table.php

Adds three columns to events: event_kind (discriminator,
indexed), church_category, church_status. Defaults existing
rows to event_kind = 'running'.

One extended model:

    app/Models/Event.php
      + event_kind, church_category, church_status fillable entries
      + church_status cast to ChurchEventStatus
      + KIND_RUNNING, KIND_CHURCH constants
      + isChurchEvent(), isRunningEvent(), statusLabel()

One extended validation trait:

    app/Concerns/EventValidationRules.php
      + currentEventKind() resolver
      + eventRules() conditionally branches on event_kind
      + validateEventNameUnique() scopes by event_kind and
        branches on church_status vs status
      + four new validation messages

One extended controller:

    app/Http/Controllers/EventController.php
      + index(Request) accepts ?kind= filter
      + index/show/edit payloads include event_kind,
        church_category, church_status
      + status/status_label dispatch through isChurchEvent()
      + create() offers EventType::runningTypes() only
      + store() branches on event_kind (church fields null for
        running events, running fields null for church events)
      + update() mirrors store() branching; event_kind and
        event_type remain locked after creation

### What was deferred

Frontend work. The four tasks below moved to Phase 3:

    /events/create branch for church events
    /events?kind= filter on the events index
    Event detail conditional sections
    Status pills for the four church statuses

Rationale, per the session discussion: no dedicated mockup for
the General Events create or detail pages exists. Building them
now would risk throwaway work when the Services module lands
and its frontend establishes the church-event UI patterns.

Church event creation is available via direct POST with the
right payload. The UI does not yet expose the option.

### Test delta

    0 new tests written for Phase 2.
    609 existing tests continue to pass (3 skipped).
    2279 assertions unchanged.

This is an honest gap. Phase 2 shipped backend capabilities
without test coverage. The eventual Phase 3 tests must cover
church-event creation, the kind filter on the index, and the
status label dispatch. Recorded so it is not silently inherited.

### Verification

    php artisan test
    -> 3 skipped, 609 passed (2307 assertions)

    php artisan migrate
    -> all migrations applied, including the new one
    -> full migrate:fresh --seed took ~30 seconds post-config-change

    php -l on every modified file
    -> clean

Diff reviews confirmed:
    Event.php            -- additions only
    EventValidationRules.php -- additions + two methods rewritten
    EventController.php  -- additions + three methods rewritten

### Effect on later phases

Phase 3 (Services) is unblocked on Phase 2 completion and
inherits the frontend work. Q7 (non-English event names) remains
the Phase 3 blocker.

Phases 4 through 6 depend on Phase 2's schema (event_kind,
church_category, church_status) being stable. It is.

### No code, enum, or configuration was changed outside this scope

Files touched:

    app/Enums/ChurchEventStatus.php            (new)
    app/Enums/EventType.php                    (extended)
    app/Models/Event.php                       (extended)
    app/Concerns/EventValidationRules.php      (extended)
    app/Http/Controllers/EventController.php   (extended)
    database/migrations/2026_10_16_110000_*.php (new)

Nothing else in app/, bootstrap/, config/, routes/, resources/,
or tests/ was modified.

