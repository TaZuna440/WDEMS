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

---

## Phase 0 findings (2026-09-25)

Reconnaissance complete. All 22 files in the Phase 0 list were read in
the same session. This section records what each file does today, what
Phases 1–6 will need to change, and what the phase plans were missing.

### State machine and its drivers

**`app/Enums/EventStatus.php`** — Seven-case string enum (`Draft`,
`Configured`, `RegistrationOpen`, `RegistrationClosed`, `Ongoing`,
`Completed`, `Cancelled`). `allowedTransitions()` is a `match` returning
the next states per case. `Draft` currently transitions to
`Configured` or `Cancelled`. Phase 1 removes the `Configured` case and
changes `Draft` to transition directly to `RegistrationOpen` or
`Cancelled`.

**`app/Services/EventWorkflow.php`** — Thin service. `transition()` is
the single write path; throws `InvalidArgumentException` on illegal
transitions. `configure()` is one of six convenience helpers. Phase 1
deletes only `configure()`; the other five remain.

**`app/Models/Event.php`** — Model with Laravel 11 `#[Fillable]`
attribute. Casts include `EventStatus::class`. Guards: `canEdit()`,
`canConfigure()`, `canOpenRegistration()`, `canCloseRegistration()`,
`canRecordAttendance()`. `canEdit()` currently allows editing events in
`RegistrationOpen` and `RegistrationClosed` — deliberate; the spec
locks the *registration form*, not the event. `canConfigure()` is used
only by `EventOptionController`. Phase 1: rename or re-scope
`canConfigure()`, drop `Configured` from `canEdit()`. Phase 3: add
`registration_slug` (string, nullable) and `registration_form_locked_at`
(timestamp, nullable) to fillable + casts.

**`app/Http/Controllers/EventController.php`** — Full CRUD + three
workflow actions. `configure()` is Phase 1 removal. `openRegistration()`
sets `registration_start = now()` before calling the service — Phase 3
also generates the slug and stamps `registration_form_locked_at` here.
`show()` payload already exposes `can_open_registration`,
`can_close_registration`, `requires_otp`, `can_record_attendance` — the
frontend reads these directly.

### Domain models

**`app/Http/Controllers/EventOptionController.php`** — CRUD, gated on
`$event->canConfigure()`. Validates `option_type`, `option_name`,
`option_value`, `is_required`, `is_available`. No `field_type` or
`validation_rules` because those columns do not exist yet. Phase 2
rewrite adds them and switches the guard.

**`app/Models/EventOption.php`** — Simple model. Fillable + boolean
casts for `is_required` and `is_available`. Relations: `event()`,
`registrationOptions()`. Phase 2 extends fillable and casts.

**`app/Models/Registration.php`** — Already has `source` and
`registered_at` in fillable. **Nothing writes them today.** The schema
is ready for Phase 3; only the write side needs building.

**`app/Models/RegistrationOption.php`** — Pivot. `registration_id`,
`event_option_id`, `option_value`. No casts. Phase 3 writes one row per
custom field per submission.

**`app/Models/Participant.php`** — Exactly matches spec §4: six fields,
age casts to integer. `fullName()` is a PHP method, not a queryable
column. Phase 4 name search must either query two columns with
individual indexes, add a generated `full_name` column, or accept the
two-column `LIKE` at this scale. See cross-cutting finding C-5.

**`app/Models/Attendance.php`** — Matches spec §7. `attendance_status`
enum cast, `attendance_time` datetime, `recorded_by` FK to `User`. No
changes needed for Phase 4 beyond the new write path.

### Schema

**`database/migrations/2026_09_16_160308_create_event_options_table.php`**
— Five columns: `option_type`, `option_name`, `option_value`,
`is_required`, `is_available`. Index on `[event_id, option_type]`.
Confirms Phase 2 migration is required, not optional. Also worth
considering: index may want to shift to `[event_id, display_order]` for
ordered rendering once `display_order` is added.

### Routes

**`routes/web.php`** — Three route groups: public (`/` only),
device-verification (`auth` + `verified`), main app
(`auth` + `verified.or.admin` + `device.trusted`). No public
registration route exists. No `registrations` prefix exists. Phase 1
adds the registrations route group inside the main app group. Phase 3
adds `/r/{slug}` at the top level, outside all auth groups.

### Frontend shell

**`resources/js/components/app-sidebar.tsx`** — Single
`mainNavItems` array, two entries (Dashboard, Events). Phase 1 adds a
third entry.

**`resources/js/components/nav-main.tsx`** — Hardcoded
`<SidebarGroupLabel>Platform</SidebarGroupLabel>`. Single-group
assumption. If Registration lives in the same "Platform" group, no
change. If it needs its own group heading, `NavMain` needs a `label`
prop. See cross-cutting finding C-6.

**`resources/js/types/navigation.ts`** — `NavItem` is `{ title, href,
icon?, isActive? }`. No `roles` field. Same issue as ISSUE-001 from the
auth docs — not fixed, not blocking.

**`resources/js/layouts/app-layout.tsx`** — Thin wrapper. Public
registration page must bypass this. Phase 3 must add a case to the
layout switch in `resources/js/app.tsx`. See cross-cutting finding C-2.

### Frontend pages

**`resources/js/pages/events/options.tsx`** — The page Phase 2 replaces.
Editable list of `{option_type, option_name, option_value, is_required,
is_available}` rows. Add form, inline toggles, delete confirmation, and
a "Mark as Configured" call-to-action. `statusStyles` map includes
`configured` — Phase 1/2 removes that key. **Also note:** the file's
closing `};` for the layout static property appears to be missing its
module-closing `}`, based on the snapshot. Either the paste lost a line
or the file is genuinely malformed. Phase 2 replaces the file whole,
so this is not blocking.

**`resources/js/pages/events/show.tsx`** — Event detail view with
Workflow Actions panel. `statusStyles` includes `configured` — Phase
1/2 removes that key. The "Configure Options" button links to
`/events/{id}/options` — Phase 2 relabels and re-points. The stale
panel "These actions are being built in later pages" is now inaccurate
and should be removed. **Also note:** the current `openRegistration`
confirmation copy is generic ("Participants will be able to register
for this event"). Phase 3 needs richer copy that explains the URL is
about to go public.

**`resources/js/pages/events/index.tsx`** — Event list. `statusStyles`
includes `configured` — Phase 1/2 removes that key.

### Deletion flow pattern (reference for Phase 5)

**`app/Http/Controllers/EventDeletionController.php`** — Three actions:
`destroy` (admin only), `requestOtp` (staff only), `verifyOtp` (staff
only). Each checks `isAdmin()` and aborts the opposite role. Phase 5
mirrors this shape for registrations — but keyed on
`registration_id` instead of `event_id`.

**`app/Services/EventDeletion/EventDeletionOtpService.php`** — Cache-
backed OTP. Constants: `CODE_TTL_SECONDS = 600`, `RESEND_COOLDOWN_SECONDS
= 60`, `MAX_ATTEMPTS = 5`. Codes hashed with HMAC-SHA256 using
`APP_KEY`. Phase 5 for registration mirrors this exactly — same
constants, same hashing, different cache keys.

**`app/Mail/EventDeletionOtpMail.php`** — Mailable with `code`,
`eventName`, `expiresInMinutes`. View is
`emails.event-deletion-otp`. Phase 5 needs a parallel
`RegistrationDeletionOtpMail` with a `participantName` instead of
`eventName`.

---

## Cross-cutting findings

Numbered for reference in later phases.

### C-1 — `configured` appears in three status-style maps

`events/options.tsx`, `events/show.tsx`, and `events/index.tsx` each
define a `statusStyles` map with a `configured` key. Phase 1 or 2 must
remove that key from all three. Not in the original Phase 1 file list.
**Add to Phase 1 scope.**

### C-2 — Public layout must be wired in `app.tsx`

`resources/js/app.tsx` contains the layout selector — a switch on page
name that returns `null` for `welcome`, `AuthLayout` for `auth/*`, etc.
Phase 3 must add a `registrations/public` case returning `null` or a
dedicated minimal layout. Not in the original Phase 3 file list.
**Add to Phase 3 scope.**

### C-3 — Wayfinder-generated routes will break on route removal

Phase 1 removes `events.configure`. Wayfinder regenerates
`resources/js/routes/**` and `resources/js/actions/**` on the next dev-
server restart. Any file still importing `events.configure` will fail
to compile. Phase 1 must `grep -rn "events.configure" resources/js/`
before and after route removal. **Add to Phase 1 scope.**

### C-4 — `registration_end` auto-close is not implemented anywhere

The spec §6 says "if `registration_end` is set, POST rejects after that
timestamp." Today, `registration_end` is written only when
`closeRegistration()` is called manually. There is no scheduled task,
no middleware check, no query that reads it for auto-close logic.
Phase 3 must add the enforcement. Three options:

1. Check inside `PublicRegistrationController::store` — simple, works
   for POST, but the public form still renders after close time until
   the organizer manually closes
2. Check in a middleware on `/r/{slug}` — covers both GET and POST,
   shows a friendly "registration closed" page
3. Scheduled job that transitions status automatically — most correct,
   most complex

Recommendation: **(2)**. Middleware on the public route group checks
`registration_end` on every request; if past, aborts with a friendly
message. Preserves the manual close as well. **Add to Phase 3 scope.**

### C-5 — Participant name search strategy

`Participant::fullName()` is a PHP method, not a column. Phase 4's
"search by name" needs a query-level answer. Three options:

1. `WHERE first_name LIKE ? OR last_name LIKE ?` with individual indexes
2. Add a generated `full_name` column
3. Accept the two-column `LIKE` at this scale

Recommendation: **(1)**. At hundreds of rows, two-column `LIKE` with
indexes is fast. A generated column can be added later if profiling
shows a need. **Add to Phase 4 scope: migration for
`participants.first_name` and `participants.last_name` indexes.**

### C-6 — Sidebar group structure

`NavMain` renders a single "Platform" group. If the Registration tab
should live in the same group as Dashboard and Events, no change to
`nav-main.tsx` is needed. If Registration gets its own group heading
("Registrations" / "Workflows"), `NavMain` needs a `label` prop and
`AppSidebar` renders two `NavMain` instances. **Decision needed before
Phase 1.**

### C-7 — `canConfigure()` rename touches frontend prop key

`EventOptionController::index()` passes `can_configure` to the page.
If Phase 1 renames `canConfigure()` → `canEditRegistrationForm()`, the
Inertia prop key should rename too, and `events/options.tsx` (or its
Phase 2 replacement) updates. Keep this pair in sync. **Note for
Phase 1/2 boundary.**

---

## Phase plan additions

These files were not in the original Phase 1–6 lists but are required
based on the reconnaissance:

| Phase | File | Reason |
|---|---|---|
| 1 | `resources/js/app.tsx` (read only) | Confirms C-2; no edit yet |
| 1 | Grep of `events.configure` across `resources/js/` | C-3 |
| 3 | `resources/js/app.tsx` | Layout case for `registrations/public` (C-2) |
| 3 | Middleware for `/r/{slug}` close-time check | C-4 |
| 4 | Migration for `participants.first_name` and `participants.last_name` indexes | C-5 |

---

## Open items carried into Phase 1

From the reconnaissance, these need decisions before Phase 1 starts:

1. **Sidebar grouping** — one group or two? (C-6)
2. **Guard method name** — `canConfigure()` renamed, or kept with new
   internal list? (C-7)
3. **`configured` styling** — the `bg-blue-500/15 text-blue-500` used
   for `configured` in three files. Reuse for another state, or delete
   entirely? (C-1)

Each is a small decision. None blocks reconnaissance; all block the
first code change.

---

## Changelog addendum

- **2026-09-25** — Phase 0 complete. 22 files read. Seven cross-cutting
  findings recorded. Five files added to Phase 1–4 scope. Three open
  decisions carried forward.

---

## Phase 0 correction — DashboardController omitted (2026-09-25)

P7 correction. The Phase 0 reconnaissance read 22 files and reported
findings as if complete. A pre-flight grep before Phase 1 code
identified a 23rd file that was not read:
`app/Http/Controllers/DashboardController.php`.

### What was missed

`DashboardController` builds the dashboard action items by walking every
event, filtering terminal states, and sorting the rest into urgency
buckets by `event_date` and `status`. It references
`EventStatus::Configured` at three sites:

- Line 56 — inside the "urgent" match arm:
  `in_array($status, [EventStatus::Draft, EventStatus::Configured], true)`
- Line 70 — a standalone match arm: `$status === EventStatus::Configured`
- Line 73 — the reason text for that arm:
  `'Configured — ready to open registration'`

Phase 1 removes the `Configured` case from `EventStatus`. Without a
corresponding update to `DashboardController`, these three references
become runtime errors when a user opens the dashboard.

### Impact on Phase 1

Add `app/Http/Controllers/DashboardController.php` to the Phase 1 file
list. Changes required:

- Line 56 — collapse `[Draft, Configured]` to `[Draft]`
- Lines 70–74 — delete the entire `$status === EventStatus::Configured`
  match arm, including its reason text
- Line 61–65 — the `Draft` arm's reason text (`'Draft — needs
  configuration'`) reworded to reflect the new workflow. Proposal:
  `'Draft — no registration form yet'`
- `nextActionFor()` — unchanged for Phase 1 (still returns
  `"Configure event"` as the label for Draft). Label rename deferred to
  Phase 2 when the actual form page exists.

### Decisions recorded in this session

**D-1 — Dashboard grouping replacement.** Just `Draft`. No new state
added. Events are either actionable (`Draft`) or not.

**D-2 — Dashboard description replacement.** Option A — drop the
`Configured` branch entirely. Events fall through to whatever `Draft`
renders today. No new `registration_form_saved_at` column. Revisit
after Phase 3 if the dashboard feels underinformative.

### Process note

This is the second time in this session that a file outside the initial
Phase 0 list was needed for a phase. The first was
`HandleInertiaRequests.php` in the auth session, where it was needed to
confirm flash message behavior and was not in the docs' file map.

The pattern: enumerating files from a directory walk misses consumers.
The correct method is to grep for every identifier the phase will
touch — status enum values, method names, prop keys — then read every
file the grep returns. P1 as written ("read before proposing") was
satisfied against the listed files. The listed files were derived from
a directory walk, not from a call-graph traversal. The gap is in how
the file list is built.

Recorded here rather than as a new protocol because P1–P10 already
require the correction. The deficiency is in the enumeration method,
not the protocol.

## Changelog addendum

- **2026-09-25** — Phase 0 correction. `DashboardController` identified
  as a missed file. Three `Configured` references added to Phase 1
  scope. D-1 and D-2 answered in-session.
