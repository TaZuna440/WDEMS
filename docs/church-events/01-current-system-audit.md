# WDEMS — Church Events Audit: Current System

**Status:** Draft for review. Documentation-only. No code, schema,
or configuration was changed to produce this document.

**Audience:** Whoever executes documents 02–06 of the Church Events
documentation set.

**Purpose:** Establish what exists in WDEMS today, distinguish it
from what the Church Events workflow specification proposes, and
name the decisions that must be made before any implementation
begins.

**Related:** This is the first of six documents in
`docs/church-events/`. Nothing here should be treated as a
requirement — requirements live in document 02.

---

## 0. Audit limitation notice

**The auditor had no filesystem access and could not run
commands.** Every "verified" finding below cites a file that was
provided as raw source text during the audit session. Every file
that appears in the project tree but was not provided is listed
under "Not inspected" and is not covered by any finding.

### Files inspected (raw source provided)

    bootstrap/app.php
    bootstrap/providers.php
    composer.json
    package.json
    vite.config.ts
    tsconfig.json
    routes/web.php
    routes/settings.php
    routes/registration-verification.php

    app/Providers/AppServiceProvider.php
    app/Providers/FortifyServiceProvider.php
    app/Http/Middleware/AdminMiddleware.php
    app/Http/Middleware/AdvanceEventStatus.php
    app/Http/Middleware/EnsureDeviceIsTrusted.php
    app/Http/Middleware/EnsureEmailIsVerifiedOrAdmin.php
    app/Http/Middleware/EnsureRegistrationIsOpen.php
    app/Http/Middleware/HandleAppearance.php
    app/Http/Middleware/HandleInertiaRequests.php
    app/Http/Middleware/ProtectRegistrationVerification.php

    app/Http/Controllers/AttendanceController.php
    app/Http/Controllers/DashboardController.php
    app/Http/Controllers/EventController.php
    app/Http/Controllers/EventDeletionController.php
    app/Http/Controllers/PublicRegistrationController.php
    app/Http/Controllers/RegistrationController.php
    app/Http/Controllers/RegistrationDeletionController.php
    app/Http/Controllers/RegistrationFormController.php
    app/Http/Controllers/RegistrationMonitorController.php
    app/Http/Controllers/RegistrationVerificationController.php
    app/Http/Controllers/ReportController.php
    app/Http/Controllers/Auth/DeviceVerificationController.php
    app/Http/Controllers/Settings/SecurityController.php
    app/Http/Controllers/Settings/ProfileController.php

    app/Http/Requests/EventRequest.php
    app/Http/Requests/RegistrationFieldRequest.php
    app/Http/Requests/PublicRegistrationRequest.php
    app/Http/Requests/WalkInRegistrationRequest.php
    app/Http/Requests/BulkWalkInRegistrationRequest.php
    app/Http/Requests/BulkMarkAttendanceRequest.php
    app/Http/Requests/EditParticipantRequest.php
    app/Http/Requests/FlagRegistrationRequest.php
    app/Http/Requests/SaveRegistrationNoteRequest.php
    app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php

    app/Concerns/EventValidationRules.php
    app/Concerns/RegistrationFieldValidationRules.php
    app/Concerns/HumanNameQualityRules.php
    app/Concerns/ContactAndAddressQualityRules.php
    app/Concerns/WalkInFieldValidationRules.php
    app/Concerns/PasswordValidationRules.php
    app/Concerns/ProfileValidationRules.php

    app/Actions/Fortify/ResetUserPassword.php

    app/Enums/EventStatus.php
    app/Enums/EventType.php
    app/Enums/RegistrationStatus.php
    app/Enums/AttendanceStatus.php

    app/Models/Event.php
    app/Models/Registration.php
    app/Models/Participant.php
    app/Models/Attendance.php
    app/Models/RegistrationField.php
    app/Models/RegistrationFieldResponse.php
    app/Models/RegistrationVerificationChallenge.php
    app/Models/User.php

    app/Services/EventWorkflow.php
    app/Services/EventDeletion/EventDeletionOtpService.php
    app/Services/EventDeletion/EventDeletionService.php
    app/Services/EventDeletion/RegistrationDeletionOtpService.php
    app/Services/EventDeletion/RegistrationDeletionService.php
    app/Services/EventDeletion/OtpVerificationResult.php
    app/Services/EventDeletion/OtpResendTooSoonException.php
    app/Services/Otp/OtpCode.php
    app/Services/Reporting/ReportService.php
    app/Services/RegistrationVerification/RegistrationVerificationService.php
    app/Services/TwoFactor/EmailTwoFactorService.php
    app/Services/TwoFactor/EmailTwoFactorResult.php

    app/Mail/RegistrationVerificationOtpMail.php
    app/Mail/RegistrationConfirmationMail.php
    app/Mail/LoginVerificationCodeMail.php

    database/migrations/2026_09_16_160315_create_participants_table.php
    database/migrations/2026_09_16_160321_create_registrations_table.php
    database/migrations/2026_10_02_110000_make_contact_number_nullable_on_participants_table.php
    database/migrations/2026_10_02_120000_add_contact_number_normalized_to_participants_table.php
    database/migrations/2026_10_02_122000_add_unique_email_to_participants_table.php
    database/migrations/2026_10_02_123000_add_unique_normalized_phone_to_participants_table.php
    database/migrations/2026_10_13_100000_create_registration_verification_challenges_table.php
    database/migrations/2026_10_13_100100_add_submitted_contacts_to_registrations_table.php
    database/migrations/2026_10_14_100000_add_attendance_code_to_registrations_table.php

    resources/js/app.tsx
    resources/js/pages/registrations/verify.tsx
    resources/js/pages/registrations/public.tsx
    resources/js/pages/registrations/confirmed.tsx
    resources/js/pages/registrations/monitor/show.tsx
    resources/js/components/firebase-phone-verifier.tsx
    resources/js/components/app-sidebar.tsx
    resources/js/lib/firebase.ts
    resources/js/lib/public-registration-validation.ts

    docs/AI-CONTEXT.md
    docs/architecture.md
    docs/known-issues.md
    docs/fixes.md
    docs/authentication.md
    docs/attendance-code.md
    docs/attendance-redesign.md
    docs/attendance-encoding-and-reporting.md
    docs/participant-identity.md
    docs/participant-identity-and-monitoring-plan.md
    docs/progress.md
    docs/registration.md
    docs/registration-development-plan.md
    docs/registration-monitoring.md
    docs/registration-contact-verification.md
    docs/dev-workflow/README.md
    docs/archive/google-integration.md
    docs/archive/phase-a-b-report.md

### Files in the project tree that were NOT inspected

The following files appear in the repository tree but were not
provided as source during the audit. No finding in this document
is based on them, and the "Unverified" section marks the areas
where they are likely to matter.

    docs/implementation-notes.md
    docs/design-system.md
    docs/event-creation.md
    docs/event-creation-wizard.md
    docs/event-creation-issues.md
    docs/event-deletion.md
    docs/development-log.md
    docs/demo-email.md

    All of tests/** (file names are known; contents are not)
    All of database/factories/**
    All of database/seeders/** except by name
    app/Http/Controllers/Auth/* except DeviceVerificationController
    app/Http/Requests/Settings/* except TwoFactorAuthenticationRequest
    app/Services/EventDeletion/* — all inspected
    app/Services/TwoFactor/* — all inspected
    All of resources/js/pages/** except the four registration pages
    All of resources/js/components/** except app-sidebar,
      firebase-phone-verifier
    All of resources/js/lib/** except firebase.ts and
      public-registration-validation.ts
    All of resources/js/layouts/**
    All of resources/js/hooks/**
    All of resources/js/routes/** (generated)
    All of resources/js/actions/** (generated)

### Commands NOT run

    grep -rn -i "payment|stripe|paymongo|checkout|paid_at|amount_paid" \
      app/ resources/js/ database/ routes/

    php artisan migrate:status
    php artisan route:list
    php artisan test

Findings that depend on any of these are marked Unverified.

---

## 1. Verified

Everything in this section cites a specific file that was read.
Line references are not available (the auditor saw file contents,
not line numbers) — class, method, and constant names are used
instead.

### 1.1 Event model and schema

**`app/Models/Event.php`**

- Uses `#[Fillable([...])]` attribute listing roughly thirty
  columns, including `event_type`, `event_name`, `description`,
  `event_date`, `start_time`, `end_time`, `distance_value`,
  `distance_unit`, `venue`, `venue_address`, `venue_map_url`,
  `venue_latitude`, `venue_longitude`, `status`,
  `registration_start`, `registration_end`,
  `registration_form_saved_at`, `registration_slug`,
  `registration_common_field_requirements`, `partners`,
  `created_by`, and eight accessibility booleans.
- `casts()` maps `event_type` to `EventType`, `status` to
  `EventStatus`, `event_date` to `date`, `start_time`/`end_time`
  to `datetime:H:i`, `distance_value` to `decimal:2`,
  `registration_start`/`registration_end`/`registration_form_saved_at`
  to `datetime`, `registration_common_field_requirements` and
  `partners` to `array`, `venue_latitude`/`venue_longitude` to
  `decimal:7`, and the eight accessibility flags to `boolean`.
- Constants: `COMMON_FIELDS_ALWAYS_REQUIRED = ['first_name',
'last_name', 'age']`, `COMMON_FIELDS_TOGGLEABLE = ['email',
'contact_number', 'address']`,
  `ATTENDANCE_WINDOW_MINUTES_BEFORE = 60`.
- Methods (verified present): `isCommonFieldRequired`,
  `creator()`, `registrationFields()`, `registrations()`,
  `relatedRecordCounts()`, `canEdit()`, `canEditRegistrationForm()`,
  `canOpenRegistration()`, `canCloseRegistration()`,
  `canFinalize()`, `canRecordAttendance()`,
  `canRecordAttendanceNow()`, `canViewAttendance()`,
  `isCommunityRun()`, `isFunRun()`, `distanceLabel()`,
  `attendanceWindowOpensAt()`.
- **No `occurrence` relation, no `recurrence` columns, no
  `parent_event_id`, no `series_id`.**

**Every `canX()` method is a `match` or `in_array` on
`$this->status`.** Adding new statuses to `EventStatus` would force
each of these methods to handle the new cases.

### 1.2 Event types and statuses

**`app/Enums/EventType.php`**

Exactly two cases:

    case CommunityRun = 'community_run';
    case FunRun = 'fun_run';

Both have `label()` returning "Community Run" and "Fun Run".
`values()` returns all values as an array.

**`app/Enums/EventStatus.php`**

Exactly six cases:

    case Draft = 'draft';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

`allowedTransitions()` is a linear chain:
`Draft → [RegistrationOpen, Cancelled]`,
`RegistrationOpen → [RegistrationClosed, Cancelled]`,
`RegistrationClosed → [Ongoing, Cancelled]`,
`Ongoing → [Completed, Cancelled]`,
`Completed → []`, `Cancelled → []`.

**`app/Enums/RegistrationStatus.php`** — `Pending`, `Confirmed`,
`Cancelled`.

**`app/Enums/AttendanceStatus.php`** — `Present`, `Absent`,
`Late`, `Excused`.

### 1.3 Event lifecycle

**`app/Services/EventWorkflow.php`** — a thin service wrapping the
state machine. `transition(Event, EventStatus)` validates via
`$current->canTransitionTo($target)` and throws
`InvalidArgumentException` on illegal transitions. Convenience
methods: `openRegistration()`, `closeRegistration()`, `start()`,
`complete()`, `cancel()`, `canTransition()`.

**`app/Http/Middleware/AdvanceEventStatus.php`** — runs on the main
authenticated route group. When a request binds an `Event` whose
status is `RegistrationClosed` and whose `event_date + start_time`
is in the past, calls `EventWorkflow::start()` and logs
`event.status.auto_advanced`. Idempotent; catches
`InvalidArgumentException` silently on race.

### 1.4 Registration and participant identity

**`app/Models/Participant.php`** — `#[Fillable]` on eight columns.
Mutator `setContactNumberAttribute` writes both `contact_number`
(raw) and `contact_number_normalized` (canonical PH mobile).
Static `resolveFrom(?string $email, ?string $rawPhone, array
$attributes): self` — email-first, phone-fallback, throws
`IdentityRequiredException` when neither is present. Static
`normalizeContactNumber(?string): ?string` accepts four PH mobile
formats and returns null otherwise.

**`app/Models/Registration.php`** — `#[Fillable]` includes
`event_id`, `participant_id`, `registration_date`,
`registration_status`, `source`, `registered_at`, `flagged_at`,
`notes`, `submitted_email`, `submitted_contact_number`,
`attendance_code`. Relations: `event()`, `participant()`,
`attendance()`, `fieldResponses()`.

**`app/Models/Attendance.php`** — `#[Fillable]` includes
`registration_id`, `attendance_status`, `attendance_time`,
`recorded_by`, `notes`, `verified_at`. Relation: `registration()`,
`recorder()`. **The FK is `registration_id`, not `event_id`.**

**`app/Models/RegistrationField.php`** — the per-event custom
field definitions.

**`app/Models/RegistrationFieldResponse.php`** — one row per
answered custom field, keyed on `registration_id` and
`registration_field_id`.

**`app/Models/RegistrationVerificationChallenge.php`** —
encrypted pending intake. `$guarded = ['*']`,
`#[Hidden(['token_hash', 'payload', 'email_code_hash',
'phone_code_hash'])]`. Casts include `payload =>
'encrypted:array'`. Uses `MassPrunable` with a `prunable()` that
deletes rows whose `expires_at <= now()`.

### 1.5 Public registration and verification

**`app/Http/Controllers/PublicRegistrationController.php`** —
three actions: `show`, `store`, `confirmed`. `store` begins a
verification challenge (does not create Participant or
Registration), sets an encrypted HttpOnly cookie scoped to
`/r/{slug}`, and redirects to the verification page.

**`app/Http/Requests/PublicRegistrationRequest.php`** — dynamic
rules for the six common fields plus the event's custom fields.
Static `validatePending(Event, array)` for re-validation at
finalize. Uses `HumanNameQualityRules`, `ContactAndAddressQualityRules`.

**`app/Services/RegistrationVerification/RegistrationVerificationService.php`** —
`begin`, `findChallenge`, `publicState`, `acknowledgeCode`,
`sendEmail`, `verify`, `finalize`, `verifyPhoneWithFirebase`,
`challengeState`, `channelState`, `generateAttendanceCode`.
Constants: `CODE_TTL_SECONDS = 600`,
`RESEND_COOLDOWN_SECONDS = 60`, `MAX_ATTEMPTS = 5`,
`CHALLENGE_TTL_HOURS = 24`,
`ATTENDANCE_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'`,
`ATTENDANCE_CODE_LENGTH = 8`, `ATTENDANCE_CODE_MAX_ATTEMPTS = 3`.
`finalize` is idempotent on `finalized` state, retries on
`UniqueConstraintViolationException` up to 3 times, locks the
event row and the challenge row, re-validates the payload,
resolves participant identity, creates Registration, writes
field responses, issues the attendance code, and dispatches the
confirmation email via `DB::afterCommit()`.

**`app/Http/Controllers/RegistrationVerificationController.php`** —
`show`, `verify` (email only), `firebasePhone`, `resendEmail`,
`complete`, `acknowledge`. All actions are wrapped in a
`publicAction()` helper that catches `ValidationException` and
`\Throwable`, logs `event_id` only, and never flashes request
input.

**`app/Http/Middleware/EnsureRegistrationIsOpen.php`** — renders
`registrations/closed` with HTTP 200 when the event is not
`RegistrationOpen` or when `registration_end` has passed.

**`app/Http/Middleware/ProtectRegistrationVerification.php`** —
sets `Cache-Control: no-store`, `Referrer-Policy: no-referrer`,
`X-Robots-Tag: noindex, nofollow`, and calls
`Inertia::encryptHistory()`. **It performs no authorization** —
it is a response-header hardener, despite the name.

### 1.6 Attendance

**`app/Http/Controllers/AttendanceController.php`** — actions:
`index`, `show`, `mark`, `bulkMark`, `walkIn`, `bulkWalkIn`.
`show` performs server-side search, six filter chips, 50-row
pagination, code lookup via `?code=`. `mark` sets `verified_at`
only when the submitted `verified_code` matches the registration's
stored `attendance_code` and the status is Present or Late.
`bulkMark` accepts either an explicit `registration_ids` array or
`mark_all_visible` + filter + q. `bulkWalkIn` processes rows
independently in their own transactions with per-row errors under
`rows.{i}.identity`. Admin `?force_open=1` bypasses the time
window on all write actions.

**`app/Http/Requests/WalkInRegistrationRequest.php`,
`BulkWalkInRegistrationRequest.php`,
`BulkMarkAttendanceRequest.php`** — the request shapes for the
three walk-in / mark endpoints.

**`app/Concerns/WalkInFieldValidationRules.php`** — shared
`isEmpty()` and `validateResponseType()` for single and bulk
walk-in validation.

### 1.7 Reports

**`app/Http/Controllers/ReportController.php`** — `show` and
`exportCsv`. Both guard on `EventStatus::Ongoing` or `Completed`
via `assertReportable()`; anything else 404s. `exportCsv` streams
via `response()->streamDownload`.

**`app/Services/Reporting/ReportService.php`** — `build(Event)`
returns `['event' => [...], 'summary' => [...], 'cross_event' =>
[...], 'participants' => [...]]`. `summary()` uses a single
conditional-aggregation query. `cross_event()` scopes to the same
`event_type`. `participants()` computes per-participant
`total_appearances` and `history` scoped to the same
`event_type`.

### 1.8 Authorization

**No `app/Policies/` directory exists** (confirmed by the tree).

**Roles:** `User::isAdmin()` returns `$this->role === 'admin'`.
The only two roles in the schema are `admin` and `staff`.

**Middleware aliases** registered in `bootstrap/app.php`:
`admin`, `device.trusted`, `verified.or.admin`, `event.advance`.

**`EventRequest::authorize()` returns `true`.**

**`RegistrationMonitorController::ensureEditableRegistration()`**
and **`RegistrationDeletionController::ensureManageable()`** both
only check the event's status. Neither checks that the acting
user has any relationship to the registration or event.

**`AdminMiddleware`** blocks non-admins from routes in its group.

**`EnsureEmailIsVerifiedOrAdmin`** lets admins bypass email
verification; staff must verify.

**`EnsureDeviceIsTrusted`** lets admins bypass 2FA and lets users
with `email_two_factor_enabled === false` through.

### 1.9 Recurring-event support

**None.** No `event_occurrences` table, no occurrence model, no
recurring schedule columns, no job or command that generates
future occurrences. `events.registration_start` and
`events.registration_end` are single timestamps. `Event` has no
`parent_event_id`, `series_id`, or `recurrence_rule`. The
`AdvanceEventStatus` middleware operates on a single event row.

### 1.10 Payment-related implementation

**None found in the files inspected.** In particular:

- `composer.json` `require` and `require-dev` list no payment
  SDK (`stripe`, `paymongo`, `laravel/cashier`, etc.).
- `package.json` `dependencies` list no payment SDK.
- No `Event` cast or fillable entry references a payment status,
  amount, or reference.
- No `Registration` cast or fillable entry references a payment.
- No `AttendanceController`, `EventController`,
  `PublicRegistrationController`, or `RegistrationVerificationService`
  method mentions payment.
- No migration inspected contains a `payment` or `paid` column.

**This is not a definitive negative** — the grep was not run and
several doc files (`implementation-notes.md`, `event-creation.md`)
were not read.

### 1.11 Migrations

Thirty-five migration files exist in the tree (per `tree`
output previously provided). Those inspected follow the
Laravel convention: timestamp-prefixed names, anonymous classes
returning `up()` and `down()`, `Schema::create` / `Schema::table`
based.

Existing migrations inspected do not use `->after()` except in a
few well-commented cases. No migration alters `event_type`,
`status`, or any column that would be affected by adding a
church-events category system.

### 1.12 Tests

Test framework: Pest 5 (`pestphp/pest`,
`pestphp/pest-plugin-laravel` in `composer.json`). Feature tests
bind `RefreshDatabase` via `tests/Pest.php`. SQLite in-memory in
`phpunit.xml`.

Test files exist under `tests/Feature/{Auth,Events,Participants,
Public,Settings,Integration,Support}` and
`tests/Unit/Services`. **Their contents were not inspected.** No
finding in this document depends on test behavior.

`composer.json` scripts confirm the standard workflow:
`test` runs `@lint:check`, `@types:check`, and
`@php artisan test`. `ci:check` runs `npm run check`,
`npm run types:check`, and `@test`.

### 1.13 Frontend shell

**`resources/js/app.tsx`** — `createInertiaApp` with a `layout()`
function that switches on page name. Recognised cases:
`welcome`, `auth/login`, `registrations/public`,
`registrations/closed`, `registrations/verify`,
`registrations/confirmed`, `auth/*`, `settings/*`. Default is
`AppLayout`.

**`resources/js/components/app-sidebar.tsx`** — a single
`mainNavItems` array with four entries: Dashboard (`/dashboard`),
Events (`/events`), Registration (`/registrations`), Attendance
(`/attendance`). Passed to `<NavMain>`.

**Sidebar is not role-aware** — the same four items render for
admin and staff. Recorded as ISSUE-001 in
`docs/known-issues.md`.

### 1.14 Existing backlog and doc drift

**`docs/known-issues.md`** lists ISSUE-001 through ISSUE-013.
Notable for Church Events:

- ISSUE-001 — sidebar not role-aware.
- ISSUE-002 — dashboard not role-aware.
- ISSUE-003 — `admin/dashboard.tsx` is dead code.
- ISSUE-004 — no authorization policy on event edit. **This
  finding is broader than the issue describes**: no policy exists
  for any event, registration, or report action.
- ISSUE-010 — legal layer is a UI demo; no server-side consent
  recording.

**`docs/architecture.md`** contains at least three stale claims
already corrected by its own append-only corrections block:
the deletion algorithm references dropped tables; the data model
claims a `google_form_response_id` column that does not exist; the
auth section describes an `EmailTwoFactorResult::TooSoon` case
that does not exist.

**`docs/AI-CONTEXT.md`** §7 reading-order table still lists
`docs/known-issues.md` for RBAC and does not list the newer
`registration-contact-verification.md`, `attendance-code.md`,
`attendance-redesign.md`, or `attendance-encoding-and-reporting.md`
until later sections.

---

## 2. Inferred

Conclusions that are reasonable given the verified facts but
that have not been confirmed against primary sources.

- **The `Event` model is load-bearing across every subsystem.**
  Every `canX()` method on `Event` branches on `status`, and
  every controller that lists or guards events calls one of those
  methods. Adding new `EventStatus` values would force each method
  to be re-read and each branch to be tested. **Inference: the
  Church Events feature is cheaper to build if it does not add
  cases to `EventStatus`.**

- **Adding church types to `EventType` would couple the running
  and church paths.** `Rule::enum(EventType::class)` is used in
  `EventValidationRules::eventRules()`. The `Event` model's
  `isCommunityRun()` and `isFunRun()` methods would need new
  siblings. `ReportService::cross_event()` scopes on
  `event_type`. **Inference: a hybrid (enum for running, DB
  tables for church) preserves the running path without
  contaminating it.**

- **`Attendance` is coupled to `Registration`, not to `Event`.**
  The FK is `registration_id`. Church Services need
  attendance-per-occurrence, which has no `registration_id` for
  the general case (attendance of a Sunday service is not
  attendance of a registration). **Inference: church attendance
  cannot reuse the `Attendance` table without a schema change.**

- **The public verification flow is highly specialized to
  registration with contact verification.** Email OTP, Firebase
  phone, encrypted challenge payload, cookie-scoped auth. Church
  Services and many church activities do not need any of this.
  **Inference: church registration should not reuse
  `RegistrationVerificationService` for events that do not need
  contact verification.**

- **`ReportService::cross_event()` scoping to `event_type` is
  currently the only cross-event aggregation.** Church reports
  ("attendance by service type", "attendance summary per group")
  need different scoping — by category, by occurrence, by
  subgroup. **Inference: reports will need to be extended or a
  parallel reporting layer added.**

- **The `docs/` corpus is disciplined but large.** Fifteen-plus
  authoritative docs, with an append-only correction pattern.
  **Inference: Church Events documentation should follow the
  same pattern and live under `docs/church-events/` rather than
  editing existing docs.**

---

## 3. Unverified

Claims that exist in a mockup, a specification, an existing
documentation file, or the surrounding conversation, but that
have not been confirmed against source code.

- **Payments.** The conversation mentioned "Fun Run might be out
  of scope because of payments". No payment code was found in
  the files inspected. The grep was not run. **Unverified:
  whether any payment-related code exists elsewhere in the
  repository, and whether Fun Run has any payment behavior at
  all.**

- **`docs/implementation-notes.md`.** Referenced by
  `attendance-code.md`, `registration-contact-verification.md`,
  and others. Not inspected. It is described as recording
  cross-subsystem behaviors (error merge order, shared quality
  rules, slug constants, rate limiter inventory). **Unverified:
  what constraints it imposes on new features.**

- **`docs/design-system.md`.** Not inspected. Referenced by
  `AI-CONTEXT.md` §20 as governing visual changes.
  **Unverified: whether existing design tokens support the
  Category / Event Type UI the mockup shows.**

- **`docs/event-creation.md` and `docs/event-creation-wizard.md`.**
  Not inspected. Referenced by many docs. Likely describe the
  wizard's structure and the event-creation contract.
  **Unverified: whether the wizard is generic enough to
  accommodate new categories, or whether it hardcodes running
  fields.**

- **`docs/event-deletion.md`.** Not inspected.
  **Unverified: whether the deletion cascade covers any new
  tables Church Events might introduce.**

- **`docs/development-log.md`.** Not inspected.
  **Unverified: the narrative behind past architectural
  decisions that might bear on Church Events.**

- **Test contents.** File names were visible in the tree;
  contents were not. **Unverified: which tests will need
  companions for Church Events, and which assert behavior that
  new features must not break.**

- **Migration state.** `php artisan migrate:status` was not run.
  **Unverified: which migrations have already been applied to
  the development database and which have not.**

- **`event_categories` / `event_types` DB tables.** The mockup
  shows a Categories sidebar item and an "Add Category" button.
  No such tables exist in the migrations inspected.
  **Unverified: whether the mockup represents an approved
  requirement or a UI sketch.**

- **`ChurchEventStatus`, `event_occurrences`, budget fields,
  approval schema.** Proposed in the workflow spec. None exist
  in the inspected migrations, models, or enums.
  **Unverified: whether the workflow spec is approved as a
  requirement or is a discovery document.**

- **Event ownership as a concept.** `events.created_by` exists
  and is set. No code reads it for authorization. **Unverified:
  whether ownership should gate edit, deletion, or approval
  actions in the Church Events context, and whether that should
  also change running-event behavior.**

---

## 4. Impact map

Components that Church Events will or may affect, with reasons.
"Must change" means the component cannot satisfy the requirements
without modification. "May need change" means the requirement
depends on a decision documented in A–F. "Should not change"
means the component is a load-bearing piece of the running path
that is not obviously required to change.

### 4.1 Must change (given any of the proposed requirements)

| Component                                          | Why                                                                                                                                                            |
| -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Enums/EventType.php`                          | The two running cases do not cover Services, Fellowship, Discipleship, etc. At minimum, some way to distinguish church events from running events is required. |
| `app/Models/Event.php`                             | Either new `canX()` guards, new relations (occurrences, categories), or new fillable/cast entries are required.                                                |
| `app/Http/Controllers/EventController.php`         | `index`, `show`, `create`, `store`, `edit`, `update` all assume running-event fields. New fields require new payload entries.                                  |
| `app/Concerns/EventValidationRules.php`            | `eventRules()` hardcodes `distance_value` and `distance_unit` as required and lists accessibility flags. Church events have neither.                           |
| `resources/js/pages/events/create.tsx`, `edit.tsx` | The wizard steps assume running-event fields.                                                                                                                  |
| `resources/js/components/app-sidebar.tsx`          | Adding a Categories or Church Events entry requires editing `mainNavItems`.                                                                                    |
| `routes/web.php`                                   | New routes for category management, church event creation, occurrence handling.                                                                                |

### 4.2 May need change (depending on A–F decisions)

| Component                                                                   | Depends on                            | Note                                                                           |
| --------------------------------------------------------------------------- | ------------------------------------- | ------------------------------------------------------------------------------ |
| `app/Enums/EventStatus.php`                                                 | Decision A                            | Adding church states here contaminates every `Event::canX()` method.           |
| `app/Http/Controllers/AttendanceController.php`                             | Decision D                            | Church attendance needs to attach to occurrences, not registrations.           |
| `app/Models/Attendance.php`                                                 | Decision D                            | Current FK is `registration_id`.                                               |
| `app/Services/Reporting/ReportService.php`                                  | Decision D                            | `cross_event()` scopes on `event_type`; church reports need different scoping. |
| `app/Models/User.php`                                                       | Decision C                            | If approval introduces a new role or a new permission.                         |
| `app/Http/Requests/EventRequest.php`                                        | Decision F                            | If categories become DB-driven, event_type validation changes.                 |
| `app/Services/EventDeletion/EventDeletionService.php`                       | Any new table                         | Cascade coverage needs to include new child tables.                            |
| `resources/js/app.tsx`                                                      | Any new page family                   | Layout switch may need new cases.                                              |
| `app/Services/RegistrationVerification/RegistrationVerificationService.php` | Only if church registration reuses it | Otherwise untouched.                                                           |

### 4.3 Should not change (running path preservation)

| Component                                                                     | Why                                                                                                         |
| ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `app/Enums/EventStatus.php` (existing cases)                                  | Every running-path controller, middleware, and view depends on the current six cases and their transitions. |
| `app/Services/EventWorkflow.php`                                              | The running lifecycle's only write path.                                                                    |
| `app/Http/Middleware/AdvanceEventStatus.php`                                  | Running-event-only; church events do not auto-advance on start time.                                        |
| `app/Http/Middleware/EnsureRegistrationIsOpen.php`                            | Running-event public surface.                                                                               |
| `app/Http/Controllers/PublicRegistrationController.php`                       | Running-event-only public surface.                                                                          |
| `app/Services/RegistrationVerification/RegistrationVerificationService.php`   | If church events do not need contact verification.                                                          |
| `app/Models/Participant.php`                                                  | Cross-cutting; reuse rather than modify.                                                                    |
| `app/Concerns/HumanNameQualityRules.php`, `ContactAndAddressQualityRules.php` | Shared; reuse rather than modify.                                                                           |

---

## 5. Unknowns

Questions the audit cannot resolve from the repository alone.

1. **Is the Church Events workflow specification an approved
   requirement, a client sketch, or a discovery document?** The
   document itself is titled "Final Workflow, Event Categories,
   Sequences, and Functional Requirements" and describes itself
   as "coding-ready". If approved, most of §A–F are answered
   already. If a sketch, none are.

2. **Is the mockup authoritative for product scope?** It shows a
   Categories sidebar item, "Add Category" button, and a
   Church Events Categories panel with subtotal counts per
   category. These imply DB-managed categories and per-category
   counts. Whether the mockup is approved or exploratory is not
   stated.

3. **What is the timeline and priority relationship between
   Community Run, Fun Run, and Church Events?** Which is primary?
   Does Church Events ship before, alongside, or after running
   improvements?

4. **Who is "the client" in authorization terms?** The mockup
   shows a "Charish · Admin / Event Organizer" identity. Is the
   approver always an admin, always a specific person, or
   configurable per event?

5. **Are there any existing production deployments?** If so,
   any schema change must preserve existing data. If not,
   migrations can be written without backfill.

6. **What is the intended reporting surface for church events?**
   The workflow spec mentions "service-specific attendance
   reports" and "attendance summary per group". Whether these
   live in the existing `ReportController` or a new controller
   is undecided.

7. **Are "Personal Events" still a concept?** The scoping prompt
   says "Church Events replaces Personal Events in the new
   product scope." No `PersonalEvent` type exists in the code.
   Whether this refers to something that was planned but never
   built, or to something currently in the code under a different
   name, is unclear.

---

## A. Event status and lifecycle

### Evidence

- `EventStatus` currently has six cases, linear transitions, and
  is consumed by: `EventWorkflow`, `AdvanceEventStatus`, every
  `Event::canX()` method, `EventController` (show/index),
  `DashboardController::buildActionItems()`,
  `AttendanceController::index()`,
  `RegistrationController::index()`,
  `RegistrationMonitorController::index()` and `::show()`,
  `ReportController::assertReportable()`,
  `EventDeletionService`, and every frontend page that renders a
  status pill.
- `EventWorkflow::transition()` is the only legal write path.
- The workflow specification (client document) describes a
  ten-state chain:
  `Draft → Proposed → Under Review → Approved / Revision Required
/ Rejected → In Preparation → Ready → Ongoing → Event Ended →
Under Evaluation → Completed`.
- The mockup shows only four labels: `Upcoming`,
  `Open Registration`, `Ongoing`, `Completed`.
- `Upcoming` and `Open Registration` are not in either the code
  or the workflow spec. `Ready` and `Under Review` are in the
  workflow spec but not the mockup.

### Alternatives

**Alternative A1 — Extend `EventStatus`.** Add the seven new
church cases to the existing enum. Every `Event::canX()` method
gains a branch on category. `EventWorkflow` grows new helpers.
The dashboard, events index, and monitor must render new
statuses.

- Benefit: one status machine, one place to read a status, one
  set of pills.
- Cost: every consumer of `EventStatus` must be reviewed and
  retested. Running-event tests that assert status transitions
  may break.

**Alternative A2 — New `ChurchEventStatus` enum.** Keep
`EventStatus` untouched. Introduce a second enum for church
events, with `Event` dispatching to whichever applies based on
category.

- Benefit: running path untouched. Church lifecycle evolves
  independently.
- Cost: two status systems in the UI, two sets of pills, two
  transition tables. `Event::status` becomes polymorphic in
  intent, even if it stores a string.

**Alternative A3 — Keep `EventStatus` for running events;
model church stages as a checklist or workflow state separate
from status.** The event status remains `Draft`, `Ongoing`,
`Completed`, `Cancelled`. Church stages are tracked in a
separate `workflow_stage` field or a `church_event_stages`
side table.

- Benefit: the status pill system in the UI stays simple.
  Mockup stays accurate as-is.
- Cost: two concepts to maintain (status and stage). Approval
  is not a status transition.

**Alternative A4 — Extend `EventStatus` but restrict church
events to a subset.** Church events never leave the running
status set; the stages are pure UI.

- Benefit: no schema change to status.
- Cost: cannot represent `Proposed`, `Under Review`, or
  `Rejected` as real states. Approval becomes a boolean.

### Provisional recommendation

**A2 or A3, with A3 preferred if the client's mental model
matches the mockup.** A2 if the client insists on a first-class
ten-state machine. A1 is the most invasive and the least
reversible.

The reason A2/A3 are preferred: the running path is stable and
well-tested. Anything that touches `EventStatus` invalidates
existing tests, forces re-reads of ten-plus consumers, and
risks subtle regressions in the running lifecycle. The Church
Events feature can be introduced without that cost if status
stays simple.

### Risks

- If A1 is chosen and something breaks in the running path, the
  rollback is nontrivial.
- If A2 is chosen, the UI must distinguish "running status" from
  "church status" in a way that does not confuse users.
- If A3 is chosen, the ten-stage workflow becomes a UI concept
  that must be enforced in code somewhere (a service or a
  checklist), and "Approved" does not appear as a status pill.

---

## B. Fun Run and payments

### Evidence

- `EventType::FunRun` exists. It is treated identically to
  `CommunityRun` in every inspected code path. No branch anywhere
  distinguishes them except `isFunRun()` and the label.
- No payment-related column, cast, model, service, request, or
  route was found in any file inspected.
- `composer.json` and `package.json` list no payment SDK.
- The workflow spec does not mention payments.
- The scoping prompt (this document's source) says: _"Fun Run
  remains in scope. Do not remove its existing functionality or
  assume its payment behavior is changing."_
- The prior conversation said: _"Fun Run might be out of scope
  because of payments."_

### Alternatives

**Alternative B1 — Assume Fun Run has no payment behavior.**
Document that the "out of scope" concern in the prior
conversation does not match the codebase. Leave Fun Run
untouched.

**Alternative B2 — Run the grep before deciding.** Complete the
audit limitation by running
`grep -rn -i "payment|stripe|paymongo|checkout|paid_at|amount_paid"`.

**Alternative B3 — Defer the Fun Run question to document 02.**
Treat it as a product decision, not a code finding.

### Provisional recommendation

**B2 then B1.** The grep is a thirty-second command; running it
removes the ambiguity completely. If the grep is empty, Fun Run
has no payment behavior and the concern is moot. If the grep
returns hits, they become findings for document 02.

### Risks

- If the grep is skipped, document 02 may encode a wrong
  assumption.
- If B1 is wrong and payment code exists elsewhere, subsequent
  documents may inadvertently reference or break it.

---

## C. Approval and permissions

### Evidence

- `User::isAdmin()` is the only role check in the codebase.
- `AdminMiddleware` blocks non-admins from a small route group
  (`admin/dashboard`).
- No `Policy` classes exist. `EventRequest::authorize()` returns
  `true`.
- `RegistrationMonitorController::ensureEditableRegistration()`
  and `RegistrationDeletionController::ensureManageable()` only
  check event status, not user identity or ownership.
- `events.created_by` exists and is set by `EventController::store`.
  Nothing reads it for authorization.
- The workflow spec says: _"Approval permissions must be
  restricted to authorized leadership or designated approvers."_
  It does not say who that is.
- The mockup shows a single user identity ("Charish · Admin /
  Event Organizer") without a separate approver view.

### Alternatives

**Alternative C1 — New `approver` role.** A third role alongside
`admin` and `staff`. Approval routes gated by the new role.

**Alternative C2 — `admin` role is the approver.** No new role.
Approval actions require `isAdmin()`.

**Alternative C3 — Per-event approver assignment.** A new
nullable `approver_id` column on `events`. The event creator
assigns the approver at proposal time. Approval actions gated on
`auth()->id() === $event->approver_id || isAdmin()`.

**Alternative C4 — Approval not implemented in v1.** The
workflow spec's approval stage becomes a checklist item that
admins mark complete without a formal approval record.

### Provisional recommendation

**C2 for the first release, C3 if per-event assignment becomes
required.** C1 introduces a role-management surface that the
rest of the application does not currently have and does not
obviously need. C4 is defensible if the client has not asked
for audit-grade approvals.

### Risks

- Any choice here also re-opens ISSUE-004 (no event ownership
  policy). If a `ChurchEventPolicy` is introduced, a running
  `EventPolicy` probably should be too, to keep the two
  consistent. That expands the change.

---

## D. Recurring events and occurrences

### Evidence

- No occurrence model, table, or relation exists.
- `events` has exactly one `event_date`, one `start_time`, one
  `end_time`.
- `AdvanceEventStatus` operates on a single event row and would
  not fire per-occurrence for a recurring service.
- `Attendance` FK is `registration_id`. For a Sunday service
  with no registration, there is no `registration_id` to attach
  attendance to.
- The workflow spec says: _"A recurring schedule is not itself
  an attendance record. Every service date needs its own
  occurrence."_
- The workflow spec also says: _"Each occurrence/session must
  have its own date, status, attendance, and notes."_

### Alternatives

**Alternative D1 — No occurrences.** Every service date is a
separate `Event` row. A "Morning Service" series is a set of
events the organizer creates manually.

- Benefit: no schema change.
- Cost: mass creation is tedious. No series-level reporting.

**Alternative D2 — Add `event_occurrences` table.**
`id, event_id, occurrence_date, label, start_time, end_time,
status, notes`. Attendance attaches to occurrences for church
events. Running events do not have occurrences.

- Benefit: matches the workflow spec exactly. Enables series
  reports. Attendance has a natural home.
- Cost: new table, new model, new relation on `Event`, new
  write path in the controller. `Attendance` FK question is
  reopened — occurrence-based attendance needs a different
  shape from registration-based attendance.

**Alternative D3 — `event_occurrences` plus a new
`occurrence_attendances` table.** Church attendance is its own
table. Running attendance stays as-is.

- Benefit: full separation. No risk of breaking running
  attendance.
- Cost: two attendance tables. Reporting must union them.

**Alternative D4 — Add occurrences but reuse `Attendance` with
a nullable `occurrence_id`.** `Attendance.registration_id` stays
for running events; `Attendance.occurrence_id` is populated for
church events.

- Benefit: one attendance table.
- Cost: `Attendance` becomes polymorphic; every query that
  reads it must filter.

### Provisional recommendation

**D2 or D3.** D1 fails the workflow spec's core requirement.
D4 makes `Attendance` polymorphic in a way that every existing
attendance query — and there are many — would need to account
for. Between D2 and D3: if the reporting requirements for
church attendance are genuinely different from running
attendance (which the workflow spec suggests they are), D3 is
cleaner. If the reporting is mostly the same, D2 reuses more.

### Risks

- Any D-choice made before decision A is made risks being
  revisited. Occurrences interact with status: does each
  occurrence have its own status, or does the parent event?
- If occurrences are added, `EventDeletionService` must cascade
  through them. The running-only test suite must be extended.

---

## E. Budget and expenses

### Evidence

- No budget or expense column exists in any migration inspected.
- No budget-related controller, service, or model exists.
- The workflow spec mentions budget twice: once as a Stage 2
  item ("budget estimate, resource requirements") and once as a
  Stage 5 item ("record actual expenses and submit
  liquidation/reconciliation documents when applicable").
- The scoping prompt says: _"Budget and expense management only
  if approved as part of the scope."_

### Alternatives

**Alternative E1 — Tier 1.** A single nullable decimal
`events.budget_amount` and a text `events.budget_notes` column.
Reconciliation is a PDF upload via a generic attachments table.

**Alternative E2 — Tier 2.** A `budget_line_items` table
(`event_id, category, description, amount, notes`). Total is
computed. Reconciliation is still a PDF.

**Alternative E3 — Tier 3.** Line items, per-receipt
attachments, per-item reconciliation status, approval workflow.

### Provisional recommendation

**E1 for the first release.** The workflow spec describes budget
as a supporting artifact, not the primary feature. Tier 1
satisfies "budget and resource requirements" and "submit
liquidation/reconciliation documents" without the complexity of
E2/E3. A generic `event_attachments` table (see §F) covers the
document side.

### Risks

- If E1 is chosen and the client later wants line-item
  reporting, the migration to E2 is additive and safe.
- If the client wants finance-grade reconciliation, E1 will be
  seen as insufficient and the feature will be rebuilt.

---

## F. Categories and event types

### Evidence

- `EventType` is a two-case PHP enum. Validation uses
  `Rule::enum(EventType::class)`.
- No `event_categories` or `event_types` tables exist.
- The mockup shows a Categories sidebar item, a Church Events
  Categories panel with per-category counts, and an "Add
  Category" button.
- The workflow spec says: _"Keep dropdown options configurable
  by an authorized administrator so the system can adapt
  without code changes."_

### Alternatives

**Alternative F1 — Extend the `EventType` enum with church
cases.** All types — running and church — in one PHP enum. No
DB tables.

- Benefit: no new tables. Type safety preserved.
- Cost: adding a type requires a deploy. The workflow spec's
  "configurable by an administrator" requirement is not met.

**Alternative F2 — Hybrid.** Keep `EventType` for running
events. Add `event_categories` and `event_types` DB tables for
church events. `events` gains a nullable `event_category_id`
and `event_type_id`, populated only for church events.

- Benefit: running path untouched. Church types are
  admin-configurable. Reports can scope on category or type.
- Cost: two parallel systems. Validation branches on category.
  The UI must render types from two sources.

**Alternative F3 — Move everything to DB tables.** Deprecate
the `EventType` enum. `Community Run` and `Fun Run` become
seeded rows in `event_types`.

- Benefit: one system.
- Cost: every reference to `EventType` (`isCommunityRun()`,
  `isFunRun()`, `Rule::enum`, all tests, all frontend types)
  must be migrated. High blast radius.

**Alternative F4 — Enum for all types, no DB tables.** Add
church types to the enum but do not implement the mockup's
"Add Category" flow.

- Benefit: simplest change.
- Cost: contradicts the workflow spec's configurability
  requirement.

### Provisional recommendation

**F2.** The running path is stable, tested, and specialized.
Adding church types to the enum (F1, F4) either fails the
configurability requirement or contaminates every branch on
`event_type`. Moving everything to DB tables (F3) is a
disproportionate refactor. F2 keeps the two systems separate at
the cost of some duplicated validation and rendering logic.

### Risks

- If F2 is chosen, the "Categories" sidebar item and admin
  management UI must be built, which the mockup shows but the
  scope has not confirmed.
- If the client later wants running categories to also be
  configurable, F2 becomes F3, which is the more invasive
  change.

---

## Findings for review

1. **No filesystem access.** Every finding above rests on files
   pasted into the audit session. The grep for payment code and
   `php artisan migrate:status` were not run. Confirm the
   limitation is acceptable or provide the outputs.

2. **Eight documentation files were not inspected:**
   `implementation-notes.md`, `design-system.md`,
   `event-creation.md`, `event-creation-wizard.md`,
   `event-creation-issues.md`, `event-deletion.md`,
   `development-log.md`, `demo-email.md`. At least four are
   likely to contain constraints relevant to Church Events.
   Recommend pasting them before document 02.

3. **No test files were inspected.** The audit cannot say which
   existing tests will break or which will need companions.
   Recommend pasting `tests/Pest.php` plus the file list under
   `tests/Feature/Events/` and `tests/Feature/Public/` before
   document 05.

4. **The workflow spec's ten-state lifecycle and the mockup's
   four status labels do not agree.** This is the single most
   important unresolved decision. Recommend confirming with the
   client whether the ten-state chain is a requirement or a
   discovery artifact.

5. **No payment code was found in inspected files.** The prior
   conversation's "Fun Run out of scope because of payments"
   concern does not match the code. Recommend running the grep
   and confirming.

6. **`event_categories` and `event_types` DB tables do not
   exist.** The mockup shows a management UI for them. Whether
   this is a requirement or a sketch is unresolved.

7. **No occurrence support exists.** Any recurring-services
   requirement is net-new. This is the largest schema addition
   in the proposed scope.

8. **No approval workflow exists.** No role, permission, queue,
   or audit trail. This is net-new.

9. **ISSUE-004 is wider than documented.** The known-issues
   entry describes "no authorization policy on event edit." The
   audit finds no policy exists for any event, registration,
   attendance, deletion, or report action. Any Church Events
   authorization work should either expand ISSUE-004 or
   introduce a parallel policy structure that also covers
   running events.

10. **`docs/architecture.md` has known stale claims** (dropped
    tables in the deletion algorithm, `google_form_response_id`
    column that does not exist, `EmailTwoFactorResult::TooSoon`
    case that does not exist). If document 03 cites
    `architecture.md` as authoritative, it will inherit the
    stale claims. Recommend re-verifying against source or
    citing the corrections blocks.

---

## Audit conclusion

No application source code, database migrations, enums,
configuration, or tests were modified during this audit.

One file was created: `docs/church-events/01-current-system-audit.md`.

Awaiting review before proceeding to document 02.

---

## Review response (2026-10-16)

**Status:** The A–F recommendations in the section above have
been reviewed. None are approved. This block records the review
outcomes and supersedes the "Provisional recommendation"
sub-section for each of A–F. The alternative comparisons stand.

The reviewer's position on all six items is **defer selection
until the underlying requirement is confirmed and specific
verifications are completed**. The audit's recommendations were
premature; they are withdrawn here, not rewritten in place (P7).

### A — Status workflow: deferred

**Reviewer position.** A2 and A3 are plausible but not ready to
be selected. The crucial question is whether the detailed church
workflow is a genuine, approved state machine, or whether some
stages are a checklist while the dashboard continues to display
simplified statuses.

**Withdrawn.** The line _"A2 or A3, with A3 preferred"_ is
withdrawn. No alternative is selected.

**Reviewer note on persistence.** Creating a `ChurchEventStatus`
enum does not isolate the workflows by itself. A deliberate
design is required for how the value is stored (nullable
`church_status` column, side table, or reuse of `status`) and
how the `Event` model casts it. The audit did not address
persistence design and should not have implied the enum alone
was sufficient.

**Prerequisite decisions before A can be selected.**

1. Is the workflow specification approved as a requirement, or
   is it a discovery artifact?
2. If approved: are all ten stages mandatory for every category,
   or do low-ceremony categories (Services, Church
   Administration) skip stages?
3. Where do the mockup's four display labels (`Upcoming`,
   `Open Registration`, `Ongoing`, `Completed`) fit — are they
   derived display states, or actual state values?

### B — Fun Run payments: verification only

**Reviewer position.** B2 (run the corrected search) is correct.
Additional qualification: an empty grep establishes that no
matching references were found in the searched locations. It
does not establish whether payments were originally intended or
are required by the client.

**Withdrawn.** The line _"B2 then B1"_ is withdrawn. B1's claim
that Fun Run has no payment behavior is unsupported until the
search runs **and** the product question is answered separately.

**Product question remains open.** Was payment behavior for Fun
Run planned but never built, or was it always out of scope? A
grep cannot answer this.

### C — Approval and permissions: deferred

**Reviewer position.** C2 may be simplest if administrators are
confirmed as the approvers. But the mockup's combined
_"Admin / Event Organizer"_ label does not establish who may
approve events or whether a creator may approve their own
submission.

**Withdrawn.** The line _"C2 for the first release, C3 if
per-event assignment becomes required"_ is withdrawn. No
approver schema (`approver_id`, `requires_approval`, or a new
role) should be added until the policy is confirmed.

**Prerequisite decisions before C can be selected.**

1. Who approves — admin-only, per-event assignment, or a new
   role?
2. May the event creator approve their own proposal?
3. Is rejection terminal, or can a rejected proposal be
   revised and resubmitted?
4. What is the audit-trail requirement for approval decisions?
5. Does approval interact with event ownership, which reopens
   ISSUE-004?

### D — Recurring events and occurrences: deferred

**Reviewer position.** Confirm how attendance is recorded
before choosing a schema. Against what entity?

**Withdrawn.** The line _"D2 or D3"_ is withdrawn. The schema
is undecided.

**Prerequisite decisions before D can be selected.**

1. What is the entity attendance is recorded against?
   Registrations (current running-event model), a church member
   list, walk-ins, or some combination?
2. If a church member list is needed: does a `members` table
   exist, and does it relate to `participants`?
3. Does each occurrence carry its own status, or does the
   parent event carry status and occurrences are pure child
   records with only date/time?
4. For a service with attendance against a member list, are
   unregistered attendees allowed? Do they become
   `participants`, or are they counts only?

**Reviewer note on D1.** D1 (each service date is an
independent Event row) is only compatible with the workflow
spec's _"each occurrence/session must have its own date,
status, attendance, and notes"_ if each occurrence is treated
as a full Event — which defeats the "recurring schedule"
concept.

### E — Budget and expenses: deferred

**Reviewer position.** Push back on selecting E1 for v1. If the
approved workflow requires recording actual expenses and
submitting liquidation/reconciliation documents, a budget
amount and notes alone may not satisfy those requirements. If
finance functionality is deferred, the deferral must be
explicitly agreed.

**Withdrawn.** The line _"E1 for the first release"_ is
withdrawn.

**Prerequisite decision.** What is the minimum financial
requirement for the first release? Three tiers remain on the
table:

- **Tier 1** — Budget amount + notes + a PDF upload for
  reconciliation
- **Tier 2** — Line items (`category`, `description`, `amount`)
  with a computed total + PDF upload
- **Tier 3** — Line items + receipt attachments + per-item
  reconciliation status + approval

The tier must be chosen before any schema is designed.

**Explicitly noted.** Do not add `budget_amount`, `budget_notes`,
or an `event_attachments` table until this is confirmed.

### F — Categories and event types: leading candidate, verification pending

**Reviewer position.** F2 is a reasonable leading candidate for
minimizing disruption, but it is not yet proven to be the safest
design. Specific verifications are required.

**Retained, not selected.** F2 is retained as a **leading
candidate**, not a selection. The line _"F2"_ as a
recommendation is softened here.

**Prerequisite verifications before F can be selected.**

1. **The original events migration.** Whether `event_type` is
   nullable, required, or constrained. The audit read the
   `Event` model's `casts()` and `#[Fillable]` but did not
   inspect the `create_events_table` migration. This is
   material.
2. **All consumers of `event_type`.** The audit identified
   `isCommunityRun()`, `isFunRun()`, `Rule::enum()`,
   `ReportService::cross_event()`. A grep is required to
   confirm the list is complete before deciding how to extend
   it.
3. **The plan for the existing column under F2.** If church
   events populate `event_category_id` and `event_type_id`,
   what value does `events.event_type` take for a church row —
   `NULL`, a sentinel case like `EventType::Church`, or is the
   column left unchanged and only the model branches on
   category?
4. **Comparison with F3.** An all-DB-managed type system
   (F1/F4 ruled out by the configurability requirement) needs a
   costed comparison before F2 is final.

---

## Review response — conclusions

**No alternative in A–F is selected.** The alternative
comparisons in the audit remain the useful output; the
"provisional recommendation" sub-sections are withdrawn.

**Document 02 cannot be written accurately** until the
following are answered. Items 1–5 are product decisions. Item 6
requires a command to be run.

1. Is the Church Events workflow specification an approved
   requirement, a client sketch, or a discovery document?
2. How is church attendance recorded, and against what entity?
3. Is budget and expense reconciliation in scope for the first
   release, and at which tier?
4. Who approves, under what conditions, with what audit trail?
5. Was Fun Run payment behavior planned but never built, or
   always out of scope?
6. Does `events.event_type` participate in any constraint, and
   is the audit's list of `event_type` consumers complete?

**Additional verification the audit requires from the
repository.** Each requires a paste back — the auditor cannot
run commands.

    grep -rn -i "payment|stripe|paymongo|checkout|paid_at|amount_paid" \
      app/ resources/js/ database/ routes/ tests/

    cat database/migrations/*_create_events_table.php

    grep -rn "event_type\|EventType::" app/ resources/js/ \
      database/ routes/ tests/

Once those outputs are provided, `01-current-system-audit.md`
can be revised again (append-only) with verified findings, and
document 02 can proceed on accurate scope.

**No implementation decision is made by this review response.**
The audit's §4 impact map stands unchanged. The audit's
conclusion that no code, schema, migrations, enums, or tests
were modified stands unchanged.
