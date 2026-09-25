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
