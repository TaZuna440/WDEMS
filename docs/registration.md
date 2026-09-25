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
