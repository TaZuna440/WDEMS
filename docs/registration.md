# WDEMS — Registration

**Audience:** Whoever implements or modifies the participant registration
subsystem.

**Purpose:** Describe the feature to be built. This is a spec, not a
reference — nothing here describes current code. When the feature lands,
this doc converts to a reference (like `authentication.md`).

**Status:** Planned. No code exists yet.

**Related:** `docs/registration-development-plan.md` — the phased build plan.

---

## 1. What this replaces

The current event workflow has a **Configure Options** step that writes
`event_options` rows (`{type, name, value, required, available}`). Those
rows modeled the schema of a Google Form that no longer exists. The
workflow was designed when "open registration" meant "publish the Google
Form URL". The integration is gone; the step remains and produces dead
data.

Registration replaces it end-to-end.

- **Configure Options** → **Create Registration Form**
- **`configured` state** → dropped
- **`event_options` table** → extended, reused for form-field definitions
- **Open Registration button** → publishes a public URL

---

## 2. The workflow after event creation

    Event created (draft)
        │
        │  Organizer opens the event show page
        │  "Create Registration Form" is the primary forward action
        ▼
    Create Registration Form
        │  Common fields (fixed) + custom fields (editable)
        │  Save → confirm modal → form is saved
        │  Form remains EDITABLE until Open Registration
        ▼
    Open Registration button click
        │  → second confirm modal
        ▼
    registration_open
        │  Public URL live: /r/{slug}
        │  Anyone can submit
        │  Form is now LOCKED
        │  Auto-closes at registration_end (if set) OR manual close
        ▼
    registration_closed
        │  Attendance page shows all submissions
        │  Walk-ins can be added on-site
        │  Pre-registered and walk-in rows labeled
        ▼
    ongoing → completed

### Two confirmation modals, not one

- Modal at **Save Form**: "Save this form? You can still edit it until
  you open registration."
- Modal at **Open Registration**: "This publishes the public
  registration link. The form can no longer be edited. Continue?"

These are different surfaces with different stakes. The form locks at
Open Registration, not at Save.

---

## 3. State machine change

Drops `configured`. New chain:

    draft → registration_open → registration_closed → ongoing → completed

    (cancelled reachable from any non-terminal state)

`EventWorkflow::configure()` becomes unused. The route
`POST /events/{id}/configure` is removed. The "Mark as Configured"
button on the current options page disappears.

---

## 4. Common participant fields

Every registration includes these fields, always. Required unless marked
optional.

| Field | Type | Required | Notes |
|---|---|---|---|
| `first_name` | text | yes | |
| `last_name` | text | yes | |
| `email` | email | yes | Unique per event — duplicate submissions rejected |
| `contact_number` | text | yes | Mobile number for on-site coordination |
| `age` | number | yes | 1–120 |
| `address` | text | no | Free-form |

These map 1:1 to the existing `participants` table columns. No migration
needed.

Common fields are **not** editable in the form builder — they are
structural.

---

## 5. Custom fields (form builder)

The organizer adds custom fields on the Create Registration Form page.
Each custom field has:

| Attribute | Notes |
|---|---|
| `label` | Human-readable, e.g. "Shirt Size" |
| `type` | One of: `text`, `number`, `email`, `select`, `checkbox`, `radio`, `date` |
| `options` | Only for `select`, `radio`, `checkbox` — JSON array of strings |
| `required` | boolean |
| `validation_rules` | JSON — min length, max length, regex, min/max value |
| `display_order` | Integer, controls rendering order |

### Schema

The existing `event_options` table is close but not sufficient — it
lacks `type` and `validation_rules`. Extend it:

    ALTER TABLE event_options ADD COLUMN field_type VARCHAR(20) DEFAULT 'text';
    ALTER TABLE event_options ADD COLUMN validation_rules JSON NULL;
    ALTER TABLE event_options ADD COLUMN display_order INT DEFAULT 0;

`option_value` becomes the stored answer slot in `registration_options`.

### Validation contract

Custom field rules mirror `EventValidationRules` conventions:

- Every rule on the server has a mirror on the client
  (`resources/js/lib/registration-validation.ts`)
- Required-vs-optional checked before format checks, so the user sees
  the most specific error
- The organizer's typed `label` uses the same quality rules as
  `humanNameQualityRules()` — reject keyboard mashing

---

## 6. Open Registration — the public surface

When the organizer clicks Open Registration:

1. Server generates `registration_slug` (8 chars, random, unique per
   `events` table)
2. Status changes to `registration_open`
3. `registration_form_locked_at` set to `now()`
4. Public URL available at `/r/{slug}`

### Public URL

- **No auth.** Anyone with the link can access
- **CSRF** on submission — standard Laravel token
- **Rate limit** — 5 submissions per IP per minute
- **Idempotency** — submit creates `participant` + `registration`. If
  `email` already exists for the event, submission is rejected with a
  friendly message
- **Not guessable** — 8 chars from 32-char alphabet

### Close

Two ways:

1. **Auto-close** — if `registration_end` is set, POST rejects after
   that timestamp. Server-side check.
2. **Manual close** — organizer clicks "Close Registration" on the show
   page. Status changes to `registration_closed`.

### Public page content

- Event name, date, time, venue, address
- Map link (if `venue_map_url` set)
- Course link (if `course_url` set)
- Description
- The registration form
- Success message after submission (same page, no redirect)

The public page uses a dedicated layout — no app shell, no sidebar, no
auth header.

---

## 7. Attendance

After registration closes, the attendance page shows every submission.

### Row types

- **Pre-registered** — submitted via the public form.
  `registrations.source = 'form'`
- **Walk-in** — added on-site by the organizer.
  `registrations.source = 'walk_in'`

Both labeled clearly in the UI. No color-coding in v1.

### Table

- Paginated, 50 rows per page
- Server-side search by `first_name`, `last_name`, `email`,
  `contact_number`
- Filter chips: All / Pre-registered / Walk-in
- Columns: Name, Contact, Source badge, Attendance status, Actions

### Walk-in flow

"Add Walk-in" opens a small form:

- Common fields — email optional for walk-ins
- Custom fields — organizer fills what applies
- On submit → creates `participant` + `registration` with
  `source = 'walk_in'` + marks attendance

### Attendance marking

Each row has a "Mark Present" toggle. When marked:

- `attendances` row created via the existing model
- `attendance_time = now()`
- `recorded_by = current user`

The schema exists. The page rebuild is what is new.

### Scale

Hundreds of rows without lag:

- Pagination caps the client payload at 50 rows
- Search queries use indexed columns (`email`, `contact_number`,
  `event_id`)
- Eager loading: `registration` → `participant` in a single query

---

## 8. Delete registration (with OTP)

Mirrors the staff event-deletion flow.

- **Admin** — direct delete, no OTP
- **Staff** — OTP email verification required

New service `RegistrationDeletionOtpService` (keyed on
`registration_id`). Same constants as `EventDeletionOtpService`:

- `CODE_TTL_SECONDS = 600`
- `RESEND_COOLDOWN_SECONDS = 60`
- `MAX_ATTEMPTS = 5`

New `RegistrationDeletionOtpMail` mailable and Blade template.

Delete operation is a single row (`registrations`), cascading to
`registration_options` and `attendances`. `participants` are not
touched.

---

## 9. Out of scope for v1

- QR codes for check-in
- Bulk CSV import of pre-registered participants
- Email confirmations to registrants
- Waitlists
- Payments
- Conditional form logic ("if age < 18, show guardian")
- Multi-language public form
- Attendee-facing logins
- Live attendance dashboard
- CSV export
- Email digest to organizer per registration

---

## 10. Open items

Recorded so they are not lost. Each needs a decision before
implementation.

- **`event_options` extension vs new table** — spec assumes extension.
  Confirm during Phase 2.
- **Walk-in email** — required in common fields; walk-ins may not have
  one. Should the walk-in flow make email optional?
- **Custom field types** — the list in §5 is v1. Anything else needed
  at launch?
- **Reset form** — form is editable until Open. Is there an explicit
  "Reset form to empty" action, or only delete-event-and-restart?
- **Public page styling** — WDEMS brand or minimal? Spec assumes
  minimal.
- **Slug collision handling** — regenerate on collision. Not automatic
  in the spec; explicit.

---

## Changelog

- **2026-09-25** — File created. Spec for the registration feature.

---

## Corrections

### §5 field types — `textarea` added

The field type list in §5 originally read:

    text, number, email, select, checkbox, radio, date

A `textarea` type is now included. Use case: long-form text — dietary
restrictions, medical notes, "anything else we should know." Rendered
as a `<textarea>` with a character counter, not a single-line input.

Current v1 list:

    text, textarea, number, email, select, checkbox, radio, date

### §5 example use cases — distance removed

Earlier drafts of the Phase 2 discussion (not this spec — see
`docs/registration-development-plan.md`) listed "distance preference"
as an example for a `select` field. That example is discarded. The
event already declares `distance_value` and `distance_unit`, so every
participant does the same distance. Multi-distance events are a
separate future feature, not v1.

### Validation

The spec §5 statement "custom field rules mirror `EventValidationRules`
conventions" stands. The concrete input strategy — HTML5 types,
autocomplete hints, phone regex, inline hints, blur-time validation,
client-server mirror — is recorded in
`docs/registration-development-plan.md` under "Validation strategy —
improved inputs."

AI-assisted validation is out of scope for v1. See the same section
for rationale.

## Changelog addendum

- **2026-09-25** — `textarea` added to §5 field types. Distance
  example removed. Validation strategy cross-referenced.

---

## Status — Implemented (2026-10-02)

The §Status line at the top of this document reads:

    **Status:** Planned. No code exists yet.

That was accurate on 2026-09-25 when this file was written. It is no
longer accurate.

The registration subsystem is live end-to-end. Full commit history
and design decisions are in
`docs/registration-development-plan.md` under "Phase 3 complete".
This block records the minimal set of corrections needed to make the
spec usable as a map of what exists. It is not the full
spec-to-reference conversion described in the top-of-file note — that
conversion is deferred to a future session.

### What exists

- Public submission at `/r/{slug}` — anonymous, rate-limited,
  gated by `EnsureRegistrationIsOpen` middleware.
- Form builder at `/events/{event}/registration-form` — the six
  common fields plus up to five custom fields, drag-to-reorder,
  batch save via `PUT`.
- Per-event required/optional toggles for `email`, `contact_number`,
  and `address`.
- `registration_fields` table for custom fields;
  `registration_field_responses` for answers.
- `events.registration_slug` (8-char, generated at Open
  Registration), `events.registration_form_saved_at` (the sole
  "form exists" signal), `events.registration_common_field_requirements`
  (JSON, nullable).

### Corrections to the spec

**§1 — `event_options` was not extended.** The spec proposed
ALTER TABLE on `event_options`. It was rejected in Phase 2 (D1 in
the plan) in favor of a new `registration_fields` table.
`event_options` and `registration_options` were dropped entirely in
the same phase. Rows have no live readers.

**§2 — no `configured` state.** The spec's workflow diagram shows
`Event created (draft)` → `Create Registration Form` → `configured`
→ `open registration`. The `configured` state was removed in Phase 1
of the plan. The chain is now `draft → registration_open →
registration_closed → ongoing → completed`.

**§6 — no `registration_form_locked_at` column.** The spec proposed
a dedicated lock timestamp. D2 of the Phase 3 plan chose
`registration_start` as the sole lock signal. The column does not
exist and no code reads it.

**§6 — the slug is 8 characters from a 31-character alphabet.**
Excludes the confusables I, L, O, 0, 1. Retry loop in
`EventController::generateUniqueSlug()` throws after three collisions
(effectively unreachable).

**§8 — Delete registration with OTP is not implemented.** It is
Phase 5 of the plan. Not built.

### Sections that match the shipped code

- §4 (Common participant fields) — accurate, except that required-
  ness of email, contact_number, and address is now per-event.
- §5 (Custom fields — types, options, validation) — accurate in
  spirit. The eight types are correct. The `validation_rules` JSON
  shape remained freeform at Phase 3 close; no schema enforcement
  beyond "must be an array."
- §7 (Attendance) — not yet implemented; Phase 4.
- §9 (Out of scope) — accurate.
- §10 (Open items) — some are now decided, some remain.

### See also

- `docs/registration-development-plan.md` — Phase 3 complete block,
  addendum for common-field-requirements
- `docs/known-issues.md` — ISSUE-007 (address width),
  ISSUE-008 (no-email dedup)
- `docs/authentication.md` — for the pattern this file will follow
  when it fully converts to a reference

## Changelog addendum

- **2026-10-02** — Status block added. File no longer claims "no
  code exists." Corrections for §1 (table), §2 (state machine),
  §6 (lock signal + slug length), §8 (deletion). Full
  spec-to-reference conversion deferred.

---

## Corrections — Status block (2026-10-13)

The `## Status — Implemented (2026-10-02)` block above reads:

> The registration subsystem is live end-to-end.

That was accurate on 2026-10-02. It is no longer accurate.

Commit `4394986` (2026-10-13, "feat(registration): verify participant
contacts before finalizing intake") rewrote the public submission
path. The flow at `/r/{slug}` is now three-phase:

1. **Submit.** The form posts to `PublicRegistrationController::store`.
   No Participant or Registration is created. A
   `RegistrationVerificationChallenge` row is created and an encrypted
   HttpOnly cookie is set scoped to `/r/{slug}`.

2. **Verify.** The user is redirected to
   `/r/{slug}/verification`. Required email is verified by an OTP
   delivered through Resend. Required phone is verified by a
   staff-generated code communicated externally.

3. **Finalize.** Once every required channel is verified, the user
   clicks **Complete registration**. The final Participant,
   Registration, and `RegistrationFieldResponse` rows commit in one
   transaction.

### New columns and table (2026-10-13)

| Object | Purpose |
|---|---|
| `registration_verification_challenges` table | Pending intake. One row per submit. Encrypted payload, token hash, per-channel OTP state, 24-hour TTL |
| `registrations.submitted_email` | Snapshot of the value the participant typed. Nullable. Not an identity key |
| `registrations.submitted_contact_number` | Same, for phone. Nullable. Not an identity key |

Migrations:

    database/migrations/2026_10_13_100000_create_registration_verification_challenges_table.php
    database/migrations/2026_10_13_100100_add_submitted_contacts_to_registrations_table.php

Both are additive. No existing columns were dropped or altered.

### Where the full design lives

`docs/registration-contact-verification.md` is the authoritative
reference for the shipped flow — every state, every limit, every
non-obvious behavior. Read it before touching the public path.

### Identity model — unchanged in spirit

`Participant::resolveFrom()` is still the identity resolver. The
change is *when* it runs: at finalize, not at submit. Only verified
identity keys participate in resolution. Optional contact values are
stored on the Registration as `submitted_*` snapshots and never touch
`participants.email` or `participants.contact_number`.

---

## Corrections — Phone verification (2026-10-14)

The `## Corrections — Status block (2026-10-13)` above describes the
three-phase flow and its step 2 as:

> Required email is verified by an OTP delivered through Resend.
> Required phone is verified by a staff-generated code
> communicated externally.

That described the shipped state on 2026-10-13. Commit `b552cbb`
(2026-10-14) replaced the phone channel with Firebase Phone Auth.

Current state of step 2:

- **Email** — six-digit OTP delivered by Resend. Unchanged.
- **Phone** — reCAPTCHA, then Firebase sends the SMS. The browser SDK
  produces an ID token which the frontend posts to
  `/r/{slug}/verification/phone/firebase`. The server verifies the
  token with the Firebase Admin SDK and requires the verified phone to
  match the submitted phone after normalization.
- **No staff action.** The verification queue, the sidebar entry, and
  the `phone-code` endpoint are all deleted.

Phone is also now structurally required on every event.
`Event::COMMON_FIELDS_TOGGLEABLE` no longer contains
`contact_number`; it lives in `COMMON_FIELDS_ALWAYS_REQUIRED`
alongside `first_name`, `last_name`, and `age`.

Full record:
`docs/registration-contact-verification.md`
`## Corrections (2026-10-14)` block.

---

## Correction: attendance code issued at finalize (2026-10-14)

The `## Corrections — Status block (2026-10-13)` section above
describes the three-phase public flow. Finalization step 3 is now
amended to include issuing the participant's attendance code.

Commit `d05c7e7` added:

- `RegistrationVerificationService::finalize()` now generates a
  per-registration attendance code on the same transaction that
  creates the `Registration` row.
- A `RegistrationConfirmationMail` is dispatched via
  `DB::afterCommit()` to `submitted_email`, carrying the code. Sent
  only when the participant provided an email.
- The code is displayed on the confirmation page
  (`registrations/verify.tsx`) inside an "Your attendance code"
  panel, with a copy button.

Nothing else about the public flow changed. The three phases,
the challenge state machine, the identity resolver contract, and
the registration field responses all behave as before.

Full reference: `docs/attendance-code.md`.

---

## Status addendum (2026-10-15)

Three public-form and verification changes landed in commit
`1f0d07f`.

### Duplicate check is earlier

A submission whose submitted email or normalized phone already
belongs to a registration on the same event is now rejected at
`begin()` — the first phase, before any challenge row exists. The
finalize-time check remains as a backstop.

### Public form UX

The public form gained:

- Blur-time client validation with a `touched` set. Errors appear
  after the user leaves a field and clear on edit. Before this,
  the client validators only ran on submit.
- A React-controlled consent checkbox. The HTML5 `required`
  attribute was swallowing the submit event before any validator
  ran — the user saw a native tooltip and nothing else.
- Placeholders on all six common fields.
- A CSS rule hiding the native number-input spinners on the Age
  field.

### Phone channel is toggleable

`contact_number` is back in `Event::COMMON_FIELDS_TOGGLEABLE`. The
form builder exposes the same checkbox email gets. The at-least-one
rule (email or phone) still applies at save time.

The `## Corrections — Phone verification (2026-10-14)` block above
states the opposite. Where this addendum disagrees, this addendum
wins.

---

## Status addendum — acknowledgement (2026-10-15, later)

A second change landed in commit `57c89f3`, after the earlier
addendum on this date.

### Code acknowledgement

After finalization, the verify page displays the participant's
Registration Code and a single confirmation button. Clicking the
button records `code_acknowledged_at` on the challenge, clears the
challenge cookie, and redirects to a code-free completion page at
`/r/{slug}/registered`.

Once acknowledged, revisiting `/verification` with the same cookie
redirects to `/registered`. The code is no longer displayed.

See `docs/registration-contact-verification.md` "Code
acknowledgement (2026-10-15)" for the full flow.

### Public form event summary

The public form header now renders event_type label, end_time,
distance, partners (name + type), and the active accessibility
flags. Absent values are omitted cleanly.

### "Registration Code" terminology

Participant-facing strings now read "Registration Code" instead of
"attendance code". The code value is unchanged.

### Client validation aligned to per-event requirements

`resources/js/lib/public-registration-validation.ts`'s at-least-one
identity check now respects the per-event toggle state. Previously
it fired whenever both email and phone were blank, even if one was
optional. It now fires only when no *required* identity channel is
satisfied — matching `PublicRegistrationRequest::withValidator()`
Pass 3.

---

## Status addendum — event summary cleanup (2026-10-15, later)

Commit `6173540` trimmed the public form's event summary.

### Removed from the summary

- **Course link.** The public form header no longer renders
  `event.course_url`. Every event already stores a venue map link
  and address; the course URL was rarely populated.
- **`quiet_space_available`.** Removed from the accessibility flags
  shown on the public form. The DB column is preserved for
  historical data.

### Unchanged

- The event summary still renders event_type, date, time range,
  distance, venue, venue address, venue map link, description,
  partners, and the remaining eight accessibility flags.

### See also

- `docs/event-creation-wizard.md` "Corrections (2026-10-15, second
  pass)" for the wizard-side changes.

---

## Correction — birthdate swap (2026-10-19)

The demographic field changed from an age number input to a date of
birth picker. Four statements in this file are superseded; the
original text above is preserved and this block wins where they
disagree.

1. **§4 table.** The row `| `age` | number | yes | 1–120 |` no longer
   describes the shipped form. The field is now `birthday` (label
   "Date of birth"), required, bounded to ages 3–120 inclusive.
   During the transition the server still accepts `age` (nullable,
   1–120) and prefers `birthday` on write when both are present.

2. **`## Corrections — Phone verification (2026-10-14)`.** The
   sentence placing `age` in `Event::COMMON_FIELDS_ALWAYS_REQUIRED`
   alongside `first_name` and `last_name` is stale. The
   always-required set is now `first_name`, `last_name`, `birthday`.

3. **`## Status addendum (2026-10-15)` — Public form UX.** The bullet
   "A CSS rule hiding the native number-input spinners on the Age
   field" describes a control that no longer exists. The public form
   collects date of birth through a DatePicker: typed MM/DD/YYYY
   entry, calendar popup, friendly "Sep 25, 1990" display after
   entry.

4. **Display strings.** `RegistrationFieldValidationRules` now renders
   the group as "Date of birth". The server-side group key stays
   `age`; the form-builder collision check is unchanged.

Decisions: store birthday only and derive age on read
(`Participant::getAgeAttribute()`, falling back to the stored `age`
column for legacy rows); bounds tightened from 1–120 to 3–120.
Client and server each enforce the bounds. Full phase record:
`docs/progress.md` "2026-10-19 — Birthdate swap closure".


## Running Events audit addendum — 2026-10-21

The current public flow is the five-step wizard. Newly saved draft forms are version 2 (email required, email-only verification); published version 1 forms retain their channel contract. On-site intake remains open during Ongoing until explicitly closed. Step 2 now includes an acknowledged similarity warning.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.
