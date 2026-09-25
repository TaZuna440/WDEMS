# WDEMS — Workflow-Driven Event Management System

A Laravel + React web application for managing running events, built
around the **Community Run** workflow. WDEMS handles event setup,
registration, attendance, and post-run monitoring in one place, with a
role-based access model (admin / staff) and email-based two-factor
authentication.

---

## Status

The core workflow is running end-to-end. Registration is in progress.

| Feature | Status |
|---|---|
| Authentication (Fortify + custom email 2FA) | ✅ |
| Trusted-device challenge (30-day cookie) | ✅ |
| Admin / Staff roles | ✅ |
| Password management, account deletion | ✅ |
| Event CRUD (list, create, edit, show) | ✅ |
| Event Creation Wizard (4-step + draft checkpoint) | ✅ |
| Event lifecycle (draft → configured → open → closed → ongoing → completed) | ✅ |
| Event Options (organizer-configurable, no hardcoded choices) | ✅ |
| Delete Event with role-based OTP verification | ✅ |
| Action Feed dashboard (events grouped by urgency) | ✅ |
| Custom Date / Time / Distance pickers | ✅ |
| Registration flow (participant sign-up) | 🚧 |
| Attendance recording UI | 🚧 |
| Monitoring dashboard (recurring participants, rates) | ⏸ |
| Walk-in / paper registration UI | ⏸ |

**Test suite:** run `php artisan test` for the current count. Auth and
event deletion have broad coverage; registration and attendance are not
yet tested because they are not yet complete.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 · PHP 8.5 |
| Frontend | React 19 · TypeScript 5.7 · Inertia 3 |
| Styling | Tailwind CSS 4 · Radix UI primitives |
| Auth | Laravel Fortify + custom email two-factor (`EmailTwoFactorService`) |
| Database | MySQL (dev + prod) · SQLite in-memory (tests) |
| Email | Resend (transactional) |
| Testing | Pest 5 |
| Bundler | Vite 8 |

**Note on auth:** Fortify provides the login, logout, password reset, and
email verification scaffolding. The 6-digit email 2FA, trusted-device
cookie, and device-challenge middleware are custom. Fortify's TOTP
two-factor feature is **not** enabled. See `docs/authentication.md`.

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

**New to the project?** Start with `docs/architecture.md`.

| Doc | Covers | Audience |
|---|---|---|
| `docs/architecture.md` | Whole-system overview: layers, data model, foreign keys, event workflow | Everyone |
| `docs/authentication.md` | Login, email 2FA, trusted devices, password management | Anyone touching `auth`, `device.trusted`, `settings/security` |
| `docs/event-creation.md` | Code reference for event creation, options, and workflow | Anyone touching event controllers or validation rules |
| `docs/event-creation-wizard.md` | The wizard as a feature: steps, checkpoint, field components | Anyone touching wizard components or `use-wizard-persistence` |
| `docs/event-deletion.md` | The two delete paths (admin direct, staff OTP) and the deletion algorithm | Anyone touching `EventDeletionController` or the deletion services |
| `docs/development-log.md` | Chronological record of what was built and when | Reference |
| `docs/TinkerManual.md` | Common Tinker commands | Reference |
| `docs/AI-CONTEXT.md` | Paste-me-at-chat-start context for AI sessions | Everyone |
| `docs/fixes.md` | Fixed problems reference — FIX-NNN | Reference |
| `docs/known-issues.md` | Open backlog — ISSUE-NNN | Reference |
| `docs/progress.md` | Dated changelog, one entry per work session | Reference |
| `docs/event-creation-issues.md` | Event-creation backlog — EC-NN and related | Anyone touching event creation |
| `docs/demo-email.md` | Repeatable Resend email demo procedure | Anyone demoing auth |
| `docs/dev-workflow/README.md` | Workflow rules, protocols, failure modes | Everyone |

**Historical (Google integration, removed from the project):**

- `docs/archive/phase-a-b-report.md`
- `docs/archive/google-integration.md`

These describe an integration that was designed and built, then removed.
They are kept for historical context only. The current registration
approach is being rebuilt without Google.

---

## Project Structure

    app/
    ├── Actions/Fortify/       ResetUserPassword.php
    ├── Concerns/              PasswordValidationRules, ProfileValidationRules,
    │                          EventValidationRules
    ├── Enums/                 EventStatus, EventType, RegistrationStatus, AttendanceStatus
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Auth/          DeviceVerificationController (email 2FA challenge)
    │   │   ├── Settings/      ProfileController, SecurityController
    │   │   ├── AttendanceController.php
    │   │   ├── DashboardController.php
    │   │   ├── EventController.php
    │   │   ├── EventDeletionController.php
    │   │   └── EventOptionController.php
    │   ├── Middleware/        AdminMiddleware, EnsureDeviceIsTrusted,
    │   │                      EnsureEmailIsVerifiedOrAdmin,
    │   │                      HandleAppearance, HandleInertiaRequests
    │   └── Requests/          EventRequest + Settings/*
    ├── Mail/                  EventDeletionOtpMail, LoginVerificationCodeMail
    ├── Models/                Attendance, Event, EventOption, Participant,
    │                          Registration, RegistrationOption, User
    ├── Providers/             AppServiceProvider, FortifyServiceProvider
    └── Services/
        ├── EventWorkflow.php                 lifecycle state machine
        ├── EventDeletion/                    ordered deletion + OTP verification
        ├── Otp/                              shared OTP helper
        └── TwoFactor/                        email 2FA + trusted devices

    resources/js/
    ├── components/            wizard, date-picker, time-picker, distance-picker,
    │                          map-picker, partner-editor, accessibility-grid,
    │                          password-input, confirm-dialog, event-deletion-otp-dialog
    │   └── ui/                shadcn-style primitives (button, input, dialog, …)
    ├── hooks/                 use-confirm-dialog, use-wizard-persistence, use-appearance
    ├── layouts/               app-layout, auth-layout, settings/layout
    ├── pages/
    │   ├── admin/             dashboard.tsx
    │   ├── auth/              login, verify-device, forgot/reset/confirm, verify-email
    │   ├── events/            index, create, edit, show, options, attendance
    │   │   └── step/          BasicsStep, ScheduleStep, VenueStep, ExtrasStep
    │   ├── settings/          profile, security, appearance
    │   ├── dashboard.tsx
    │   └── welcome.tsx
    ├── routes/                Wayfinder-generated (do not edit by hand)
    └── types/                 auth, navigation, ui

    database/migrations/       full schema
    docs/                      subsystem guides (see above)
    routes/
    ├── web.php                event, device-verification, attendance, admin routes
    └── settings.php           profile, security, appearance

    tests/
    ├── Feature/Auth/          login, device verification, password reset, logout
    ├── Feature/Events/        deletion (admin + staff paths)
    ├── Feature/Settings/      profile update, security
    ├── Unit/Services/         EmailTwoFactorService, EventDeletionOtpService
    └── Pest.php               shared test helpers

---

## Current Status

### In progress

- **Registration flow.** Participant registration is being rebuilt after the
  Google integration was removed. The `Registration` and `RegistrationOption`
  models exist and the schema supports `source` (`form` / `walk_in` / `paper`)
  and `registered_at`, but no UI or controller currently creates registrations.
- **Attendance recording.** `AttendanceController` and `events/attendance.tsx`
  exist and can mark attendance for existing registrations. Without a
  registration flow, the page is empty in practice.

### Known gaps

1. **No authorization policy on event edit.** `EventRequest::authorize()`
   returns `true`. Any authenticated staff user can edit any event. See
   `docs/event-creation.md` Trap #11.
2. **`admin/dashboard.tsx` is unused.** `DashboardController` renders the same
   `dashboard` page for both `/dashboard` and `/admin/dashboard`. The admin
   dashboard file exists but no controller renders it.
3. **`tests/Feature/Settings/SecurityTest.php` skips 3 tests.** They are
   guarded on a Fortify feature that is not enabled. See
   `docs/authentication.md` Trap #6.
4. **Checkpoint refinements.** The wizard's draft save works, but lacks a
   "resume draft" prompt, a discard button, a saved-at indicator, and
   cross-tab coordination. See `docs/event-creation-wizard.md` §11.1.

---

## Roadmap

| Phase | Deliverable | Status |
|---|---|---|
| **Auth** | Login, email 2FA, trusted devices, password management | ✅ |
| **Events** | Event CRUD, wizard, options, lifecycle, deletion with OTP | ✅ |
| **Registration** | Participant registration flow (source: form / walk-in / paper) | 🚧 |
| **Attendance** | Attendance recording UI, wired to real registrations | 🚧 |
| **Monitoring** | Dashboard for recurring participants, attendance rates | ⏸ |
| **Post-run** | Partner activity, follow-up tracking | ⏸ |

---

## License

Private project. Not for public distribution.
