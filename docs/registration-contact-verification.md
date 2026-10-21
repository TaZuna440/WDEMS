# Participant contact verification

Current implementation reference, added 2026-10-13. Read with
`docs/dev-workflow/README.md`, `docs/registration.md`,
`docs/participant-identity.md`, and `docs/authentication.md`.
Historical descriptions of immediate public registration are superseded by
this workflow. Attendance walk-in and bulk paper intake keep their existing
participant-resolution contract.

## Public registration

1. Submit the existing form at `/r/{registration_slug}`. Validation uses
   `Event::isCommonFieldRequired()`; omitted settings retain the existing
   required default. No Participant, Registration, or field response is
   created at this point.
2. The browser receives an encrypted, HttpOnly, SameSite=Lax cookie scoped to
   that event path. Continue in that browser at `/r/{registration_slug}/verification`.
   The random bearer token is never placed in a URL or page props. The database
   stores its SHA-256 hash and an encrypted pending payload.
3. Required email receives a six-digit code through the existing Resend mailer.
   Required phone waits for an authorized organizer to generate a code and
   communicate it externally using their own phone. WDEMS sends no SMS.
4. Submit each required code. One verified channel cannot bypass the other.
   Then select **Complete registration**. Confirmation appears on the
   verification page only after the final transaction succeeds.

The screen distinguishes pending, delivery failure, cooldown, invalid code,
code expiry, attempt lockout, challenge expiry, changed requirements, closed
registration, and completion. Refresh status after staff generate a phone code.
Email send failure invalidates the new code, keeps the challenge pending, and
allows retry after cooldown. No provider exception details are shown or logged.

## Staff phone queue

**Phone verification** in the sidebar opens `/registrations/verification`.
The existing `auth`, `verified.or.admin`, and `device.trusted` middleware apply.
The authorization check permits administrators or a staff member who created
that event. Other staff cannot generate codes for it or see it in their queue.
Unknown roles are denied. Existing administrator email/device exemptions and
staff's `email_two_factor_enabled` setting are respected.

The queue contains the event name, submitted phone, timestamps, and verification
state; it excludes names, emails, addresses, custom answers, and bearer secrets.
Generating a code returns it once to that authorized request. The UI shows it
in a transient dialog and conceals it on close, hidden tab, or expiry. There is
no retrieval endpoint or plaintext code stored in the database. A lost code
requires regeneration after cooldown. Communicate it only to the submitted
number; WDEMS cannot attest how staff perform that external step.

## Persisted security controls

- Codes use `App\Services\Otp\OtpCode`: secure random six digits, HMAC storage,
  and constant-time matching. Code lifetime is ten minutes.
- A channel can issue another code after 60 seconds. Regeneration invalidates
  the previous code. Five incorrect attempts lock that channel for the entire
  challenge; generating new codes does not reset the count.
- Successful verification consumes the code and records that channel's verified
  timestamp independently. Replay is rejected.
- The challenge lasts at most 24 hours, including after successful individual
  verification. Current event association, status, requiredness, and finalization
  state are checked server-side. Changed requiredness requires a fresh submission.
- Payload validation runs again at finalization. Address length is capped at 255
  to match the existing participant database column. Custom response keys must
  be event-owned canonical IDs; response count and string length are bounded.
- All mutations use database transactions, taking event and challenge row locks
  in a consistent order. Identity matches are locked; existing unique indexes
  remain in place. Unique-constraint races are retried safely. The participant,
  registration, answers, and finalized marker commit together or all roll back.
- Same-challenge completion retries are idempotent. A new challenge for an
  already registered identity receives a generic organizer-assistance error.
- Existing web CSRF middleware protects mutations. Routes have IP/user request
  throttles in addition to persisted challenge limits. Sensitive responses use
  no-store, no-referrer, noindex headers and encrypted Inertia history.
- Application error logging in this module contains only event/challenge IDs;
  no raw OTP, token, contact values, transport details, or secret links.

## Identity and optional fields

Only required, successfully verified identifiers participate in global
Participant lookup or creation. Optional email/phone never triggers verification
or updates an existing participant, even when supplied. Both submitted values
are retained on Registration as `submitted_email` and
`submitted_contact_number`, including when an existing participant is reused.
Existing records have nullable snapshots; there is no historical backfill.

Verified email and normalized phone that match the same participant reuse it
without replacing its details. If they match different participants, finalization
stops with a non-revealing error and no merge or partial writes. Reconciliation
remains an organizer task; there is no automatic merge interface.

The form builder already forbids both contacts optional. Legacy invalid events
with both optional now reject public submissions safely; fix their requirements
through the existing event form builder. Names and optional contacts cannot
substitute for a verified identity.

## Deployment and retention

Deploy both additive `2026_10_13_100000` and `2026_10_13_100100` migrations with
the completed application code through the normal release process. Preserve
`APP_KEY`; it protects pending payloads and cookies. Use the existing Resend
credentials and sender configuration. This module explicitly chooses the Resend
transport so a default log mailer cannot write participant codes to logs.

Run the normal Laravel scheduler. `routes/console.php` prunes expired challenge
rows hourly through `model:prune --model=App\Models\RegistrationVerificationChallenge`.
Successful finalization clears pending payload and code hashes immediately.
Abandoned challenges are rejected at expiry even if the scheduler is stopped;
physical deletion then waits for the scheduler or a manual prune run. Cookie
lifetime also ends after 24 hours. Browser loss requires a new submission.

## Automated verification

Normal tests use `phpunit.xml` SQLite `:memory:` and `Mail::fake()`; they need no
provider credentials. Build assets first with `npm run build` for existing
Inertia rendering tests. Supply a synthetic APP_KEY to tests when the sandbox
has no environment file. Do not cache configuration or run tests against the
local development database.

`tests/Feature/Public/RegistrationVerificationTest.php` covers gates, security,
mail failure, snapshots, conflict handling, middleware, CSRF, pruning, and retry
rollback. Public registration regressions complete the real verification
endpoints through `tests/Support/PublicVerification.php`.

The separate concurrency suite deliberately requires an empty disposable
MySQL-compatible database named `wdems_verification_test`. It resets that database.
It is excluded from the ordinary in-memory suite. With test-only connection
variables and a synthetic APP_KEY exported, run:

```sh
WDEMS_CONCURRENCY_TEST=1 DB_CONNECTION=mysql DB_DATABASE=wdems_verification_test \
  php artisan test tests/Integration/RegistrationVerificationConcurrencyTest.php
```

Two subprocesses compete for a locked event row and verify same-challenge retries
and independent duplicate submissions. The test asserts exactly one participant,
registration, answer, and finalized challenge. It was exercised on MariaDB 10.11
with InnoDB; production MySQL and actual Resend delivery require deployment
verification. Staff's external phone communication remains a manual operation.

---

## Corrections (2026-10-14)

Append-only. The body above describes the staff-mediated phone flow
that shipped on 2026-10-13. Commit `b552cbb` replaced that channel
with Firebase Phone Auth on 2026-10-14. Where this section disagrees
with the body, this section wins.

### §1 step 3 — phone is now Firebase-mediated

Body reads:

> Required phone waits for an authorized organizer to generate a code
> and communicate it externally using their own phone. WDEMS sends no
> SMS.

Correction. Required phone is verified by Firebase Phone Auth in the
browser. The user completes a reCAPTCHA, receives an SMS code sent by
Firebase (not WDEMS), enters the code, and the Firebase SDK returns an
ID token. The frontend posts the token to
`POST /r/{slug}/verification/phone/firebase`. The service verifies the
token with the Admin SDK and, on success, sets `phone_verified_at` on
the challenge.

WDEMS still does not send an SMS. The SMS is sent by Google's Firebase
service, not by WDEMS. Firebase credentials live server-side at
`storage/app/firebase/service-account.json`; the web SDK config lives
in `.env` under `VITE_FIREBASE_*`.

### §1 step 4 — the phone code input is now a reCAPTCHA + SMS widget

Body reads:

> Submit each required code. One verified channel cannot bypass the
> other.

Correction. Email is still a six-digit code input. Phone is now a
reCAPTCHA checkbox, a "Send verification code" button, and a
six-digit SMS input. The two channels still cannot bypass each other —
`allVerified()` remains the gate before finalization.

### §2 — the entire staff phone queue section is obsolete

Body describes:

- The queue at `/registrations/verification`
- Staff generating codes via `registration-verification.phone-code`
- `authorizeStaff()` gating code generation on event ownership

All of this is gone. The queue page is deleted, the sidebar entry is
removed, the controller actions and service methods are deleted, the
route group is removed, and the two rate limiters are removed.

The Firebase path is deliberately unauthenticated on the public side:
the participant's own phone is the authenticator. No staff action is
required. If Firebase fails, the participant retries — there is no
manual fallback.

### §3 — persisted security controls apply to email only

Body describes six-digit codes, ten-minute lifetime, sixty-second
resend cooldown, five-attempt lockout, single-use success, and
`OtpCode` HMAC storage. All of that still applies — but to the
**email** channel only.

The phone channel is verified by Firebase. There is no code stored on
the challenge, no resend cooldown, and no code expiry — those are all
Firebase's responsibility. What the challenge still records for phone:

- `phone_verified_at` — set when Firebase verification succeeds
- `phone_attempts` — incremented on invalid token, missing phone claim,
  or phone mismatch. Lockout at `MAX_ATTEMPTS = 5` still applies.

The 24-hour challenge TTL, transaction locking, address length cap,
CSRF, and no-PII-in-logs invariants all apply unchanged to both
channels.

### §4 — phone is no longer optional

Body reads:

> Optional email/phone never triggers verification or updates an
> existing participant, even when supplied.

Correction. Phone is now structurally required on every event.
`Event::COMMON_FIELDS_TOGGLEABLE` no longer contains
`contact_number`; it lives in `COMMON_FIELDS_ALWAYS_REQUIRED`
alongside `first_name`, `last_name`, and `age`. The form builder's
common-field toggle for phone is disabled.

Consequence. The scenario the body describes — "optional phone never
triggers verification" — can no longer occur. Email remains optional.
Address remains optional. Phone does not.

### §6 — automated verification, updated

Body names `tests/Feature/Public/RegistrationVerificationTest.php`.
That file remains the primary suite and still passes — but the six
staff/queue tests inside it are deleted.

New: `tests/Feature/Public/RegistrationVerificationFirebaseTest.php`
covers the Firebase token path with a stubbed Admin SDK. Six tests:
matching phone, matching across normalized formats, mismatched phone,
missing `phone_number` claim, invalid token, and already-verified
guard.

The stub constructs a real `Lcobucci\JWT\Token\DataSet` and returns it
from a `Mockery::mock(UnencryptedToken::class)` whose `claims()` method
is stubbed. This satisfies `verifyIdToken()`'s declared return type
without needing a real Firebase project.

### New configuration

    config/firebase.php                         published config
    storage/app/firebase/service-account.json   Admin SDK credentials
    .env  VITE_FIREBASE_API_KEY                 web SDK config
    .env  VITE_FIREBASE_AUTH_DOMAIN
    .env  VITE_FIREBASE_PROJECT_ID
    .env  VITE_FIREBASE_APP_ID
    .env  FIREBASE_CREDENTIALS                  path to service account
    .env  FIREBASE_PROJECT_ID

`config/firebase.php` wraps `FIREBASE_CREDENTIALS` in `base_path()` so
a relative path in `.env` resolves from the project root. Without the
wrap, `Kreait\Firebase\Factory` cannot locate the file and fails with
"Unable to determine the Firebase Project ID".

`storage/app/firebase/` is gitignored. The service account JSON must
never be committed.

---

## Correction: attendance code on finalize (2026-10-14)

The "Public registration" section above describes step 4 as ending
at "Complete registration". Commit `d05c7e7` extended the finalize
transaction to also generate the participant's attendance code and
dispatch a confirmation email.

The email is sent via `DB::afterCommit()`, so a transport failure
never rolls back the registration. The code is also rendered on the
confirmation page immediately. If the mail fails silently, the
participant still has the code on the page and in the browser
cookie.

No other part of the verification flow changed. Email OTP, Firebase
phone auth, challenge state machine, and the finalize transaction
boundary all behave as before.

Full reference: `docs/attendance-code.md`.

---

## Corrections (2026-10-15)

Append-only. The body and the 2026-10-14 correction describe the
phone channel as structurally required and the duplicate check as
a finalize-time concern. Commit `1f0d07f` changed three things on
2026-10-15. Where this section disagrees with the body or the
earlier correction, this section wins.

### Phone is toggleable again

The `## Corrections (2026-10-14)` block reads:

> Phone is now structurally required on every event.
> `Event::COMMON_FIELDS_TOGGLEABLE` no longer contains
> `contact_number`; it lives in `COMMON_FIELDS_ALWAYS_REQUIRED`
> alongside `first_name`, `last_name`, and `age`.

Correction. `contact_number` moved back to
`COMMON_FIELDS_TOGGLEABLE`. The form builder's phone checkbox is
active again. Organizers may publish events where phone is
optional, matching the email channel.

The at-least-one rule still applies — the form builder rejects a
save where both `email` and `contact_number` are toggled off.
Whatever channels are required must be verified before
finalization. Email-only events verify email only. Phone-only
events verify phone only. Both-required events verify both.

### Duplicate check runs at begin()

The body describes the same-event duplicate-registration check as
a finalize-time concern:

> Run the same-event duplicate-registration check against the
> resolved verified participant.

Commit `1f0d07f` adds an earlier check in `begin()` — immediately
after pending-payload validation, before the challenge row is
created. The early check rejects a submission whose submitted
email or normalized phone already belongs to a registration on
the same event. It runs regardless of whether the channel is
required.

The finalize-time check remains. It is the backstop for two
challenges for the same identity that were opened before either
finalized.

### reCAPTCHA is hoisted above both channels

The `## Corrections (2026-10-14)` block describes the Firebase
reCAPTCHA as rendered inside the phone card. Commit `1f0d07f`
moved the widget container to a dedicated "Human verification"
section above both contact cards.

The card renders whenever any required channel still needs work.
For events with phone required, the widget gates the SMS send
(unchanged). For events with phone optional and only email
required, the widget is a visual checkpoint — the solved token is
not consumed server-side. A server-enforced gate for email-only
events is a separate integration, not part of this flow.

---

## Code acknowledgement (2026-10-15)

Commit `57c89f3` added an explicit acknowledgement step between
finalization and completion. Append-only; the sections above
describe the flow before the acknowledgement layer.

### What changed

After the finalize transaction commits, the participant lands on
the verification page and sees their Registration Code. Before the
flow closes, they must click a confirmation button ("I've saved my
Registration Code — finish"). This records `code_acknowledged_at`
on the challenge row.

The verify page's `publicState()` now returns one of two states for
a finalized challenge:

- `'finalized'` — the code is displayed and the acknowledgement
  button is available.
- `'acknowledged'` — the code is hidden; the acknowledgement button
  is gone.

### New column

`registration_verification_challenges.code_acknowledged_at`,
nullable timestamp, after `finalized_at`.

### New routes

    POST /r/{slug}/verification/acknowledge-code    → acknowledge()
    GET  /r/{slug}/registered                        → confirmed()

`acknowledge-code` runs under the existing
`registration-verification-attempt` throttle. Idempotent — a
repeated POST with the original cookie is a no-op.

`/registered` sits outside the `EnsureRegistrationIsOpen` group
but inside `ProtectRegistrationVerification`, so the code-free
completion page remains reachable after registration closes. The
page name is `registrations/confirmed`, wired to `PublicLayout` in
`app.tsx`.

### Cookie lifecycle

`acknowledge()` clears the challenge cookie with a negative
expiry and the same path, flags, and SameSite attribute used when
the cookie was set in `PublicRegistrationController::store()`. A
subsequent GET on `/verification` with the same cookie hits
`show()`, sees `'acknowledged'`, and redirects to `/registered`.

### Rename — "attendance code" → "Registration Code"

Participant-facing strings (email subject, blade email body,
verify page UI, mail docblock) were renamed. The code value itself
is unchanged. The new label reflects what the code is: a lookup
key the participant presents at the event table, not an attendance
marking.

## Running Events audit addendum — 2026-10-21

Version 2 uses email-only verification with reCAPTCHA, 100-second cooldown, and a per-email five-attempt/two-hour lock shared across resends and replacement challenges. Published version 1 channel choice/Firebase compatibility remains intentional. Closing intake preserves existing challenge expiry; Completed/Cancelled prevent completion. Similarity review now occurs at Step 2, before challenge creation.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.

## Participant form sequence update — 2026-10-21

Following Mark's participant walkthrough: the public birthdate control is now one
numeric MM/DD/YYYY input (month, then day, then year), with calendar validation;
existing age limits remain enforced. Partial typed dates are saved with the draft.
Personal and emergency mobile inputs have a fixed +63 prefix and normalize pasted
09 numbers. Version 2 server validation also enforces Philippine mobile numbers.
Version 2 emergency contacts retain separate first/last names and now offer Parent,
Guardian, or Other radio choices; Other requires 2–15 letters/spaces/apostrophes/
hyphens including at least one letter. Stored legacy relationship choices remain
accepted for existing records and pending challenges.

Email-only verification displays reCAPTCHA, then Send code, then the OTP input
after delivery. Reloading that page restores the server-side challenge state;
reCAPTCHA itself must be completed again when a fresh token is required. Legacy
published verification contracts remain supported. Disclaimer and confirmation
code/barcode content are unchanged. Confirm acknowledges the code, expires only
the event's verification cookie, clears the event's local draft, and returns to
the form. It does not sign out staff or clear other events' drafts/device trust.
The wizard retains its autosaved draft during submission failures and verification;
it is cleared by the explicit final Confirm action.
