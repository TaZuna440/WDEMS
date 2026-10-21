# WDEMS -- Church Events Compatibility and Test Plan

**Status:** Draft for review. Documentation-only. No code,
migrations, tests, or configuration were changed to produce this
document. No compatibility decision is selected; every
candidate-conditional item names the decision it depends on.

**Audience:** Whoever executes document 06 and whoever implements
and verifies the accepted features.

**Purpose:** Establish how compatibility is preserved, how new
behavior is verified, how migrations are made safe, and what
risks and rollbacks apply. This document converts the acceptance
criteria in document 04 into a test plan and adds migration and
rollback analysis that document 04 does not cover.

**Related:**

- `docs/church-events/01-current-system-audit.md`
- `docs/church-events/02-product-scope-and-requirements.md`
- `docs/church-events/03-domain-model-and-workflows.md`
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
- `docs/dev-workflow/README.md` -- especially P11 (migrations are
  live in tests)

**Limitation notice (repeated):** The auditor has no filesystem
access. Test file contents were not inspected during the audit.
Every claim about existing tests is either inferred from file
names or from the standard Laravel/Pest conventions declared in
`composer.json` and `phpunit.xml`. Claims that require test
contents are marked PENDING-DATA.

---

## 0. Reading this document

### 0.1 Tags

| Tag              | Meaning                                                              |
| ---------------- | -------------------------------------------------------------------- |
| **MUST-PASS**    | A test that must pass unchanged before any phase is merged.          |
| **NEW**          | A test that must be written as part of a phase.                      |
| **CONDITIONAL**  | A test whose existence depends on a decision from doc 03 section 16. |
| **PENDING-DATA** | A claim that requires reading a file the auditor has not seen.       |
| **RISK**         | A named failure mode and its mitigation.                             |
| **ROLLBACK**     | A defined path to undo a change.                                     |

### 0.2 Traceability

Every MUST-PASS and NEW item below traces to either:

- An acceptance criterion in doc 04 (labeled `AC-NN.N`).
- A preservation requirement in doc 02 section 8 (`PRES-NN`).
- A migration in doc 03 section 12.1.

If a test cannot be traced to one of those, it is a gap and is
listed in section 12.

### 0.3 What this document does not do

- It does not select candidates.
- It does not propose new schemas.
- It does not change existing tests.
- It does not run any test.

---

## 1. Existing test infrastructure

### 1.1 What the audit established (verified)

- Framework: Pest 5, with `pestphp/pest-plugin-laravel`.
- `tests/Pest.php` binds `RefreshDatabase` to the `Feature`
  suite.
- `phpunit.xml` sets `DB_CONNECTION=sqlite` and
  `DB_DATABASE=:memory:`.
- `composer.json` scripts:
    - `test` -> `@lint:check` -> `@types:check` ->
      `@php artisan test`.
    - `ci:check` -> `npm run check` -> `npm run types:check` ->
      `@test`.
- Pint and PHPStan are in the CI chain via `lint:check` and
  `types:check`.

### 1.2 What the audit could not establish (PENDING-DATA)

- Test file contents.
- Exact test counts per suite.
- Which tests assert on `EventStatus` transitions.
- Which tests assert on `EventType` values.
- Which tests assert on attendance behavior tied to
  `registration_id`.
- Which tests touch the public registration flow end-to-end.
- Whether `tests/Integration/` runs by default or is gated (the
  audit noted a concurrency test that requires a real MySQL
  database; the gating mechanism was not inspected).

**PENDING-DATA command required:**

    cat tests/Pest.php
    find tests -type f -name "*.php" | sort
    php artisan test

The output of the last command establishes the baseline pass
count that every phase must preserve.

### 1.3 Suite layout (from tree, file names only)

    tests/Feature/Auth/
    tests/Feature/Events/
    tests/Feature/Participants/
    tests/Feature/Public/
    tests/Feature/Settings/
    tests/Integration/
    tests/Support/
    tests/Unit/Services/

Names seen in the tree (contents not inspected):

Auth: AuthenticationTest, DeviceVerificationTest,
EmailVerificationGatingTest, EmailVerificationTest,
LogoutDeviceTrustTest, PasswordConfirmationTest,
PasswordResetTest, VerificationNotificationTest.

Events: many, including AttendanceBulkEncodingModeTest,
AttendanceBulkMarkTest, AttendanceBulkWalkInTest,
AttendanceDateGateTest, AttendanceEncodingModeTest,
AttendanceForceOpenTest, AttendanceTest,
AttendanceVerificationTest, EventAutoAdvanceTest,
EventCommonFieldRequirementsTest, EventDateRulesTest,
EventDeletionAdminTest, EventDeletionStaffTest,
EventDuplicateNameTest, EventFinalizeTest, EventScheduleRulesTest,
EventStartTimeValidationTest, EventUrlRulesTest, EventWorkflowTest,
RegistrationDeletionTest, RegistrationFormCommonFieldRequirementsTest,
RegistrationFormIdentityRuleTest, RegistrationFormTest,
RegistrationLabelQualityTest, RegistrationMonitorActionsTest,
RegistrationMonitorExtrasTest, RegistrationMonitorTest,
RegistrationSlugTest, ReportControllerTest, ReportServiceTest,
WalkInCustomFieldsTest.

Participants: ParticipantIdentityConstraintsTest,
ParticipantIdentityResolverTest.

Public: ParticipantNameValidationTest,
PublicRegistrationIdentityResolutionTest,
PublicRegistrationIdentityRuleTest,
RegistrationConfirmationMailTest, RegistrationSubmissionTest,
RegistrationVerificationFirebaseTest, RegistrationVerificationTest.

Settings: ProfileUpdateTest, SecurityTest.

Integration: RegistrationVerificationConcurrencyTest.

Support: FinalizeVerification, PublicVerification.

Unit: Services/EmailTwoFactorServiceTest,
Services/EventDeletionOtpServiceTest.

---

## 2. Running-event regression baseline

These tests must pass unchanged at the end of every phase. Any
phase that breaks one of these is rejected before merge.

### 2.1 MUST-PASS suites

- `tests/Feature/Events/` -- all files.
- `tests/Feature/Public/` -- all files.
- `tests/Feature/Auth/` -- all files.
- `tests/Feature/Participants/` -- all files.
- `tests/Feature/Settings/` -- all files.
- `tests/Unit/Services/` -- all files.
- `tests/Integration/RegistrationVerificationConcurrencyTest.php`
  -- when the gating environment is available (PENDING-DATA to
  confirm gating).

### 2.2 MUST-PASS commands

    php artisan test
    npx tsc --noEmit
    npm run build

`lint:check` (Pint) and `types:check` (PHPStan) are part of
`composer test` and run before `php artisan test`.

### 2.3 Preservation acceptance criteria (from doc 04 section 16)

- PRES-AC-01 through PRES-AC-11 are the specific criteria.
- Each phase appends its own preservation run to the phase
  checklist (see section 10 of this doc).

### 2.4 What "unchanged" means

- No file under `tests/` is edited unless the edit is documented
  as a required companion change (see section 2.5).
- No test is skipped, marked incomplete, or removed.
- No assertion is weakened.

### 2.5 When editing an existing test is acceptable

Only in these cases, and only with an entry in doc 06's phase
record:

1. A candidate from doc 03 requires a new case in an existing
   test (e.g., adding a `ChurchEvent` case to an existing
   `match` statement). The edit adds coverage, does not remove
   it.
2. A test asserts on behavior that the client has explicitly
   approved changing. There is no such approval today.

If neither applies, the test is treated as a hard boundary.

---

## 3. Test categories for church-event work

The following categories organize the NEW tests. Each category
lists the doc 04 criteria it covers and the decisions it depends
on.

### 3.1 Category administration (FS-01)

**Depends on:** Decision 1.

**Tests when F2/F3/F5 is chosen:**

- NEW: admin can create a category.
- NEW: admin can create a type within a category.
- NEW: staff cannot access the Categories page (403).
- NEW: category list is returned in sorted order.
- NEW: deactivating a category hides it from creation dropdowns.
- NEW: deactivating a category does not affect existing events.
- NEW: duplicate category name is rejected.
- NEW: duplicate type name within a category is rejected.
- NEW: `Other` type requires a custom name.

**Tests when F1/F4 is chosen:**

- NEW: `Other` type stores a free-text name.
- NEW: the enum has the confirmed nine categories as types.

**Covers doc 04:** AC-01.5 through AC-01.11.

### 3.2 Unified event creation (FS-02)

**Depends on:** Decision 1, Decision 2.

- NEW: `/events/create` shows a running/church selector.
- NEW: selecting church shows category and type dropdowns.
- NEW: selecting a category filters the type dropdown.
- NEW: submitting a valid church event creates a row with the
  correct category and type.
- NEW: a church event created with null distance and null
  accessibility flags renders without errors on `/events/{id}`.
- NEW: running-event creation path is unchanged (delegated to
  existing tests).
- CONDITIONAL (Decision 2): the created event has status
  `Draft` (if A1/A4) or the initial stage is `Proposal` (if
  A2/A3).

**Covers doc 04:** AC-02.1 through AC-02.12.

### 3.3 Event listing and filtering (FS-03)

**Depends on:** none.

- NEW: `/events` lists both kinds of events.
- NEW: filter by kind returns only that kind.
- NEW: filter by category returns only that category.
- NEW: filter by status returns only that status.
- NEW: filter combinations behave correctly.
- NEW: filter state persists in the query string.
- NEW: empty state renders when no events match.

**Covers doc 04:** AC-03.1 through AC-03.7.

### 3.4 Church event detail (FS-04)

**Depends on:** Decision 1, Decision 2.

- NEW: `/events/{id}` renders for a church event.
- NEW: distance row is hidden.
- NEW: accessibility rows are hidden.
- NEW: category and type are displayed.
- NEW: stage-appropriate actions are shown (per Decision 2).
- NEW: running-event detail still renders (existing test).

**Covers doc 04:** AC-04.1 through AC-04.5.

### 3.5 Proposal and approval (FS-05)

**Depends on:** Decision 2, Decision 4.

- CONDITIONAL (Decision 2): submitting a proposal moves the
  event out of draft.
- CONDITIONAL (Decision 4): the approver identity check works.
- CONDITIONAL (Decision 4): non-approvers receive 403.
- NEW: revision requires a reason.
- NEW: rejection requires a reason.
- NEW: approval moves the event to the approved state.
- NEW: a rejected event does not appear in active lists unless
  filtered.
- NEW: resubmission after revision is allowed.

**Covers doc 04:** AC-05.1 through AC-05.12.

### 3.6 Recurring events and occurrences (FS-06)

**Depends on:** Decision 3.

- CONDITIONAL (D2/D3/D4): creating a recurring event generates
  the expected occurrences.
- CONDITIONAL (D2/D3/D4): each occurrence has independent
  status and attendance.
- CONDITIONAL (D2/D3/D4): cancelling one occurrence does not
  affect the series.
- CONDITIONAL (D2/D3/D4): deleting the parent event cascades to
  occurrences.
- CONDITIONAL (D2/D3/D4): reports show both per-occurrence and
  aggregate figures.
- NEW: invalid recurrence rules are rejected.
- NEW: end date before start date is rejected.

**Covers doc 04:** AC-06.1 through AC-06.6.

### 3.7 Church event registration (FS-07)

**Depends on:** Decision 3, Decision 6, Decision 8.

- NEW: registration can be toggled on/off per church event.
- NEW: if enabled, public URL is reachable.
- NEW: if disabled, no public URL exists.
- CONDITIONAL (Decision 8): if contact verification is required,
  the existing verification flow applies.
- CONDITIONAL (Decision 8): if the existing `/r/{slug}` flow is
  reused, existing tests cover it; otherwise new tests are
  added.
- CONDITIONAL (Decision 6): enrollment is tested separately if
  distinct.

**Covers doc 04:** AC-07.1 through AC-07.7.

### 3.8 Church event attendance (FS-08)

**Depends on:** Decision 3, Decision 8.

- CONDITIONAL (AT2/AT3): attendance attaches to occurrences.
- CONDITIONAL (AT3): separate table from running attendance.
- CONDITIONAL (AT4): count-only, no per-participant records.
- NEW: duplicate attendance for the same participant and
  occurrence is rejected.
- NEW: attendance history for a participant is viewable.
- NEW: attendance corrections follow the existing window rules
  if reused.
- NEW: attendance endpoint refuses unknown participants when a
  member list is in use.

**Covers doc 04:** AC-08.1 through AC-08.9.

### 3.9 Enrollment and completion (FS-09)

**Depends on:** Decision 6.

- CONDITIONAL (EN1): `RegistrationStatus` extended with
  enrollment statuses.
- CONDITIONAL (EN2/EN3): new tables.
- NEW: enrollment requires completion criteria to be defined.
- NEW: duplicate enrollment is rejected.
- NEW: completion is independent of parent event completion.
- NEW: status transitions are validated.

**Covers doc 04:** AC-09.1 through AC-09.5.

### 3.10 Subgroups (FS-10)

**Depends on:** Decision 7.

- CONDITIONAL (SG2): subgroup must belong to the event's
  category.
- NEW: subgroup is stored and displayed.
- NEW: reports can filter by subgroup.

**Covers doc 04:** AC-10.1 through AC-10.3.

### 3.11 Budget and expenses (FS-11)

**Depends on:** Decision 5.

- CONDITIONAL (E1/E2/E3): whichever tier is chosen.
- NEW: negative amounts are rejected.
- NEW: oversize attachments are rejected.
- NEW: invalid file types are rejected.
- NEW: computed total is correct (E2/E3).

**Covers doc 04:** AC-11.1 through AC-11.5.

### 3.12 Reports (FS-12)

**Depends on:** Decision 3, Decision 9.

- NEW: report renders for a church event in a reportable state.
- NEW: 404 for non-reportable states.
- NEW: summary figures match underlying data.
- NEW: CSV export produces a valid file.
- CONDITIONAL (Decision 9): whether reports extend the existing
  `ReportController` or a new controller.

**Covers doc 04:** AC-12.1 through AC-12.5.

### 3.13 Authorization (FS-13)

**Depends on:** Decision 10.

- CONDITIONAL (OWN1/OWN2): unauthorized users receive 403.
- CONDITIONAL (OWN1): running-event authorization also changes;
  existing tests must be updated with the change documented.
- CONDITIONAL (OWN2): running-event authorization unchanged.
- NEW: authorized users can perform permitted actions.

**Covers doc 04:** AC-13.1 through AC-13.5.

### 3.14 Dashboard and navigation (FS-14)

**Depends on:** Decision 1, Decision 2.

- NEW: dashboard renders with the new layout.
- NEW: stats aggregate correctly.
- CONDITIONAL (F2/F3/F5): Categories entry visible to admins.
- NEW: running-event users see no regression.
- NEW: quick actions route correctly.

**Covers doc 04:** AC-14.1 through AC-14.5.

---

## 4. Cross-cutting test areas

These tests apply across multiple features and are grouped
together so they are not duplicated.

### 4.1 Authorization tests

- Every new controller action must be covered by at least one
  403 test and one authorized-success test.
- Guest access to authenticated routes returns the expected
  redirect or 403.
- Existing `AdminMiddleware` behavior is verified unchanged.

### 4.2 Validation tests

- Every new FormRequest has a test for each rule that is not a
  pass-through.
- Quality rules (`HumanNameQualityRules`,
  `ContactAndAddressQualityRules`) are reused, not duplicated.
  A test asserts the reuse by validating the same garbage input
  is rejected in a church context as in a running context.
- Cross-field rules (e.g., end-after-start) are tested with the
  same boundaries as the existing tests.

### 4.3 Rate limiter tests

- Every new public endpoint has a rate limiter and a test
  asserting it rejects beyond the limit.
- Every new authenticated write endpoint has a rate limiter
  unless a documented reason exists.

### 4.4 Error and flash behavior

- Validation errors flash on the correct field key.
- Errors do not leak PII (mirroring the audit's findings about
  `RegistrationVerificationService`).
- Flash messages render correctly in the layout.

### 4.5 Read-only state

- Completed events reject writes from all mutation endpoints.
- Corrections require elevated permission (per Decision 10).

### 4.6 Cascade and deletion

- Deleting a church event cascades to its occurrences,
  attendance, enrollments, and attachments.
- Deleting a church event does not touch `Participant` rows.
- Deleting a running event is unchanged.

**Critical.** Every new table introduced by any candidate in
doc 03 section 12.1 must be added to the cascade test coverage.
Doc 03 lists which candidates introduce which tables.

### 4.7 TypeScript and lint

- `npx tsc --noEmit` runs clean.
- `npm run build` succeeds.
- Pint passes on new PHP files.
- PHPStan passes at the project level.

---

## 5. Payment-related tests

**Depends on:** doc 01 section B verification (grep not yet run).

### 5.1 Current state (PENDING-DATA)

- No payment-related code was found in the files inspected.
- The grep was not run.
- Whether Fun Run has payment behavior at all is unresolved.

### 5.2 Tests required once the grep is run

**If the grep is empty:**

- No payment tests required.
- A preservation criterion is added: "no payment behavior
  exists; nothing to preserve."

**If the grep returns hits:**

- For each hit, a preservation test asserting the current
  behavior continues to work.
- A note in doc 06 that these features are not touched.

**Either way:**

- A test asserting that no new code path introduces a payment
  reference is added to the CI chain (simple grep guard).

---

## 6. Migration safety

**This section is critical.** Doc 01's finding P11-adjacent
concern: `RefreshDatabase` runs every migration in
`database/migrations/` on every test run. A migration that
drops or alters a table breaks the test suite the moment the
file is saved, not when `php artisan migrate` is run.

### 6.1 General rules (inherited from P11)

- A migration that drops or alters must not exist on disk until
  its dependent code is also on disk and ready.
- Migration files are append-only. Existing migrations are not
  edited.
- Every new migration has a `down()` method that reverses it.
- Every new migration is tested in both directions (`migrate`
  and `migrate:rollback`) at least once during development.

### 6.2 Per-candidate migration risk

Each candidate's migration is assessed for risk: (a) does it
add or alter, (b) does it require backfill, (c) does it break
`RefreshDatabase` on existing tests.

**F1 / F4:** no migration. No risk.

**F2:** three migrations.

- `create_event_categories_table` -- additive. No risk.
- `create_event_types_table` -- additive. No risk.
- `add_category_and_type_to_events` -- adds nullable columns.
  No risk to existing rows. No backfill.

**F3 / F5:** migrations plus data migration.

- `create_event_types_table` -- additive.
- `create_event_categories_table` -- additive.
- `seed_running_types` -- data migration. Must be idempotent
  (seed only if not already present). Risk: running twice
  creates duplicates.
- `deprecate_event_type_enum` -- this is the risky one. If it
  drops the `events.event_type` column, every existing test
  that reads it fails. **Mitigation:** do not drop the column
  in the same migration. Keep the column and make it nullable,
  or keep both the column and the new FK during a transition
  period. Document the deprecation path.

**A1:** enum change only. No migration. The change is in PHP.
Risk: existing tests that assert on the exact set of
`EventStatus::cases()` may fail if they use `assertCount` or
similar. PENDING-DATA: confirm no such test exists.

**A2 / A3:** one migration.

- A2: `add_church_status_to_events` (nullable column).
  Additive. No risk.
- A3: `create_church_event_stages_table` (new table) or
  `add_workflow_stage_to_events` (nullable column). Additive.

**A4:** no migration.

**D1:** no migration.

**D2 / D3 / D4:** up to two migrations.

- `create_event_occurrences_table` -- additive. Safe.
- `add_occurrence_id_to_attendance` (D2, D4) -- adds a
  nullable column. Safe on existing rows. But **every existing
  query on `Attendance` must be checked** to ensure the new
  nullable column does not cause unexpected behavior when NULL.
  RISK.
- `create_occurrence_attendances_table` (D3) -- additive.
  Safe.

**C1:** `add_role_column_or_reuse` -- reuse existing `role`.
No migration if the new role is stored in the same string
column.

**C2:** no migration.

**C3:** `add_approver_id_to_events` -- nullable FK. Safe.

**C4:** no migration.

**E1 / E2 / E3:** one to three migrations.

- `add_budget_to_events` -- two nullable columns. Safe.
- `create_budget_line_items` -- additive.
- `create_event_attachments` -- additive.
- E3 adds receipts and reconciliation columns -- additive.

**EN1:** enum change plus `add_completed_at_to_registrations`
-- nullable column. Safe.

**EN2 / EN3:** `create_program_enrollments` and possibly
`create_program_session_attendance` -- additive.

**SG1:** `add_subgroup_to_events` -- nullable column. Safe.

**SG2:** `create_subgroups` and `add_subgroup_id_to_events` --
additive.

**SG3:** `create_event_tags` -- additive.

**AT2 / AT4:** `add_occurrence_id_to_attendance` -- nullable
column. RISK as noted under D2.

**AT3:** `create_occurrence_attendances` -- additive.

**RP1 / RP2 / RP3 / OWN1 / OWN2:** no migration.

### 6.3 Migration order requirements

Any phase that touches multiple tables must define migration
order. In particular:

- The parent table must be created before the FK on a child.
- Seed migrations must run after the table they seed.
- Deprecation migrations must run after their replacements
  exist and have been backfilled.

### 6.4 Backfill policy

None of the candidates proposed in doc 03 require backfill of
existing rows, **provided** the columns they add are nullable
or have safe defaults. This is intentional. If a candidate
cannot avoid a backfill, that candidate is flagged as risky and
is deferred to a later phase or split into two migrations
(add column, then backfill, then constrain).

### 6.5 Fresh-database check

Every phase must run:

    php artisan migrate:fresh --seed

on the development environment and confirm the app boots
without errors. This catches:

- Migration order errors.
- Missing seeder data for the new categories and types.
- FK constraint errors that SQLite in-memory tests do not
  surface.

### 6.6 Rollback check

Every phase must run:

    php artisan migrate:rollback --step=1
    php artisan migrate

and confirm the rollback and re-apply succeed. This catches:

- Missing or incorrect `down()` methods.
- Non-reversible operations (e.g., dropping data in `up()`
  without restoring it in `down()`).

### 6.7 RefreshDatabase pitfall (from P11)

P11 states: a migration file is live in tests the moment it
exists on disk. This is the highest-frequency failure mode in
this project. Every phase that adds a migration must:

1. Write the migration in the same change set as the code that
   uses it.
2. Never write a migration that breaks the codebase before the
   code that depends on it is written.
3. Run `php artisan test` immediately after saving the
   migration file, before proceeding to other files.

---

## 7. Integration and end-to-end scenarios

These are multi-step scenarios that span features. Each one is
a candidate for a Pest test that walks through the full user
journey.

### 7.1 Running-event preservation scenarios

- SCN-01. Create a Community Run event end-to-end (wizard,
  publish, register a participant via `/r/{slug}`, verify,
  finalize, record attendance, view report).
- SCN-02. Same as SCN-01 but for a Fun Run.
- SCN-03. Delete a running event with admin direct path.
- SCN-04. Delete a running event with staff OTP path.
- SCN-05. Auto-advance a running event from
  `registration_closed` to `ongoing` via the middleware.
- SCN-06. Finalize a running event with password confirmation.

These must pass before and after every phase.

### 7.2 Church-event scenarios (candidate-conditional)

Each scenario names the decisions it depends on.

- SCN-10 [Decision 1, Decision 2]. Create a church event
  (category, type, dates, venue), submit for approval, get
  approved, mark ready, run the event, mark completed.
- SCN-11 [Decision 3]. Create a recurring service, verify
  occurrences generate, record attendance for one occurrence,
  cancel another, view per-occurrence and aggregate reports.
- SCN-12 [Decision 3, Decision 8]. Create a Discipleship batch,
  enroll participants, record attendance per session, mark
  completion, view completion report.
- SCN-13 [Decision 1]. Admin creates a new event type under
  Services, creates an event with it, confirms it appears in
  listings and reports.
- SCN-14 [Decision 5]. Create a church event with a budget,
  record actual expenses, upload a liquidation document.
- SCN-15 [Decision 10]. Attempt an unauthorized action on a
  church event; confirm 403.

### 7.3 Cross-kind scenarios

- SCN-20. Both a running event and a church event appear on
  `/events` and can be filtered by kind.
- SCN-21. Dashboard stats aggregate both kinds correctly.
- SCN-22. Deleting a running event does not affect church
  events, and vice versa.

---

## 8. Risks

Named failure modes and their mitigations.

### 8.1 RISK-01 -- Running-event behavior regression

**Description:** A change to shared code (`Event` model,
`EventStatus`, `EventType`, `EventController`, or any shared
`Concerns` trait) causes a running-event test to fail.

**Likelihood:** Medium if decisions A, F, or ownership are
selected; low if they are deferred.

**Impact:** High -- running events are the current production
path.

**Mitigation:**

- Do not edit `EventStatus`, `EventType`, or `EventWorkflow`
  unless a specific decision requires it.
- If editing is required, add a branching case for church
  events rather than modifying existing cases.
- Run `php artisan test` after every file save during phases
  that touch shared code.
- Keep the preservation suite (section 2) as a hard gate for
  every phase.

### 8.2 RISK-02 -- Migration breaks the test suite on save

**Description:** A migration file added to the directory
immediately runs under `RefreshDatabase` and breaks tests.

**Likelihood:** High (this is the project's documented
recurring failure mode; P11 exists because of it).

**Impact:** Medium -- the failure is fast to detect but easy to
miss if tests are not run.

**Mitigation:**

- Section 6.7 rules. Write migration and code together. Run
  tests immediately.
- If a migration must land before dependent code, move it out
  of `database/migrations/` until the code is ready.

### 8.3 RISK-03 -- Enum extension breaks a `match` statement

**Description:** Adding cases to `EventType` or `EventStatus`
causes a PHP `match` without a default case to throw
`UnhandledMatchError` at runtime.

**Likelihood:** High if enums are extended.

**Impact:** High -- runtime errors in production-adjacent
code paths.

**Mitigation:**

- Before extending either enum, grep for every `match` on
  `EventType` or `EventStatus` and add explicit cases or a
  `default`.
- Test each affected code path.

### 8.4 RISK-04 -- Nullable column changes query behavior

**Description:** Adding a nullable column to a shared table
(e.g., `occurrence_id` on `Attendance`) changes the result of
queries that filter or order on that column.

**Likelihood:** Medium if D2/D4 or AT2 is chosen.

**Impact:** Medium -- silent behavior change.

**Mitigation:**

- Audit every existing query on the affected table.
- Add tests that assert the running-event behavior is
  unchanged with the new column NULL.
- Consider a separate table (D3/AT3) instead of a nullable
  column if the audit surfaces many queries.

### 8.5 RISK-05 -- Cascade gap on event deletion

**Description:** A new child table is added but
`EventDeletionService::delete()` is not updated to include it.
Deleting an event leaves orphan rows.

**Likelihood:** Medium -- depends on discipline.

**Impact:** Medium -- data integrity.

**Mitigation:**

- Doc 03 section 12.1 lists every new table by candidate.
- Every phase that adds a table updates
  `EventDeletionService`.
- A cascade test is added per new table (section 4.6).

### 8.6 RISK-06 -- Authorization change reopens ISSUE-004

**Description:** Introducing a policy layer for church events
(doc 01 finding 9) exposes that running events have no policy
either. Fixing one without the other leaves an inconsistency.

**Likelihood:** High if Decision 10 resolves to OWN1.

**Impact:** Medium -- scope creep.

**Mitigation:**

- Doc 06 records this as an explicit decision point.
- If OWN1 is chosen, the phase includes running-event policy
  coverage as a first-class deliverable, not a follow-up.

### 8.7 RISK-07 -- UI layout regression

**Description:** Changes to `resources/js/app.tsx` or the
sidebar break the running-event layout.

**Likelihood:** Low -- layout changes are additive.

**Impact:** Medium -- visible regression.

**Mitigation:**

- Add new layout cases only, do not modify existing ones.
- Run `npm run build` and load the running-event pages after
  every frontend phase.

### 8.8 RISK-08 -- Payment behavior regression (if any exists)

**Description:** If Fun Run has payment behavior and it is
touched by church-event work, the payment flow may break.

**Likelihood:** Unknown -- grep not run.

**Impact:** High if payments exist.

**Mitigation:**

- Run the payment grep before any phase that touches shared
  event code.
- If payments exist, add a preservation test per payment code
  path (section 5.2).

### 8.9 RISK-09 -- Test suite drift

**Description:** Tests are edited to accommodate new behavior
without recording the edit.

**Likelihood:** Medium -- pressure to make tests pass.

**Impact:** High -- the safety net erodes.

**Mitigation:**

- Section 2.5 defines the only allowed cases for editing
  existing tests.
- Every edit requires an entry in doc 06's phase record.

### 8.10 RISK-10 -- Documentation-to-code drift

**Description:** Docs 01-06 describe a design; the implementation
diverges; the docs become stale and misleading.

**Likelihood:** Medium without discipline.

**Mitigation:**

- Follow P7 (append-only corrections). When implementation
  diverges from a doc, append a corrections block rather than
  editing.
- Doc 06 records the mapping from decision to implementation
  for traceability.

---

## 9. Rollback considerations

Rollback applies at three levels.

### 9.1 Phase-level rollback

Each phase is a sequence of commits. If a phase is judged
unsafe after landing:

- Revert commits in reverse order.
- If the phase added a migration, run
  `php artisan migrate:rollback --step=N` where N is the
  number of new migrations.
- Re-run the full preservation suite.

### 9.2 Migration-level rollback

Every migration must have a working `down()` method. The
`migrate:rollback` command is exercised in every phase
(section 6.6).

If a migration is not reversible (e.g., drops a column), the
phase must document the recovery path. None of the candidates
in doc 03 propose irreversible operations except potentially
F3's deprecation of the `event_type` column. That is flagged
in section 6.2 and must be split into a reversible two-step
migration.

### 9.3 Feature-level rollback

If a church-event feature ships and turns out to be unwanted:

- The feature is hidden by a feature flag or by reverting the
  routes and UI, not by dropping tables.
- Data is preserved until a separate decision is made to
  remove it.
- A "kill switch" is preferred over a destructive rollback for
  any feature that accumulates data (occurrences, attendance,
  enrollments).

---

## 10. Phase-level test checklists

Each phase (as defined in doc 06) must satisfy the following
checklist before merging. Doc 06 will enumerate the phases;
this section provides the reusable template.

### 10.1 Pre-phase checklist

- All decisions that the phase depends on are resolved.
- The phase scope is documented in doc 06.
- The affected files and tables are known.

### 10.2 During-phase checklist

- Every new file passes Pint and PHPStan.
- Every migration is reversible and tested both directions.
- Every new controller action has at least one authorization
  test.
- Every new endpoint has rate limiting unless documented
  otherwise.
- Every new table is added to cascade coverage.

### 10.3 Post-phase checklist

- `php artisan test` -- full suite green.
- `npx tsc --noEmit` -- clean.
- `npm run build` -- succeeds.
- `php artisan migrate:fresh --seed` -- clean on a fresh
  database.
- `php artisan migrate:rollback --step=N && php artisan migrate`
  -- clean.
- Running-event preservation scenarios (section 7.1) --
  manually verified.
- Church-event scenarios for this phase -- manually verified.
- Doc 06 updated with the phase record.

### 10.4 Rejection criteria

A phase is rejected if:

- Any preservation test fails and cannot be restored.
- Any existing test is edited without documentation.
- Any migration lacks a `down()` method.
- Any new table is missing from cascade coverage.
- Any new endpoint lacks rate limiting without a documented
  reason.

---

## 11. What is not covered

- Exact Pest file names for new tests. These are decided
  during implementation, not in this document.
- Fixture and factory data. Existing factories are reused;
  new ones are added as needed.
- Coverage thresholds. WDEMS does not currently enforce a
  coverage metric. This document does not introduce one.
- Browser automation. All tests are Pest feature tests or
  manual browser verification. No Playwright/Selenium.
- Load testing. Not in scope for the first release unless
  NFR-08 (doc 02) is answered with specific targets.

---

## 12. Traceability gaps

Any acceptance criterion from doc 04 that does not have a
corresponding test in this document is a gap.

### 12.1 Coverage check

Every `AC-NN.N` in doc 04 sections 2 through 15 is covered by
a NEW test in section 3 of this document, except:

- AC-02.8 through AC-02.10 -- running-event preservation.
  Covered by the MUST-PASS suites in section 2.1.
- AC-03.6 -- URL filter persistence. Covered by listing tests
  in section 3.3.
- AC-13.3, AC-13.4 -- conditional on Decision 10. Covered by
  section 3.13.
- AC-14.4 -- running-event dashboard unchanged. Covered by
  section 2.1 (Auth suite) and manual verification.

### 12.2 No orphan tests

Every MUST-PASS and NEW test in this document traces to a doc
04 criterion, a doc 02 preservation requirement, or a doc 03
migration. No orphan tests are proposed.

### 12.3 PENDING-DATA closures required

The following must be closed before this test plan is complete:

1. Run `php artisan test` and record the baseline pass count.
2. Cat `tests/Pest.php` to confirm the database binding.
3. Run `grep -rn "payment|stripe|paymongo|checkout|paid_at|
amount_paid" app/ resources/js/ database/ routes/ tests/` to
   confirm section 5.
4. Run `grep -rn "EventType::" app/ resources/js/ database/
routes/ tests/` to confirm the full consumer list.
5. Run `grep -rn "EventStatus::" app/ resources/js/ database/
routes/ tests/` to confirm the full consumer list.

Until those are run, sections 1.2, 5.1, 6.2 (partially), and
8.8 remain marked PENDING-DATA.

---

## 13. Cross-references

- `docs/church-events/01-current-system-audit.md`
- `docs/church-events/02-product-scope-and-requirements.md`
- `docs/church-events/03-domain-model-and-workflows.md`
- `docs/church-events/04-feature-specifications-and-acceptance-criteria.md`
- `docs/church-events/06-open-questions-and-implementation-roadmap.md`
  -- to be written
- `docs/dev-workflow/README.md` -- P2, P3, P5, P7, P11 apply to
  every phase described here

---

## 14. Changelog

- **2026-10-16** -- File created. Test categories, migration
  safety analysis, integration scenarios, risks, and rollbacks
  recorded. Every test traces to a doc 04 criterion or a doc 02
  preservation requirement. Section 12 lists the PENDING-DATA
  closures required before this plan is complete. No code,
  migrations, tests, or configuration changed.
