# WDEMS — Workflow-Driven Event Management System

A Laravel + React web application for managing running events, built
around the **Community Run** workflow. WDEMS handles event setup,
registration, attendance, and post-run monitoring in one place, with a
role-based access model (admin / staff) and email-based two-factor
authentication.

**Last updated:** 2026-10-02.

---

## Status

The core workflow is running end-to-end — events, registration form
builder, public submission surface, and the identity model that
powers deduplication and returning-participant flags.

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
| Registration — public submission surface at `/r/{slug}` | ✅ |
| Registration — per-event required/optional toggles | ✅ |
| Registration — legal layer (demo privacy + terms, consent) | 🚧 demo |
| Participant identity model (email + normalized phone) | 📋 planned |
| Registration monitoring (live feed during registration) | 📋 planned |
| Attendance recording UI | 🚧 |
| Walk-in / paper registration UI | ⏸ |
| Post-run monitoring (recurring participants, rates) | ⏸ |

**Test suite:** run `php artisan test` for the current count. Auth,
event deletion, and registration have broad coverage. Attendance and
monitoring are not yet tested because they are not yet complete.

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

| Doc | Covers |
|---|---|
| `docs/architecture.md` | Whole-system overview: layers, data model, foreign keys, event workflow |
| `docs/authentication.md` | Login, email 2FA, trusted devices, password management |
| `docs/event-creation.md` | Code reference for event creation and workflow |
| `docs/event-creation-wizard.md` | The wizard as a feature: steps, checkpoint, field components |
| `docs/event-deletion.md` | The two delete paths (admin direct, staff OTP) and the deletion algorithm |
| `docs/registration.md` | Registration feature — spec + Status: Implemented block |
| `docs/registration-development-plan.md` | Phase plan and close for the registration build |
| `docs/participant-identity.md` | Identity model — email + normalized phone |
| `docs/registration-monitoring.md` | Live monitoring during the registration window |
| `docs/participant-identity-and-monitoring-plan.md` | Ten-phase plan for identity + monitoring |
| `docs/dev-workflow/README.md` | Workflow rules, protocols, failure modes |
| `docs/AI-CONTEXT.md` | Paste-me-at-chat-start file for AI sessions |
| `docs/fixes.md` | Fixed problems reference — FIX-NNN |
| `docs/known-issues.md` | Open backlog — ISSUE-NNN |
| `docs/event-creation-issues.md` | Event-creation backlog — EC-NN |
| `docs/progress.md` | Dated changelog, one entry per work session |
| `docs/development-log.md` | Narrative record of what was built and when |
| `docs/demo-email.md` | Repeatable Resend email demo procedure |
| `docs/TinkerManual.md` | Common Tinker commands |

**Historical (Google integration, removed from the project):**

- `docs/archive/phase-a-b-report.md`
- `docs/archive/google-integration.md`

These describe an integration that was designed and built, then
removed. They are kept for historical context only.

---

## Project Structure

    app/
    ├── Actions/Fortify/       ResetUserPassword.php
    ├── Concerns/              PasswordValidationRules, ProfileValidationRules,
    │                          HumanNameQualityRules, EventValidationRules,
    │                          RegistrationFieldValidationRules
    ├── Enums/                 EventStatus, EventType, RegistrationStatus,
    │                          AttendanceStatus
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Auth/          DeviceVerificationController
    │   │   ├── Settings/      ProfileController, SecurityController
    │   │   ├── AttendanceController.php
    │   │   ├── DashboardController.php
    │   │   ├── EventController.php
    │   │   ├── EventDeletionController.php
    │   │   ├── PublicRegistrationController.php
    │   │   ├── RegistrationController.php
    │   │   └── RegistrationFormController.php
    │   ├── Middleware/        AdminMiddleware, EnsureDeviceIsTrusted,
    │   │                      EnsureEmailIsVerifiedOrAdmin,
    │   │                      EnsureRegistrationIsOpen,
    │   │                      HandleAppearance, HandleInertiaRequests
    │   └── Requests/          EventRequest, PublicRegistrationRequest,
    │                          RegistrationFieldRequest, Settings/*
    ├── Mail/                  EventDeletionOtpMail, LoginVerificationCodeMail
    ├── Models/                Attendance, Event, Participant, Registration,
    │                          RegistrationField, RegistrationFieldResponse, User
    ├── Providers/             AppServiceProvider, FortifyServiceProvider
    └── Services/
        ├── EventWorkflow.php                 lifecycle state machine
        ├── EventDeletion/                    ordered deletion + OTP verification
        ├── Otp/                              shared OTP helper
        └── TwoFactor/                        email 2FA + trusted devices

    resources/js/
    ├── components/            wizard, date-picker, time-picker, distance-picker,
    │                          map-picker, partner-editor, accessibility-grid,
    │                          registration-field-editor, sortable-field-list,
    │                          event-deletion-otp-dialog, password-input, …
    │   └── ui/                shadcn-style primitives
    ├── hooks/                 use-confirm-dialog, use-wizard-persistence,
    │                          use-appearance, use-clipboard, …
    ├── layouts/               app/, app-layout, auth/, auth-layout,
    │                          public/, settings/
    ├── lib/                   event-validation, registration-field-types,
    │                          registration-field-validation,
    │                          public-registration-validation,
    │                          demo-legal-content, utils
    ├── pages/
    │   ├── admin/             dashboard.tsx (stub — see Known gaps)
    │   ├── auth/              login, verify-device, forgot/reset/confirm,
    │   │                      verify-email
    │   ├── events/            index, create, edit, show, attendance,
    │   │   │                  registration-form
    │   │   └── step/          BasicsStep, ScheduleStep, VenueStep, ExtrasStep
    │   ├── registrations/     index, public, closed
    │   ├── settings/          profile, security, appearance
    │   ├── dashboard.tsx
    │   └── welcome.tsx
    ├── routes/                Wayfinder-generated (do not edit by hand)
    └── types/                 auth, navigation, ui

    database/migrations/       full schema
    docs/                      subsystem guides (see above)
    routes/
    ├── web.php                public registration, events, deletion, admin
    └── settings.php           profile, security, appearance

    tests/
    ├── Feature/Auth/          login, device verification, password reset, logout
    ├── Feature/Events/        CRUD rules, deletion, workflow, registration form
    ├── Feature/Public/        anonymous registration submission
    ├── Feature/Settings/      profile update, security
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
- **Attendance recording.** `AttendanceController` and
  `events/attendance.tsx` exist and can mark attendance for existing
  registrations. The page is functional but has not been rebuilt for
  search, pagination, or walk-in support — that is Phase 4 of the
  registration plan.

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

---

## Roadmap

| Phase | Deliverable | Status |
|---|---|---|
| **Auth** | Login, email 2FA, trusted devices, password management | ✅ |
| **Events** | Event CRUD, wizard, lifecycle, deletion with OTP | ✅ |
| **Registration — form builder** | Per-event registration form, common + custom fields | ✅ |
| **Registration — public surface** | Anonymous submission at `/r/{slug}` | ✅ |
| **Registration — field requirements** | Per-event required/optional toggles | ✅ |
| **Registration — legal layer (demo)** | Privacy notice + terms modals, consent checkbox | 🚧 demo |
| **Participant identity** | Email + phone identity, cross-event deduplication | 📋 planned |
| **Registration monitoring** | Live submission feed during the registration window | 📋 planned |
| **Compliance (Phase 3.5)** | Per-event legal content, consent recording, guardian fields | 📋 planned |
| **Attendance** | Attendance recording UI, wired to real registrations | 🚧 |
| **Walk-in registration** | On-site registration for latecomers | ⏸ |
| **Post-run monitoring** | Recurring participants, attendance rates | ⏸ |

The phased build plan for **Participant identity** and **Registration
monitoring** lives in
`docs/participant-identity-and-monitoring-plan.md`.

---

## License

Private project. Not for public distribution.
