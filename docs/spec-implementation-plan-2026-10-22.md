# WDEMS Specification Audit and Remaining-Work Plan

**Audit date:** 2026-10-22
**Branch:** `feat/church-registration`
**Status:** Working plan; local changes are uncommitted.
**Authority:** `docs/specs/WDEMS_MASTER_PROJECT_DOCUMENT.md` for system scope; `docs/specs/Church_Events_Update.md` Amendments B–D for church requirements. The companion church spec is subordinate, and older implementation snapshots must be reconciled against the current branch.

> This is not a claim that WDEMS is complete. Implementation status is based on current source and test review on this branch. Latest local full suite: 969 passed / 3 skipped (972 total). Frontend lint/format and Pint pass; TypeScript reports 11 existing errors in five files, PHPStan is not clean, and remote CI, browser, production-service, and deployment acceptance have not been verified.

## 1. Current implementation status

| Specification area                                | Status                                  | Evidence / boundary                                                                                                                                                                                                                                                                                |
| ------------------------------------------------- | --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Running event CRUD, lifecycle and kind separation | Substantially implemented               | Event wizard, lifecycle services, kind guards, running-only registration/attendance/report surfaces and regressions exist. See `docs/running-events-audit-2026-10-21.md`.                                                                                                                          |
| Running registration and identity                 | Substantially implemented               | Public wizard, composite identity, email OTP/reCAPTCHA, exact duplicate blocking and likely-duplicate HOLD/review exist. Published legacy form/challenge compatibility remains relevant to Firebase cleanup.                                                                                       |
| Running attendance and reporting                  | Implemented in source                   | Code/barcode check-in, walk-ins, paper attestation, reports and CSV exist. Camera/physical scanner and production delivery remain unverified.                                                                                                                                                      |
| Church pool separation                            | Implemented locally                     | Church registration/resolvers use the church pool; cross-pool tests exist. The migration/data audit and release verification remain.                                                                                                                                                               |
| Generic church Event registration                 | Implemented locally                     | Staff CRUD, codes, attestation, duplicate/HOLD handling and organizer review exist in the current branch.                                                                                                                                                                                          |
| Church public link                                | Implemented for generic Events          | Publication switch and shared email challenge flow, with CAPTCHA, OTP, duplicate and HOLD test coverage. Do not infer production email/CAPTCHA acceptance from local tests.                                                                                                                        |
| Services/Fellowship bridge                        | Implemented locally                     | Activation transaction copies history and group assignment; legacy write/edit/delete guards share source-row locks. Legacy Add UI remains intentionally until acceptance.                                                                                                                          |
| Church monitoring attendance and summary          | Core implemented locally                | Active registrations only, Unmarked default, typed code/search/filter/inline mark; full-roster summary and status counts. Verify interactively before retiring legacy UI.                                                                                                                          |
| Church participant history (D-11)                 | Implemented locally; acceptance pending | History service/view aggregates attended generic Events, unbridged Services/Fellowship, and Discipleship sessions; bridged legacy rows are counted from the new Event only. Regression tests cover cross-module history, bridge deduplication, and pool/provisional exclusion. Browser QA remains. |
| Nine church ministry areas                        | Partial                                 | Services/Fellowship/Discipleship foundations exist; several other category pages are placeholders or generic Event paths rather than complete specialist workflows. Scope/priorities need explicit release decision.                                                                               |
| Activity-specific forms                           | Partial/not built across scope          | Youth Camp, Kids, Young Adult, Ministry Meeting, Special Occasions and similar specialized fields/workflows need an activity model and approved acceptance requirements.                                                                                                                           |
| Dashboard                                         | Partial                                 | Split side, stats, action items, upcoming, basic registration activity, category counts and calendar shell exist. Full DASH checklist remains.                                                                                                                                                     |
| Discipleship                                      | Core module implemented; update pending | Programs, batches, enrollments, sessions, attendance matrix/progress/CSV exist. Client Q14 progress behavior and migration are not complete. Discipleship self-enrollment is outside the current link release.                                                                                     |
| Authorization                                     | **Open/high priority**                  | Event ownership and church-controller authorization policies require a decision and implementation; authenticated access alone is not object-level authorization.                                                                                                                                  |
| CI/static analysis                                | **Not fully green**                     | Frontend lint/format and Pint passed in last reported run; TypeScript has 11 existing errors; PHPStan has baseline debt/stale entries. Current remote CI not verified.                                                                                                                             |
| Production acceptance                             | **Not verified**                        | Real-host reCAPTCHA, Resend delivery, browser flows, camera/physical scanner, production-like migration/rollback remain.                                                                                                                                                                           |

## 2. Completed in this implementation pass

- Added the church-only participant history service and page for D-11.
- Aggregated present attendance from generic church Events, legacy Service occurrences not yet bridged, legacy Fellowship events not yet bridged, and Discipleship sessions.
- Prevented double-counting bridged Service/Fellowship histories by excluding legacy records after `registration_event_id` is set; bridged attendance then counts through the church Event registration.
- Excluded Running pool, provisional church records, absent/unmarked records and non-attended statuses from the attended-history count.
- Added an authenticated history route and a clickable participant name from the church registration list.
- Added regression tests for cross-module history, bridged-history deduplication, and access/pool guards.

## 3. Remaining tasks

### Phase A — Finish and verify participant history

1. Run focused tests, full Pest suite, frontend format/lint/type check, PHPStan, Pint and `git diff --check`; resolve any new findings separately from baseline failures.
2. Add any missing regressions for held/removed registrations, duplicate history rows, absent/excused/late statuses, null dates, and a participant with registrations but no attendance.
3. Browser QA the history page and registration-list link. Verify responsive layout, correct sorting/counts, empty state, and that no email/phone or running-side data is exposed.
4. Decide whether Church dashboard should surface top-attending participants now or defer it to DASH-2. The canonical dashboard spec expects a church Participant History panel.
5. Update the D-11 status/checklist in the Master/Church docs only after validation passes.

### Phase B — Complete B-5/B-6/B-7 acceptance

1. End-to-end browser flows for a generic church Event, a Service activation, and a Fellowship activation with an existing group and attendance.
2. Exercise closed/unpublished/open public links, CAPTCHA failure/success, OTP cooldown/expiry/lockout, confirmation access, exact duplicate before OTP, sibling/shared contact, generic held response and organizer resolution.
3. Verify counts obey `Registered = Present + Absent + Unmarked`, held rows never enter attendance, church code lookup works, and running pool records remain isolated.
4. Keep Services/Fellowship legacy Add actions until bridge acceptance and user sign-off; then remove or visibly retire them in a follow-up.
5. Review the bridge migration filename `2026_10_24_100000_add_church_registration_bridge` against deployment chronology. It has been applied locally; do not rename an applied migration. Verify fresh install, upgrade and rollback/recovery on a disposable environment.

### Phase C — Authorization and reliability

1. Decide object ownership for Events and whether staff may edit other staff’s events. Implement policy checks for event edit/update, options, workflow transitions and deletion; add cross-user tests.
2. Decide church role rules for create/update/delete/attendance/publication/bridge/history; implement and test them across Services, Fellowship, Discipleship and generic church Event routes.
3. Decide the deferred outside-event-day policy for registration and attendance writes.
4. Resolve key data/reliability findings in `docs/known-issues.md`: address validation/schema width (ISSUE-007), email-optional duplicate warning (ISSUE-008), consent/legal content and server enforcement (ISSUE-010), atomic OTP attempts (ISSUE-018), unique races (ISSUE-019), email/phone identity conflicts (ISSUE-020), recurring Service batch transaction/unique guard (ISSUE-022), shared participant edits (ISSUE-023), and repair migration edge case (ISSUE-027).
5. Confirm whether Firebase compatibility remains necessary for published legacy forms; remove dead config/components/references only after deciding that compatibility window is closed (D-16).
6. Implement the Discipleship Q14 change only with a confirmed treatment of existing `excused` rows, then migrate and test.

### Phase D — Complete agreed product scope

1. Confirm whether the first release is limited to generic Events + Services + Fellowship + shared attendance/history, or includes bespoke workflows for all nine Church MD categories.
2. Add activity discriminator and approved context-specific behavior before tightening guardian conditions (Youth Camp vs all Youth events, Kids activities, etc.).
3. Create separately prioritized slices for unbuilt ministry modules and specialist fields (Young Adult partner/GRC nesting, Youth, Kids locations/VBS, Worship & Prayer, Church Administration, Special Occasions). Do not treat placeholders as complete modules.
4. Keep Discipleship public self-enrollment outside current scope unless approved; specify how its per-session attendance appears in church participant history and dashboard aggregates.
5. Decide whether church summaries need CSV/print export.

### Phase E — Finish DASH acceptance

1. Persist the last-viewed side per user; `?side=` overrides the visit without overwriting the remembered setting.
2. Add complete side-specific Quick Actions and link each stat/category/action to the correct filtered source.
3. Make the calendar event-aware: multiple-event dots, keyboard/touch event-name tooltip, clickable dates, side-scoped results and month navigation.
4. Expand Recent Activity beyond registration-only entries to verified, side-scoped attendance and lifecycle/HOLD events.
5. Replace church attendance placeholders and add the D-11 participant history panel after Phase A data is validated.
6. Complete Running Event Statistics/chart or explicitly defer DASH-3 with an approved note.
7. Test accessibility, phone layout and desktop aligned-row layout against `docs/specs/dashboard_update.md`.

### Phase F — Release readiness and documentation

1. Fix the current 11 TypeScript errors; clear PHPStan findings and reduce baseline debt without suppressing new errors. Run all checks on the exact release commit.
2. Confirm current branch CI is green; the historical main status from 2026-10-21 is not evidence for this branch.
3. Run fresh-install and upgrade migration tests on MySQL-compatible staging; verify backup/restore and rollback procedure.
4. Live acceptance: production reCAPTCHA hostname, Resend email delivery/failure behavior, staff and public browser walkthrough, mobile camera and physical Code 128 scanner for Running only. Church must remain lookup-only/no scanning.
5. Reconcile stale status in Master Part 6/7/9.1, `docs/church-registration-refactor-2026-10-22.md`, README, `docs/specs/dashboard_update.md`, `docs/church-events/TASKS.md`, and `docs/AI-CONTEXT.md`; preserve append-only rules.
6. Review local changes in a PR, verify CI and review, then document deployment and migration notes. Do not claim merged/released until it is.

## 4. Explicit release boundaries

- Current church registration release: generic Event public registration plus Services/Fellowship bridge; retain legacy Add actions until bridge acceptance.
- Discipleship self-enrollment/public links: excluded unless separately approved.
- Payments, finance, participant accounts, automatic merges, and native mobile app: out of scope per Master/Church MD.
- Specialized ministry forms/modules and full DASH completion are not implied by the generic Event registration workflow.
- Live email, CAPTCHA and device/scanner acceptance must be reported separately from automated tests.

## 5. Verification checklist

- [x] Focused `ChurchParticipantHistoryTest` passes (3 tests, 29 assertions).
- [x] Full `php artisan test` passes (969 passed, 3 skipped, 4,112 assertions).
- [x] Frontend `check`, Pint and `git diff --check` pass; TypeScript is separately failing on 11 errors in five existing files.
- [ ] PHPStan passes; current analysis remains blocked by existing baseline/stale-suppression debt, with no new history-file errors reported.
- [ ] Browser-verified D-11 page and registration link; confirm no data leaks across pools.
- [ ] Browser-verified generic Event, Service bridge, Fellowship bridge, group assignment and historical attendance.
- [ ] Fresh migration and upgrade/recovery path tested on disposable/staging database.
- [ ] Current branch remote CI and live external-service acceptance verified.
