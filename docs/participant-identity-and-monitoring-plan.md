# WDEMS — Participant Identity & Monitoring Development Plan

**Audience:** Whoever executes the identity model and monitoring
build.

**Purpose:** Phased plan for the features described in
`docs/participant-identity.md` and
`docs/registration-monitoring.md`. Each phase has a "Files to cat
first" section, satisfying P1 (read before proposing changes).

**Status:** Planned. Phase 0 has not been executed.

**Related:**
- `docs/participant-identity.md` — the identity model spec
- `docs/registration-monitoring.md` — the monitoring feature spec
- `docs/registration-development-plan.md` — the registration work
  this builds on (Phases 1–6 complete through Phase 3)
- `docs/dev-workflow/README.md` — the workflow protocol (P1–P11)

---

## How to use this plan

Each phase is one coherent unit of work. Every phase ends with:

1. Files read
2. Changes made
3. Tests written or updated
4. Verification run
5. Commit landed with a message sourced from the diff (P9)

Phases ship independently. If a phase stalls, the previous commit is
the recovery point.

This plan is append-only, like the registration plan. Corrections go
in an addendum at the bottom, not by rewriting the phase body.

## Phase structure

| Phase | Goal | Sessions |
|---|---|---|
| 0 | Reconnaissance — read everything, no changes | 1 |
| 1 | Identity schema migrations | 1 |
| 2 | Legacy data backfill + verification | 0.5 |
| 3 | Participant model — normalizer + resolver | 1 |
| 4 | At-least-one rule (request + form builder) | 1 |
| 5 | Controller rewrite + integration tests | 1 |
| 6 | Attendance sidebar + date gate | 0.5 |
| 7 | Monitor landing + per-event pages | 1–2 |
| 8 | Monitor actions (edit, flag, note, export) | 1 |
| 9 | Delete registration with OTP | 1 |
| 10 | Docs convert from spec to reference | 0.5 |

Rough total: 9–11 sessions.

Phases 1–5 are the identity model. Phases 6–9 are monitoring and
adjacent work. Phase 10 is the close.

---

## Phase 0 — Reconnaissance

**Goal:** Read every file the identity and monitoring work will
touch. No changes. Output is a written summary of what each file
currently does, appended to this doc as a `## Phase 0 findings`
section.

### Files to cat

    # Identity model — current state
    app/Models/Participant.php
    app/Http/Controllers/PublicRegistrationController.php
    app/Http/Requests/PublicRegistrationRequest.php
    database/migrations/2026_09_16_160315_create_participants_table.php
    database/migrations/2026_09_16_160321_create_registrations_table.php

    # Form builder — the toggle that must reject both-off
    app/Concerns/RegistrationFieldValidationRules.php
    app/Http/Requests/RegistrationFieldRequest.php
    app/Http/Controllers/RegistrationFormController.php
    resources/js/pages/events/registration-form.tsx

    # Monitoring — existing registration list and event show page
    app/Http/Controllers/RegistrationController.php
    resources/js/pages/registrations/index.tsx
    app/Http/Controllers/EventController.php
    resources/js/pages/events/show.tsx

    # Attendance — the sidebar entry and its gate
    app/Http/Controllers/AttendanceController.php
    app/Models/Attendance.php
    resources/js/pages/events/attendance.tsx
    resources/js/components/app-sidebar.tsx
    resources/js/types/navigation.ts

    # Sidebar pattern for reference
    resources/js/components/nav-main.tsx

    # Routes and providers
    routes/web.php
    app/Providers/AppServiceProvider.php

### Data reconnaissance

Two queries against the current DB, run in Phase 0, before any
migration:

    # Duplicate emails among existing participants
    php artisan tinker --execute="
    \$dupes = \App\Models\Participant::query()
        ->whereNotNull('email')
        ->select('email')
        ->groupBy('email')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('email');
    echo 'duplicate emails: ' . json_encode(\$dupes) . PHP_EOL;
    "

    # Counts by identity field presence
    php artisan tinker --execute="
    echo 'total: ' . \App\Models\Participant::count() . PHP_EOL;
    echo 'with email: ' . \App\Models\Participant::whereNotNull('email')->count() . PHP_EOL;
    echo 'with phone: ' . \App\Models\Participant::whereNotNull('contact_number')->count() . PHP_EOL;
    echo 'with neither: ' . \App\Models\Participant::whereNull('email')->whereNull('contact_number')->count() . PHP_EOL;
    "

Both outputs are recorded in the Phase 0 findings. They determine
whether the UNIQUE migration in Phase 1 can run cleanly, or whether
Phase 2's cleanup is a prerequisite.

### Output

Append a `## Phase 0 findings` section to this file. One paragraph
per file: what it does, what will change, what will not. Include the
two query outputs. No code.

---

## Phase 1 — Identity schema migrations

**Goal:** Add the columns and constraints the identity model needs.

### Files to cat first

    app/Models/Participant.php
    database/migrations/2026_09_16_160315_create_participants_table.php
    database/migrations/2026_09_16_160321_create_registrations_table.php

### Migrations

Four new migrations, ordered:

1. **`add_contact_number_normalized_to_participants_table.php`** —
   adds `varchar(11)` nullable, no index yet.
2. **`add_consent_columns_to_registrations_table.php`** — adds
   `consent_accepted_at` timestamp nullable,
   `privacy_notice_version` varchar(32) nullable.
3. **`add_unique_email_to_participants_table.php`** — adds a UNIQUE
   index on `email` (non-null enforced by MySQL's implicit NULL
   handling).
4. **`add_unique_event_participant_to_registrations_table.php`** —
   adds UNIQUE index on `(event_id, participant_id)`.
5. **`add_unique_normalized_phone_to_participants_table.php`** —
   adds UNIQUE index on `contact_number_normalized`.

The phone-unique migration runs last because it depends on the
backfill from Phase 2. If Phase 0 findings show unparseable phone
values on existing rows, the index addition must wait for Phase 2.

### Ordering caveat

Migrations 3 and 4 fail if the existing data violates the constraint.
Phase 0's queries answer this. If duplicates exist:

- Email dupes → Phase 2's cleanup must run first
- Registration dupes → same
- Phone dupes → backfill normalization may collapse distinct raw
  values into the same normalized form, surfacing new dupes

### Tests

- Migration up/down runs clean on SQLite in-memory
- A participant can be created with NULL email
- Two participants with NULL email coexist (SQLite allows this;
  MySQL also allows this)
- Two participants with the same non-null email fail to insert
- Two registrations for the same participant and event fail to
  insert

### Verify

    php artisan migrate
    php artisan migrate:status | grep -E "contact_number_normalized|consent|unique_email|unique_event|unique_normalized"
    php artisan test tests/Feature/Events
    php artisan test tests/Feature/Public
    php artisan test tests/Feature/Auth

---

## Phase 2 — Legacy data backfill

**Goal:** Normalize existing `contact_number` values into
`contact_number_normalized`, verify no collisions, then enable the
UNIQUE index from Phase 1.

### Prerequisite

Phase 1's migration 5 (unique on normalized phone) is deferred until
this phase completes, if Phase 0 showed unparseable or colliding
values.

### Files to cat first

    app/Models/Participant.php
    database/migrations/2026_10_02_120000_add_contact_number_normalized_to_participants_table.php

### The backfill migration

One migration with a `DB::table('participants')->orderBy('id')->chunk(...)` loop:

- For each participant with a non-null `contact_number`:
  - Normalize using the same rules as the model mutator (rules are
    duplicated in the migration, since it cannot call the model)
  - Write the result to `contact_number_normalized`
- Log any rows where normalization returns null
- Log any rows where two participants normalize to the same value

### Verify before the unique index lands

    php artisan tinker --execute="
    \$nulls = \App\Models\Participant::whereNull('contact_number_normalized')
        ->whereNotNull('contact_number')
        ->count();
    echo 'unparseable: ' . \$nulls . PHP_EOL;

    \$dupes = \App\Models\Participant::query()
        ->whereNotNull('contact_number_normalized')
        ->select('contact_number_normalized')
        ->groupBy('contact_number_normalized')
        ->havingRaw('COUNT(*) > 1')
        ->get();
    echo 'collisions: ' . \$dupes->count() . PHP_EOL;
    foreach (\$dupes as \$d) {
        echo '  ' . \$d->contact_number_normalized . PHP_EOL;
    }
    "

If `unparseable > 0` or `collisions > 0`, stop. Do not add the
unique index. Report the offending rows and decide the cleanup in a
follow-up phase.

If both are zero, run migration 5 from Phase 1.

### Tests

- Backfill migration runs clean
- Post-backfill, `contact_number_normalized` is populated for every
  row that had a parseable phone
- UNIQUE index accepts the backfilled data

### Verify

    php artisan migrate
    php artisan migrate:status | grep unique_normalized
    php artisan test tests/Feature/Public

---

## Phase 3 — Participant model

**Goal:** Add the phone normalizer and the `resolveFrom` method to
the `Participant` model.

### Files to cat first

    app/Models/Participant.php
    app/Http/Controllers/PublicRegistrationController.php

### Changes

- **`setContactNumberAttribute`** — a mutator that stores both the
  raw value and the normalized form. Runs on every write.
- **`resolveFrom(?string $email, ?string $rawPhone, array $attrs)`** —
  the resolver. Returns an existing participant on identity match, or
  creates a new one. Discards `$attrs` on match (first-write-wins).
- **`IdentityRequiredException`** — a new exception class in
  `App\Exceptions`, thrown when both email and normalized phone are
  empty.
- **`normalizeContactNumber`** — a private static method implementing
  the rules from `participant-identity.md` §5.

### Tests

- Mutator: each of the four accepted formats writes the same
  normalized value
- Mutator: unparseable input writes null to normalized
- Mutator: original raw value is preserved
- Resolver: email match → returns existing participant
- Resolver: phone match → returns existing participant
- Resolver: email present, phone differs → matches on email (email
  wins)
- Resolver: no email, phone match → returns existing participant
- Resolver: no match → creates new participant
- Resolver: first-write-wins — second call with different name does
  not update
- Resolver: neither email nor phone → throws
  `IdentityRequiredException`

### Verify

    php artisan test tests/Unit/Services/ParticipantIdentityTest.php
    php artisan test tests/Feature/Public

---

## Phase 4 — At-least-one rule

**Goal:** Enforce the "at least one of email or contact number"
constraint on both the public form and the form builder.

### Files to cat first

    app/Http/Requests/PublicRegistrationRequest.php
    app/Concerns/RegistrationFieldValidationRules.php
    app/Http/Requests/RegistrationFieldRequest.php

### Changes

**Public form** — `PublicRegistrationRequest::rules()` gets a
form-level rule. Implementation is a `withValidator` after-callback
that checks both fields, since the rule depends on the normalized
value of phone, which is not known during the standard rule pass.

**Form builder** — `RegistrationFieldValidationRules` gains a
`validateCommonFieldRequirementsIdentity` method. Registered in
`RegistrationFieldRequest::withValidator()`. Rejects when both
`common_field_requirements.email === false` and
`common_field_requirements.contact_number === false`.

### Tests

- Public form: email only → accepted
- Public form: phone only → accepted
- Public form: both → accepted
- Public form: neither → rejected with the new message
- Public form: unparseable phone only → rejected (treated as no
  phone)
- Form builder: both toggles off → rejected
- Form builder: email on, phone off → accepted
- Form builder: email off, phone on → accepted
- Form builder: both on → accepted

### Verify

    php artisan test tests/Feature/Public
    php artisan test tests/Feature/Events

---

## Phase 5 — Controller rewrite

**Goal:** Replace the current inline `firstOrCreate` in
`PublicRegistrationController::store()` with a call to
`Participant::resolveFrom()`.

### Files to cat first

    app/Http/Controllers/PublicRegistrationController.php
    app/Models/Participant.php

### Changes

The store method's transaction body:

- Reads `email` and `contact_number` from `$validated`
- Calls `Participant::resolveFrom($email, $contact_number, $attributes)`
- The `attributes` array carries `first_name`, `last_name`, `age`,
  `address`, and any other descriptive fields
- D5 duplicate check runs before the transaction, unchanged — it
  queries on email, still valid

### Tests

- A submission with email only creates or reuses a participant,
  keyed on email
- A submission with phone only creates or reuses a participant,
  keyed on normalized phone
- Two submissions with the same email but different names reuse the
  same participant row
- Two submissions with the same phone but different names reuse the
  same participant row
- The D5 duplicate check still fires when email is required

### Verify

    php artisan test tests/Feature/Public
    php artisan test tests/Feature/Events
    php artisan test tests/Feature/Auth

---

## Phase 6 — Attendance sidebar and date gate

**Goal:** New sidebar entry for Attendance. Restrict the
`events/{event}/attendance` route to events whose date has arrived.

### Files to cat first

    app/Http/Controllers/AttendanceController.php
    app/Models/Event.php
    resources/js/pages/events/attendance.tsx
    resources/js/components/app-sidebar.tsx
    resources/js/types/navigation.ts
    resources/js/components/nav-main.tsx
    routes/web.php

### Changes

**Sidebar** — a new entry between Registration and Events. Icon
choice deferred to implementation. Active state on `/attendance`.

**New page** — `resources/js/pages/attendance/index.tsx`. Lists
events eligible for attendance:

- `event_date <= today`
- `status IN (registration_closed, ongoing, completed)`

Grouped by Today and Recent (last 7 days). Each row shows event
name, date, registered count, marked count. Clicking goes to
`/events/{event}/attendance`.

**New route** — `GET /attendance` →
`AttendanceController::index`.

**Date gate** — a new method on `Event`:
`canRecordAttendanceToday()`. Returns true when
`canRecordAttendance()` **and** `event_date <= today`. Replaces
`canRecordAttendance()` in the attendance show method.

Error when gate fails: *"Attendance can only be recorded on the
event day."*

### Tests

- Sidebar shows the Attendance entry
- `/attendance` lists events with past or today dates
- `/attendance` does not list future events
- `/events/{event}/attendance` returns 403 for a future event
- `/events/{event}/attendance` returns 200 for a today event

### Verify

    php artisan test tests/Feature/Events
    npx tsc --noEmit
    Browser: sidebar shows Attendance; page renders; future event's
    attendance page shows the error

---

## Phase 7 — Monitor landing and per-event pages

**Goal:** Two pages. The landing lists open events. The per-event
shows the live feed.

### Files to cat first

    app/Http/Controllers/RegistrationController.php
    resources/js/pages/registrations/index.tsx
    app/Models/Registration.php
    app/Models/Participant.php
    routes/web.php
    app/Http/Controllers/EventController.php

### Changes

**New controller** — `RegistrationMonitorController`. Two actions:

- `index()` — lists events with `status = registration_open`,
  sorted by most recent submission
- `show(Event $event)` — the per-event feed, gated to
  `status IN (registration_open, registration_closed)` (so the
  post-close view is reachable)

**Redirect on open.** `EventController::openRegistration()` returns
`redirect()->route('registration-monitor.show', $event)` instead of
the event show page.

**Queue link.** `registrations/index.tsx` gets a Monitor link on
each open-event row.

**Landing page** — `resources/js/pages/registrations/monitor/index.tsx`.
Card per open event. Empty state. Links to per-event.

**Per-event page** —
`resources/js/pages/registrations/monitor/show.tsx`. Header with
stats strip, filter chips, feed of cards, ⋯ menu (stubbed in this
phase — actions land in Phase 8). Polling every 10s with visibility
pause. Manual refresh button.

### Tests

- Landing renders for staff
- Landing shows open events sorted by most recent submission
- Landing empty state when no open events
- Per-event renders for staff
- Per-event shows only this event's registrations
- Per-event shows NEW/RETURNING badges correctly
- Per-event filters by chip
- Post-close, per-event renders but actions are disabled
- Open Registration action redirects to the monitor show page

### Verify

    php artisan test tests/Feature/Public
    php artisan test tests/Feature/Events
    npx tsc --noEmit
    Browser: open an event, land on the monitor, see the feed

---

## Phase 8 — Monitor actions

**Goal:** The five ⋯ menu actions.

### Files to cat first

    resources/js/pages/registrations/monitor/show.tsx
    app/Http/Controllers/RegistrationMonitorController.php
    app/Models/Registration.php

### Changes

**Schema** — one migration:

- `registrations.flagged_at` timestamp nullable
- `registrations.notes` text nullable

**Controller actions** — four new endpoints on the monitor
controller:

- `PUT /registrations/{registration}/participant` — edit participant
- `POST /registrations/{registration}/flag` — toggle flag
- `PUT /registrations/{registration}/notes` — save note
- `GET /events/{event}/registrations/export` — CSV download

Delete is deferred to Phase 9.

**Frontend** — the ⋯ menu wired. Edit participant modal, note
modal. Flag is a direct call. Export is a plain link.

### Tests

- Edit participant updates name/email/phone
- Edit participant with a duplicate email fails
- Flag toggle sets and clears `flagged_at`
- Notes save and appear on the card
- CSV export includes all common fields + one column per custom
  field

### Verify

    php artisan test tests/Feature/Public
    npx tsc --noEmit
    Browser: each action works from the monitor

---

## Phase 9 — Delete registration with OTP

**Goal:** Mirror the event-deletion OTP flow for registrations.

### Files to cat first

    app/Http/Controllers/EventDeletionController.php
    app/Services/EventDeletion/EventDeletionOtpService.php
    app/Services/Otp/OtpCode.php
    app/Mail/EventDeletionOtpMail.php
    routes/web.php

### Changes

- New `RegistrationDeletionController`
- New `RegistrationDeletionOtpService` (fork of the event version,
  keyed on `registration_id`)
- New `RegistrationDeletionOtpMail` + Blade template
- New routes mirroring `events.deletion.*`
- New React component `RegistrationDeletionOtpDialog` — fork of
  `event-deletion-otp-dialog.tsx`
- Delete action wired to the monitor's ⋯ menu

Delete operation: removes the `Registration` row. Cascades to
`field_responses` and `attendances`. `Participant` row is never
touched — shared across events.

### Tests

- Admin deletes directly without OTP
- Staff requests OTP, receives it, verifies, delete succeeds
- Wrong OTP rejected, registration survives
- Lockout after 5 attempts
- Delete cascades correctly (responses and attendances removed,
  participant preserved)

### Verify

    php artisan test tests/Feature/Public
    php artisan test tests/Feature/Events
    Browser: delete a registration from the monitor as staff

---

## Phase 10 — Docs close

**Goal:** Convert the two specs from Planned to Implemented. Sweep
stale mentions. Final pass.

### Files to cat first

    docs/participant-identity.md
    docs/registration-monitoring.md
    docs/registration.md
    docs/known-issues.md
    docs/architecture.md

### Changes

- **`participant-identity.md`** — status block at the top: Planned →
  Implemented. Reference block with the actual files, trap list
  (first-write-wins, phone normalization edge cases, shared emails),
  and glossary.
- **`registration-monitoring.md`** — same treatment.
- **`registration.md`** — the §Status block updated to point at the
  new docs. The "at least one required" rule recorded.
- **`known-issues.md`** — ISSUE-008 closed (no-email dedup
  limitation is now solved by the identity model). Any new issues
  discovered during Phase 1–9 appended.
- **`architecture.md`** — the data model section updated to include
  the new columns and constraints.

### Verify

    php artisan test
    npx tsc --noEmit
    All doc cross-references resolve

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
- **P11** — before any migration that drops or alters, grep for
  every reader of the affected table or column

If a phase spans multiple files and any file has not been read this
session, that phase is blocked until it has been read.

## Open items carried into Phase 1

- **Phase 0 findings.** Not yet written. The duplicate-email and
  data-presence queries must run before Phase 1 begins. Their output
  determines whether Phase 2 is a prerequisite for the UNIQUE index
  migrations.
- **Migration ordering.** If Phase 0 shows existing duplicate
  emails, the unique-email migration must be reordered after a
  cleanup migration. Same for the `(event_id, participant_id)`
  unique — if the current table already has duplicates, they block
  the constraint.
- **Phone format coverage.** The four accepted formats are the
  minimum. If Phase 0 shows phones in other formats (landlines,
  international), decide whether to add formats or accept null for
  those rows.
- **`IdentityRequiredException` placement.** New class in
  `App\Exceptions`. Confirm no such namespace exists — the
  registration build did not create one.

## Deferred to a future plan

- **Participant history page.** Linked from the RETURNING badge.
  Stubbed in Phase 7, built later.
- **Merging participant records.** Two rows discovered to be the
  same person. Manual merge UI is out of scope for this plan.
- **Phase 3.5 from the registration plan** — per-event Privacy
  Notice and Terms, consent versioning. Independent of identity and
  monitoring.

## Changelog

- **2026-10-02** — File created. Phased plan for participant
  identity and registration monitoring. Ten phases. Open items
  carried into Phase 1 recorded.

---

## Phase 0 findings (2026-10-02)

Reconnaissance of the identity-relevant files. The form builder and
monitoring files will be read at the phase where they are needed
(Phase 4 and Phase 7), not preemptively — holding 20 file contexts
across 8 sessions is more drift than coverage.

### Data queries — results

    duplicate emails: []      (zero duplicates)
    total participants: 1
    with email: 0
    with phone: 0
    with neither: 1

The single existing participant is the test submission from the
Phase 3 verification pass. Email NULL, contact_number NULL. It
predates the at-least-one rule that Phase 4 introduces. The rule is
forward-looking — it applies at submission time, not retroactively.
This row is grandfathered and harmless.

Consequence for Phase 1: no duplicate-email cleanup migration is
required. The unique-email index can land in a single migration.
Consequence for Phase 2: the backfill loop iterates zero rows. The
migration still runs (the column is populated), but there is nothing
to log.

### `app/Models/Participant.php`

Simple model. Six fields: `first_name`, `last_name`, `contact_number`,
`email`, `age`, `address`. Fillable includes all six. No mutators, no
identity logic, no phone normalization. `age` casts to integer.
Relations: `registrations(): HasMany`. One helper: `fullName()`.

Phase 3 adds: the `setContactNumberAttribute` mutator, the
`resolveFrom` resolver, the `normalizeContactNumber` helper, and
new fillable entries for the extra columns. `fullName()` and the
relations stay unchanged.

### `app/Http/Controllers/PublicRegistrationController.php`

Two actions. `show()` renders `registrations/public` with the event
payload, the custom field list, the resolved common-field
requirements, the submit URL, and the session's `success` flash.
`store()` runs the D5 duplicate check (only when email is provided),
opens a transaction, does a `firstOrCreate` on
`(email, first_name, last_name)` or a plain `create()` when email is
absent, then creates the `Registration` and one
`RegistrationFieldResponse` per submitted response.

Phase 5 replaces the `firstOrCreate` and the conditional branch with
a single call to `Participant::resolveFrom()`. The D5 check stays —
it queries on email, unaffected by the resolver. The transaction
boundary stays.

### `app/Http/Requests/PublicRegistrationRequest.php`

`rules()` is dynamic — `email`, `contact_number`, and `address`
each go through a `commonFieldRules()` helper that reads
`Event::isCommonFieldRequired()` and emits `required` or `nullable`
as the leading rule. Format rules still apply when a value is
provided. `withValidator()` runs a two-pass check on custom field
responses.

Phase 4 adds: an identity check in `withValidator()` — if both
`email` and `contact_number` are blank (after normalization for the
phone), add a form-level error. The existing two-pass logic is
untouched.

### `database/migrations/2026_09_16_160315_create_participants_table.php`

Six columns plus timestamps. `first_name` and `last_name` are
`string` (255) NOT NULL. `contact_number` is `string` (255) NOT NULL
in the base migration — flipped to nullable by
`2026_10_02_110000_make_contact_number_nullable_on_participants_table.php`
(landed in the registration Phase 3 block C). `email` is
`string` (255) nullable, indexed, **not unique**. `age` is
`unsignedTinyInteger` nullable. `address` is `string` (255) nullable.

Two indexes: composite `(last_name, first_name)` and single `email`.
Phase 1 adds a unique index on `email` — MySQL accepts both a
non-unique and a unique index on the same column; the non-unique
index can stay.

### `database/migrations/2026_09_16_160321_create_registrations_table.php`

Seven columns plus timestamps. `event_id` FK to events, CASCADE.
`participant_id` FK to participants, RESTRICT. `registration_date`
`dateTime` with `useCurrent()`. `registration_status` string with
default `pending`, indexed. Timestamps.

**The migration already declares `$table->unique(['event_id', 'participant_id'])`.**

This is a plan deviation — Phase 1 migration #4
(`add_unique_event_participant_to_registrations_table.php`) is not
needed. The constraint exists. The plan's table of Phase 1
migrations is reduced from five to four.

### Plan deviations discovered during Phase 0

**D-1 — Phase 1 migration #4 is redundant.** The unique constraint
on `(event_id, participant_id)` already exists in the base migration.
Removed from Phase 1 scope.

**D-2 — Phase 2 is a no-op on current data.** The backfill loop
iterates zero rows. The migration still runs (as a no-op) so the
phase is completed in the log, but there is no cleanup to do.

**D-3 — No `google_form_response_id` column exists on
`registrations`.** `docs/architecture.md` claims
`UNIQUE (event_id, google_form_response_id)` on that table. The
migration shows no such column. This is a stale doc claim. The
correction goes in `architecture.md` at the next doc pass, not in
this plan. Noted here so the next reader does not go looking for
the column.

**D-4 — `contact_number` nullability was flipped mid-Phase-3.** The
base migration declares it NOT NULL, but
`2026_10_02_110000_make_contact_number_nullable_on_participants_table.php`
changed it to nullable. This is not a deviation from this plan — it
happened during the registration work. Recorded here so the reader
of the participants migration is not confused.

### Data cleanup decision

The single existing participant (email NULL, contact_number NULL)
is grandfathered. No cleanup migration. If it causes confusion in
later testing, it can be deleted manually:
`\App\Models\Participant::truncate()` — cascades to registrations
and their responses.

### Files read this phase

    app/Models/Participant.php
    app/Http/Controllers/PublicRegistrationController.php
    app/Http/Requests/PublicRegistrationRequest.php
    database/migrations/2026_09_16_160315_create_participants_table.php
    database/migrations/2026_09_16_160321_create_registrations_table.php

Five of the twenty files the plan listed. The remaining fifteen are
deferred to the phase where they are needed:

    Phase 4   app/Concerns/RegistrationFieldValidationRules.php
              app/Http/Requests/RegistrationFieldRequest.php
              app/Http/Controllers/RegistrationFormController.php
              resources/js/pages/events/registration-form.tsx

    Phase 6   app/Http/Controllers/AttendanceController.php
              app/Models/Attendance.php
              resources/js/pages/events/attendance.tsx
              resources/js/components/app-sidebar.tsx
              resources/js/types/navigation.ts
              resources/js/components/nav-main.tsx

    Phase 7   app/Http/Controllers/RegistrationController.php
              resources/js/pages/registrations/index.tsx
              app/Http/Controllers/EventController.php
              resources/js/pages/events/show.tsx
              routes/web.php

This is a deviation from the plan's "read everything in Phase 0"
structure. Rationale: reading fifteen files that will not be touched
until six phases later is drift risk, not coverage. Each phase's
"Files to cat first" section is the authoritative list for that
phase.

## Changelog addendum

- **2026-10-02** — Phase 0 findings recorded. Four deviations
  discovered: D-1 (unique constraint already exists), D-2 (backfill
  is a no-op), D-3 (stale `google_form_response_id` claim), D-4
  (mid-phase contact_number nullability flip). File-read list
  narrowed from twenty to five for the identity-side work.

---

## Phase 8 complete (2026-10-10)

The status block in this doc lists Phase 8 as not yet started. It
has shipped.

### What shipped

| Deliverable | Status |
|---|---|
| Migration: `flagged_at`, `notes` | ✅ |
| Edit participant endpoint | ✅ |
| Flag toggle endpoint | ✅ |
| Note save endpoint | ✅ |
| CSV export endpoint | ✅ |
| Actions menu on monitor cards | ✅ |
| Flagged filter chip | ✅ |

### What did not ship

**Delete registration with OTP.** Deferred to Phase 9. The spec for
Phase 8 named it as part of the actions menu, but the delete flow
needs the OTP subsystem that Phase 9 builds. The three endpoints
that shipped here — edit, flag, note — cover the monitor's daily
needs. Delete lands when Phase 9 lands.

### Files

    database/migrations/2026_10_10_140000_add_flagged_at_and_notes_to_registrations_table.php
    app/Http/Requests/EditParticipantRequest.php
    app/Http/Requests/FlagRegistrationRequest.php
    app/Http/Requests/SaveRegistrationNoteRequest.php
    app/Http/Controllers/RegistrationMonitorController.php
    resources/js/components/edit-participant-dialog.tsx
    resources/js/components/note-dialog.tsx
    resources/js/pages/registrations/monitor/show.tsx
    tests/Feature/Events/RegistrationMonitorActionsTest.php

### Cross-references

- `docs/registration-monitoring.md` — the spec, with a "Phase 8
  shipped" addendum
- `docs/progress.md` — dated entry for this session

---

## Phase 8 complete (2026-10-10)

The status block in this doc lists Phase 8 as not yet started. It
has shipped.

### What shipped

| Deliverable | Status |
|---|---|
| Migration: `flagged_at`, `notes` | ✅ |
| Edit participant endpoint | ✅ |
| Flag toggle endpoint | ✅ |
| Note save endpoint | ✅ |
| CSV export endpoint | ✅ |
| Actions menu on monitor cards | ✅ |
| Flagged filter chip | ✅ |

### What did not ship

**Delete registration with OTP.** Deferred to Phase 9. The spec for
Phase 8 named it as part of the actions menu, but the delete flow
needs the OTP subsystem that Phase 9 builds. The three endpoints
that shipped here — edit, flag, note — cover the monitor's daily
needs. Delete lands when Phase 9 lands.

### Files

    database/migrations/2026_10_10_140000_add_flagged_at_and_notes_to_registrations_table.php
    app/Http/Requests/EditParticipantRequest.php
    app/Http/Requests/FlagRegistrationRequest.php
    app/Http/Requests/SaveRegistrationNoteRequest.php
    app/Http/Controllers/RegistrationMonitorController.php
    resources/js/components/edit-participant-dialog.tsx
    resources/js/components/note-dialog.tsx
    resources/js/pages/registrations/monitor/show.tsx
    tests/Feature/Events/RegistrationMonitorActionsTest.php

### Cross-references

- `docs/registration-monitoring.md` — the spec, with a "Phase 8
  shipped" addendum
- `docs/progress.md` — dated entry for this session
