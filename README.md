# WDEMS — Workflow-Driven Event Management System

A Laravel + React web application for managing community runs and
church events in one place. Two workstreams share the same core:
the original **Community Run** workflow (event setup, registration,
attendance, post-run monitoring) and a **church events** extension
that adds Services, Fellowship, and Discipleship modules alongside
the running-event system. Role-based access model (admin / staff)
with email-based two-factor authentication.

**Last updated:** 2026-10-16.

---

## Status

The core workflow is running end-to-end — events, registration form
builder, three-phase submission (submit → verify → finalize), and the
identity model that powers deduplication and returning-participant flags.
Three of the five church-events workstreams from `docs/church-events/07`
have shipped on top of that foundation.

### Running events

| Feature | Status |
|---|---|
| Authentication (Fortify + custom email 2FA) | ✅ |
| Trusted-device challenge (30-day cookie) | ✅ |
| Admin / Staff roles | ✅ |
| Password management, account deletion | ✅ |
| Event CRUD (list, create, edit, show) | ✅ |
| Event Creation Wizard (4-step + draft checkpoint) | ✅ |
| Event lifecycle (draft → registration_open → registration_closed → ongoing → completed) | ✅ |
| Delete Event with role-based OTP verification | ✅ |
| Action Feed dashboard (events grouped by urgency) | ✅ |
| Custom Date / Time / Distance pickers | ✅ |
| Map picker (Leaflet + Nominatim) | ✅ |
| Registration — form builder (common + up to 5 custom fields) | ✅ |
| Registration — public submission surface (submit → verify → finalize) | ✅ |
| Registration — per-event required/optional toggles | ✅ |
| Registration — legal layer (demo privacy + terms, consent) | 🚧 demo |
| Participant identity model (email + normalized phone) | ✅ |
| Registration monitoring (live submission feed) | ✅ |
| Attendance recording UI (search, filter, pagination, walk-in) | ✅ |
| Walk-in / paper registration UI (single dialog + batch table) | ✅ |
| Post-run monitoring (returning participants, live submission rates) | ✅ |
| Registration — contact verification (email OTP + Firebase phone auth) | ✅ |
| Registration — deletion with OTP | ✅ |
| Attendance code, workspace lookup, `verified_at`, live/deferred encoding | ✅ |
| Event finalize action | ✅ |
| End-of-event report | ✅ |

### Church events

| Feature | Status |
|---|---|
| Foundation — members + first_timers tables, models, relations | ✅ |
| General Events — church event schema, category sub-line, kind filter | ✅ |
| Services module — Morning/Midweek/Youth/Other, recurring schedule generator | ✅ Phase 3 |
| Fellowship module — six types, groups, attendance roster | ✅ Phase 4 |
| Discipleship module — programs, batches, enrollment, sessions, attendance matrix, progress, filter, CSV | ✅ Phase 5 |
| Youth module | 📋 blocked — see Roadmap |
| Cell Group module | 📋 blocked — see Roadmap |

**Test suite:** 791 passed, 3 skipped, 3,080 assertions across the full
suite. Run `php artisan test` for the current count.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 · PHP 8.3+ |
| Frontend | React 19 · TypeScript 5.7 · Inertia 3 |
| Styling | Tailwind CSS 4 · Radix UI primitives |
| Drag-and-drop | `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` |
| Maps | Leaflet (lazy-loaded) · OpenStreetMap tiles · Nominatim geocoding |
| Auth | Laravel Fortify + custom email two-factor (`EmailTwoFactorService`) |
| Database | MySQL (dev + prod) · SQLite in-memory (tests) |
| Email | Resend (transactional) |
| Testing | Pest 5 |
| Bundler | Vite 8 |

**Note on auth:** Fortify provides the login, logout, password reset,
and email verification scaffolding. The 6-digit email 2FA,
trusted-device cookie, and device-challenge middleware are custom.
Fortify's TOTP two-factor feature is **not** enabled. See
`docs/authentication.md`.

---

## Quick Start

### Prerequisites

- PHP 8.4+ with extensions: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `xml`, `curl`, `gd`
- Composer 2.x
- Node.js 20+ and npm
- MySQL 8 running locally
- A Resend account for real email delivery (optional for local dev — set `MAIL_MAILER=log`)

### Setup

    git clone <repo-url> wdems
    cd wdems
    composer install
    cp .env.example .env
    php artisan key:generate
    npm install

### Configure environment

Edit `.env`:

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=wdems
    DB_USERNAME=root
    DB_PASSWORD=

    MAIL_MAILER=log
    RESEND_API_KEY=re_xxxxxxxxxxxxxxxx

### Database

    mysql -u root -p -e "CREATE DATABASE wdems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    php artisan migrate
    php artisan db:seed --class=DiscipleshipProgramSeeder

The `DiscipleshipProgramSeeder` seeds the six Discipleship programs
(Life Class, Encounter God Retreat, School of Leaders 1-3, Cell
Convergence). It is idempotent — safe to re-run.

### Run

    composer run dev

This boots the dev environment via `laravel/multiplex` — server, queue
listener, log viewer, and Vite dev server.

### Create your first user

    php artisan tinker

    \App\Models\User::forceCreate([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

Log in with `admin@example.com` / `password`. To create a staff user,
change `'role' => 'staff'`.

**Why `forceCreate` and not `create`?** `email_verified_at` and the
two-factor columns are not in `User::$fillable` — deliberately, so
mass assignment cannot flip 2FA state. `create()` would silently drop
them and produce an unverified admin, which the `verified.or.admin`
middleware would then bounce at login. `forceCreate()` bypasses the
fillable list.

---

## Testing

Tests run against SQLite in-memory (configured in `phpunit.xml`), so no
MySQL test database is needed.

    php artisan test                          # full suite
    php artisan test --compact                # terse output
    php artisan test tests/Feature/Auth       # specific directory
    php artisan test tests/Feature/Discipleship   # single module

Static analysis:

    composer types:check    # PHPStan
    composer lint           # Pint (auto-fix)
    composer lint:check     # Pint (check only)

Frontend types:

    npx tsc --noEmit

---

## Documentation

The `docs/` folder contains subsystem guides written for developers who
use AI coding tools and need to know what not to break. Each guide
includes a "traps" section — the specific mistakes AI tends to make on
that subsystem.

**New to the project?** Start with `docs/architecture.md`. If you are
an AI session, paste `docs/AI-CONTEXT.md` at the top of your first
message.

### Core platform

| Doc | Covers |
|---|---|
| `docs/architecture.md` | Whole-system overview: layers, data model, foreign keys, event workflow |
| `docs/authentication.md` | Login, email 2FA, trusted devices, password management |
| `docs/design-system.md` | Visual contract: layer model, glass tiers, semantic colors |
| `docs/implementation-notes.md` | Non-obvious behaviors that cross subsystem boundaries |

### Running events

| Doc | Covers |
|---|---|
| `docs/event-creation.md` | Code reference for event creation and workflow |
| `docs/event-creation-wizard.md` | The wizard as a feature: steps, checkpoint, field components |
| `docs/event-deletion.md` | The two delete paths (admin direct, staff OTP) and the deletion algorithm |
| `docs/registration.md` | Registration feature — spec + Status: Implemented block |
| `docs/registration-development-plan.md` | Phase plan and close for the registration build |
| `docs/registration-contact-verification.md` | Contact OTPs, Firebase phone auth, three-phase intake |
| `docs/participant-identity.md` | Identity model — email + normalized phone |
| `docs/registration-monitoring.md` | Live monitoring during the registration window |
| `docs/participant-identity-and-monitoring-plan.md` | Ten-phase plan for identity + monitoring |
| `docs/attendance-redesign.md` | Three-mode attendance workspace (Mark / Confirm / Batch) |
| `docs/attendance-code.md` | Attendance code, workspace lookup, `verified_at`, live/deferred encoding |
| `docs/attendance-encoding-and-reporting.md` | Finalize event + end-of-event report (spec + shipped pointers) |

### Church events

| Doc | Covers |
|---|---|
| `docs/church-events/01-current-system-audit.md` through `08-phase-0-handoff.md` | The scope-shift record: audit, product scope, domain model, task division |
| `docs/church-events/09-mockup-scope-inventory.md` | Reconciliation: what the client mockups show vs what exists |
| `docs/church-events/10-client-decision-log.md` | Q1-Q20 tracking for the mockup expansion |
| `docs/church-events/TASKS.md` | Live task board — Phases 1-5 done, Phase 6 blocked |
| `docs/fellowship.md` | Fellowship module reference (Phase 4) |
| `docs/discipleship.md` | Discipleship module reference (Phase 5) |

### Process

| Doc | Covers |
|---|---|
| `docs/dev-workflow/README.md` | Workflow rules, protocols, failure modes |
| `docs/AI-CONTEXT.md` | Paste-me-at-chat-start file for AI sessions |
| `docs/fixes.md` | Fixed problems reference — FIX-NNN |
| `docs/known-issues.md` | Open backlog — ISSUE-NNN |
| `docs/event-creation-issues.md` | Event-creation backlog — EC-NN |
| `docs/progress.md` | Dated changelog, one entry per work session |
| `docs/development-log.md` | Narrative record of what was built and when |
| `docs/demo-email.md` | Repeatable Resend email demo procedure |

**Historical (Google integration, removed from the project):**

- `docs/archive/phase-a-b-report.md`
- `docs/archive/google-integration.md`

These describe an integration that was designed and built, then
removed. They are kept for historical context only.

**Missing, deferred:**

- `docs/services.md` — the Services subsystem doc (Phase 3) is
  deferred pending client answer to Q1 in
  `docs/church-events/09-mockup-scope-inventory.md`. If Services is
  removed from the sidebar, the doc is wasted effort.

---

## Project Structure

    app/
    ├── Actions/Fortify/       ResetUserPassword.php
    ├── Concerns/              PasswordValidationRules, ProfileValidationRules,
    │                          HumanNameQualityRules, EventValidationRules,
    │                          RegistrationFieldValidationRules,
    │                          ContactAndAddressQualityRules,
    │                          WalkInFieldValidationRules
    ├── Enums/                 AttendanceStatus, ChurchEventStatus,
    │                          DiscipleshipAttendanceStatus,
    │                          DiscipleshipEnrollmentStatus,
    │                          EventStatus, EventType, FellowshipType,
    │                          RegistrationStatus, ServiceType
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Auth/          DeviceVerificationController
    │   │   ├── Settings/      ProfileController, SecurityController
    │   │   ├── AttendanceController.php
    │   │   ├── DashboardController.php
    │   │   ├── DiscipleshipAttendanceController.php
    │   │   ├── DiscipleshipBatchController.php
    │   │   ├── DiscipleshipController.php
    │   │   ├── DiscipleshipEnrollmentController.php
    │   │   ├── DiscipleshipSessionController.php
    │   │   ├── EventController.php
    │   │   ├── EventDeletionController.php
    │   │   ├── FellowshipAttendanceController.php
    │   │   ├── FellowshipController.php
    │   │   ├── FellowshipGroupController.php
    │   │   ├── PublicRegistrationController.php
    │   │   ├── RegistrationController.php
    │   │   ├── RegistrationDeletionController.php
    │   │   ├── RegistrationFormController.php
    │   │   ├── RegistrationMonitorController.php
    │   │   ├── RegistrationVerificationController.php
    │   │   ├── ReportController.php
    │   │   ├── ServiceAttendanceController.php
    │   │   └── ServiceController.php
    │   ├── Middleware/        AdminMiddleware, AdvanceEventStatus,
    │   │                      EnsureDeviceIsTrusted,
    │   │                      EnsureEmailIsVerifiedOrAdmin,
    │   │                      EnsureRegistrationIsOpen,
    │   │                      HandleAppearance, HandleInertiaRequests,
    │   │                      ProtectRegistrationVerification
    │   └── Requests/          EventRequest, PublicRegistrationRequest,
    │                          RegistrationFieldRequest, WalkInRegistrationRequest,
    │                          Discipleship/*, Fellowship/*, Services/*, Settings/*
    ├── Mail/                  EventDeletionOtpMail, LoginVerificationCodeMail,
    │                          RegistrationConfirmationMail,
    │                          RegistrationDeletionOtpMail,
    │                          RegistrationVerificationOtpMail
    ├── Models/                Attendance, DiscipleshipAttendance,
    │                          DiscipleshipBatch, DiscipleshipEnrollment,
    │                          DiscipleshipProgram, DiscipleshipSession,
    │                          Event, FellowshipAttendance, FellowshipEvent,
    │                          FellowshipGroup, FirstTimer, Member,
    │                          Participant, Registration, RegistrationField,
    │                          RegistrationFieldResponse,
    │                          RegistrationVerificationChallenge,
    │                          ServiceAttendanceFirstTimer,
    │                          ServiceAttendanceMember, ServiceOccurrence, User
    ├── Providers/             AppServiceProvider, FortifyServiceProvider
    └── Services/
        ├── Discipleship/                     progress calculator
        ├── EventWorkflow.php                 lifecycle state machine
        ├── EventDeletion/                    ordered deletion + OTP verification
        ├── Otp/                              shared OTP helper
        ├── RegistrationVerification/         contact verification + attendance code
        ├── Reporting/                        report payload builder
        └── TwoFactor/                        email 2FA + trusted devices

    resources/js/
    ├── components/            wizard, date-picker, time-picker, distance-picker,
    │                          map-picker, partner-editor, accessibility-grid,
    │                          registration-field-editor, sortable-field-list,
    │                          event-deletion-otp-dialog, walk-in-dialog,
    │                          bulk-walk-in-table, bulk-mark-toolbar,
    │                          registration-deletion-otp-dialog, …
    │   └── ui/                shadcn-style primitives
    ├── hooks/                 use-confirm-dialog, use-wizard-persistence,
    │                          use-appearance, use-clipboard, use-polling, …
    ├── layouts/               app/, app-layout, auth/, auth-layout,
    │                          public/, settings/
    ├── lib/                   event-validation, registration-field-types,
    │                          registration-field-validation,
    │                          public-registration-validation,
    │                          attendance-bulk-validation,
    │                          demo-legal-content, firebase, utils
    ├── pages/
    │   ├── admin/             dashboard.tsx (stub — see Known gaps)
    │   ├── attendance/        index.tsx (running-event attendance workspace)
    │   ├── auth/              login, verify-device, forgot/reset/confirm,
    │   │                      verify-email
    │   ├── discipleship/      index, show, batch-show
    │   ├── events/            index, create, edit, show, attendance, report,
    │   │   │                  registration-form
    │   │   └── step/          BasicsStep, ScheduleStep, VenueStep, ExtrasStep
    │   ├── fellowship/        index, show, attendance
    │   ├── registrations/     index, public, verify, confirmed, closed,
    │   │   └── monitor/       index, show
    │   ├── services/          index, show, attendance
    │   ├── settings/          profile, security, appearance
    │   ├── dashboard.tsx
    │   └── welcome.tsx
    ├── routes/                Wayfinder-generated (do not edit by hand)
    └── types/                 auth, navigation, ui

    database/migrations/       full schema
    docs/                      subsystem guides (see above)
    routes/
    ├── web.php                public registration, events, deletion, admin,
    │                          services, fellowship, discipleship
    └── settings.php           profile, security, appearance

    tests/
    ├── Feature/Auth/          login, device verification, password reset, logout
    ├── Feature/Discipleship/  programs, batches, enrollment, sessions,
    │                          attendance, progress, filter/CSV
    ├── Feature/Events/        CRUD rules, deletion, workflow, registration form,
    │                          attendance
    ├── Feature/Fellowship/    events, groups, attendance
    ├── Feature/Participants/  identity constraints, resolver, FirstTimer, Member
    ├── Feature/Public/        anonymous registration submission, verification
    ├── Feature/Services/      occurrences, two-track attendance
    ├── Feature/Settings/      profile update, security
    ├── Integration/           concurrency tests
    ├── Unit/Services/         EmailTwoFactorService, EventDeletionOtpService
    └── Pest.php               shared test helpers

---

## Current Status

### In progress

- **Legal layer.** The public registration page renders a demo privacy
  notice and terms of service in modals, with a required consent
  checkbox. Server-side consent recording, per-event legal content, and
  guardian fields for minors are deferred to Phase 3.5 and wait on real
  legal content. See `docs/known-issues.md` ISSUE-009.

### Known gaps

1. **No authorization policy on event edit.** `EventRequest::authorize()`
   returns `true`. Any authenticated staff user can edit any event. See
   `docs/known-issues.md` ISSUE-004.
2. **`admin/dashboard.tsx` is unused.** `DashboardController` renders the
   same page for `/dashboard` and `/admin/dashboard`. See
   `docs/known-issues.md` ISSUE-003.
3. **`participants.address` is varchar(255) but validation allows 500.**
   Silent truncation or throw depending on MySQL mode. See ISSUE-007.
4. **No-email deduplication limitation is not surfaced in the form
   builder UI.** See ISSUE-008.
5. **`tests/Feature/Settings/SecurityTest.php` skips 3 tests.** They are
   guarded on a Fortify feature that is not enabled. See
   `docs/authentication.md` Trap #6.
6. **The wizard's checkpoint lacks a "resume draft" prompt, a discard
   button, and cross-tab coordination.** See
   `docs/event-creation-wizard.md` §11.1.
7. **`docs/services.md` subsystem doc missing.** Deferred pending
   Q1 (mockup sidebar may remove Services).
8. **Discipleship `required_sessions` not backfilled.** All six
   seeded programs have null. The client decides per-program thresholds.
9. **Discipleship cell-clearing on the attendance matrix.** The DELETE
   endpoint supports it; the frontend does not call it.
10. **Four `creator()`/`recorder()` relations uncovered in tests.**
    Cosmetic — not called by any controller or service.

---

## Roadmap

### Running events — complete

| Phase | Deliverable | Status |
|---|---|---|
| **Auth** | Login, email 2FA, trusted devices, password management | ✅ |
| **Events** | Event CRUD, wizard, lifecycle, deletion with OTP | ✅ |
| **Registration — form builder** | Per-event registration form, common + custom fields | ✅ |
| **Registration — public surface** | Anonymous submit → verify → finalize at `/r/{slug}` | ✅ |
| **Registration — field requirements** | Per-event required/optional toggles | ✅ |
| **Registration — contact verification** | Email OTP + Firebase phone auth, three-phase intake | ✅ |
| **Registration — deletion with OTP** | Staff delete a registration via email OTP | ✅ |
| **Registration — legal layer (demo)** | Privacy notice + terms modals, consent checkbox | 🚧 demo |
| **Participant identity** | Email + phone identity, cross-event deduplication | ✅ |
| **Registration monitoring** | Live submission feed during the registration window | ✅ |
| **Compliance (Phase 3.5)** | Per-event legal content, consent recording, guardian fields | 📋 planned |
| **Attendance** | Attendance recording UI, wired to real registrations | ✅ |
| **Attendance code + encoding modes** | Per-registration code, live/deferred, `verified_at` | ✅ |
| **Walk-in registration** | On-site registration for latecomers, single + bulk | ✅ |
| **Event finalize + report** | Password-gated close, end-of-event report, CSV export | ✅ |
| **Post-run monitoring** | Recurring participants, attendance rates | ✅ |

The phased build plan for **Participant identity** and **Registration
monitoring** lives in
`docs/participant-identity-and-monitoring-plan.md`.

### Church events — in progress

| Phase | Deliverable | Status |
|---|---|---|
| **Phase 1 — Foundation** | members + first_timers tables, models, relations | ✅ |
| **Phase 2 — General Events** | church event schema, `ChurchEventStatus`, category sub-line | ✅ |
| **Phase 3 — Services module** | Morning/Midweek/Youth/Other, recurring schedule generator | ✅ |
| **Phase 4 — Fellowship module** | Six types, groups, attendance roster | ✅ |
| **Phase 5 — Discipleship module** | Programs, batches, enrollment, sessions, attendance matrix, progress, dashboard | ✅ |
| **Phase 6 — Youth + Cell Group** | — | 📋 blocked — see below |
| **Phase 7 — Cross-module** | doc 07 sec 3.8 | 📋 pending |
| **Phase 8 — Cross-cutting** | doc 07 sec 3.9 | 📋 pending |

The scope, phases, and task breakdown live in
`docs/church-events/07-scope-and-task-division.md` and the live task
board at `docs/church-events/TASKS.md`.

### Mockup expansion — blocked on client

The client mockups (`docs/church-events/09-mockup-scope-inventory.md`)
propose five additional modules and eleven cross-cutting features
beyond the doc-07 roadmap:

- **Modules:** Youth, Kids Ministry, Young Adult, Worship & Prayer,
  Church Administration
- **Features:** Payments, Budgets, Tasks, Roles & Ministries,
  Documents, Multi-tab detail pages, Grade/Year level, Locations,
  Ministries, Church plans, Meeting minutes

No work on the expansion starts until the client answers Q1–Q20 in
`docs/church-events/10-client-decision-log.md`. Twenty questions,
zero answered. Any one of Q10, Q11, Q16, or Q19 unblocks a
multi-session workstream.

---

## License

Private project. Not for public distribution.


## Running Events status update — 2026-10-21

The status tables above are historical. The current full-name composite identity,
versioned email-only forms, Check-in/Walk-in attendance, on-site intake lifecycle,
reports, and duplicate-defense implementation are recorded in
[the Running Events audit](docs/running-events-audit-2026-10-21.md).
That document includes source traceability and explicitly separates automated
verification from outstanding live CAPTCHA/camera/scanner acceptance checks.
Kit claiming remains a staff procedure outside the app. Church expansion has a
specification, not a completed implementation. No push occurred during this audit.

## Database schema export — 2026-10-21

[Schema-only MySQL SQL file](docs/wdems-schema.sql) contains the 30-table local schema after all project migrations. No records or credentials are included. Import into an empty MySQL database for schema inspection; the migrations table has no history rows, so this is not a replacement for Laravel migrations when setting up the app.
