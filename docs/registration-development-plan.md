# WDEMS — Registration Development Plan

**Audience:** Whoever executes the registration build.

**Purpose:** Phased plan for building the feature described in
`docs/registration.md`. Each phase has a "Files to cat first" section,
which satisfies P1 (read before proposing changes). No changes to a
listed file without reading it in the same session.

**Status:** Planned. Phase 0 has not been executed.

**Related:**

- `docs/registration.md` — the feature spec
- `docs/dev-workflow/README.md` — the workflow protocol (P1–P10)

---

## Phase structure

Each phase is one coherent unit of work. Every phase ends with:

1. Files read
2. Changes made
3. Tests written or updated
4. Verification run
5. Commit landed with a message sourced from the diff (P9)

Phases ship independently. If a phase stalls, the previous commit is the
recovery point.

| Phase | Goal | Sessions (estimate) |
|---|---|---|
| 0 | Reconnaissance — read everything, no changes | 1 |
| 1 | State machine collapse + sidebar Registration tab | 1 |
| 2 | Create Registration Form page (replaces Configure Options) | 1–2 |
| 3 | Public URL + anonymous form | 1–2 |
| 4 | Attendance rebuild | 1–2 |
| 5 | Delete registration with OTP | 1 |
| 6 | Docs convert from spec to reference; cleanup | 1 |

Rough total: 7–10 sessions.

---

## Phase 0 — Reconnaissance

**Goal:** Read every file that later phases will touch. No changes.
Output is a written summary of what each file currently does, appended
to this doc as a `## Phase 0 findings` section.

### Files to cat

    # Event lifecycle and state machine
    app/Enums/EventStatus.php
    app/Services/EventWorkflow.php

    # Event controller — currently owns create/edit/show/configure and
    # open/close registration actions
    app/Http/Controllers/EventController.php

    # Event options controller — will be replaced by the registration
    # form controller
    app/Http/Controllers/EventOptionController.php

    # Event model
    app/Models/Event.php

    # Registration-related models (schema-only today)
    app/Models/Registration.php
    app/Models/RegistrationOption.php
    app/Models/Participant.php
    app/Models/Attendance.php

    # event_options migration — the table we plan to extend
    database/migrations/2026_09_16_160308_create_event_options_table.php

    # Routes
    routes/web.php

    # Frontend pages we will replace or modify
    resources/js/pages/events/options.tsx
    resources/js/pages/events/show.tsx
    resources/js/pages/events/index.tsx

    # Frontend sidebar — the new Registration tab goes here
    resources/js/components/app-sidebar.tsx
    resources/js/components/nav-main.tsx
    resources/js/types/navigation.ts

    # Layout (to confirm what the public page should NOT include)
    resources/js/layouts/app-layout.tsx

    # Existing deletion flow — reference for Phase 5
    app/Http/Controllers/EventDeletionController.php
    app/Services/EventDeletion/EventDeletionOtpService.php
    app/Mail/EventDeletionOtpMail.php

### Output

Append a `## Phase 0 findings` section to this file. One paragraph per
file: what it does, what will change, what will not. No code.

---

## Phase 1 — State machine collapse + sidebar Registration tab

**Goal:** Remove `configured`. Add a Registration tab to the sidebar.
Add a Registration queue page (visual only).

### Files to cat first

    app/Enums/EventStatus.php
    app/Services/EventWorkflow.php
    app/Http/Controllers/EventController.php
    resources/js/components/app-sidebar.tsx
    resources/js/components/nav-main.tsx
    resources/js/types/navigation.ts

### Changes

- `EventStatus` enum — remove `configured` case, update
  `allowedTransitions()`
- `EventWorkflow` — remove `configure()` method
- `EventController` — remove `configure()` action; update
  `openRegistration()` for new guards
- Sidebar — add Registration nav item between Dashboard and Events
- New page `resources/js/pages/registrations/index.tsx` — three
  sections, empty state

### Tests

- Update existing tests referencing `configured`
- New: `draft → registration_open` transition allowed
- New: legacy `configured → registration_open` no longer exists

### Verify

    php artisan test tests/Feature/Events
    npx tsc --noEmit
    Browser: sidebar shows Registration tab; page renders empty state

---

## Phase 2 — Create Registration Form

**Goal:** Replace Configure Options with Create Registration Form.
Common fields fixed. Custom fields editable. Save triggers confirm
modal; form remains editable until Open Registration.

### Files to cat first

    app/Http/Controllers/EventOptionController.php
    app/Models/EventOption.php
    resources/js/pages/events/options.tsx
    database/migrations/2026_09_16_160308_create_event_options_table.php
    app/Concerns/EventValidationRules.php

### Changes

- Migration extends `event_options`: add `field_type`,
  `validation_rules`, `display_order`
- Rename `EventOptionController` → `RegistrationFormController` (or
  rename actions only)
- Rewrite `events/options.tsx` → `events/registration-form.tsx`
- New `resources/js/lib/registration-validation.ts`
- Confirm modal on Save

### Tests

- New `tests/Feature/Events/RegistrationFormTest.php` — save,
  validation, common fields always present, custom CRUD

### Verify

    php artisan test tests/Feature/Events
    npx tsc --noEmit
    Browser: form builder renders, common fields fixed, add custom field,
    save

---

## Phase 3 — Public URL + anonymous form

**Goal:** Public surface. Open Registration publishes `/r/{slug}`.
Anonymous submissions create `participant` + `registration`. Form locks
on Open.

### Files to cat first

    app/Http/Controllers/EventController.php
    app/Services/EventWorkflow.php
    app/Models/Registration.php
    app/Models/Participant.php
    app/Providers/AppServiceProvider.php   (rate limiters)
    resources/js/layouts/app-layout.tsx    (the shell we are NOT using)

### Changes

- Migration: `events.registration_slug` (string, unique, nullable),
  `events.registration_form_locked_at` (timestamp, nullable)
- New `PublicRegistrationController`
- New routes `GET /r/{slug}`, `POST /r/{slug}`
- New page `resources/js/pages/registrations/public.tsx` — no app
  layout
- Rate limiter in `AppServiceProvider`
- Duplicate email check within event

### Tests

- New `tests/Feature/Public/RegistrationSubmissionTest.php` — anonymous
  access, validation, duplicate rejection, rate limit, closed-event
  rejection

### Verify

    php artisan test tests/Feature/Public
    npx tsc --noEmit
    Browser: Open Registration → visit /r/{slug} in incognito → submit
    → participant appears in attendance

---

## Phase 4 — Attendance rebuild

**Goal:** Replace the current attendance page. Show pre-registered and
walk-in. Server-side search. Pagination. Mark attendance. Add walk-in.

### Files to cat first

    app/Http/Controllers/AttendanceController.php
    app/Models/Attendance.php
    resources/js/pages/events/attendance.tsx
    resources/js/components/event-deletion-otp-dialog.tsx   (pattern ref)

### Changes

- Rewrite `AttendanceController` — search, filter, pagination, walk-in
- Rewrite `events/attendance.tsx` — table, search, filter chips,
  walk-in button, mark-present toggle
- Walk-in form modal
- Index migration on `participants.email`,
  `participants.contact_number`

### Tests

- New `tests/Feature/Events/AttendanceTest.php` — pagination, search,
  filter, mark, walk-in

### Verify

    php artisan test tests/Feature/Events
    Browser: attendance page with 200+ rows — paginate, search, filter

---

## Phase 5 — Delete registration with OTP

**Goal:** Delete a registration from the attendance page, with OTP
verification for staff.

### Files to cat first

    app/Http/Controllers/EventDeletionController.php
    app/Services/EventDeletion/EventDeletionOtpService.php
    app/Services/Otp/OtpCode.php
    app/Mail/EventDeletionOtpMail.php
    routes/web.php

### Changes

- New `app/Services/RegistrationDeletion/RegistrationDeletionOtpService.php`
- New `app/Http/Controllers/RegistrationDeletionController.php`
- New `app/Mail/RegistrationDeletionOtpMail.php` + Blade template
- New route group mirroring `events.deletion.*`
- Frontend `RegistrationDeletionOtpDialog` (fork of existing dialog)

### Tests

- New `tests/Feature/Events/RegistrationDeletionTest.php` — admin
  direct, staff OTP, both paths, rate limits

### Verify

    php artisan test tests/Feature/Events
    Browser: as staff, try to delete a registration, receive OTP,
    verify, delete

---

## Phase 6 — Docs, tests, cleanup

**Goal:** Convert `docs/registration.md` from spec to reference. Sweep
for stale docs. Final test pass.

### Files to cat first

    docs/event-creation.md §7 (options / workflow)
    docs/architecture.md (event lifecycle section)
    docs/event-creation-wizard.md (if it references options)

### Changes

- Rewrite `docs/registration.md` — spec → reference (file map, traps,
  open items)
- Append corrections to `event-creation.md` and `architecture.md`
- Update `README.md` status table
- Remove dead code from the options → form transition

### Verify

    php artisan test
    npx tsc --noEmit
    All docs cross-references resolve

---

## Protocol compliance per phase

Each phase runs under the workflow rules in
`docs/dev-workflow/README.md`:

- **P1** — cat every file listed in "Files to cat first" before
  proposing changes
- **P2** — heredoc only; no sed or editors for non-trivial edits
- **P4** — one change block per message
- **P5** — verify every write (`wc -l`, delimiter grep, syntax check)
- **P7** — corrections append, never rewrite
- **P9** — commit messages from diffs
- **P10** — do not commit unread files

If a phase spans multiple files and any file has not been read this
session, that phase is blocked until it has been read.

---

## Changelog

- **2026-09-25** — File created. Phased plan for building registration
  per `docs/registration.md`.
