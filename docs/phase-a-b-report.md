# WDEMS — Phase A + Phase B Technical Report

**Subsystem:** Google Integration (OAuth connection + Form creation & management)

**Status:** Complete and operational as of September 18, 2026

**Scope:** This document covers the design, architecture, and implementation
of the first two Google-integration phases of the Workflow-Driven Event
Management System (WDEMS). It is a factual technical reference, not a
replacement for the wider WDEMS system description.

---

## 1. System context

WDEMS is a Laravel 13 / React 19 / Inertia 3 web application whose primary
development target is the **Community Run** event type. The system manages
the full lifecycle of a Community Run from creation through completion,
including registration, attendance, and post-run monitoring.

Participant registration is deliberately handled outside WDEMS — via
**Google Forms** owned by the organizing staff. WDEMS's role is to:

1. Create the Google Form on the organizer's behalf
2. Let the organizer shape its questions from inside WDEMS
3. (Later) Read the form's responses from a linked Google Sheet
4. (Later) Import responses into WDEMS as Participant + Registration records

Phases A and B — covered here — deliver items 1 and 2. Phases C and beyond
deliver items 3 and 4.

### 1.1 Phase split

| Phase | Deliverable | Status |
|-------|-------------|--------|
| A | Google OAuth connection: WDEMS can link to a Google account and store its token | Complete |
| B | Google Form creation + question editor: WDEMS can create a real Google Form per event and edit its questions | Complete |
| C | Sheet creation + Form↔Sheet linkage via Apps Script | Not started |
| D | Sheet response polling / webhook + Participant/Registration import | Not started |
| E | Monitoring dashboard, recurring-participant detection | Not started |

---

## 2. Phase A — Google OAuth Connection

### 2.1 Purpose

Each WDEMS staff or admin user may connect their own Google account. That
connection is then used as the acting identity whenever WDEMS talks to
Google APIs on that user's behalf.

The design allows any WDEMS user to create Forms for any event. If the
event's creator has a Google connection but the acting user does not, WDEMS
falls back to the creator's connection so that the workflow is not blocked.

### 2.2 Data model

Table: `google_integrations`

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint, PK | |
| `user_id` | bigint, FK → users, unique | One connection per WDEMS user |
| `google_account_id` | string, nullable | Google's stable account ID |
| `google_email` | string, nullable | Human-readable email shown in UI |
| `access_token` | text | OAuth 2.0 access token |
| `refresh_token` | text, nullable | OAuth 2.0 refresh token |
| `expires_at` | timestamp, nullable | Access token expiry (checked before use) |
| `scopes` | json, nullable | Granted OAuth scopes |
| timestamps | | |

Foreign-key behavior: cascade on user delete (deleting a WDEMS user removes
the associated Google connection).

Model: `App\Models\GoogleIntegration`. `access_token` and `refresh_token`
are declared in `#[Hidden]` so they never leak through accidental
serialization (e.g. Inertia props, API responses).

### 2.3 OAuth scopes requested

The following scopes are requested during the OAuth consent flow:

| Scope | Purpose |
|-------|---------|
| `openid` | User identity |
| `.../userinfo.email` | Read account email |
| `.../userinfo.profile` | Read account name |
| `.../forms.body` | Create and edit Forms |
| `.../forms.responses.readonly` | Read Form responses (Phase D) |
| `.../spreadsheets` | Create and read Sheets (Phase C/D) |
| `.../drive.file` | Access files created by WDEMS only |
| `.../script.projects` | Deploy Apps Script projects (Phase C) |

`drive.file` is deliberately narrow: WDEMS can only see files it has
created, not the user's whole Drive.

### 2.4 Endpoints

| Method | URL | Handler | Purpose |
|--------|-----|---------|---------|
| GET | `/settings/google` | `GoogleIntegrationController@show` | Settings page |
| GET | `/auth/google/redirect` | `GoogleIntegrationController@redirect` | Start OAuth |
| GET | `/auth/google/callback` | `GoogleIntegrationController@callback` | OAuth return |
| DELETE | `/settings/google` | `GoogleIntegrationController@destroy` | Revoke + delete |

All four live inside the existing `auth` + `verified` middleware group.

### 2.5 Service layer

`App\Services\Google\GoogleOAuthService` encapsulates all Google Auth logic.
Public methods:

- `authUrl(): string` — builds the Google consent URL with the requested scopes and `access_type=offline` so that a refresh token is returned.
- `handleCallback(User $user, string $code): GoogleIntegration` — exchanges the auth code for tokens, fetches the user's account info, and persists both. Uses `updateOrCreate` so reconnecting replaces the old connection.
- `disconnect(User $user): void` — revokes the token at Google and deletes the local row.
- `client(): GoogleClient` — returns a configured `Google\Client` instance used by other services.

### 2.6 Flow: connecting


### 2.7 Flow: token refresh

The access token expires roughly every hour. Before every Google API call,
the service checks `expires_at`. If expired, it calls
`fetchAccessTokenWithRefreshToken()` and persists the new access token.
The refresh token is long-lived and does not require user interaction.

This means a connected Google account keeps working indefinitely as long as
the user does not revoke access.

### 2.8 Security properties

- **Tokens are never logged.** Log statements around Google operations
  reference `user_id` and `google_email` only.
- **Tokens are never sent to the frontend.** The model's `#[Hidden]` covers
  serialization paths.
- **The callback URL is fixed** and registered in Google Cloud, preventing
  open redirect attacks.
- **Only authenticated and email-verified users** can initiate or complete
  the flow (enforced by the `auth` + `verified` middleware).

### 2.9 Testing

Phase A is verified manually (the browser flow described in §2.6). No
automated tests exist for this phase because mocking Google's OAuth
endpoints would not verify the real integration.

---

## 3. Phase B — Google Form creation + question management

### 3.1 Purpose

For each event that reaches the `configured` workflow state, an organizer
can generate a Google Form. WDEMS:

1. Creates a blank Form in the connected Google account's Drive
2. Seeds it with six default questions plus one question per event option group
3. Publishes it (marks it as accepting responses)
4. Stores its Form ID and public URL against the event

After creation, the organizer can freely add, edit, and remove questions
from inside WDEMS. Changes are staged locally and pushed to Google in a
single batched API call.

### 3.2 Data model

Table: `registration_setups` — one row per event

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint, PK | |
| `event_id` | bigint, FK → events, unique | One Form per event |
| `google_form_id` | string, nullable | Google's Form ID |
| `form_url` | string(500), nullable | Public responder URL |
| `status` | string, default 'draft' | `draft` \| `published` |
| `created_by` | bigint, FK → users | The user who created it |
| timestamps | | |

Table: `registration_setup_changes` — append-only audit log

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint, PK | |
| `registration_setup_id` | bigint, FK → registration_setups | |
| `user_id` | bigint, FK → users | Acting user |
| `google_email_used` | string | Which Google account executed the API call |
| `action` | string | See actions list below |
| `google_item_id` | string, nullable | Google Form item ID (for question-level changes) |
| `item_title` | string, nullable | Snapshot of the question title |
| `changes` | json, nullable | Before/after snapshots |
| `created_at` | timestamp | Append-only; no `updated_at` |

Actions logged:

- `form_created` — Form created via `GoogleFormService::createForEvent`
- `question_added` — individual question added (legacy endpoint)
- `question_deleted` — individual question deleted (legacy endpoint)
- `questions_synced` — batched diff-and-apply via `/sync`

### 3.3 Default questions

Every newly-created Form is seeded with these six questions, all required:

| # | Title | Type |
|---|-------|------|
| 1 | First name | Short answer |
| 2 | Last name | Short answer |
| 3 | Contact number | Short answer |
| 4 | Email address | Short answer |
| 5 | Age | Short answer |
| 6 | Address | Paragraph |

Additionally, for each distinct `event_options.option_type` on the event,
one multiple-choice question is generated. For example, if the event has
options `5KM` and `10KM` of type `distance`, the Form receives a "Distance"
question with those two choices.

The organizer can edit, remove, or add to these questions after creation.

### 3.4 Endpoints

| Method | URL | Handler | Purpose |
|--------|-----|---------|---------|
| GET | `/events/{event}/registration/setup` | `show` | Setup page |
| POST | `/events/{event}/registration/setup` | `store` | Create the Form |
| POST | `/events/{event}/registration/setup/questions/sync` | `syncQuestions` | Batched diff-and-apply |

State gating: setup creation is only enabled when the event status is
`configured`; question editing is only enabled in the same state. Later
states (`registration_open` and beyond) lock the setup page to read-only
to prevent changing a Form whose responses are being collected.

### 3.5 Service layer

`App\Services\Google\GoogleFormService` encapsulates all Forms API logic.
Public methods:

- `createForEvent(Event $event, User $actor): RegistrationSetup` — creates, seeds, and publishes a Form; persists the setup row; logs `form_created`.
- `getQuestions(RegistrationSetup $setup, User $actor): array` — fetches the live Form from Google and returns a normalized question list.
- `syncQuestions(RegistrationSetup $setup, array $desired, User $actor): array` — applies adds/edits/deletes in a single batch call; logs `questions_synced`; returns the updated question list.
- `deleteQuestion(RegistrationSetup $setup, string $itemId, User $actor): void` — legacy single-item delete (kept for completeness).

### 3.6 Flow: creating a Form


### 3.7 Flow: editing questions (draft-then-save)

The original design issued one HTTP request per question mutation. That
produced one Google API call per change and took several seconds per
keystroke of change. The current design stages all mutations locally.


Result: **one Google API call regardless of how many changes were made.**

### 3.8 Algorithm: diff-based sync

Given:
- `serverItems` — the Form's current items (from `forms.get`)
- `desired` — the client's requested final list

The algorithm produces three ordered sets of Google API requests:

1. **Deletes** — server items whose IDs are not in `desired`. Deletes are sorted descending by index so that each delete does not shift the index of pending deletes.
2. **Updates** — desired items with a non-null `id` that exists on the server. The new index is computed against a "post-delete" mapping.
3. **Creates** — desired items with a null `id`. Appended after the surviving items.

This is important because Google Forms API expresses item location by
**index** in the Form, not by stable ID. Deleting item 3 shifts everything
after it. The two-pass index computation (delete-then-map) is what makes
the batch consistent.

### 3.9 Audit trail design

Every mutation writes one row to `registration_setup_changes`. Rows are:

- **Append-only** — the model declares `public const UPDATED_AT = null` and only ever inserts.
- **User-attributed** — both the WDEMS user (`user_id`) and the Google account whose token executed the call (`google_email_used`). These can differ because of the actor/creator fallback described in §2.1.
- **Snapshot-based** — `changes` stores before/after JSON for question-level mutations.
- **Displayed** — the setup page renders the last 50 rows as a History panel.

This gives the organizer (and any future auditor) a complete, chronological
record of who did what to the Form, when, and via which Google account.

### 3.10 UI design

The setup page is at `/events/{id}/registration/setup` and consists of:

1. **Form info card** — public URL, status, "Open Form in Google" button
2. **Questions card** — list with per-question Edit and Remove controls
3. **History card** — chronological audit trail
4. **Sticky Save bar** — appears only when local state differs from server state

Per-question controls use red-bordered destructive styling so they're
visibly distinct from neutral actions. The Save bar is fixed to the bottom
of the viewport so it's always visible while editing a long list.

### 3.11 Error handling

Both `createForEvent` and `syncQuestions` wrap their Google calls in
try/catch. On failure:

- The exception is logged to `storage/logs/laravel.log` with structured context (event ID, user ID, error message)
- The user sees a red alert with the exception message
- The Form's existing state is preserved (no partial application)

For `createForEvent`, if the Form is created but seeding fails, a best-effort
cleanup deletes the Form from Google Drive so no orphan appears.

### 3.12 Testing

Automated tests for Phase B are not yet written. All behavior has been
verified manually through the browser and by inspecting the resulting
Google Forms and the `registration_setup_changes` table.

A future test pass should cover:

- Successful Form creation writes the correct row and log entry
- Question sync correctly computes deletes/updates/creates
- Index-shift behavior under delete+update in the same batch
- Error handling: Google API failure produces a friendly message, not a 500

---

## 4. Cross-cutting concerns

### 4.1 HTTP timeouts

The `Google\Client` used by `GoogleFormService` is configured with a
Guzzle HTTP client carrying `timeout: 25s` and `connect_timeout: 10s`.
This prevents a stalled Google call from blocking the request indefinitely.

### 4.2 Logging

Every mutation logs structured context at `INFO` or `ERROR` level. Fields:

- `event_id`, `actor_id`, `action` (for form-level events)
- `event_id`, `user_id`, `item_id` (for question-level events)
- `error` and `exception` class (on failure)

No tokens, OAuth secrets, or Form content are ever logged.

### 4.3 Free-tier constraints

All Google APIs used are free with a valid Google Cloud project. No billing
account is required. The current design's expected usage (1–10 API calls
per event setup, 1 call per save) is well under 1% of the free-tier limits.

The OAuth app remains in **Testing** mode, which caps it at 100 test users
and avoids the Google app-verification process. This is appropriate for
the MVP phase.

### 4.4 Google Cloud project structure

| Item | Value |
|------|-------|
| Project | `wdems` (ID: `wdems-509007`) |
| APIs enabled | Forms, Sheets, Drive |
| OAuth consent screen | External, Testing |
| Test users | 1 (the developer's account) |
| OAuth client | Web application, 2 redirect URIs |

---

## 5. Known limitations and open questions

1. **`updateItem` requests are issued for every existing question on every save**, even when a question's content did not change. Functionally correct but slightly noisy in the audit log (`changes.updated` count reads high). Fix: compare each desired item against the server snapshot and only emit `updateItem` when fields differ.

2. **No automated tests** for Phase B. Manual verification only.

3. **Apps Script is not yet integrated.** Phase C (Sheet creation + Form↔Sheet linkage) requires either programmatic Apps Script deployment (complex) or a manual copy-paste step per event (simple). Decision pending.

4. **No transaction across Google + WDEMS.** If the WDEMS DB write fails after Google successfully created the Form, the Form is orphaned. Currently mitigated by best-effort cleanup on failure, but not perfectly atomic.

5. **Single event → single Form.** There is no way to reset or regenerate a Form for an event without manual cleanup. This may be added in a later phase.

6. **No versioning of questions.** The audit log records each change, but there is no way to "roll back" a Form to a previous state.

---

## 6. Suggested chapter structure for the thesis

If this becomes a thesis chapter, here's a natural structure:

**Chapter: Google Integration Subsystem**

- 6.1 Architectural overview
  - Why Google Forms as the participant-registration surface
  - Why per-organizer OAuth
  - The three-layer split: OAuth → Form creation → Response sync
- 6.2 Phase A: OAuth connection
  - Data model
  - Flow diagram
  - Security properties
- 6.3 Phase B: Form creation and editing
  - Data model (setups + audit log)
  - The default question template
  - The batched sync algorithm (this is the interesting bit)
  - The draft-then-save UI pattern
- 6.4 Audit trail
  - Design rationale
  - What it records and why
- 6.5 Constraints
  - Google Cloud free tier
  - Testing-mode OAuth
  - No Apps Script yet
- 6.6 Evaluation
  - Manual verification results
  - Performance characteristics (single-call saves)
  - Outstanding limitations

The **batched sync algorithm** (§3.8) is the strongest technical
contribution of this chapter. It's the piece worth a diagram.

---

## 7. Appendix — file inventory

Files created or modified across Phases A and B:

**Models:**
- `app/Models/GoogleIntegration.php`
- `app/Models/RegistrationSetup.php`
- `app/Models/RegistrationSetupChange.php`

**Services:**
- `app/Services/Google/GoogleOAuthService.php`
- `app/Services/Google/GoogleFormService.php`

**Controllers:**
- `app/Http/Controllers/GoogleIntegrationController.php`
- `app/Http/Controllers/RegistrationSetupController.php`

**Routes:**
- `routes/web.php` (Google + registration routes)
- `routes/settings.php` (Google settings page)

**Migrations:**
- `xxxx_create_google_integrations_table.php`
- `xxxx_create_registration_setups_and_changes_tables.php`

**React pages/components:**
- `resources/js/pages/settings/google.tsx`
- `resources/js/pages/events/registration-setup.tsx`
- `resources/js/components/question-editor-dialog.tsx`

**Modified:**
- `app/Models/Event.php` (added `registrationSetup()` relation)
- `app/Models/User.php` (added `googleIntegration()` relation)
- `app/Http/Controllers/EventController.php` (added `has_registration_setup` prop)
- `resources/js/pages/events/show.tsx` (setup button + state gating)
- `app/Providers/AppServiceProvider.php` (rate limiters)
- `config/services.php` (google credentials)
