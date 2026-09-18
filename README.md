# WDEMS — Workflow-Driven Event Management System

A Laravel + React web application for managing running events, built around the **Community Run** workflow. WDEMS handles event setup, participant registration, attendance, and monitoring — with participant registration offloaded to **Google Forms** so organizers get a familiar form builder while WDEMS keeps the structured data.

---

## Status

**Phases A, B, and C are complete and working end-to-end.** Participant registration currently happens through Google Forms created by WDEMS; automated response import is the next phase.

| Feature | Status |
|---|---|
| Authentication (Fortify, email verification) | ✅ |
| Admin / Staff roles | ✅ |
| Event CRUD | ✅ |
| Event lifecycle (draft → configured → open → closed → ongoing → completed) | ✅ |
| Event configuration (options like distance, shirt size) | ✅ |
| Delete Event with role-based OTP verification | ✅ |
| Google OAuth connection (per-user, token refresh, disconnect) | ✅ |
| Google Form auto-creation (seeded with default + option questions) | ✅ |
| Question editor (add / edit / remove, batched sync) | ✅ |
| Form ↔ Google Sheet linkage (via one-time Apps Script paste) | ✅ |
| Copy Registration Link button on setup + event pages | ✅ |
| Audit trail of every form change (user, action, timestamp) | ✅ |
| Action Feed dashboard (events grouped by urgency) | ✅ |
| Custom Date + Time pickers | ✅ |
| Response sync (Sheet → Participant + Registration) | 🚧 Phase D |
| Walk-in / paper registration UI | 🚧 Phase E |
| Attendance recording UI | 🚧 Phase F |
| Monitoring dashboard (recurring participants, rates) | 🚧 Phase G |
| Post-run / partner activity | 🚧 Later |

**Test suite:** 55 passing, 4 skipped (Fortify 2FA tests — 2FA not enabled).

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 · PHP 8.5 |
| Frontend | React 19 · TypeScript 5.7 · Inertia 3 |
| Styling | Tailwind CSS 4 · Radix UI primitives |
| Auth | Laravel Fortify |
| Database | MySQL (dev + prod) · SQLite in-memory (tests) |
| Email | Resend (transactional) |
| External APIs | Google Forms · Google Sheets · Google Drive |
| Testing | Pest 5 |
| Bundler | Vite 8 |

---

## Quick Start

### Prerequisites

- PHP 8.4+ with extensions: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `xml`, `curl`, `gd`
- Composer 2.x
- Node.js 20+ and npm
- MySQL 8 running locally
- A Google Cloud project (see `docs/google-integration.md`)
- A Resend account for real email delivery (optional for local dev)

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

    GOOGLE_CLIENT_ID=xxxxxxxxxxxx.apps.googleusercontent.com
    GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxx
    GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback

### Database

    mysql -u root -p -e "CREATE DATABASE wdems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    php artisan migrate

### Run

    composer run dev

This boots four processes via `laravel/multiplex`:

- **server** — `php artisan serve` on http://127.0.0.1:8000
- **queue** — database queue listener
- **logs** — `php artisan pail` live log viewer
- **vite** — Vite dev server with HMR

### Create your first user

    php artisan tinker

    \App\Models\User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

Log in with `admin@example.com` / `password`.

---

## Testing

Tests run against SQLite in-memory (configured in `phpunit.xml`), so no MySQL test database is needed.

    php artisan test                          # full suite
    php artisan test --compact                # terse output
    ./vendor/bin/pest tests/Feature/Events    # specific directory

Static analysis:

    composer types:check    # PHPStan
    composer lint           # Pint (auto-fix)
    composer lint:check     # Pint (check only)

Frontend types:

    npx tsc --noEmit

---

## Google Integration

WDEMS uses Google Forms as the participant-facing registration surface. The organizer connects a Google account once, then WDEMS can create Forms, seed them with questions, and read responses from a linked Google Sheet.

See:

- `docs/google-integration.md` — Google Cloud setup instructions
- `docs/architecture.md` — how OAuth, Form creation, question editing, and Sheet linkage fit together

---

## Development History

Detailed technical reports and chronological records live in `docs/`:

- `docs/development-log.md` — chronological log of everything built
- `docs/phase-a-b-report.md` — deep dive on the Google integration
- `docs/architecture.md` — service layer, data model, workflows
- `docs/google-integration.md` — Google Cloud setup
- `docs/TinkerManual.md` — common Tinker commands

---

## Project Structure

    app/
    ├── Enums/               EventStatus, EventType, RegistrationStatus, AttendanceStatus
    ├── Http/Controllers/    EventController, EventDeletionController,
    │                        GoogleIntegrationController, RegistrationSetupController
    ├── Mail/                EventDeletionOtpMail
    ├── Models/              Event, EventOption, Participant, Registration,
    │                        RegistrationOption, Attendance,
    │                        GoogleIntegration, RegistrationSetup, RegistrationSetupChange
    └── Services/
        ├── EventWorkflow.php                 lifecycle state machine
        ├── EventDeletion/                    ordered deletion + OTP verification
        └── Google/
            ├── GoogleOAuthService.php        OAuth connection + token refresh
            └── GoogleFormService.php         Form CRUD + Sheet linkage

    resources/js/
    ├── components/          confirm-dialog, event-deletion-otp-dialog,
    │                        question-editor-dialog, date-picker, time-picker
    ├── hooks/               use-confirm-dialog
    └── pages/
        ├── dashboard.tsx              action feed grouped by urgency
        ├── events/                    index, create, edit, show, options,
        │                              registration-setup
        └── settings/google.tsx        OAuth connect/disconnect

    database/migrations/     full schema
    docs/                    technical documentation
    routes/
    ├── web.php              all authenticated routes
    └── settings.php         profile, security, Google integration

    tests/
    ├── Feature/Events/      deletion admin + staff, cascade
    └── Unit/Services/       OTP service

---

## Known Limitations

1. **Form deletion on event delete.** Deleting an event cleans the WDEMS database but leaves the Google Form + Sheet in Drive. Manual cleanup required. Fix planned.
2. **Only `updateItem` for changed questions.** The question sync sends an update request for every existing question on every save, even if the field didn't change. Functionally correct but adds latency on large forms.
3. **No automated tests for the Google integration.** Would require mocking Google's API.
4. **Resend sandbox restriction.** The free tier only sends to the account owner's email until a domain is verified.
5. **Apps Script linkage is manual.** Each new Form requires the organizer to paste a small script — 30 seconds per event, by design.

---

## Roadmap

| Phase | Deliverable |
|---|---|
| **C** ✅ | Google Sheet creation + Form linkage (via Apps Script) |
| **D** 🚧 | Read Sheet rows → create Participant + Registration records |
| **E** 🚧 | Walk-in / paper registration UI (schema already supports it) |
| **F** 🚧 | Attendance recording UI |
| **G** 🚧 | Monitoring dashboard — recurring participants, attendance rates |
| **H** 🚧 | Post-run / partner activity module |

---

## License

Private project. Not for public distribution.
