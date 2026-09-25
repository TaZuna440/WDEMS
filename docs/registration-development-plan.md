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

---

## Phase 1 complete (2026-09-25)

State machine collapse + sidebar Registration tab + queue page. Three
commits.

### Commits landed

- `ec88543` — refactor: collapse event workflow — remove Configured
  state. Six files, +20/−43.
- `7ffee27` — feat(registration): add queue page and sidebar tab;
  clean up Configured remnants. Seven files, +246/−7.
- `bd6be34` — test(events): pin the collapsed state machine. One new
  file, 170 lines, 17 tests.

### What shipped

Backend:
- `EventStatus` — Configured case removed; six cases remain
- `EventWorkflow::configure()` — removed
- `Event::canConfigure()` renamed to `canEditRegistrationForm()`;
  guard is now `status === Draft`
- `Event::canEdit()` no longer lists Configured
- `Event::canOpenRegistration()` guard changes from Configured to Draft
- `EventController::configure()` action removed
- `DashboardController` — three Configured references removed, Draft
  reason text reworded to "Draft — no registration form yet"
- `EventOptionController` — four call sites renamed; `can_configure`
  Inertia prop key preserved
- Route `events.configure` removed
- New route `registrations.index`

Frontend:
- Sidebar Registration tab (between Dashboard and Events)
- New page `resources/js/pages/registrations/index.tsx` — three
  sections (Needs Registration Form, Open, Closed), empty states
- Three `statusStyles` maps cleaned of the `configured:` key

Tests:
- `EventWorkflowTest.php` — 17 tests pinning the collapsed state
  machine. Full Events suite 71 passed / 167 assertions.

### Interim state

Open Registration is clickable on any Draft event. Clicking it sets
status to `registration_open` and stamps `registration_start`, but no
public URL exists behind it yet. Phase 3 of this plan closes that gap
by generating a slug, setting `registration_form_locked_at`, and
publishing `/r/{slug}`.

Decision recorded: leave the interim state as-is rather than adding a
temporary guard that Phase 3 would remove. Rationale: short window,
development-only environment, guard-then-unguard is churn.

### Block numbering note

Commits labeled Blocks 1, 2, 3, 4, 7. Blocks 5 and 6 were not
separate code changes — they were the "route changes" and "new page"
concerns, which landed inside Block 4's frontend half. The numbering
was planned but not followed literally. Phase 1 is Blocks 1–4 plus the
transition tests (labeled 7 because 5 and 6 were collapsed).

Future sessions should not look for missing Blocks 5 or 6. The phase
plan is the authoritative scope, not the block numbers.

### C-1 through C-7 resolution status

- **C-1** — `configured` key in three statusStyles maps — **resolved**
  in `7ffee27`
- **C-2** — public layout case in `app.tsx` — deferred to **Phase 3**
- **C-3** — Wayfinder routes breaking on `events.configure` removal —
  **no issue existed**. Grep before removal found only the route
  definition itself; no frontend file imported the generated route.
  No action needed.
- **C-4** — `registration_end` auto-close middleware — deferred to
  **Phase 3**
- **C-5** — Participant name search strategy — deferred to **Phase 4**
  (indexes on first_name and last_name)
- **C-6** — sidebar grouping decision — **resolved**: single group
- **C-7** — `canConfigure()` rename touches prop key — **resolved**:
  method renamed, prop key preserved

### Open decisions for Phase 2

Three decisions block the first Phase 2 change. All recorded here so
the next session sees them before starting.

1. **`event_options` extension vs new table.** The Phase 2 plan
   assumes extending `event_options` with `field_type`,
   `validation_rules`, and `display_order`. Alternative: create a new
   `registration_fields` table and deprecate `event_options`. Decision
   needed before Phase 2 begins.

2. **Walk-in email.** Spec §4 lists `email` as required on the common
   fields. Walk-in participants added on-site may not have an email.
   The Phase 4 walk-in flow needs a decision: make email optional for
   walk-ins only, or require the organizer to enter a placeholder.

3. **Custom field types.** Spec §5 lists seven types: text, number,
   email, select, checkbox, radio, date. Confirm this is the v1 list,
   or add/remove before Phase 2 implements the field-type selector.

### Verification at close

| Check | Result |
|---|---|
| `php artisan test tests/Feature/Events` | 71 passed / 167 assertions |
| `php artisan test tests/Feature/Auth` | 44 passed / 119 assertions |
| `npx tsc --noEmit` | silent |
| Browser: `/registrations` renders three sections | confirmed |
| Browser: sidebar shows three items with Registration active | confirmed |
| Browser: existing `/dashboard`, `/events`, `/events/{id}` still render | confirmed |
| `grep -rn "events.configure\|EventStatus::Configured" app/ routes/ resources/js/ tests/` | only the intentional comment on `routes/web.php:55` |

## Changelog addendum

- **2026-09-25** — Phase 1 complete. Three commits. Interim state
  recorded (Open Registration clickable, no URL behind it). C-1, C-3,
  C-6, C-7 resolved. C-2, C-4, C-5 deferred. Three Phase 2 decisions
  recorded.

---

## Phase 2 decisions (2026-09-25)

Three decisions answered before Phase 2 code begins. All three reshape
the Phase 2 scope from what the original section said.

### D1 — New `registration_fields` table, not an extension

The Phase 2 plan originally said "extend `event_options` with
`field_type`, `validation_rules`, `display_order`." That is rejected.

**Reason.** `event_options` models choices (one row per shirt size). A
form field models a field with nested choices (one row per field, with
`options` as a JSON array). Extending the existing table would require
overloading `option_name` to sometimes mean "field label" and sometimes
mean "choice value" — a semantic ambiguity future readers cannot
resolve from column names alone.

**Decision.** New table `registration_fields`:

    id, event_id (FK, cascade), label, field_type, options (JSON nullable),
    validation_rules (JSON nullable), is_required (bool),
    display_order (int), created_at, updated_at

**Deprecation path.** `event_options` is not dropped in Phase 2. It has
no live rows. Phase 6 cleanup drops it in a single migration.

### D2 — Walk-in email optional

The spec §4 lists `email` as required on the common fields. Walk-ins
added on-site may not have one.

**Decision.** Public submission form: email required, validated,
unique per event. Walk-in form: email optional; if blank, no duplicate
check runs.

**Data separation.** `registrations.source` distinguishes walk-in from
pre-registered. Walk-in data is often paper-based and less complete —
allowing a null email on walk-ins keeps the schema honest about what
was actually captured.

**Action item.** Verify `participants.email` is nullable in the schema
during Phase 2. If not, a small migration is required.

### D3 — Seven field types plus `textarea`

**Decision.** The v1 field-type list is:

- `text`
- `textarea` (new)
- `number`
- `email`
- `select`
- `checkbox`
- `radio`
- `date`

**Removed from consideration.** "Distance preference" as an example
use case. The event already declares `distance_value` and
`distance_unit` — every participant does the same distance. Multi-
distance events (5K/10K/half at one venue) are a separate future
feature not in v1 scope.

### Validation strategy — improved inputs

The spec §5 says custom field rules mirror `EventValidationRules`.
Concrete plan for Phase 2:

**HTML5 input mapping:**

| Field type | Rendered input |
|---|---|
| `text` | `<input type="text">` |
| `textarea` | `<textarea>` with character counter |
| `number` | `<input type="number" inputmode="numeric">` |
| `email` | `<input type="email" inputmode="email" autocomplete="email">` |
| `date` | `<input type="date">` |
| `select` / `radio` / `checkbox` | native controls |

**Autocomplete hints** on common participant fields — `given-name`,
`family-name`, `email`, `tel` — for browser autofill from the user's
saved contact card.

**Phone validation** — `inputmode="tel"` for the mobile keyboard, plus
a loose regex `+?\d[\d\s\-()]{8,15}` that accepts international formats
without rejecting legitimate variation.

**Inline hints** under each common field (first name, email, contact,
age) explaining the expected input.

**Real-time validation** on blur only — never on keystroke. Valid
inputs get a subtle tick; invalid inputs get a red border and inline
error. Typing is never blocked.

**Client-server mirror.** Every rule has an identical server-side
counterpart in `app/Concerns/RegistrationFormValidationRules.php` (or
similar). Client gives fast feedback; server remains authoritative.

### AI validation — out of scope

Considered and rejected for v1. An LLM in the submit path adds latency
(1–3s per submission), non-determinism (same input, different answers),
privacy concerns (participant PII sent to a third party), and cost.
The HTML5 + regex + autocomplete approach catches the same 95% that a
per-field LLM would — deterministically, offline, at zero cost.

If AI-assisted review is ever added, it belongs as a batch background
job (Phase 7+) that flags suspicious submissions for organizer review,
not in the field-level validation path.

## Changelog addendum

- **2026-09-25** — Phase 2 decisions recorded. D1 (new
  `registration_fields` table), D2 (walk-in email optional), D3
  (eight field types). Validation strategy and AI out-of-scope
  rationale captured.

---

## Phase 2 plan — scope, decisions, and file inventory (2026-09-25)

Supersedes the "Phase 2 — Create Registration Form" section above.
The original section assumed extending `event_options` with per-field
CRUD. Both assumptions are rejected.

### Decisions

**Q1 — FormRequest.** `RegistrationFormController` uses a FormRequest
(`RegistrationFieldRequest`), not inline `$request->validate()`. The
registration form has enough field-specific rules (field_type enum,
label quality, options-required-for-select, etc.) that a FormRequest
pays off. The trait `RegistrationFieldValidationRules` is consumed by
the request, and its mirror lives in
`resources/js/lib/registration-field-validation.ts`.

**Q2 — `option_value` concept dropped.** The new schema has no
equivalent column. Its only purpose was an ambiguous "optional
metadata" string whose actual use was never defined.

**Q3 — Entire options scaffolding deleted in Phase 2**, not deferred to
Phase 6. The clean break preserves code integrity — no code path
reaches the old scaffolding after Phase 2 ships.

**Q4 — Drag-to-reorder via `@dnd-kit`.** Three packages:
`@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities`. About 30KB
gzipped. React 19 compatible. Order persists via the batch save (see
C3 below) — drag is local-only until Save.

**Q5 — Inline field editor**, matching the current `options.tsx`
pattern. No modal.

**C1 — Path A.** Both `event_options` and `registration_options` are
dropped in Phase 2. `RegistrationOption` model is deleted. Phase 3
creates `registration_field_responses` fresh. Zero rows exist today;
no data is lost.

**C3 — Batch save.** One `PUT /events/{event}/registration-form`
endpoint replaces the whole form in a single transaction. The frontend
holds local state, one Save button, one confirm modal. This differs
from the current per-field immediate-round-trip pattern in
`options.tsx`.

The spec §2 was right: a registration form is a coherent artifact,
not a list of independent options. Batch save simplifies the drag
reorder (local only until Save), reduces server round-trips, and gives
the confirm modal a natural scope.

### Cascade of C1 — five dependent files must change in Phase 2

Dropping `event_options` and `registration_options` breaks five
dependents. All five must be updated in the same commit sequence:

| File | Change |
|---|---|
| `app/Models/RegistrationOption.php` | **Delete** — the table is gone |
| `app/Models/EventOption.php` | **Delete** — the table is gone |
| `app/Models/Event.php` | Swap `eventOptions()` relation for `registrationFields()`. Update `relatedRecordCounts()` to drop `event_options` and `registration_options` keys |
| `app/Services/EventDeletion/EventDeletionService.php` | Drop steps 3 (delete registration_options) and 5 (delete event_options) from the ordered delete. The remaining order is: collect registration IDs, delete attendances, delete registrations, delete event |
| `tests/Feature/Events/EventDeletionAdminTest.php` | Rename the test "it removes event options, registrations, registration options and attendances" and drop the event-options assertions. The renamed test becomes "it removes registrations and attendances" |
| `app/Http/Controllers/EventController.php` | `show()` payload sends `related.event_options` and `related.registration_options` — remove those keys |

### File inventory

**New files (14):**

1. `database/migrations/2026_09_25_XXXXXX_create_registration_fields_table.php`
2. `database/migrations/2026_09_25_XXXXXX_drop_event_options_scaffolding.php`
3. `app/Models/RegistrationField.php`
4. `app/Http/Controllers/RegistrationFormController.php`
5. `app/Http/Requests/RegistrationFieldRequest.php`
6. `app/Concerns/RegistrationFieldValidationRules.php`
7. `app/Concerns/HumanNameQualityRules.php` (extracted from `EventValidationRules`)
8. `resources/js/pages/events/registration-form.tsx`
9. `resources/js/components/registration-field-editor.tsx`
10. `resources/js/components/sortable-field-list.tsx`
11. `resources/js/lib/registration-field-types.ts`
12. `resources/js/lib/registration-field-validation.ts`
13. `tests/Feature/Events/RegistrationFormTest.php`
14. `tests/Feature/Events/RegistrationFieldCrudTest.php`

**Modified files (7):**

1. `routes/web.php` — add `events.registration-form.show` and `.update`, remove `events.options.*`
2. `app/Models/Event.php` — `registrationFields()` relation + `relatedRecordCounts()` update
3. `app/Concerns/EventValidationRules.php` — remove `humanNameQualityRules()` (moved to new trait)
4. `app/Http/Controllers/EventController.php` — `show()` payload cleanup
5. `app/Services/EventDeletion/EventDeletionService.php` — ordered delete trimmed
6. `resources/js/pages/events/show.tsx` — "Configure Options" → "Create Registration Form", new href, related-counts block
7. `package.json` + `package-lock.json` — add `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities`

**Deleted files (4):**

1. `app/Http/Controllers/EventOptionController.php`
2. `app/Models/EventOption.php`
3. `app/Models/RegistrationOption.php`
4. `resources/js/pages/events/options.tsx`

**Tests updated (1):**

- `tests/Feature/Events/EventDeletionAdminTest.php`

### New table — `registration_fields`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | FK → `events`, cascade | |
| `label` | string(255) | Human-readable field name |
| `field_type` | string(20) | One of the eight types |
| `options` | JSON nullable | Only for `select`, `radio`, `checkbox` |
| `validation_rules` | JSON nullable | min/max, regex, length — shape TBD during implementation |
| `is_required` | boolean default false | |
| `display_order` | integer | Sort key |
| `created_at`, `updated_at` | timestamps | |

Index on `[event_id, display_order]`.

### Migrations

**Migration 1 — create:**

    Schema::create('registration_fields', function (Blueprint $table) {
        $table->id();
        $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
        $table->string('label');
        $table->string('field_type', 20);
        $table->json('options')->nullable();
        $table->json('validation_rules')->nullable();
        $table->boolean('is_required')->default(false);
        $table->integer('display_order')->default(0);
        $table->timestamps();
        $table->index(['event_id', 'display_order']);
    });

**Migration 2 — drop:**

    Schema::table('registration_options', function (Blueprint $table) {
        $table->dropForeign(['event_option_id']);
    });
    Schema::dropIfExists('registration_options');
    Schema::dropIfExists('event_options');

Foreign key must be dropped before the referenced table. `registration_options` drops first, then `event_options`.

### Controller shape — batch

    class RegistrationFormController extends Controller
    {
        public function show(Event $event): Response
        // Renders events/registration-form with the event + its fields.

        public function update(RegistrationFieldRequest $request, Event $event): RedirectResponse
        // Guard: $event->canEditRegistrationForm()
        // Reads validated fields array
        // In a transaction: delete all event's registration_fields, re-insert from payload with display_order = array index
        // Redirects to show
    }

No per-field store/update/destroy. The batch update replaces everything.

### UI shape

- Top: page header + status pill + locked banner if !canEditRegistrationForm
- Section: Common Fields (read-only list — six fixed fields, no editing)
- Section: Custom Fields
  - Sortable list via `@dnd-kit/sortable`
  - Each row: drag handle, label input, field_type select, options textarea (only for select/radio/checkbox), required toggle, remove button
  - Add Field button appends a new row
- Bottom: Save Registration Form button + confirm modal

When the form locks, drag/inputs/add/remove all disable.

### Block structure — 11 blocks

| Block | Concern |
|---|---|
| 1 | Migrations — create + drop |
| 2 | `RegistrationField` model + `HumanNameQualityRules` extraction |
| 3 | `RegistrationFieldValidationRules` trait + `RegistrationFieldRequest` |
| 4 | `RegistrationFormController` |
| 5 | Routes + `Event` model relation + `EventDeletionService` + `EventController::show` cleanup |
| 6 | Delete four files + test update |
| 7 | `registration-field-types.ts` + `registration-field-validation.ts` |
| 8 | `sortable-field-list.tsx` + `registration-field-editor.tsx` |
| 9 | `registration-form.tsx` page |
| 10 | `events/show.tsx` link relabel + related-counts block |
| 11 | Tests — new + updated |

Estimate: 2–3 sessions.

### Open items for Phase 2 implementation

- `validation_rules` JSON shape — decided during Block 3 based on what the eight field types actually need. No fixed format yet.
- `@dnd-kit` React 19 compatibility — confirmed by the package's peer dep range; will verify during `npm install` in Block 8.
- Form lock enforcement — the controller guard `canEditRegistrationForm()` is the server gate. The frontend also disables controls. Both must agree.

## Changelog addendum

- **2026-09-25** — Phase 2 plan recorded. Decisions Q1–Q5, C1, C3. File inventory 14 new / 7 modified / 4 deleted. Block structure 11 blocks. Cascade of Path A (five dependent files) documented.
