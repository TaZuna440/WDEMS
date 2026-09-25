# WDEMS — Architecture

A technical overview of how the pieces of WDEMS fit together. For a chronological record of what was built, see `development-log.md`. For the Event Creation Wizard in detail, see `event-creation-wizard.md`. For historical context on the removed Google integration, see `archive/`.

---

## Layered Overview

    ┌─────────────────────────────────────────────────────────┐
    │  React 19 + Inertia 3 (resources/js/)                   │
    │  Pages · Components · Hooks                             │
    └────────────────────────┬────────────────────────────────┘
                             │ Inertia props (server → client)
                             │ router.post/delete (client → server)
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  Laravel Controllers (app/Http/Controllers/)            │
    │  Thin: authorize → call service → return redirect/Inertia│
    └────────────────────────┬────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  Services (app/Services/)                               │
    │                                                          │
    │  EventWorkflow                 — lifecycle state machine │
    │  EventDeletion/                — ordered delete + OTP    │
    │  Otp/OtpCode                   — shared code helper      │
    │  TwoFactor/EmailTwoFactorService — email 2FA + devices   │
    └────────────────────────┬────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  MySQL  ·  Cache (database)  ·  Resend                  │
    └─────────────────────────────────────────────────────────┘

Key principle: **controllers never touch domain logic directly.** Every multi-step operation lives behind a service.

---

## Data Model

### Core domain

    users
      id, name, email, role (admin|staff), email_verified_at, password
      email_two_factor_enabled (bool, default true)
      trusted_devices (JSON, nullable)  ← [{id, label, ip, added_at}]

    events
      id, created_by → users (RESTRICT)
      event_type (community_run | fun_run)
      event_name, description, event_date, start_time, end_time
      venue, venue_address, venue_latitude, venue_longitude
      distance_value, distance_unit (km|mi)
      course_url, rsvp_required
      partners (JSON), faq (JSON)
      walkers_welcome, all_paces_welcome, all_ages_welcome,
      stroller_friendly, wheelchair_accessible, sweeper_present,
      service_animals_allowed, leashed_pets_allowed, quiet_space_available
      status (draft|configured|registration_open|registration_closed|ongoing|completed|cancelled)
      registration_start, registration_end

    event_options
      id, event_id → events (CASCADE)
      option_type (e.g. distance, shirt_size)
      option_name (e.g. 5KM, Large)
      option_value, is_required, is_available

    participants
      id, first_name, last_name, contact_number, email, age, address

    registrations
      id, event_id → events (CASCADE)
      participant_id → participants (RESTRICT)
      registration_date, registration_status
      source (form|walk_in|paper)     ← nullable, schema ready
      registered_at                    ← nullable
      UNIQUE (event_id, google_form_response_id)   ← legacy column, unused

    registration_options
      id, registration_id → registrations (CASCADE)
      event_option_id → event_options (RESTRICT)
      option_value
      UNIQUE (registration_id, event_option_id)

    attendances
      id, registration_id → registrations (CASCADE, UNIQUE)
      attendance_status, attendance_time
      recorded_by → users (NULL on delete), notes

The `google_form_response_id` column on `registrations` and the `source` / `registered_at` columns are schema remnants from the removed Google integration. They are nullable and no current code reads or writes them; they may be dropped in a future migration.

---

## Foreign Key Rules

| Relationship | On delete |
|---|---|
| `events.created_by` → `users` | RESTRICT |
| `event_options.event_id` → `events` | CASCADE |
| `registrations.event_id` → `events` | CASCADE |
| `registrations.participant_id` → `participants` | **RESTRICT** |
| `registration_options.registration_id` → `registrations` | CASCADE |
| `registration_options.event_option_id` → `event_options` | **RESTRICT** |
| `attendances.registration_id` → `registrations` | CASCADE |

The **RESTRICT** on `registration_options.event_option_id` is the reason we cannot rely on cascade alone when deleting an event. See "Deletion algorithm" below.

---

## The Event Lifecycle

Managed by `App\Services\EventWorkflow`. States are held in `EventStatus` enum.

    draft
      │ configure()
      ▼
    configured
      │ openRegistration()
      ▼
    registration_open
      │ closeRegistration()
      ▼
    registration_closed
      │ start()
      ▼
    ongoing
      │ complete()
      ▼
    completed

    (cancelled reachable from any non-terminal state)

Guards live on the `Event` model as `canEdit()`, `canConfigure()`, `canOpenRegistration()`, `canCloseRegistration()`. Controllers check these before allowing transitions.

Every transition triggers the `EventWorkflow` service, which throws on illegal transitions rather than silently no-oping.

---

## Deletion Algorithm

Deleting an event is non-trivial because of the RESTRICT constraint on `registration_options.event_option_id`. Relying on MySQL cascade would sometimes raise a constraint violation. Instead, `EventDeletionService::delete()` performs an explicit ordered delete inside a transaction:

    1. Collect registration IDs for the event
    2. Delete attendances      (via registration_id)
    3. Delete registration_options  (via registration_id)
    4. Delete registrations    (via event_id)
    5. Delete event_options    (via event_id)
    6. Delete the event

All inside `DB::transaction()`. Participants are never touched — they are shared across events.

Role branching is handled at the controller layer, not here:

- **Admin** — direct DELETE route, no verification
- **Staff** — must pass OTP verification first. The OTP endpoints call the same `EventDeletionService::delete()` on success.

---

## OTP Verification (Staff Event Deletion)

Implemented in `App\Services\EventDeletion\EventDeletionOtpService`. Backed by Laravel Cache (database driver). Shares code shape with the 2FA service via `App\Services\Otp\OtpCode`.

Storage shape:

    key:   event-delete-otp:{user_id}:{event_id}
    value: {
      code_hash:  HMAC-SHA256(code, APP_KEY),
      attempts:   integer,
      issued_at:  unix timestamp,
      expires_at: unix timestamp
    }
    ttl:   10 minutes

    key:   event-delete-otp-resend:{user_id}:{event_id}
    value: unix timestamp
    ttl:   60 seconds

Rules:

- Code is 6 digits, generated with `random_int(0, 999999)`.
- Never stored in plaintext — only an HMAC under `APP_KEY`.
- Single-use: both cache keys forgotten on success.
- 5 failed attempts → invalidate the OTP entirely.
- 60-second resend cooldown.
- Never logged.

Rate limiters on the endpoints (registered in `AppServiceProvider`):

- `event-deletion-otp-request` — 3 per minute per (user, event)
- `event-deletion-otp-verify` — 10 per minute per (user, event)

---

## Email Two-Factor Authentication

Implemented in `App\Services\TwoFactor\EmailTwoFactorService`. Backed by Laravel Cache (database driver). Called from `App\Http\Controllers\Auth\DeviceVerificationController`.

### Flow

1. User logs in on a new device.
2. `EnsureDeviceIsTrusted` middleware intercepts the request.
3. Middleware allows through if: user is admin, OR `email_two_factor_enabled = false`, OR the request carries a valid `wdems_trusted_device` cookie matching a `trusted_devices` entry.
4. Otherwise: redirect to `/verify-device`.
5. User requests a code → `EmailTwoFactorService::issue()` mails a 6-digit code (10-minute TTL, single-use, HMAC-hashed in cache).
6. User submits code → `verify()` returns one of: `Verified`, `Invalid`, `Expired`, `Locked`, `NotFound`, `TooSoon`.
7. On `Verified` with remember=true: `trustDevice()` adds an entry to `trusted_devices` and queues a `wdems_trusted_device` cookie (30-day TTL).
8. Successful login clears the OTP cache keys.

### Storage shape

    key:   email-2fa:{user_id}
    value: {
      code_hash:  HMAC-SHA256(code, APP_KEY),
      attempts:   integer,
      issued_at:  unix timestamp,
      expires_at: unix timestamp
    }
    ttl:   10 minutes

    key:   email-2fa-resend:{user_id}
    value: unix timestamp
    ttl:   60 seconds

### Trusted device entry

Stored on `users.trusted_devices` (JSON array):

    {
      id:       string (UUID)
      label:    string  (e.g. "Chrome")
      ip:       string|null
      added_at: ISO 8601 string
      token_hash: HMAC-SHA256(token, APP_KEY)
    }

The raw token is only ever sent to the client (as the cookie value) and is never persisted.

### Admin exemption

Admins bypass 2FA entirely — enforced inside `EnsureDeviceIsTrusted`. This is a deliberate scope decision: admins control the server and are trusted at a higher level.

### Rate limiters

- `device-verification-send` — 3 per minute per user
- `device-verification-verify` — 10 per minute per user

### Logout cleanup

`AppServiceProvider::configureLogoutCleanup()` listens for `Illuminate\Auth\Events\Logout` and queues `Cookie::forget('wdems_trusted_device')`. This clears stale trust state on a shared browser.

---

## Event Creation Wizard

A 4-step wizard for creating and editing events, implemented in React with a Laravel-side validation contract. Full feature documentation lives in `event-creation-wizard.md`.

### Steps

| Step | Fields |
|---|---|
| 1 — Basics | `event_type`, `event_name`, `description` |
| 2 — Schedule | `event_date`, `start_time`, `end_time`, `distance_value`, `distance_unit`, `course_url` |
| 3 — Venue | `venue`, `venue_address`, `venue_latitude`, `venue_longitude` |
| 4 — Extras | `rsvp_required`, `partners`, 9 accessibility flags |

### Key files

    resources/js/components/
      wizard.tsx                    — shell: step state, checkpointing, nav
      wizard-progress.tsx           — progress bar with clickable visited steps
      distance-picker.tsx           — whole + decimal input, KM/Miles toggle
      map-picker.tsx                — dialog wrapper (renders always)
      map-picker-leaflet.tsx        — Leaflet map, lazy-loaded
      partner-editor.tsx            — add/remove partner rows
      accessibility-grid.tsx        — 9 checkboxes in 4 categories

    resources/js/hooks/
      use-wizard-persistence.ts     — localStorage save/restore

### Checkpoint semantics

The wizard tracks two values separately:

- **`maxStep`** — the furthest step ever reached. Saved as the checkpoint.
- **`currentStep`** — the step the user is viewing.

Going back does **not** reduce `maxStep`. On reload, the wizard opens at `maxStep`. The progress bar allows jumps to any step ≤ `maxStep`.

Persisted to `localStorage` under `wdems-wizard-create` or `wdems-wizard-edit-{id}`. 24-hour TTL. Version guard (`STORAGE_VERSION`) invalidates drafts on schema change.

### Client vs server validation

Client validates **only field emptiness** before advancing a step. Everything else — URL format, enum values, numeric ranges, the event-name heuristics — is server-side in `App\Concerns\EventValidationRules`.

When the server rejects, the wizard reads `errors` and jumps to the first step containing a failing field.

### Map stack

Free stack — no API key required:

- **Leaflet** for rendering
- **OpenStreetMap** tiles
- **Nominatim** for reverse geocoding (1 req/sec limit, silent retry on failure)

The Leaflet bundle (154 KB) is lazy-loaded only when the MapPicker dialog opens — see `map-picker.tsx` / `map-picker-leaflet.tsx` split.

---

## React Component Architecture

### Layout

Every page has a `Layout` static property attaching it to a breadcrumb trail. The sidebar and header come from `AppLayout`.

### Confirm dialog

`useConfirmDialog()` hook + `ConfirmDialog` component. Variants: `primary`, `warning`, `danger`. Accepts `children` for structured content (used by the event deletion warning to list record counts, and by the security page to describe trust revocation).

### OTP dialog

`EventDeletionOtpDialog` — self-contained, handles request-verify flow, countdown, error states.

### Wizard

See "Event Creation Wizard" above. `Wizard` is a generic shell over `WizardStepConfig[]` — it takes a `children(stepId)` render function and provides step navigation, checkpoint persistence, error jumping, and the progress bar.

### Pickers

Built on Radix primitives (no extra npm packages):

- `DatePicker` — popover calendar, keyboard-navigable, "Today" shortcut
- `TimePicker` — hour/minute/period selects, converts between `HH:MM` (24h) and the display format
- `DistancePicker` — text input with ▲▼ steppers, KM/Miles segmented toggle, auto-conversion on unit switch
- `MapPicker` / `MapPickerLeaflet` — dialog + lazy Leaflet, Nominatim reverse geocode

All are DRY-shared between `events/create.tsx` and `events/edit.tsx`.

### Security page

`resources/js/pages/settings/security.tsx` — password update form + email 2FA toggle + trusted-device list with per-device revoke.

---

## Error Handling

Every service method that can fail is wrapped in `try/catch` at the calling controller. Failures:

1. Are logged with structured context (`user_id`, `event_id`, `error`).
2. Return a user-friendly error via `back()->withErrors([...])`.
3. Never produce a 500 page.

Frontend reads errors from `usePage().props.errors` when using `router.post/delete` (wizard, settings toggles), and from `form.errors` when using `<Form>` (password update, login). This distinction matters — `form.errors` only reflects `<Form>`-submitted requests.

---

## Testing Strategy

- **SQLite in-memory** for all tests (`phpunit.xml`). Full suite runs in ~3 seconds.
- **Feature tests** for HTTP-facing behavior — role gates, redirects, middleware, cascade semantics, wizard submit flow.
- **Unit tests** for isolated services — `EmailTwoFactorServiceTest`, `EventDeletionOtpServiceTest` are the canonical examples.
- **Pest** as the test framework. `tests/Pest.php` binds `RefreshDatabase` to the `Feature` suite.

### Notable test files

    tests/Unit/Services/EmailTwoFactorServiceTest.php
    tests/Unit/Services/EventDeletionOtpServiceTest.php
    tests/Feature/Auth/DeviceVerificationTest.php
    tests/Feature/Auth/LogoutDeviceTrustTest.php
    tests/Feature/Events/EventDeletionAdminTest.php
    tests/Feature/Events/EventDeletionStaffTest.php
    tests/Feature/Settings/SecurityTest.php

### Current suite size

85 passed, 4 skipped (Fortify gating), 253 assertions.
