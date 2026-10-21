# WDEMS — AI Chat Context

**Paste this file at the start of every chat with an AI coding tool.**

---

## How to use this file

You are an AI assistant. The developer has pasted this file because
WDEMS is a real project with specific rules and known traps, and you
have no other way to see the codebase.

Follow these rules:

1. **Do not assume anything not in this file or in files the developer
   pastes.** If you need to see a file, ask: "Please `cat <path>`."
2. **Read before proposing changes.** Do not invent signatures, file
   paths, or behavior.
3. **If a task touches a subsystem, ask for the subsystem doc first.**
   See §7.
4. **Respect the never-touch list in §8.** If you think something there
   should change, say so explicitly and explain why.
5. **Check §9 before suggesting a "fix" for something that looks
   wrong.** It may be deliberate.

---

## 1. What WDEMS is

WDEMS — Workflow-Driven Event Management System — is a Laravel + React
web app for managing **running events**, primarily **Community Runs**.

The system handles the full lifecycle:

- Event setup (a 4-step wizard)
- Configuration (organizer-defined options)
- Registration (in progress)
- Event-day attendance (in progress)
- Event deletion (with OTP verification for staff)
- Post-run monitoring (later)

There is **no public sign-up**. Accounts are created by an admin.

## 2. Roles

Two roles exist:

| Role | Can do |
|---|---|
| **Admin** | Everything. Bypasses 2FA. Can delete any event without verification. |
| **Staff** | Runs events day to day. Must verify via email OTP to delete an event. |

Participants are **not** users. They do not log in. Their data lives in
the `participants` table but has no account.

## 3. What is built

- Authentication: Fortify login + **custom email 2FA** (not Fortify
  TOTP) + trusted devices
- Password management, account deletion, email verification
- Event CRUD + 4-step creation wizard with draft checkpoint
- Event Options (organizer-configurable, no hardcoded choices)
- Event lifecycle: draft → configured → registration_open →
  registration_closed → ongoing → completed
- Event Deletion with two paths (admin direct, staff OTP)
- Action Feed dashboard
- Custom Date / Time / Distance pickers, Map picker with reverse
  geocoding

## 4. In progress

- **Registration flow.** Google Forms integration was built then
  removed. Being rebuilt.
- **Attendance UI.** Controller and page exist but the registration
  flow behind them is incomplete.

## 5. Out of scope — do NOT propose these

- Public sign-up
- Google Forms integration (removed — see `docs/archive/`)
- Payments, bookings, scheduling (dropped in the narrowing to
  Community Run)
- Participant-facing accounts
- SMS 2FA

## 6. Tech stack

- Laravel 13, PHP 8.5
- React 19, TypeScript 5.7, Inertia 3
- Tailwind 4, Radix UI
- MySQL (dev/prod), SQLite in-memory (tests)
- Pest 5, Fortify, Vite 8
- Wayfinder (auto-generates `resources/js/routes/**` from PHP routes)

## 7. Reading order for subsystem tasks

Before working on a subsystem, ask the developer to paste the relevant
doc:

| Task touches | Ask for |
|---|---|
| Login, 2FA, sessions, settings/security | `docs/authentication.md` |
| Event creation, edit, options, lifecycle | `docs/event-creation.md` |
| Wizard shell, steps, checkpoint, field components | `docs/event-creation-wizard.md` |
| Event deletion (OTP, admin/staff paths) | `docs/event-deletion.md` |
| RBAC, dashboard, sidebar | `docs/known-issues.md` (ISSUE-001–004) |
| Whole-system overview | `docs/architecture.md` |

**Read the doc before proposing code.**

## 8. Never touch without asking

These are deliberate. If you change them, something breaks silently.

- `config/fortify.php` — features array. **TOTP 2FA is off on purpose.**
- `resources/js/routes/**` and `resources/js/actions/**` — Wayfinder-
  generated. Edits are erased on the next dev run.
- Base migrations (`0001_01_01_*`) — have already run everywhere.
  Create a new migration instead.
- `User::$fillable` — does **not** include `email_two_factor_enabled`
  or `trusted_devices`. Those go through `forceFill()`.
- `EnsureDeviceIsTrusted` admin check — removing it locks admins out.
- `EventDeletionService::delete()` order — required because of a
  RESTRICT foreign key.
- `EventValidationRules::event_name` regexes — 5 rules, all needed.
- `EventValidationRules::partnerTypes()` — must stay in sync with
  `partner-editor.tsx`.
- `EventStatus::allowedTransitions()` — the state machine.

## 9. Common AI mistakes

If you find yourself about to do one of these, stop and ask.

1. **Enabling Fortify's TOTP 2FA.** WDEMS uses a custom email 2FA.
   They are separate systems.
2. **Editing Wayfinder-generated files.** Edit the PHP route, not the
   generated file.
3. **Editing base migrations.** Create a new migration.
4. **Adding 2FA fields to `User::$fillable`.** Security-sensitive. Use
   `forceFill()`.
5. **Simplifying `forceFill()` to `fill()`.** Silently drops the 2FA
   fields.
6. **Removing the admin check in `EventDeletionController::destroy()`.**
   Staff could delete without OTP.
7. **Logging OTP codes.** Never. They are secrets.
8. **Reusing `verify-device.tsx` for the deletion OTP.** They look
   similar but behave differently. Keep them separate.
9. **Adding FAQ to the wizard.** FAQ is seeded server-side, not
   editable from any UI.
10. **Making `event_type` editable.** The controller ignores it on
    update. Deliberate.
11. **Queuing the OTP mailables.** They are sent synchronously on
    purpose.
12. **Assuming the admin dashboard is different from the staff
    dashboard.** It is not — see `known-issues.md` ISSUE-002.
13. **Proposing generic event-management features.** No payments, no
    bookings, no public sign-up. See §5.

## 10. How changes should be proposed

The developer uses a **cat-and-verify** workflow:

- Files are read via `cat` before editing.
- Edits are applied via `cat > file <<'EOF'` heredocs (`cat >>` for
  appends).
- After every edit, verify with `wc -l`, `head`, `tail`, and a `grep`
  for the delimiter.
- **Never use `sed`, `python`, `nano`, or an editor** unless the
  developer explicitly asks.

When proposing a change, give:

1. The exact `cat` command with the new file contents.
2. The verify commands to run after.

Do not summarize what the change will do and then ask permission to
proceed. Produce the heredoc.

## 11. Where problems get logged

New problems go in `docs/known-issues.md`. Do not silently work around
a problem. If you find one:

1. Note it explicitly.
2. Tell the developer to add it to the backlog (or add it via heredoc).

## 12. Reference — one-line map

| File | Purpose |
|---|---|
| `README.md` | Entry point |
| `docs/architecture.md` | Whole-system overview |
| `docs/authentication.md` | Auth subsystem |
| `docs/event-creation.md` | Event code reference |
| `docs/event-creation-wizard.md` | Wizard as a feature |
| `docs/event-deletion.md` | Deletion subsystem |
| `docs/known-issues.md` | Backlog |
| `docs/development-log.md` | Chronological log |
| `docs/archive/` | Historical (Google integration, removed) |

---

## 13. Never run these in local development

The following commands cause silent, hard-to-diagnose breakage in local
dev. Do not use them unless you know exactly why.

### `php artisan config:cache`

Writes a compiled `bootstrap/cache/config.php`. From that moment on:

- **`.env` changes are ignored** until you run `config:clear`.
- **`phpunit.xml` env overrides are ignored.** Feature tests will run
  against the DB configured in `.env` (usually MySQL) instead of
  SQLite `:memory:`.
- `RefreshDatabase` will then **drop every table** in that MySQL
  database, including real data.

This exact cascade happened once. A `config:cache` in local dev caused
the next test run to wipe the `wdems` MySQL database, deleting all
demo accounts and requiring `migrate:fresh`.

**Detection:**

    ls bootstrap/cache/config.php

If that file exists in local dev, something is wrong.

**Recovery:**

    rm -f bootstrap/cache/config.php
    php artisan config:clear

### `php artisan route:cache` and `view:cache`

Same class of problem. Rarely needed outside production. Skip them in
local dev.

### `php artisan optimize`

Bundles `config:cache`, `route:cache`, and `view:cache`. Never in dev.

### Multiple artisan processes on the same DB

Running `php artisan test`, `php artisan migrate`, and
`composer run dev` at the same time will cause:

- Migrations that take 20–40 seconds each (they should take under 1)
- "Table already exists" errors mid-migration
- Intermittent `migrations` table missing errors
- Tests that fail with no obvious cause

**Detection:**

    ps aux | grep -E "artisan|pest|phpunit" | grep -v grep

If more than one `artisan` line appears, kill the extras:

    pkill -f "artisan serve"
    pkill -f "queue:work"
    pkill -f "queue:listen"
    pkill -f "artisan pail"
    pkill -f "phpunit"
    pkill -f "pest"

Then re-run the migration or test in isolation.

**Rule:** one artisan process at a time when touching the database.

---

## 14. Pre-test checklist

Before running `php artisan test`, confirm:

1. `ls bootstrap/cache/config.php` — should not exist
2. `php artisan tinker --execute="echo config('database.default');"` —
   should print `mysql` in normal usage, but tests will override to
   `sqlite` **as long as no config cache exists**
3. No other artisan process is running against the same DB

If all three are clean, tests run against SQLite in-memory and take
3–5 seconds for the full suite. If any is off, tests will hit MySQL
and can delete data.

---

## 15. Full workflow rules

The rules above are a summary. The complete workflow — including
protocols, failure modes to avoid, and how this project expects
changes to be proposed — lives in:

    docs/dev-workflow/README.md

Read that file before proposing your first change. It was written from
a real session and includes the specific mistakes to avoid.

---

## 16. Never-touch additions (2026-09-25)

Two files were added to the never-touch list after the section §8 was
written. They are listed here rather than edited into §8 above — the
file is append-only.

### `app/Http/Middleware/EnsureEmailIsVerifiedOrAdmin.php`

The admin check inside this middleware:

    if ($request->user()?->isAdmin()) {
        return $next($request);
    }

Removing it locks every admin with an unverified email out of
`/admin/dashboard` and every other route in the group. This is the
exact lockout that FIX-003 solved on 2026-09-25.

Do not remove. Do not simplify. Do not "clean up" the delegation to
`parent::handle()`.

### The `MustVerifyEmail` interface on `User`

`app/Models/User.php` implements
`Illuminate\Contracts\Auth\MustVerifyEmail`. Removing the interface
silently disables the framework's `verified` middleware on every route
that lists it — recreating FIX-002's no-op gate.

The interface is invisible in ordinary usage. It only matters when the
middleware checks it. Removing it produces no error at boot; it only
produces a missing redirect at runtime, and only for unverified users.

If you need to add a new interface to `User`, do so alongside
`MustVerifyEmail`, not in place of it.

### Reference

Full details of both fixes: `docs/fixes.md` FIX-002 and FIX-003.
Subsystem view: `docs/authentication.md` `## Corrections — Email
Verification`.

---

## 17. Reading-order additions (2026-10-13)

Section §7's reading-order table was written before several
subsystems shipped. Two entries are missing. When working on these
tasks, ask for the corresponding doc:

| Task touches | Ask for |
|---|---|
| Public registration verification, contact OTPs, staff phone queue | `docs/registration-contact-verification.md` |
| Non-obvious behaviors that don't fit a subsystem doc | `docs/implementation-notes.md` |

### Why `registration-contact-verification.md` matters

Since commit `4394986` (2026-10-13), the public form at `/r/{slug}` no
longer creates a Participant or Registration on submit. It creates a
`RegistrationVerificationChallenge` and redirects to a verification
page. Final records only commit after every required channel is
verified.

An AI session that assumes immediate creation will propose fixes for
a flow that no longer exists. Read the doc first.

### Why `implementation-notes.md` matters

It records behaviors that cross subsystem boundaries — error merge
order in the wizard, shared name-quality rules, slug constants, rate
limiter inventory. These are the behaviors most likely to be
"cleaned up" by a well-meaning AI and silently broken.

### Companion file

`docs/fixes.md` — every fixed problem with its evidence. Not a
reading-order entry, but useful when a claim about the current state
seems to contradict something else.

### Cross-references

- Section §7 above — the original reading-order table
- `docs/progress.md` 2026-10-13 entry — the doc-pass record

---

## 18. Phone verification is Firebase-mediated (2026-10-14)

Section §17 above lists this reading-order entry:

> | Public registration verification, contact OTPs, staff phone queue |
> | `docs/registration-contact-verification.md` |

The "staff phone queue" part is stale. Commit `b552cbb` (2026-10-14)
replaced the staff-mediated phone channel with Firebase Phone Auth.
There is no queue, no sidebar entry, no staff code generation.

When working on public registration verification, the current flow is:

- **Email** — six-digit OTP via Resend. Unchanged.
- **Phone** — Firebase Phone Auth in the browser. The user completes a
  reCAPTCHA, receives an SMS from Firebase, and the browser SDK
  produces an ID token. The frontend posts the token to
  `/r/{slug}/verification/phone/firebase`. The backend verifies it
  through the Firebase Admin SDK.

Phone is also now structurally required on every event.
`Event::COMMON_FIELDS_TOGGLEABLE` no longer contains
`contact_number`; it lives in `COMMON_FIELDS_ALWAYS_REQUIRED`.

Section §7's reading-order table still points at the right file
(`docs/registration-contact-verification.md`). Read it together with
its `## Corrections (2026-10-14)` block, which is the authoritative
description of the current phone channel.

### Config touched

    config/firebase.php
    storage/app/firebase/service-account.json  (gitignored)
    .env  VITE_FIREBASE_*  (public web SDK config)
    .env  FIREBASE_CREDENTIALS  (path to service account)

---

## 19. Reading-order additions — second pass (2026-10-14)

Section §7's reading-order table was written before several
subsystems shipped. §17 (2026-10-13) added two entries. Four more
are still missing. When working on these tasks, ask for the
corresponding doc:

| Task touches | Ask for |
|---|---|
| Public registration form, verification challenge, finalize | `docs/registration.md` |
| Attendance code, workspace lookup, `verified_at`, live/deferred encoding | `docs/attendance-code.md` |
| Three-mode attendance workspace (Mark / Confirm / Batch) | `docs/attendance-redesign.md` |
| Attendance encoding spec, finalize event, end-of-event report | `docs/attendance-encoding-and-reporting.md` |

### Why each matters

`docs/registration.md` is the registration feature spec, now marked
implemented via its `## Corrections — Status block (2026-10-13)`
and `## Correction: attendance code issued at finalize` blocks.
Read both corrections before touching the public path.

`docs/attendance-code.md` is the reference for the per-registration
attendance code and the live / deferred encoding modes, shipped in
commit `d05c7e7`. It supersedes the encoding-workspace half of
`attendance-encoding-and-reporting.md`.

`docs/attendance-redesign.md` describes the three-mode workspace
(Mark / Confirm / Batch) that the attendance page implements. The
window gate, search, filter chips, and pagination behavior all live
there.

`docs/attendance-encoding-and-reporting.md` remains the forward spec
for two unbuilt features — finalize event (§4) and end-of-event
report (§5). Do not read it as documentation of current code except
where its `## Pointer: the attendance code shipped (2026-10-14)`
section says otherwise.

### Cross-references

- Section §7 above — the original reading-order table
- Section §17 — first reading-order addition (2026-10-13)
- `docs/progress.md` 2026-10-14 entry — the shipping session

---

## 20. Design system — read before touching colors (2026-10-15)

Visual changes to WDEMS are governed by `docs/design-system.md`.
Read it before proposing any edit to a `.tsx` color class or to
`resources/css/app.css`.

The three most common traps, all documented in full in that file:

1. **Yellow / amber without a light-mode override.** Every yellow
   utility must be paired with `dark:`. Light mode uses
   `#13253B` (WDEMS navy) for the warning semantic. See
   design-system.md §5.

2. **Nested glass.** Do not add a tint to a row inside a card.
   Tier C surfaces are transparent and inherit their parent. See
   §2.

3. **`.text-on-image` on glass.** That utility is only for text
   placed directly on the page image. Inside a panel, use
   `text-foreground`. See §6.

Run `./scripts/audit-design.sh` before committing any UI change.
It checks Rule 1 hard and warns on the other two.

---

## 21. Reading-order additions — third pass (2026-10-16)

Section §7's reading-order table was written before the
church-events workstreams shipped. §17 (2026-10-13) added two
entries. §19 (2026-10-14) added four more. Two remain missing.
When working on these tasks, ask for the corresponding doc:

| Task touches | Ask for |
|---|---|
| Fellowship module — types, events, groups, attendance roster | `docs/fellowship.md` |
| Discipleship module — programs, batches, enrollment, sessions, attendance matrix, progress | `docs/discipleship.md` |

### Why each matters

`docs/fellowship.md` is the consolidated reference for the
Fellowship module, shipped as Phase 4. Covers the six-type
enum, event lifecycle, group management, and attendance. The
module has no enrollment layer and no progress tracking —
Fellowship attendance is a per-event fact, not a course.

`docs/discipleship.md` is the consolidated reference for the
Discipleship module, shipped as Phase 5 across eight sub-phases
(5A–5H). It is the largest single workstream in the
church-events scope: programs, batches, enrollment with a
nullable batch pointer, sessions, a session attendance matrix,
and a progress/completion service.

### Note on Services

`docs/services.md` does not exist. Phase 3 shipped with progress
entries only. The scope inventory
(`docs/church-events/09-mockup-scope-inventory.md` Q1) asks
whether Services remains a top-level module after the mockup
sidebar is applied. Until that question is answered, do not
write a Services subsystem doc — it may become wasted effort.

### Cross-references

- Section §7 — the original reading-order table
- Section §17 — first reading-order addition (2026-10-13)
- Section §19 — second reading-order addition (2026-10-14)
- `docs/church-events/09-mockup-scope-inventory.md` — Q1
  through Q20 awaiting client answers
- `docs/progress.md` 2026-10-16 entries — Phase 4 and Phase 5
  closure records

---

## 22. Q1 answered — Services is staying (2026-10-16)

Section §21 above records:

> `docs/services.md` does not exist. Phase 3 shipped with progress
> entries only. The scope inventory
> (`docs/church-events/09-mockup-scope-inventory.md` Q1) asks
> whether Services remains a top-level module after the mockup
> sidebar is applied. Until that question is answered, do not
> write a Services subsystem doc — it may become wasted effort.

Q1 is now answered. The client confirmed on 2026-10-16 that
Services stays in the sidebar, with Attendance nested beneath it.
The full answer is recorded in
`docs/church-events/10-client-decision-log.md` under Q1.

### What changes

- `docs/services.md` is **unblocked**. Writing it is no longer
  deferred.
- Services remains a top-level sidebar module. The Phase 3
  implementation stays.

### What is still open

Registration's placement in the new sidebar was not addressed in
the same answer. A follow-up question is pending. Until it is
answered, do not move, rename, or fold the Registration module.

### Cross-references

- `docs/church-events/10-client-decision-log.md` — Q1, Q16–Q20
  answers recorded 2026-10-16
- Section §21 above — the original deferral this section revokes

---

## 23. Reading-order additions — fourth pass (2026-10-19)

Section §7's reading-order table was written before several
subsystems shipped. §17, §19, and §21 added entries. The
2026-10-19 registration-wizard and composite-identity work added
three more docs that most sessions will need. When working on these
tasks, ask for the corresponding doc:

| Task touches | Ask for |
|---|---|
| Participant identity, duplicate detection, `resolveFrom()`, `normalizeName()`, the schema for `participants` | `docs/composite-identity.md` |
| Public registration flow, 5-step wizard, consent step, emergency contact, channel choice, device trust | `docs/registration-wizard.md` |
| Any finding labelled H1, H2, M1-M5, L1-L5, D1-D12 in a session transcript | `docs/audit-2026-10-19.md` |

### Why each matters

`docs/composite-identity.md` is the **authoritative** reference for
how a participant is identified as of 2026-10-19. It reverses four
sections of `docs/participant-identity.md` (§3 name-as-identifier,
§4.1 unique email, §5 normalization, §6 resolution rule). A session
that reads `participant-identity.md` alone will propose changes that
contradict the shipped code.

The identity rule is: **person key = normalized name + verified
contact**. Contact-only matching is gone. `participants.email` and
`participants.contact_number_normalized` are no longer unique —
multiple participants may share either.

`docs/registration-wizard.md` is the current reference for the
public registration flow. It documents the 5-step spec, the 3-step
shell actually shipped (W6), the per-phase status of W1-W6, the
blocked W5.4 reCAPTCHA work, and the case-coverage matrix for the
Ana/mother/Joy scenario. The pre-wizard `docs/registration.md` is
now historical for the public path — its §3 phase description,
§4 common fields, and §6 public-surface sections are superseded.

`docs/audit-2026-10-19.md` is the snapshot of the static audit at
git `2d19a5d`. Every finding is cross-referenced to a
`docs/known-issues.md` ISSUE number. Two findings (M5, P5) are
explicitly marked superseded by same-day W-phase work; a session
that re-flags them is re-flagging closed items. The eleven open
findings (H1, H2, M1-M4, L1-L5) are all filed as ISSUE-014
through ISSUE-024.

### Two supersession notes that will not be found by searching

1. **`docs/participant-identity.md` §3, §4.1, §5, §6 are reversed
   by `docs/composite-identity.md`.** The correction block at the
   bottom of `participant-identity.md` (titled
   `## Corrections — Composite identity (2026-10-19)`) points at
   the authoritative file, but a session that reads the body of
   `participant-identity.md` without scrolling to the bottom will
   miss it.

2. **`docs/registration.md` is now historical for the public
   path.** It still describes the pre-wizard single-page submission.
   The current flow is a wizard with a consent step, emergency
   contact, and channel choice. Read
   `docs/registration-wizard.md` instead for anything that touches
   `resources/js/pages/registrations/public.tsx` or
   `RegistrationVerificationService`.

### Cross-references

- Section §7 — original reading-order table
- Section §17 — first reading-order addition (2026-10-13)
- Section §19 — second reading-order addition (2026-10-14)
- Section §21 — third reading-order addition (2026-10-16)
- `docs/progress.md` 2026-10-19 entries — the shipping record
  (birthdate swap + wizard/composite/audit)
- `docs/known-issues.md` — ISSUE-014 through ISSUE-025 filed from
  the audit and client Q14


## Running Events audit addendum — 2026-10-21

Read running-events-audit-2026-10-21.md first for current Running Events behavior. The older first/last-only identity, blocked reCAPTCHA, three attendance modes, and pending P1/P3/P4 statements are superseded. Mark authorized direct edits in ~/WDEMS. Commit 39e554f is local; audit fixes/documentation after it remain local and unpushed.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.

## 24. Documentation reorganization — `docs/specs/` (2026-10-21)

Sections §7, §17, §19, §21, §23 describe reading-order additions made
across 2026-10-13 to 2026-10-21. Those additions pointed at `docs/`
subsystem docs and were correct at the time.

On 2026-10-21 a `docs/specs/` tier was added to hold **canonical
feature definitions**, promoted from the workspace-only `docs2/`
folder that Section §21 (and Master §9.1) treated as workspace-only:

| File | Authority |
|---|---|
| `docs/specs/WDEMS_MASTER_PROJECT_DOCUMENT.md` | Top-of-tree: consolidates all decisions, feature specs, build state. Where it conflicts with older working docs, it and the Church MD prevail. |
| `docs/specs/Church_Events_Update.md` | The church guideline (v1.0 + Amendments A–D). Per Decision D-15, prevails on church matters over any other church document. |
| `docs/specs/wdems-church-events-update-spec.md` | Companion church build spec (Appendix B–E). Implementation detail; subordinate to the Church MD. |
| `docs/specs/dashboard_update.md` | Dashboard feature spec. Implementation is a separate track (DASH-1/2/3); has not started as of this writing. |
| `docs/specs/wdems-panel-duplicate-participants.md` | Panel preparation script. Not a feature spec; contains a supersession amendment per Decision D-10. |

**Ordering for AI sessions:**

1. This file's §7/§17/§19/§21/§23 reading-order entries → `docs/` subsystem docs.
2. If the task touches features, product decisions, or MVP scope → read
   `docs/specs/WDEMS_MASTER_PROJECT_DOCUMENT.md` first.
3. For church tasks → `docs/specs/Church_Events_Update.md` prevails per D-15.
4. Section §7's reading order still applies for implementation specifics.

**Why `specs/` and not `docs2/`:** `docs2` was a workspace label
(Master §9.1). Promoting to the repo required a semantic name —
`docs/specs/` reads as "canonical feature definitions" for a new
reader. Filenames are preserved so cross-references inside the
specs stay valid; the previous location (`docs2/`) is retired.

Historical docs (`docs/participant-identity.md` §3/§4.1/§5/§6,
`docs/registration.md` for the public path) remain reversed by their
own Corrections blocks per §23 — the specs tier does not undo that.

**Master §9.1 paths are now stale.** Its table references workspace
paths like `user/files/Church_Events_Update.md`. The canonical path
is now `docs/specs/Church_Events_Update.md`. Per the append-only
policy on the Master doc, do not silently rewrite §9.1 — append a
dated correction note if the discrepancy matters for a task.
