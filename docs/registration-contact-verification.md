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
