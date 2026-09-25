# WDEMS — Development Progress Log

**Purpose:** Structured, dated record of every change made to the
project. Complements `development-log.md` (narrative) and
`known-issues.md` (backlog) by listing changes by date and file.

**When to add an entry:** after a completed work session. Not after
every commit — after a coherent batch of changes.

**When to update an existing entry:** never. Corrections go in a new
dated entry. The log is append-only.

**Where to look first when investigating "when did X change":** here.

---

## How to read this log

Each dated entry has the same shape:

- **Summary** — one paragraph on what happened
- **Documentation** — files created or appended
- **Code** — source files changed, with one-line rationale
- **Tests** — test files changed, with counts
- **Database** — migrations, seeders, schema
- **Environment** — `.env`, config, external services
- **Bugs fixed** — numbered, with severity
- **Bugs found but not fixed** — numbered, pointing at `known-issues.md`
- **Failures during the session** — real problems that occurred and how
  they were recovered from
- **Verification** — how the changes were confirmed working
- **Cross-reference** — which subsystem docs contain the detail

---

## 2026-09-25 — Documentation pass + authentication fixes

### Summary

Full documentation pass on four subsystems (auth, event creation,
event deletion, RBAC/backlog) plus two live bug fixes and one
environment recovery. Eight new docs written, five existing docs
corrected. Three real bugs found and fixed: the email verification
gate was a silent no-op, admin got locked out after the gate was
fixed, and logout forced 2FA on every login.

The session also recovered from a self-inflicted database wipe —
`config:cache` in local dev caused tests to run against MySQL
instead of SQLite `:memory:`, and `RefreshDatabase` dropped every
table.

**Auth suite at end of session:** 44 tests passed, 120 assertions.

### Documentation created

| File | Lines | Purpose |
|---|---|---|
| `docs/AI-CONTEXT.md` | ~230 | Paste-me-at-chat-start file for new AI sessions |
| `docs/authentication.md` | 714 | Auth subsystem — login, 2FA, trusted devices |
| `docs/event-creation.md` | 736 | Event code reference — creation, options, workflow |
| `docs/event-creation-wizard.md` | 910 | Wizard feature doc — was truncated to 140 lines, rewritten |
| `docs/event-deletion.md` | 595 | Deletion subsystem — admin direct path + staff OTP path |
| `docs/known-issues.md` | 521 | Backlog — ISSUE-001 through ISSUE-006 |
| `docs/demo-email.md` | ~250 | Repeatable Resend email demo procedure |
| `docs/dev-workflow/README.md` | ~200 | Project workflow rules and failure modes |
| `docs/progress.md` | this file | Structured dated changelog |

### Documentation corrected

All corrections are append-only — the original text stays, a
`## Corrections` block revokes it.

| File | Change |
|---|---|
| `docs/architecture.md` | Fixed 4 factual errors in the auth section (cache key, enum list, device-token hash, test count). Added pointer to `authentication.md` |
| `docs/event-creation.md` | §12 item 3 corrected — wizard doc is a live companion, not a historical record |
| `docs/development-log.md` | Appended "What was added since September 18" narrative + a corrections table for stale claims |
| `docs/authentication.md` | Appended `## Corrections` block for ISSUE-006 |
| `docs/AI-CONTEXT.md` | §13 (never run `config:cache` in local dev), §14 (pre-test checklist), §15 (workflow pointer) |

### Code changes

| File | Change | Rationale |
|---|---|---|
| `app/Models/User.php` | Added `use Illuminate\Contracts\Auth\MustVerifyEmail;` + `implements MustVerifyEmail` | The `verified` middleware only enforces when the model implements this interface. It was not — the entire email verification gate was a silent no-op (ISSUE-005) |
| `app/Http/Middleware/EnsureEmailIsVerifiedOrAdmin.php` | **New file** | Admin bypass for email verification, mirroring the existing 2FA admin bypass in `EnsureDeviceIsTrusted` |
| `bootstrap/app.php` | Registered `verified.or.admin` alias | Points at the new middleware |
| `routes/web.php` | Main protected group changed from `'verified'` to `'verified.or.admin'`. Device-verification group kept strict `'verified'` | Admins bypass email verification on the main group. Unverified users still must verify before the 2FA challenge |
| `routes/settings.php` | Settings group changed from `'verified'` to `'verified.or.admin'` | Same rationale |
| `app/Providers/AppServiceProvider.php` | Removed `configureLogoutCleanup()` method, its call in `boot()`, and four unused imports | The listener cleared the trusted-device cookie on every logout, forcing re-2FA on the next login (ISSUE-006). Per-user trust list already provides the security it claimed to |
| `resources/js/components/app-logo-icon.tsx` | Replaced hardcoded Laravel logo SVG path with WDEMS three-bar mark | Every auth page except login was rendering the Laravel mark because `auth-simple-layout` imports this component |

### Test changes

| File | Change | Result |
|---|---|---|
| `tests/Feature/Auth/EmailVerificationGatingTest.php` | **New** — 6 tests: unverified staff blocked from dashboard and events; verified staff reach both; unverified admin reaches both | Pass |
| `tests/Feature/Auth/LogoutDeviceTrustTest.php` | Assertion inverted — from `assertCookieExpired` to `assertCookieMissing`. Test name changed to "does not forget the device trust cookie on logout" | Pass |
| Full auth suite | Re-run after all changes | **44 passed, 120 assertions, ~4.5s** |

### Database changes

| File | Change |
|---|---|
| `database/seeders/DemoUsersSeeder.php` | **New** — creates two accounts: `admin@wdems.test` (admin, verified, 2FA off) and `pomasinejboy@gmail.com` (staff, unverified, 2FA on). Idempotent — skips existing accounts. Guarded against production environment |
| `database/seeders/DatabaseSeeder.php` | Rewritten. Calls `DemoUsersSeeder`. The previous `test@example.com` factory call was removed — it was a leftover from the Laravel starter kit with no purpose |

### Environment changes

| Change | Reason |
|---|---|
| `.env` line 50: `MAIL_MAILER=log` → `MAIL_MAILER=resend` | Live email delivery via Resend was already configured but the mailer was writing to `storage/logs/laravel.log` |
| `bootstrap/cache/config.php` removed | Config cache in local dev overrode `phpunit.xml` env vars. Root cause of the database wipe |

### Bugs fixed

| # | Bug | Severity | Fix | Evidence |
|---|---|---|---|---|
| ISSUE-005 | `User` did not implement `MustVerifyEmail` — the `verified` middleware was a no-op. Any unverified user could reach the dashboard, events, settings | **High** | Added the interface to the `User` class | Test written first: 2 of 4 tests failed on unfixed code. After fix: all 4 passed |
| ISSUE-006 | Logout cleared the trusted-device cookie, forcing 2FA on every login even on trusted browsers | Low | Removed `configureLogoutCleanup()` and its dependencies from `AppServiceProvider` | Manual browser verification: logged in, trusted device, logged out, logged back in — no 2FA challenge |
| — | Email verification gate applied to admins after ISSUE-005 fix. Admin could not reach `/admin/dashboard` if unverified | Medium | New `EnsureEmailIsVerifiedOrAdmin` middleware. Routes switched to `verified.or.admin` alias | 2 new tests: unverified admin reaches `/dashboard` and `/admin/dashboard` |
| — | Auth pages showed the Laravel logo instead of the WDEMS mark | Cosmetic | Rewrote `app-logo-icon.tsx` to render the WDEMS SVG | Manual browser verification on `/verify-device`, `/verify-email`, `/forgot-password`, `/reset-password`, `/confirm-password` |

### Bugs found but not fixed

| # | Bug | Where documented |
|---|---|---|
| ISSUE-001 | Sidebar is not role-aware — admin and staff see identical navigation | `known-issues.md` |
| ISSUE-002 | Dashboard content is not role-aware — both roles render the same page | `known-issues.md` |
| ISSUE-003 | `admin/dashboard.tsx` is dead code — no controller renders it | `known-issues.md` |
| ISSUE-004 | No authorization policy on event edit — any authenticated staff can edit any event | `known-issues.md` |
| — | `README.md` Tinker snippet uses `User::create()` but `email_verified_at` is not fillable — silently produces unverified users | `README.md`, needs correction |
| — | Migration `2026_09_21_093134_add_event_metadata_to_events_table` takes ~4 minutes on this machine. Not diagnosed | Unexplained |

### Failures during the session

These are real problems that occurred while working, and how they were
recovered. Recorded so the same failures can be recognized faster next
time.

#### 1. `config:cache` in local development wiped MySQL

**What happened.** `php artisan config:cache` was run during a mail
debug. This wrote `bootstrap/cache/config.php`. From then on, every
`php artisan test` run ignored `phpunit.xml`'s `DB_CONNECTION=sqlite`
and `DB_DATABASE=:memory:`, and connected to MySQL instead.
`RefreshDatabase` then dropped every table in the `wdems` database,
including the demo accounts.

**Recovery.** `rm -f bootstrap/cache/config.php` + `php artisan
config:clear` + `php artisan migrate:fresh` + re-seed.

**Prevention.** Documented in `docs/AI-CONTEXT.md` §13 as a
forbidden command in local dev.

#### 2. Concurrent artisan processes fought over MySQL

**What happened.** A second `composer run dev` (or its queue worker)
was still running while `migrate:fresh` executed in another terminal.
Migrations that should take under a second each took 20–40 seconds,
and several failed with "table already exists" errors.

**Recovery.** `pkill -f artisan` then re-ran migrations with only one
process active.

**Prevention.** Documented in `docs/AI-CONTEXT.md` §13.

#### 3. Verification email landed in Gmail Spam

**What happened.** Resend's shared sender `onboarding@resend.dev`
sends link-emails that Gmail classifies as bulk mail. The email is
delivered (Resend dashboard shows "Delivered") but Gmail routes it to
Spam rather than Inbox.

**Recovery.** Clicked "Report not spam" in Gmail. Subsequent sends to
the same address land in Inbox.

**Prevention.** Documented as a known caveat in `docs/demo-email.md`
§7. Permanent fix requires a verified custom domain at Resend.

### Verification

All changes at end of session were confirmed with:

| Check | Result |
|---|---|
| `php artisan test tests/Feature/Auth` | 44 passed, 120 assertions, 4.57s |
| Manual browser: login → email verify → 2FA → dashboard | Works |
| Manual browser: logout → login again on same device | No 2FA challenge |
| Manual browser: admin logs in unverified | Reaches `/admin/dashboard` |
| Resend dashboard: verification email | Delivered |
| Resend dashboard: 2FA email | Delivered |
| Gmail: 2FA code entered, accepted | Works |
| `php artisan db:seed` twice in a row | Idempotent — skips on second run |
| Account count after cleanup | 2 (`admin@wdems.test` + `pomasinejboy@gmail.com`) |

### Cross-reference

| Subsystem | Primary doc |
|---|---|
| Authentication (login, 2FA, trusted devices) | `docs/authentication.md` |
| Event creation (code reference) | `docs/event-creation.md` |
| Event creation wizard (feature view) | `docs/event-creation-wizard.md` |
| Event deletion (two paths) | `docs/event-deletion.md` |
| Backlog and open issues | `docs/known-issues.md` |
| Live email demo procedure | `docs/demo-email.md` |
| Development workflow rules | `docs/dev-workflow/README.md` |
| Whole-system overview | `docs/architecture.md` |
| Chronological narrative | `docs/development-log.md` |
| AI session context | `docs/AI-CONTEXT.md` |

### Files touched this date — full inventory

```
Documentation (new):
  docs/AI-CONTEXT.md
  docs/authentication.md
  docs/demo-email.md
  docs/event-creation.md
  docs/event-creation-wizard.md
  docs/event-deletion.md
  docs/known-issues.md
  docs/progress.md
  docs/dev-workflow/README.md

Documentation (appended):
  docs/architecture.md
  docs/development-log.md

Code:
  app/Http/Middleware/EnsureEmailIsVerifiedOrAdmin.php   (new)
  app/Models/User.php
  app/Providers/AppServiceProvider.php
  bootstrap/app.php
  resources/js/components/app-logo-icon.tsx
  routes/settings.php
  routes/web.php

Tests:
  tests/Feature/Auth/EmailVerificationGatingTest.php     (new)
  tests/Feature/Auth/LogoutDeviceTrustTest.php

Database:
  database/seeders/DatabaseSeeder.php
  database/seeders/DemoUsersSeeder.php                   (new)

Environment:
  .env (line 50 — MAIL_MAILER)
  bootstrap/cache/config.php                             (removed)
```

---

## How to add a new entry

When starting a new dated entry, copy this template:

    ## YYYY-MM-DD — Short title

    ### Summary
    One paragraph: what happened, why, and the outcome.

    ### Documentation created
    ### Documentation corrected
    ### Code changes
    ### Test changes
    ### Database changes
    ### Environment changes
    ### Bugs fixed
    ### Bugs found but not fixed
    ### Failures during the session
    ### Verification
    ### Cross-reference
    ### Files touched this date — full inventory

Omit sections that do not apply. Never rewrite an existing entry. If
an earlier entry turns out to be wrong, add a `## Corrections` block
at the bottom of this file (not inside the entry).

---

## See also

For the narrative story behind these changes — what was built, in what
order, and why — see `development-log.md`. This file lists what
changed; that file explains the arc.

---

## 2026-09-25 — Event creation review + fixes.md + workflow doc

### Summary

Second documentation pass of the same day. Focused on event creation
issues and on closing the tracking loop for the auth fixes.

Three new docs: a consolidated fixed-problems reference
(`docs/fixes.md`), an event-creation issues backlog
(`docs/event-creation-issues.md`), and a development workflow guide
(`docs/dev-workflow/README.md`).

Three documentation gaps identified inside `docs/fixes.md` were
closed by appending to `authentication.md`, `architecture.md`, and
`AI-CONTEXT.md`.

No code changes in this entry. Documentation only.

### Documentation created

| File | Lines | Purpose |
|---|---|---|
| `docs/fixes.md` | 472 (+ append) | Consolidated "what has been fixed" reference, FIX-001 through FIX-007 |
| `docs/event-creation-issues.md` | 713 | EC-01 through EC-11 — event creation problems in priority order |
| `docs/dev-workflow/README.md` | ~200 | How we work — protocols, failure modes |

### Documentation appended

| File | Change |
|---|---|
| `docs/authentication.md` | `## Corrections — Email Verification` block — records FIX-002 and FIX-003 |
| `docs/architecture.md` | `## Authentication — Middleware Update` block — records the `verified.or.admin` alias and `MustVerifyEmail` interface |
| `docs/AI-CONTEXT.md` | §16 — never-touch additions for the middleware and interface |
| `docs/fixes.md` | Gap closure note — records that GAP-001 through GAP-003 are resolved |
| `docs/progress.md` | This entry |

### Event creation issues recorded

Eleven issues in `docs/event-creation-issues.md`. Priority order:

1. **EC-01** — `venue_latitude.toFixed()` crashes on edit (High)
2. **EC-02** — Partner sub-errors never route to Step 4 (High)
3. **EC-03** — Partner errors never displayed in ExtrasStep (Medium)
4. **EC-04** — No `end_time > start_time` check (Medium)
5. **EC-05** — `course_url` rule rejects scheme-less URLs (Medium)
6. **EC-06** — No cap on `partners` array size (Low)
7. **EC-07** — No trimming of text inputs (Low)
8. **EC-08** — `clear()` fires before submit (Low)
9. **EC-09** — No past-date check on `event_date` (Low, needs decision)
10. **EC-10** — `event_name` regexes reject non-ASCII names (Low, likely intentional)
11. **EC-11** — Client-side validation is emptiness-only (Note)

None fixed yet. All documented for a future work session.

### Environment changes

None. Documentation only.

### Verification

Each append verified with `wc -l` and `grep -n "UNIQUE_DELIMITER"`.
No delimiter leaked into any file.

Final line counts:

- `docs/authentication.md` — 790
- `docs/architecture.md` — 434
- `docs/AI-CONTEXT.md` — 340

### Cross-reference

| Subsystem | Primary doc |
|---|---|
| Fixed problems reference | `docs/fixes.md` |
| Open event-creation issues | `docs/event-creation-issues.md` |
| Development workflow rules | `docs/dev-workflow/README.md` |

---

## See also

For the narrative story behind these changes — what was built, in what
order, and why — see `development-log.md`. This file lists what
changed; that file explains the arc.

---

## 2026-09-25 — Event creation: validation layer + Schedule step

### Summary

Development session focused on the event creation wizard. Added a
proper client-side validation layer, fixed the venue coordinates crash
on the edit page, and closed the entire Schedule step's rule set.

Six new fixes (FIX-008 through FIX-013). Six event-creation issues
closed (EC-01, EC-02, EC-04, EC-05, EC-06, EC-11). One new issue found
and fixed in the same session (SCH-03, `data:` and `javascript:` URLs
accepted by `course_url`).

**Test suite at end of session:** 13 new feature tests, all passing.
Existing auth suite unaffected.

### Documentation created

None. All docs from the previous session still stand.

### Documentation appended

| File | Change |
|---|---|
| `docs/event-creation-issues.md` | "Fixed items" section — marks six issues as resolved, adds SCH-03, lists the remaining open items |
| `docs/fixes.md` | FIX-008 through FIX-013 — six full entries with root cause and evidence |
| `docs/progress.md` | This entry |

### Code changes

| File | Change | Rationale |
|---|---|---|
| `app/Concerns/EventValidationRules.php` | Added `MIN_EVENT_DURATION_MINUTES = 60`. `end_time` gained `after:start_time`. `course_url` swapped `'url'` → regex. New `validateEventDuration()` and `eventMessages()` methods | SCH-01a/b/c, SCH-02/03. Also capped `partners` at `max:20` |
| `app/Http/Requests/EventRequest.php` | Added `withValidator()` and `messages()` | Wire the min-duration hook and custom messages |
| `resources/js/lib/event-validation.ts` | **New file.** Four per-step validators mirroring server rules | Client-side validation (EC-11) |
| `resources/js/components/wizard.tsx` | `WizardStepConfig` gained `validate?`. Error-jump uses prefix matching. `children(stepId, errors)` signature. Live re-validation effect | EC-02, EC-11, FIX-013 |
| `resources/js/pages/events/step/BasicsStep.tsx` | Heading `Event Basics` → `Event Details`. Removed a dead ternary | Naming consistency + cleanup |
| `resources/js/pages/events/step/ScheduleStep.tsx` | Wired end_time `clearable`. Added `normalizeCourseUrl()` and `onBlur` handler | FIX-010, FIX-011 |
| `resources/js/components/time-picker.tsx` | Added `clearable` prop and X button. Pass `''` instead of `undefined` to Radix Select | FIX-010, FIX-012 |
| `resources/js/pages/events/create.tsx` | Wired the four validators. Render callback signature updated. Pill label `Basics` → `Details` | FIX-009 |
| `resources/js/pages/events/edit.tsx` | Wired validators. Added `normalizeCoordinate()`. Fixed `venue_latitude.toFixed()` crash. Pill label `Basics` → `Details` | EC-01, FIX-009 |

### Test changes

| File | Change | Result |
|---|---|---|
| `tests/Feature/Events/EventScheduleRulesTest.php` | **New** — 13 tests covering time ordering, duration boundaries, URL schemes | All pass |

### Database changes

None.

### Environment changes

None.

### Bugs fixed

| # | Bug | Fix | Evidence |
|---|---|---|---|
| FIX-008 | Event edit page crashed on coordinates | `normalizeCoordinate()` in `edit.tsx` | Browser: edit page renders |
| FIX-009 | Client-side validation was emptiness-only; sub-errors mis-routed | `event-validation.ts` + wizard hook + prefix matching | Browser + `tsc` |
| FIX-010 | No time ordering or duration rule; end_time not clearable | Server rules + `withValidator` + `clearable` prop | 13 feature tests |
| FIX-011 | URL accepted `data:` / `javascript:`; rejected scheme-less URLs | Regex on both ends + auto-prepend on blur | 5 feature tests |
| FIX-012 | TimePicker clear button left stale value | Radix Select: pass `''` not `undefined` | Browser |
| FIX-013 | Errors persisted after user fixed field | Live re-validation effect | Browser |

### Bugs found but not fixed

| # | Bug | Where documented |
|---|---|---|
| EC-03 | Partner errors not displayed in ExtrasStep | `docs/event-creation-issues.md` |
| EC-07 | No trimming of text inputs | Same |
| EC-08 | `clear()` fires before submit | Same |
| EC-09 | No past-date check on `event_date` | Same — needs a decision |
| EC-10 | `event_name` regexes reject non-ASCII | Same — needs a decision |
| SCH-04 | Time picker 5-minute snap is invisible | Same — cosmetic |
| SCH-05 | Distance unit conversion drift | Same — cosmetic |

### Failures during the session

**None.** Two sed commands missed their target during Block 4a
(`^` anchors were off by four spaces of indentation). Diagnosed
immediately and corrected with unanchored seds. The failure was
documented in chat and the correction landed clean.

### Verification

| Check | Result |
|---|---|
| `php artisan test tests/Feature/Events/EventScheduleRulesTest.php` | 13 passed, 32 assertions |
| `npx tsc --noEmit` | Silent |
| Browser: `/events/{id}/edit` with coordinates | Renders, map loads |
| Browser: garbage in Event Name | Rejected with "Please enter a valid event name." |
| Browser: end_time before start | Rejected |
| Browser: end_time under 60 min | Rejected |
| Browser: end_time clearable | X button clears all three selects |
| Browser: scheme-less URL auto-prepend | `maps.app.goo.gl/x` → `https://maps.app.goo.gl/x` |
| Browser: fix a field → error clears | Confirmed |

### Cross-reference

| Subsystem | Primary doc |
|---|---|
| Fixed problems reference | `docs/fixes.md` |
| Open event-creation issues | `docs/event-creation-issues.md` |
| Auth subsystem | `docs/authentication.md` |

---

## See also

For the narrative story behind these changes — what was built, in what
order, and why — see `development-log.md`. This file lists what
changed; that file explains the arc.

---

## 2026-09-25 — Schedule step completion

### Summary

Fourth work block of the same day. Closed the remaining Schedule step
issues: time picker intervals, date bounds, and a soft warning for
same-day events.

Two new fixes (FIX-014, FIX-015). Four event-creation issues closed
(SCH-04, SCH-06, SCH-06c, SCH-07). One new test file added
(`EventDateRulesTest.php`, 8 tests).

The Schedule step is now fully covered. Eight of the original nine
Schedule issues are closed. SCH-05 (distance unit conversion drift)
remains open as a decision item.

### Documentation created

None.

### Documentation appended

| File | Change |
|---|---|
| `docs/fixes.md` | FIX-014 and FIX-015 |
| `docs/event-creation-issues.md` | "Additional fixed items" section — marks SCH-04, SCH-06, SCH-06c, SCH-07 as fixed. Adds a consolidated list of every closed issue |
| `docs/progress.md` | This entry |

### Code changes

| File | Change | Rationale |
|---|---|---|
| `app/Concerns/EventValidationRules.php` | Added `MAX_EVENT_YEARS_AHEAD = 2`. `event_date` gained `after_or_equal:today` and `before:+2 years`. New messages | SCH-06, SCH-07 |
| `resources/js/components/time-picker.tsx` | `MINUTES` reduced to `[0, 15, 30, 45]`. Snap logic updated to `Math.min(45, Math.round(m / 15) * 15)` | SCH-04 |
| `resources/js/components/date-picker.tsx` | Added `minDate` and `maxDate` props. Out-of-range day cells render disabled. "Today" shortcut disabled when out of range | SCH-06, SCH-07 |
| `resources/js/lib/event-validation.ts` | Added `MAX_EVENT_YEARS_AHEAD`, `isoToLocalMidnight()`. Date bound checks in `validateSchedule` | SCH-06, SCH-07 |
| `resources/js/pages/events/step/ScheduleStep.tsx` | Added `pickerBounds()`, `todayIso()`, `isScheduledForToday`. Wired bounds to `<DatePicker>`. Added amber today warning. Updated date helper text | SCH-04, SCH-06, SCH-06c, SCH-07 |

### Test changes

| File | Change | Result |
|---|---|---|
| `tests/Feature/Events/EventDateRulesTest.php` | **New** — 8 tests covering past and future date bounds | All pass |

### Database changes

None.

### Environment changes

None.

### Bugs fixed

| # | Bug | Fix | Evidence |
|---|---|---|---|
| FIX-014 | Time picker forced 5-minute snap with no explanation | 15-minute intervals in the dropdown | Browser: 4 options only |
| FIX-015 | `event_date` had no bounds; calendar allowed any date | Server + client rules and calendar constraints, plus a today warning | 8 feature tests + browser |

### Bugs found but not fixed

Unchanged from the previous entry. See `docs/event-creation-issues.md`
for the current list.

### Failures during the session

**None.** All blocks landed first try.

### Verification

| Check | Result |
|---|---|
| `php artisan test tests/Feature/Events/EventDateRulesTest.php` | 8 passed |
| `php artisan test tests/Feature/Events/EventScheduleRulesTest.php` | 13 passed (unchanged) |
| `npx tsc --noEmit` | Silent |
| Browser: minute dropdown | Four options only |
| Browser: calendar past days | Disabled |
| Browser: calendar far-future days | Disabled |
| Browser: pick today | Warning appears |
| Browser: pick tomorrow | Warning hides |
| Browser: clear date | Warning hides |

### Cross-reference

| Subsystem | Primary doc |
|---|---|
| Fixed problems reference | `docs/fixes.md` |
| Open event-creation issues | `docs/event-creation-issues.md` |
| Schedule step in the wizard | `docs/event-creation-wizard.md` |

---

## See also

For the narrative story behind these changes — what was built, in what
order, and why — see `development-log.md`. This file lists what
changed; that file explains the arc.

---

## 2026-09-25 — RSVP removal, partner hygiene, and git consolidation

### Summary

Seventh work block of the same day. Three feature changes plus one
infrastructure recovery:

1. **RSVP flag fully removed** — column dropped, validation removed,
   controller reads/writes removed, wizard field removed, show page
   DetailRow removed. Five FIX entries: FIX-017 for the removal,
   FIX-018 for the partner duplicate rule that was added in the same
   commit, FIX-019 for the Sensory accessibility group merge, FIX-020
   for the shared `humanNameQualityRules()` extraction, FIX-021 for
   the inline partner error display.

2. **Partner hygiene** — duplicate rule (client + server, (ii)
   semantics: name AND type must both match), quality rules
   (`humanNameQualityRules()` shared with `event_name`), inline error
   rendering (closes EC-03).

3. **Git consolidation** — 20 untracked files were brought into git in
   commit `84c33a0`, including all of `docs/`, the
   `EnsureEmailIsVerifiedOrAdmin` middleware, `DemoUsersSeeder`, the
   `venue_map_url` migration, and three test files. Before this commit
   the entire documentation system was untracked.

Two new workflow protocols recorded as §9 in
`docs/dev-workflow/README.md`: P9 (commit messages from diffs, not
docs) and P10 (do not commit unread files). Both were motivated by
failures during this session.

**Test suite at end of session:** 54 passed, 145 assertions —
unchanged from the previous block. Both `php artisan test
tests/Feature/Events` and `php artisan test tests/Feature/Auth` run
clean.

### Documentation created

None.

### Documentation appended

| File | Change |
|---|---|
| `docs/fixes.md` | FIX-017 through FIX-021 |
| `docs/progress.md` | This entry |
| `docs/dev-workflow/README.md` | §9 — P9, P10, plus P9 verification addendum |
| `docs/event-creation-issues.md` | EC-03 marked closed, EC-12 opened |
| `README.md` | Four drift corrections — Map picker row dropped, `forceCreate` in Tinker snippet, middleware list updated, docs index expanded |
| `docs/event-creation-issues.md` | (also) RSVP + partner section resolutions |

### Code changes

| File | Change | Rationale |
|---|---|---|
| `database/migrations/2026_09_25_170000_remove_rsvp_required_from_events_table.php` | **New** | Drops the column. `down()` re-adds at original position |
| `app/Models/Event.php` | Removed `rsvp_required` from fillable + cast | FIX-017 |
| `app/Concerns/EventValidationRules.php` | Removed `rsvp_required` rule. Added `humanNameQualityRules()` and `validatePartnerDuplicates()`. Refactored `eventNameRules()`. Expanded `partners.*.name` | FIX-017, FIX-018, FIX-020 |
| `app/Http/Controllers/EventController.php` | Removed 4 `rsvp_required` references | FIX-017 |
| `app/Http/Requests/EventRequest.php` | `withValidator()` calls both duration and duplicate validators | FIX-018 |
| `resources/js/lib/event-validation.ts` | **Restored** after untracked-file corruption. Added `humanNameQualityError()`. Duplicate pass in `validateExtras`. Now git-tracked | FIX-018, FIX-020 |
| `resources/js/components/accessibility-grid.tsx` | Removed Sensory group, merged into Physical Access | FIX-019 |
| `resources/js/components/partner-editor.tsx` | Accepts `errors?` prop, renders inline `<InputError>` per row | FIX-021 |
| `resources/js/pages/events/create.tsx` | Removed `rsvp_required` from fields + defaults | FIX-017 |
| `resources/js/pages/events/edit.tsx` | Same as create | FIX-017 |
| `resources/js/pages/events/show.tsx` | Removed `rsvp_required` type field + `<DetailRow>` | FIX-017 |
| `resources/js/pages/events/step/ExtrasStep.tsx` | Rewrote without Registration section. Added partner counter, cap message, top-level error slot. Passes errors down | FIX-017, FIX-021 |

### Test changes

None. Suite stayed at 54 / 145 across the entire block.

**Gap:** the partner duplicate rule and partner name quality rules have
no test coverage. Recorded as EC-12.

### Database changes

| Change | Note |
|---|---|
| New migration drops `rsvp_required` | Reversible via `down()` |
| Base migration `2026_09_21_093134_*` untouched | Per AI-CONTEXT §8 |

### Environment changes

None.

### Bugs fixed

| # | Bug | Fix | Evidence |
|---|---|---|---|
| FIX-017 | RSVP flag survived two refactor passes; no longer decided anything | Removed column, validation, controller references, wizard field, show page DetailRow | 54/145 stable |
| FIX-018 | Same partner+role could appear twice | Duplicate rule on client + server, (ii) semantics | `tsc` + `php -l` clean |
| FIX-019 | Single-item Sensory category header | Merged into Physical Access | Browser: 3 groups render |
| FIX-020 | Partner names had no quality filter (unlike event_name) | Extracted `humanNameQualityRules()`, applied to both | Browser: garbage name shows "Please enter a valid partner name." |
| FIX-021 | Partner errors triggered the top banner but rendered nowhere | Added `errors` prop to PartnerEditor, inline `<InputError>` per row | Browser: garbage name shows inline red text (screenshot) |

### Bugs found but not fixed

| # | Bug | Where documented |
|---|---|---|
| EC-12 | Partner duplicate rule has no test coverage | `docs/event-creation-issues.md` |
| — | Walk-in messaging vanished from the wizard with the RSVP helper text | `docs/event-creation-issues.md` |

### Failures during the session

**Two.**

**1. `sed` over-consumed on `show.tsx` (Block 2).** A fixed-count
`N;N;N;N;N;N;N;N` navigation instead of a loop-until-terminator pattern
grabbed a 9-line window that spanned two `<DetailRow>` blocks. It
deleted the wrong one. Recovered via `git checkout` — the file was
tracked. Re-ran with `/<DetailRow$/{:a;N;/\/>$/!ba;/label="RSVP"/d}`,
which consumes one JSX element per match. Verified against
`sed -n '310,360p'` before re-running.

**2. `event-validation.ts` corrupted by bash history expansion.**
`grep -Fn "    if (!eventName) {"` inside `$(...)` command
substitution triggered bash history expansion on `!eventName`. The
`START` variable went empty, `head -n $((START - 1))` became
`head -n -1` (all lines but the last), and the file was corrupted to
614 lines with duplicated validators.

The recovery path — `git checkout resources/js/lib/event-validation.ts`
— **failed** because the file was not tracked (`??` in git status).
This was the highest-impact failure of the session: the file had to be
reconstructed from a known-good snapshot pasted earlier in the session.

Both root causes have been addressed:

- **Bash history expansion:** grep patterns in `$(...)` command
  substitution must be single-quoted unless shell interpolation is
  required.
- **Untracked files:** before proposing any split-edit pattern, run
  `git ls-files <target>` to confirm the file is tracked. If untracked,
  `git add` first or use a non-destructive write.

The second lesson led directly to commit `84c33a0`, which brought 20
untracked files into git.

### Verification

| Check | Result |
|---|---|
| `php artisan test tests/Feature/Events` (after `533658e`) | 54 passed, 145 assertions |
| `php artisan migrate` (drop rsvp_required) | DONE in 3s |
| `php artisan test tests/Feature/Events` (after migration) | 54 passed, 145 assertions |
| `php artisan test tests/Feature/Auth` | 44 passed, 119 assertions |
| `npx tsc --noEmit` | silent |
| Browser: Extras step renders without Registration section | confirmed |
| Browser: partners counter increments on add | confirmed |
| Browser: garbage partner name shows inline error | confirmed |
| Browser: empty partner row shows inline error | confirmed |

### Cross-reference

| Subsystem | Primary doc |
|---|---|
| Fixed problems reference | `docs/fixes.md` (FIX-017 through FIX-021) |
| Open event-creation issues | `docs/event-creation-issues.md` (EC-12 new, EC-03 closed) |
| Workflow protocols | `docs/dev-workflow/README.md` §9 |
| Event schema and wizard | `docs/event-creation-wizard.md` |

### Files touched this date — full inventory


    Documentation (appended):
      docs/fixes.md
      docs/progress.md
      docs/dev-workflow/README.md
      docs/event-creation-issues.md
      README.md

    Code:
      app/Concerns/EventValidationRules.php
      app/Http/Controllers/EventController.php
      app/Http/Requests/EventRequest.php
      app/Models/Event.php
      resources/js/components/accessibility-grid.tsx
      resources/js/components/partner-editor.tsx
      resources/js/lib/event-validation.ts                    (restored after corruption)
      resources/js/pages/events/create.tsx
      resources/js/pages/events/edit.tsx
      resources/js/pages/events/show.tsx
      resources/js/pages/events/step/ExtrasStep.tsx

    Database:
      database/migrations/2026_09_25_170000_remove_rsvp_required_from_events_table.php   (new)

    Commits (newest to oldest):
      c3e1590  docs: land Google-removal README pass; correct four drift items
      84c33a0  chore: track middleware, migrations, seeders, tests, and documentation
      0d7328f  fix: render partner errors inline; drop rsvp_required from EventController
      533658e  refactor: remove RSVP flag; add partner duplicate rule; merge Sensory into Physical Access; extract humanNameQualityRules

---

## See also

For the narrative story behind these changes — what was built, in what
order, and why — see `development-log.md`. This file lists what
changed; that file explains the arc.
