# WDEMS — Architecture

A technical overview of how the pieces of WDEMS fit together. For a chronological record of what was built, see `development-log.md`. For Google-specific setup, see `google-integration.md`.

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
    │  Laravel 13 Controllers (app/Http/Controllers/)         │
    │  Thin: authorize → call service → return redirect/Inertia│
    └────────────────────────┬────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  Services (app/Services/)                               │
    │                                                          │
    │  EventWorkflow          — lifecycle state machine        │
    │  EventDeletionService   — ordered transaction delete     │
    │  EventDeletionOtp       — staff email verification       │
    │  GoogleOAuthService     — token storage + refresh        │
    │  GoogleFormService      — Forms + Sheets API             │
    └────────────────────────┬────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  MySQL  ·  Cache  ·  Google APIs  ·  Resend             │
    └─────────────────────────────────────────────────────────┘

Key principle: **controllers never call Google APIs directly.** Every external call lives behind a service.

---

## Data Model

### Core domain

    users
      id, name, email, role (admin|staff), email_verified_at

    events
      id, created_by → users (RESTRICT)
      event_type (community_run | fun_run)
      event_name, description, event_date, start_time, end_time, venue
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
      source (form|walk_in|paper)     ← Phase C
      google_form_response_id          ← Phase C (nullable)
      registered_at                    ← Phase C (nullable)
      UNIQUE (event_id, google_form_response_id)

    registration_options
      id, registration_id → registrations (CASCADE)
      event_option_id → event_options (RESTRICT)
      option_value
      UNIQUE (registration_id, event_option_id)

    attendances
      id, registration_id → registrations (CASCADE, UNIQUE)
      attendance_status, attendance_time
      recorded_by → users (NULL on delete), notes

### Google integration

    google_integrations
      id, user_id → users (CASCADE, UNIQUE)
      google_account_id, google_email
      access_token, refresh_token  (hidden from serialization)
      expires_at, scopes

    registration_setups
      id, event_id → events (CASCADE, UNIQUE)
      google_form_id, form_url
      status (draft|published)
      created_by → users
      google_sheet_id, sheet_url, sheet_linked_at  ← Phase C

    registration_setup_changes          (append-only audit log)
      id, registration_setup_id → registration_setups (CASCADE)
      user_id → users, google_email_used
      action (form_created|sheet_linked|questions_synced|...)
      google_item_id, item_title
      changes (JSON: before/after)
      created_at (no updated_at)

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
| `google_integrations.user_id` → `users` | CASCADE |
| `registration_setups.event_id` → `events` | CASCADE |
| `registration_setup_changes.registration_setup_id` → `registration_setups` | CASCADE |

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

Implemented in `App\Services\EventDeletion\EventDeletionOtpService`. Backed by Laravel Cache (database driver).

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

Rate limiters on the endpoints:

- `event-deletion-otp-request` — 3 per minute per (user, event)
- `event-deletion-otp-verify` — 10 per minute per (user, event)

---

## Google OAuth Connection

Implemented in `App\Services\Google\GoogleOAuthService`.

### Scopes requested

    openid
    https://www.googleapis.com/auth/userinfo.email
    https://www.googleapis.com/auth/userinfo.profile
    https://www.googleapis.com/auth/forms.body
    https://www.googleapis.com/auth/forms.responses.readonly
    https://www.googleapis.com/auth/spreadsheets
    https://www.googleapis.com/auth/drive.file
    https://www.googleapis.com/auth/script.projects

`drive.file` is deliberately narrow: WDEMS can only see files it has created, not the user's whole Drive.

### Token refresh

Before every Google API call, `GoogleFormService::buildClientWithToken()` checks `expires_at`. If expired, it calls `fetchAccessTokenWithRefreshToken()` and persists the new access token. The refresh token is long-lived — a connected account keeps working indefinitely without user interaction.

### Acting account resolution

Which Google account's token gets used depends on context:

1. If the currently logged-in user has a `google_integration`, use theirs.
2. Otherwise, if the event creator has one, use theirs.
3. Otherwise, throw with a clear "connect a Google account" message.

This lets an admin without a Google connection still manage events created by a staff member who has one.

---

## Google Form Creation

`GoogleFormService::createForEvent()`:

1. Resolve the acting Google account (see above).
2. Build an authenticated Google client.
3. Call `forms.create()` with a title.
4. Call `forms.batchUpdate()` with the default question set + one question per distinct `option_type` on the event.
5. Call `forms.setPublishSettings()` to accept responses.
6. Write the `registration_setups` row.
7. Write a `form_created` entry to `registration_setup_changes`.

On failure, best-effort deletion removes the Form from Drive.

### Default questions

    1. First name       — short answer    required
    2. Last name        — short answer    required
    3. Contact number   — short answer    required
    4. Email address    — short answer    required
    5. Age              — short answer    required
    6. Address          — paragraph       required

Plus: for each distinct `event_options.option_type` (e.g. `distance` → "Distance"), one multiple-choice question with the option names as choices.

---

## Question Editing (Batched Sync)

The initial design issued one API call per question mutation. That was slow — N changes meant N round-trips to Google, each taking 1–3 seconds.

The current design uses **draft-then-save**: all mutations stage locally in React state, and one POST sends the full desired list. The service computes the diff against Google's current state and issues **one** `batchUpdate`.

`GoogleFormService::syncQuestions()`:

1. Fetch the Form.
2. Build a map of `item_id → current_index`.
3. Determine which server items aren't in the desired list → those are deletes. Sort descending so later indexes stay valid.
4. Compute "post-delete index" for each surviving item.
5. Emit deletes, then updates (using post-delete indexes), then creates (appended).

Result: O(1) API calls regardless of how many changes were made.

The UI shows a sticky "You have unsaved changes" bar whenever local state differs from server state.

---

## Sheet Linkage (A2 — Manual Apps Script)

The Google Forms API has no method to link a Form to a Sheet. That can only be done from the Forms UI or via an Apps Script bound to the Form.

WDEMS uses the "A2" approach: the organizer pastes a small script into the Form's Script Editor once. The script creates the Sheet and calls `form.setDestination()`. After that, responses auto-populate the Sheet.

The setup page walks the organizer through:

1. Open the Form in edit view
2. Click ⋮ → Apps Script
3. Paste the snippet
4. Save + Run + authorize
5. Return to WDEMS and click "Verify Sheet Link"

Verification (`verifySheetLink()`):

1. Call `forms.get()` on the Form ID.
2. Read `linkedSheetId`. If null, return false.
3. Build the Sheet URL from the ID (no Drive API call — see note below).
4. Write `google_sheet_id`, `sheet_url`, `sheet_linked_at` on the setup.
5. Log a `sheet_linked` audit entry.

### Why no Drive API call

The `drive.file` OAuth scope only grants access to files **WDEMS itself created**. Sheets created by the user's Apps Script fall outside that scope — `drive.files.get()` would return 404 even though the Sheet exists.

Fix: construct the Sheet URL directly from the ID using the standard Google Sheets URL pattern:

    https://docs.google.com/spreadsheets/d/{sheetId}/edit

No API call needed, no scope issue.

---

## Audit Trail

Every mutation to a Google Form or its Sheet linkage writes one row to `registration_setup_changes`. Rows are:

- **Append-only** — the model declares `public const UPDATED_AT = null` and only ever inserts.
- **User-attributed** — both the WDEMS user (`user_id`) and the Google account whose token executed the call (`google_email_used`). These can differ because of the actor/creator fallback.
- **Snapshot-based** — the `changes` JSON field stores before/after for question-level mutations.

Actions currently logged:

    form_created
    sheet_linked
    question_added      (legacy single-item endpoint)
    question_deleted    (legacy single-item endpoint)
    questions_synced    (batched save)

The setup page renders the last 50 rows as a History panel.

---

## React Component Architecture

### Layout

Every page has a `Layout` static property attaching it to a breadcrumb trail. The sidebar and header come from `AppLayout`.

### Confirm dialog

`useConfirmDialog()` hook + `ConfirmDialog` component. Variants: `primary`, `warning`, `danger`. Accepts `children` for structured content (used by the event deletion warning to list record counts).

### OTP dialog

`EventDeletionOtpDialog` — self-contained, handles request-verify flow, countdown, error states.

### Question editor

`QuestionEditorDialog` — reused for add and edit. Takes an optional `initialValue` prop. Calls back into the parent (`onSave`) rather than doing HTTP itself — the parent owns the draft state.

### Date + Time pickers

Custom components built on Radix primitives (no extra npm packages):

- `DatePicker` — popover calendar, keyboard-navigable, "Today" shortcut
- `TimePicker` — hour/minute/period selects, converts between `HH:MM` (24h) and the display format

Both are DRY-shared between `events/create.tsx` and `events/edit.tsx`.

---

## HTTP Client Configuration

Google API calls use a Guzzle client with a 25-second timeout and 10-second connect timeout, set inside `GoogleFormService::buildClientWithToken()`. This prevents a stalled Google call from blocking a request indefinitely.

---

## Error Handling

Every service method that touches Google APIs is wrapped in `try/catch` at the calling controller. Failures:

1. Are logged with structured context (`event_id`, `user_id`, `error`).
2. Return a user-friendly error via `back()->withErrors(['...' => ...])`.
3. Never produce a 500 page.

Frontend reads errors from `usePage().props.errors`, not `form.errors` — this is critical because `form.errors` only reflects state from `form.post()`, not from `router.post()`.

---

## Testing Strategy

- **SQLite in-memory** for all tests (`phpunit.xml`). Tests run in ~2.5 seconds instead of minutes.
- **Feature tests** for HTTP-facing behavior — role gates, redirects, cascade semantics.
- **Unit tests** for isolated services (OTP service is the canonical example).
- **No mocked Google tests yet** — the sync algorithm and Google API interactions are verified manually only.

