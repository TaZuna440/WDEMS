# WDEMS Specification Audit and Remaining-Work Plan

**Audit date:** 2026-10-22
**Branch:** `feat/church-registration`
**Status:** Working plan; local changes are uncommitted.
**Authority:** `docs/specs/WDEMS_MASTER_PROJECT_DOCUMENT.md` for system scope; `docs/specs/Church_Events_Update.md` Amendments B–D for church requirements. The companion church spec is subordinate, and older implementation snapshots must be reconciled against the current branch.

> This is not a claim that WDEMS is complete. Implementation status is based on current source and test review on this branch. Latest local full suite: 980 passed / 3 skipped (983 total; 4,320 assertions). Frontend lint/format and Pint pass; TypeScript reports 11 errors in five unrelated UI files; PHPStan reports baseline/stale-suppression debt plus DashboardController typing findings. Remote CI, browser, production-service, and deployment acceptance have not been verified.

## 1. Current implementation status

| Specification area                                | Status                                                | Evidence / boundary                                                                                                                                                                                                                                                                                                                                                                                                 |
| ------------------------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Running event CRUD, lifecycle and kind separation | Substantially implemented                             | Event wizard, lifecycle services, kind guards, running-only registration/attendance/report surfaces and regressions exist. See `docs/running-events-audit-2026-10-21.md`.                                                                                                                                                                                                                                           |
| Running registration and identity                 | Substantially implemented                             | Public wizard, composite identity, email OTP/reCAPTCHA, exact duplicate blocking and likely-duplicate HOLD/review exist. Published legacy form/challenge compatibility remains relevant to Firebase cleanup.                                                                                                                                                                                                        |
| Running attendance and reporting                  | Implemented in source                                 | Code/barcode check-in, walk-ins, paper attestation, reports and CSV exist. Camera/physical scanner and production delivery remain unverified.                                                                                                                                                                                                                                                                       |
| Church pool separation                            | Implemented locally                                   | Church registration/resolvers use the church pool; cross-pool tests exist. The migration/data audit and release verification remain.                                                                                                                                                                                                                                                                                |
| Generic church Event registration                 | Implemented locally                                   | Staff CRUD, codes, attestation, duplicate/HOLD handling and organizer review exist in the current branch.                                                                                                                                                                                                                                                                                                           |
| Church public link                                | Implemented for generic Events                        | Publication switch and shared email challenge flow, with CAPTCHA, OTP, duplicate and HOLD test coverage. Do not infer production email/CAPTCHA acceptance from local tests.                                                                                                                                                                                                                                         |
| Services/Fellowship bridge                        | Implemented locally                                   | Activation transaction copies history and group assignment; legacy write/edit/delete guards share source-row locks. Legacy Add UI remains intentionally until acceptance.                                                                                                                                                                                                                                           |
| Church monitoring attendance and summary          | Core implemented locally                              | Active registrations only, Unmarked default, typed code/search/filter/inline mark; full-roster summary and status counts. Verify interactively before retiring legacy UI.                                                                                                                                                                                                                                           |
| Church participant history (D-11)                 | Implemented locally; acceptance pending               | History service/view aggregates attended generic Events, unbridged Services/Fellowship, and Discipleship sessions; bridged legacy rows are counted from the new Event only. Registration list links to history; Church dashboard ranks top five by Present attendance with alphabetical tie-break. Shared source logic de-duplicates legacy tracks per event/session. Regression tests pass; browser QA remains.    |
| Nine church ministry areas                        | Partial                                                | Services/Fellowship/Discipleship foundations exist; five generic category pages and four Youth submodule destinations remain placeholders. This pass changes only Church input example text, not page architecture. Specialist workflows remain unbuilt. Q18 removes Cell Group. |
| Dashboard                                         | Core DASH-1/2 implemented locally; acceptance pending | Remembered side with visit-only override, side-specific linked stats/actions, Needs Review and Church unmarked action items, upcoming/calendar, multi-event dots and event-name hover/focus/tap details, activity feed, Church Categories/history, and Running statistics are implemented. Browser/accessibility review and refinements remain; no activity-log persistence or Running statistics chart is claimed. |
| Church scheduling-date rule (B.3)                 | Implemented locally; acceptance pending               | Generic Events already enforced today-to-two-years. Service single/recurring occurrences, Fellowship events, and Discipleship batches/sessions now reject past dates; UI date pickers use the same bounds. Regression tests cover server rules and recurring batch upper-bound behavior.                                                                                                                            |
| Discipleship                                      | Core module implemented; update pending               | Programs, batches, enrollments, sessions, attendance matrix/progress/CSV exist. Client Q14 progress behavior and migration are not complete. Discipleship self-enrollment is outside the current link release.                                                                                                                                                                                                      |
| Authorization                                     | **Open/high priority**                                | Event ownership and church-controller authorization policies require a decision and implementation; authenticated access alone is not object-level authorization.                                                                                                                                                                                                                                                   |
| CI/static analysis                                | **Not fully green**                                   | Frontend lint/format and Pint pass; TypeScript has 12 diagnostics (one generated Wayfinder route typing issue plus 11 existing UI errors in five files); PHPStan has baseline debt/stale entries and DashboardController typing findings. Current remote CI not verified.                                                                                                                                           |
| Production acceptance                             | **Not verified**                                      | Real-host reCAPTCHA, Resend delivery, browser flows, camera/physical scanner, production-like migration/rollback remain.                                                                                                                                                                                                                                                                                            |

## 2. Completed in this implementation pass

- Added the church-only participant history service and page for D-11.
- Aggregated present attendance from generic church Events, legacy Service occurrences not yet bridged, legacy Fellowship events not yet bridged, and Discipleship sessions.
- Prevented double-counting bridged Service/Fellowship histories by excluding legacy records after `registration_event_id` is set; bridged attendance then counts through the church Event registration.
- Excluded Running pool, provisional church records, absent/unmarked records and non-attended statuses from the attended-history count.
- Added an authenticated history route and a clickable participant name from the church registration list.
- Added regression tests for cross-module history, bridged-history deduplication, and access/pool guards.
- Completed dashboard requirements that can be implemented without new policy decisions: persisted side selection with query-only override, linked metrics/categories, side-scoped report filters, held-review and Church unmarked actions, activity aggregation, event-aware calendar, and a Running Community Run statistics band.
- Enforced Church scheduling date bounds across Services, Fellowship, and Discipleship, including the final date in generated recurring Service batches.
- Added regression coverage for month-filtered Church Events, held/unmarked dashboard actions, Running statistics, and no-past scheduling across Church modules.
- Updated Church form input example text; category and Youth placeholder routing/page architecture is intentionally unchanged.

## 3. Remaining tasks

### Phase A — Finish and verify participant history

1. D-11 aggregation/history UI and the DASH Church top-attendee panel are implemented. The Church side shows the top five church-pool participants ranked by recorded Present attendance, alphabetically for ties, beneath Church Categories; each links to full history. The Running side receives no church history.
2. Focused regressions cover generic church Events, legacy Services/Fellowship, Discipleship sessions, bridge deduplication, duplicate legacy Service tracks, absent/HOLD/provisional/Running exclusions, and top-five rank/tie behavior.
3. Remaining: browser QA the history page and dashboard panel (responsive layout, sorting/counts, empty state, no cross-pool exposure); add tests for any concrete gaps found, including nullable dates/removed registrations.
4. Update the D-11 status/checklist in the Master/Church docs only after full validation and browser QA pass.

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

### Phase D — Replace Church placeholders with specialist workflows

1. Generic Church category and Youth submodule destinations remain placeholders. Input example text was clarified, but this is not a module workflow. Replace each placeholder only as an explicit architecture/product task and vertical slice; do not infer specialist acceptance from generic Church Event support.
2. Add a persistent Church activity/submodule discriminator so Youth Camp, Huddle, Campus Ministry, Timothy Class, Kids/VBS/Outreach, Young Adult/Partner/GRC, Worship & Prayer, Special Occasions, and Church Administration events can be listed and reported without category cross-contamination. Decide migration/default behavior for existing category-only events.
3. Build approved vertical slices: Youth Camp/Huddle/Campus Ministry/Timothy Class; Kids Service/VBS/dynamic outreach locations; Young Adult Service/Seminar/Partner→GRC; Worship & Prayer activities; Special Occasions with multi-person roles and generated documents; Church Planning/Ministry Meeting with editable ministry records and Gantt timeline. Keep payment tracking and Cell Group out of scope per client decisions.
4. For each slice implement scoped data, fields, CRUD/registration/attendance/summary, role checks, responsive page, isolation/validation tests, and migration/recovery strategy. Do not create global participants for descriptive names/roles unless explicitly registered.
5. Keep Discipleship public self-enrollment outside current rollout unless separately approved; after Q14 migration decision, define how its per-session records participate in Church history without double-counting.
6. Decide whether Church summaries need CSV/print export.

### Phase E — Finish DASH acceptance

1. Implemented: last-viewed side per user; `?side=` overrides without persisting.
2. Implemented: side-aware Quick Actions, linked stat cards, category filters, report filters, and Needs Attention links. Remaining: review that every destination is an appropriate filtered source and complete future Church participant-directory links if a dedicated directory is approved.
3. Implemented: month navigation, side-scoped events, multiple-event dots, keyboard/focus and touch day details, clickable day filtering. Remaining: manual visual/keyboard QA, tooltip positioning near panel edges, and any concrete issues found.
4. Implemented locally: derived side-scoped registration, held, attendance, event-created, and review-resolution activity. Remaining: durable activity-log records and coverage for additional lifecycle/public verification activity.
5. Implemented: Church Categories and D-11 participant history panel. Browser acceptance remains pending.
6. Implemented as a Running statistics band (recent runs, average show rate, paper-encoded count, first-timer count); chart visualization remains a DASH-3 follow-up or explicit deferral.
7. Remaining: manual accessibility, phone, and desktop layout review against `docs/specs/dashboard_update.md`.

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

- [x] Focused Church category workflow regressions pass (5 tests, 110 assertions), covering category/pool isolation, active-only counts, Youth shared-workflow listing, and Youth category creation.
- [x] Full `php artisan test` passes on current local changes (980 passed, 3 skipped, 4,320 assertions).
- [x] Frontend `check`, Pint and `git diff --check` pass; TypeScript reports 12 diagnostics (one Wayfinder typing issue plus 11 errors in five unrelated UI files).
- [ ] PHPStan passes; analysis remains blocked by baseline/stale-suppression debt and reports DashboardController type findings. No suppressions or baseline entries were added for this pass.
- [ ] Browser-verified D-11 page and registration link; confirm no data leaks across pools.
- [ ] Browser-verified generic Event, Service bridge, Fellowship bridge, group assignment and historical attendance.
- [ ] Fresh migration and upgrade/recovery path tested on disposable/staging database.
- [ ] Current branch remote CI and live external-service acceptance verified.
