# WDEMS — Fixed Problems

**Purpose:** A single, scannable record of every bug, security issue,
and infrastructure problem that has been identified and fixed. Answers
the question: "was this ever broken, and what did we do about it?"

**Audience:** Whoever joins the project later and needs to know what
changed before their arrival.

**Relationship to other docs:**

| Doc | What it holds |
|---|---|
| `docs/known-issues.md` | Open problems — what is still broken |
| `docs/progress.md` | Dated changelog — every change made per session |
| `docs/fixes.md` (this file) | Fixed-only reference — every problem that has been resolved, with evidence |
| `docs/development-log.md` | Narrative history — the story |
| Subsystem docs | Deep detail on current state |

**Append-only.** When a fix lands, add an entry. Do not edit existing
entries — if an earlier entry is found to be wrong, add a note in the
entry's `Corrections` section rather than rewriting it.

---

## Index

| ID | Title | Severity | Date | Subsystem |
|---|---|---|---|---|
| FIX-001 | Auth pages showed the Laravel mark | Cosmetic | 2026-09-25 | Auth / UI |
| FIX-002 | Email verification gate was a no-op (ISSUE-005) | High | 2026-09-25 | Auth |
| FIX-003 | Admin locked out of email verification gate | Medium | 2026-09-25 | Auth / RBAC |
| FIX-004 | Logout forced 2FA on every login (ISSUE-006) | Low | 2026-09-25 | Auth / 2FA |
| FIX-005 | Mail delivery was routed to `log` not Resend | Medium | 2026-09-25 | Environment |
| FIX-006 | Database wiped by `config:cache` in local dev | High | 2026-09-25 | Environment |
| FIX-007 | Demo accounts not reproducible after a reset | Low | 2026-09-25 | Database / Seeder |

---

## How to read an entry

Each fix records the same shape:

- **What was broken** — symptom as observed
- **Root cause** — the specific code or configuration that caused it
- **What changed** — files touched, one line each
- **Evidence** — how we know it is fixed (tests, manual verification, or both)
- **Related** — cross-references to `known-issues.md`, `progress.md`,
  or subsystem docs

---

## FIX-001 — Auth pages showed the Laravel mark

**Severity:** Cosmetic
**Date fixed:** 2026-09-25
**Related:** `docs/progress.md` (2026-09-25 entry)

### What was broken

Every auth page except `/login` rendered the Laravel starter kit logo
instead of the WDEMS mark:

- `/verify-device`
- `/verify-email`
- `/forgot-password`
- `/reset-password`
- `/confirm-password`

The visual inconsistency made the app feel unfinished. Users noticed.

### Root cause

`resources/js/components/app-logo-icon.tsx` contained the Laravel
starter kit SVG path. It was imported by `auth-simple-layout.tsx`,
which wraps all five affected pages.

`/login` was not affected because it uses `auth-login-layout.tsx`,
which imports the correct `WdemsLogo` component.

### What changed

| File | Change |
|---|---|
| `resources/js/components/app-logo-icon.tsx` | Replaced the Laravel SVG path with the WDEMS three-bar mark. Uses `currentColor` for the top bar and fixed `#B6FF3B` for the two lime bars |

The component's prop API (`SVGAttributes<SVGElement>`) was preserved,
so every call site kept working without modification.

### Evidence

Manual visual verification on all five pages. Each now renders the
WDEMS mark consistent with `/login`.

### Related

None. Standalone UI fix.

---

## FIX-002 — Email verification gate was a no-op

**Severity:** High
**Date fixed:** 2026-09-25
**Related:** `docs/known-issues.md` ISSUE-005 (marked Fixed),
`docs/progress.md` (2026-09-25 entry)

### What was broken

Unverified users could reach every route behind the `verified`
middleware:

- `/dashboard`
- `/events`
- `/events/create`
- `/settings/security`
- All other `auth, verified, device.trusted` routes

The `verified` middleware was listed in `routes/web.php` and
`routes/settings.php` but never enforced. The gate was a no-op.

Not caught earlier because every user in the system was created with
`email_verified_at => now()` — the `UserFactory` default, and the
Tinker snippets used to seed accounts. The bug only manifested for
users with a `null` `email_verified_at`, and no current code path
produced such a user.

### Root cause

`app/Models/User.php` did not implement
`Illuminate\Contracts\Auth\MustVerifyEmail`. Laravel's `verified`
middleware checks whether the authenticated user is an instance of
that interface before enforcing. Since `User` was not an instance, the
middleware short-circuited and passed every request through.

The trait is provided by `Illuminate\Foundation\Auth\User` (Laravel's
base class), but the **interface** must be declared on the class
explicitly.

### What changed

| File | Change |
|---|---|
| `app/Models/User.php` | Added `use Illuminate\Contracts\Auth\MustVerifyEmail;` and changed the class declaration to `class User extends Authenticatable implements MustVerifyEmail` |

No other code changed. No migration, no config, no route change.

### Evidence

**Diagnostic — before the fix.** A new test file was written and run
against the unfixed code. Two of four tests failed:

    Expected response status code [201, 301, 302, 303, 307, 308] but received 200.

The 200 is the unverified user reaching the dashboard. That failure
was the confirmation that the bug was real.

**After the fix.** All four tests pass. The `verified` middleware now
redirects unverified users to `verification.notice`.

Full auth suite went from 38 to 42 passing tests (at that point) with
no regressions.

### Related

- `docs/authentication.md` §Email verification
- `docs/known-issues.md` ISSUE-005
- Followed immediately by FIX-003, which addressed a side effect of
  this fix.

---

## FIX-003 — Admin locked out of email verification gate

**Severity:** Medium
**Date fixed:** 2026-09-25 (same session as FIX-002)
**Related:** `docs/progress.md` (2026-09-25 entry)

### What was broken

After FIX-002 activated the email verification gate, an admin account
with an unverified email could no longer reach `/admin/dashboard`.
They were bounced to `/verify-email` like a staff user.

The admin role is supposed to bypass email verification — mirroring
the existing admin bypass in `EnsureDeviceIsTrusted`, where admins
skip the 2FA challenge for the same reason (they control the server,
so the challenge protects against nothing they could not already do).

### Root cause

FIX-002 applied the email verification gate to **all** roles. Laravel
provides no built-in admin bypass for the `verified` middleware. The
fix required a custom middleware that adds the admin exemption, then
delegates to Laravel's default check for everyone else.

### What changed

| File | Change |
|---|---|
| `app/Http/Middleware/EnsureEmailIsVerifiedOrAdmin.php` | **New.** Extends `Illuminate\Auth\Middleware\EnsureEmailIsVerified`. Checks `$request->user()?->isAdmin()` first; if true, calls `$next($request)` directly. Otherwise delegates to `parent::handle()` |
| `bootstrap/app.php` | Registered `'verified.or.admin' => EnsureEmailIsVerifiedOrAdmin::class` as a middleware alias |
| `routes/web.php` | Main protected group changed from `['auth', 'verified', 'device.trusted']` to `['auth', 'verified.or.admin', 'device.trusted']`. The device-verification routes kept strict `'verified'` — an unverified user must verify before touching 2FA |
| `routes/settings.php` | Settings group changed from `['auth', 'verified', 'device.trusted']` to `['auth', 'verified.or.admin', 'device.trusted']` |

The framework's `verified` alias was **not** overridden. Routes that
want strict behavior can still use it. The new alias is explicit —
every route that uses it declares its intent on the line.

### Evidence

Two new tests in `tests/Feature/Auth/EmailVerificationGatingTest.php`:

    test('unverified admins can reach the dashboard')
    test('unverified admins can reach the admin dashboard')

Both pass. The two existing "unverified staff blocked" tests still
pass — staff is not exempt.

Full auth suite at end of session: **44 passed, 120 assertions**.

### Related

- `docs/authentication.md` §Admin exemption
- FIX-002 (the fix that created this side effect)

---

## FIX-004 — Logout forced 2FA on every login

**Severity:** Low (usability, no security impact)
**Date fixed:** 2026-09-25
**Related:** `docs/known-issues.md` ISSUE-006 (marked Fixed),
`docs/authentication.md` `## Corrections` block,
`docs/progress.md` (2026-09-25 entry)

### What was broken

Every logout forced a fresh 2FA challenge on the next login. Users
had to enter a new 6-digit code every single time they signed back
in, even on the same browser, even on the same day, even after
ticking "Remember this device for 30 days".

### Root cause

`AppServiceProvider::configureLogoutCleanup()` listened for the
`Logout` event and queued `Cookie::forget('wdems_trusted_device')`.
So every logout cleared the trusted-device cookie.

The intent was to prevent a shared browser from leaking trust to the
next user. But the middleware that reads the cookie already checks
trust on a **per-user** basis:
`EmailTwoFactorService::hasTrustedDevice()` only matches entries in
the current user's `trusted_devices` array. A cookie left behind by
a previous user is useless to a different user.

The listener added no security — only friction.

### What changed

| File | Change |
|---|---|
| `app/Providers/AppServiceProvider.php` | Removed `configureLogoutCleanup()`, its call in `boot()`, and four now-unused imports (`EmailTwoFactorService`, `Logout`, `Cookie`, `Event`) |
| `tests/Feature/Auth/LogoutDeviceTrustTest.php` | Assertion inverted — from `assertCookieExpired` to `assertCookieMissing`. Test name changed to "does not forget the device trust cookie on logout" |

No changes to `EnsureDeviceIsTrusted`, `EmailTwoFactorService`, the
settings page, or any route. The per-user trust list was always the
real defense and remained untouched.

### Evidence

**Unit test.** `LogoutDeviceTrustTest` passes with the inverted
assertion.

**Manual browser verification.** Logged in as the demo staff account,
completed the 2FA challenge, ticked "Remember this device", landed on
`/dashboard`. Clicked logout. Logged back in with the same
credentials. **No 2FA challenge. Redirected straight to dashboard.**

Full auth suite at end of session: **44 passed, 120 assertions**.

### Related

- `docs/authentication.md` `## Corrections` block
- `docs/known-issues.md` ISSUE-006

---

## FIX-005 — Mail delivery was routed to `log` not Resend

**Severity:** Medium
**Date fixed:** 2026-09-25
**Related:** `docs/demo-email.md`, `docs/progress.md` (2026-09-25 entry)

### What was broken

Verification and 2FA emails were being written to
`storage/logs/laravel.log` instead of being delivered through Resend.
The live email demo could not work.

### Root cause

`MAIL_MAILER=log` in `.env`. This is a sensible default for local
development, but the project's intent was to demonstrate live email
delivery. The setting had never been flipped.

### What changed

| File | Change |
|---|---|
| `.env` line 50 | `MAIL_MAILER=log` → `MAIL_MAILER=resend` |

No code changes. No config file changes. Just the environment.

### Evidence

Three checks, all green:

1. `php artisan tinker --execute="echo config('mail.default');"` printed `resend`
2. Test email sent to `pomasinejboy@gmail.com` — Resend dashboard showed "Delivered"
3. Full browser demo — verification email and 2FA email both delivered. 2FA code accepted. Dashboard reached.

### Related

- `docs/demo-email.md` — the repeatable procedure, §3 covers this setting
- `docs/AI-CONTEXT.md` §13 — the reverse operation (`config:cache`) is
  forbidden in local dev

---

## FIX-006 — Database wiped by `config:cache` in local dev

**Severity:** High (data loss)
**Date fixed:** 2026-09-25
**Related:** `docs/AI-CONTEXT.md` §13, `docs/progress.md` (2026-09-25 entry)

### What was broken

The `wdems` MySQL database dropped every table. All demo accounts
vanished. Login failed with "These credentials do not match our
records" even though the credentials were correct.

### Root cause

**Cascade, not a single mistake:**

1. `php artisan config:cache` was run during a mail debug session.
   This wrote `bootstrap/cache/config.php`.
2. From that point on, `php artisan test` ignored `phpunit.xml`'s
   `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` overrides, and
   connected to MySQL instead.
3. `RefreshDatabase` — used by every Feature test — saw a `migrations`
   table it did not trust, ran `migrate:fresh`, and dropped every
   table in `wdems`.
4. The migration chain then failed partway through, leaving the
   database half-built (users table existed but was empty; some
   columns missing; migrations table missing).

### What changed

Recovery, not code:

1. `rm -f bootstrap/cache/config.php`
2. `php artisan config:clear`
3. `php artisan migrate:fresh` — all 17 migrations ran cleanly
4. Re-seeded accounts via `php artisan db:seed`

Prevention — documented in two places:

- `docs/AI-CONTEXT.md` §13 — `config:cache`, `route:cache`, `view:cache`,
  and `optimize` are forbidden in local dev. Detection and recovery
  steps documented.
- `docs/AI-CONTEXT.md` §14 — pre-test checklist to confirm no config
  cache exists before running the suite.

### Evidence

- `php artisan tinker --execute="echo config('database.default');"`
  now prints `mysql` (correct for normal usage, overridden to `sqlite`
  only when no config cache exists)
- Full auth suite runs in ~4.5s against SQLite in-memory — the correct
  speed
- Two accounts present after re-seed

### Related

- `docs/AI-CONTEXT.md` §13 and §14
- FIX-007 (which made the accounts reproducible so this recovery is
  painless if it ever happens again)

---

## FIX-007 — Demo accounts not reproducible after a reset

**Severity:** Low
**Date fixed:** 2026-09-25
**Related:** `docs/demo-email.md`, `docs/progress.md` (2026-09-25 entry)

### What was broken

After the database reset from FIX-006, the demo and admin accounts
had no seeder. They had been created manually via Tinker in an earlier
session, and there was no code path to recreate them.

### Root cause

`DatabaseSeeder` only created `test@example.com` — a Laravel starter
kit leftover with no purpose for this project. There was no seeder
for the real accounts used in demos and manual testing.

### What changed

| File | Change |
|---|---|
| `database/seeders/DemoUsersSeeder.php` | **New.** Creates two accounts: `admin@wdems.test` (admin, verified, 2FA off) and `pomasinejboy@gmail.com` (staff, unverified, 2FA on). Idempotent — skips existing accounts. Guarded against production |
| `database/seeders/DatabaseSeeder.php` | Rewritten to call `DemoUsersSeeder`. Removed the `test@example.com` factory call |

Both seeders use `WithoutModelEvents` to prevent the `Registered`
event from firing — which would send a real verification email on
every seed.

`forceCreate()` is used instead of `create()` because
`email_verified_at` and `email_two_factor_enabled` are not in
`User::$fillable`.

### Evidence

- `php artisan db:seed` on a fresh database creates both accounts
- Running it twice in a row skips both (idempotent)
- Account count after cleanup: 2 (matching the design)

### Related

- `docs/demo-email.md` §4 (seeding the demo account) and §6 (reset
  procedure for repeat demos)
- FIX-006 (the reset that made this necessary)

---

## The three gaps that this file reveals

While writing the fixes above, three documentation gaps were found in
the existing docs. They are listed here so they are not lost.

### GAP-001 — `docs/authentication.md` does not mention FIX-002

The doc describes the email verification gate as working, without
noting it was broken and fixed. A reader today would not know
ISSUE-005 existed.

### GAP-002 — `docs/authentication.md` and `docs/architecture.md` do not
mention FIX-003

Neither doc mentions `EnsureEmailIsVerifiedOrAdmin` or the
`verified.or.admin` alias. Both are now part of the auth middleware
pipeline and should be described.

### GAP-003 — `docs/AI-CONTEXT.md` does not list `EnsureEmailIsVerifiedOrAdmin` in the never-touch list

Removing the admin check in that middleware would recreate the exact
lockout FIX-003 solved. The middleware should be added to the list in
§8.

These will be closed in follow-up appends. Not in this file — this
file only records them.

---

## Changelog

- **2026-09-25** — File created. FIX-001 through FIX-007 recorded.
  GAP-001 through GAP-003 identified as documentation follow-ups.

---

## Gap closures (2026-09-25)

The three gaps identified at the end of this file have been closed.
This is an append-only record — the original gap listing stays above.

| Gap | Closed by | Verified by |
|---|---|---|
| GAP-001 — `authentication.md` did not mention FIX-002 | `docs/authentication.md` `## Corrections — Email Verification` section appended | `grep -n "FIX-002" docs/authentication.md` returns a hit |
| GAP-002 — `authentication.md` and `architecture.md` did not mention FIX-003 | Both docs got a new section describing the `verified.or.admin` alias and `EnsureEmailIsVerifiedOrAdmin` | `grep -rn "verified.or.admin" docs/` returns hits in both files |
| GAP-003 — `AI-CONTEXT.md` never-touch list missing the middleware | `AI-CONTEXT.md` §16 lists both the middleware and the `MustVerifyEmail` interface | `grep -n "^## 16\." docs/AI-CONTEXT.md` returns a hit |

No further documentation follow-ups are pending from this session.

## Changelog addendum

- **2026-09-25** — GAP-001 through GAP-003 closed. See the three
  subsystem docs for the appended sections.

---

## FIX-008 — Event edit page crashed on any event with coordinates

**Severity:** High (blocked the entire edit flow)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` EC-01

### What was broken

Opening `/events/{id}/edit` for any event that had a venue picked on
the map threw `TypeError: data.venue_latitude.toFixed is not a function`.
The page did not render. Editing was impossible for those events.

### Root cause

Laravel's `decimal:7` cast returns a **string**, not a float. On the
edit page, `event.venue_latitude` was `"14.5995000"`, not `14.5995`.
`VenueStep.tsx` calls `.toFixed(6)` on those values. Strings do not
have `.toFixed`.

The same problem had already been solved once in the same file —
`edit.tsx` had a `normalizeDistance()` helper for the `decimal:2`
distance value. Latitude and longitude did not get the same treatment.

### What changed

| File | Change |
|---|---|
| `resources/js/pages/events/edit.tsx` | Added `normalizeCoordinate()` helper. Wrapped `event.venue_latitude` and `event.venue_longitude` in it before passing to `useForm` |

No changes to `VenueStep.tsx`. The fix normalizes at the boundary.

### Evidence

Browser: opened `/events/2/edit` for an event with coordinates set. The
page rendered. The map picker loaded with the marker in the correct
place. The coordinate display showed `14.593717, 120.988312`.

### Related

- `docs/event-creation-issues.md` EC-01

---

## FIX-009 — Client-side validation was emptiness-only; sub-errors did not route

**Severity:** High (UX dead-end and mis-routed server errors)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` EC-02, EC-06, EC-11

### What was broken

Two related problems:

1. **Empty-only validation.** The wizard's `validateCurrentStep` only
   checked whether required fields were empty. Any non-empty string
   passed, including keyboard mashing. The user could click Next
   through every step and only see the server error after clicking
   Create Event on the last step.

2. **Sub-errors did not route.** Server errors keyed
   `partners.0.name` could not be matched against the Extras step's
   `fields` array, which contains the bare name `partners`. The check
   `'partners' in errors` is always false. The wizard never jumped to
   the step with the error.

### Root cause

`wizard.tsx` had a fixed emptiness check with no room for per-step
format rules, and its error-jump used exact key matching against the
step's `fields` array.

### What changed

| File | Change |
|---|---|
| `resources/js/lib/event-validation.ts` | **New.** Four validators: `validateBasics`, `validateSchedule`, `validateVenue`, `validateExtras`. Each returns a `Record<fieldName, errorMessage>` |
| `resources/js/components/wizard.tsx` | Added optional `validate?: (data) => Record<string, string>` to `WizardStepConfig`. `validateCurrentStep` uses it when present, falls back to emptiness otherwise |
| `resources/js/components/wizard.tsx` | Error-jump effect now uses prefix matching: `k === f \|\| k.startsWith(f + '.')`, so `partners.0.name` routes to the step whose `fields` contains `partners` |
| `resources/js/components/wizard.tsx` | `children(stepId, errors)` — render function now receives merged errors so field-level `<InputError>` slots fire |
| `resources/js/pages/events/create.tsx` | Wired the four validators into `STEPS`. Render callback signature updated |
| `resources/js/pages/events/edit.tsx` | Same wiring. Also renamed the step label `Basics` → `Details` |
| `resources/js/pages/events/step/BasicsStep.tsx` | Renamed heading `Event Basics` → `Event Details`. Removed a dead `isEdit` ternary |

Server-side rules were also tightened to match: `partners` now has a
`max:20` cap (was unlimited).

### Evidence

Browser: `/events/create`, typing `fdhgfhgfhgfhfgfhdfgfr` in Event Name
and clicking Next no longer advances. Red text under the field:
**"Please enter a valid event name."**

The three original technical messages ("must contain at least one
vowel", "must contain at least one consonant", "cannot have three
identical characters in a row") were collapsed into a single
professional message. The length and starting-character messages are
retained because they are clear and useful.

`npx tsc --noEmit` clean.

### Related

- `docs/event-creation-issues.md` EC-02, EC-06, EC-11

---

## FIX-010 — No minimum event duration; end_time was not clearable

**Severity:** High (allowed nonsense data) + Medium (UX dead-end)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` EC-04

### What was broken

Two problems:

1. **No time ordering or duration rule.** A user could set `18:00 → 06:00`
   (end before start) or `18:00 → 18:30` (30-minute event). Both passed
   validation. There was no concept of a minimum event length.

2. **End time was not clearable.** Once the user picked an end time,
   there was no way to remove it. The field is optional, but the UI
   made it a one-way door.

### Root cause

`EventValidationRules` had `'end_time' => ['nullable', 'date_format:H:i']` —
format only, no comparison. `TimePicker.tsx` had no way to render a
clear button.

### What changed

| File | Change |
|---|---|
| `app/Concerns/EventValidationRules.php` | Added `public const MIN_EVENT_DURATION_MINUTES = 60`. `end_time` rule gained `after:start_time`. New `validateEventDuration()` method that runs via `Validator::after` and enforces the minimum when end is after start. New `eventMessages()` returns the custom `end_time.after` message |
| `app/Http/Requests/EventRequest.php` | Added `withValidator()` that calls `validateEventDuration()`. Added `messages()` that returns `eventMessages()` |
| `resources/js/lib/event-validation.ts` | `validateSchedule` now checks format → ordering → duration, in that order, and reports the most specific error first |
| `resources/js/components/time-picker.tsx` | Added `clearable` prop. Renders a small X button when set and a value exists |
| `resources/js/pages/events/step/ScheduleStep.tsx` | Passed `clearable` to the end_time picker only. Start time is required and stays non-clearable |

### Evidence

13 feature tests in `tests/Feature/Events/EventScheduleRulesTest.php`,
all passing. Boundary cases covered:

- `18:00 → 20:00` (120 min) — accepted
- `18:00 → 19:00` (60 min) — accepted
- `18:00 → 18:59` (59 min) — rejected
- `18:00 → 18:30` (30 min) — rejected
- `18:00 → 06:00` (end before start) — rejected
- `18:00 → 18:00` (equal) — rejected
- no end_time — accepted

Browser: X button appears on the end_time picker only. Clicking it
clears the field. See also FIX-012 for a follow-up UI bug in that
button.

### Related

- `docs/event-creation-issues.md` EC-04
- FIX-012 (TimePicker X button visual fix)

---

## FIX-011 — `course_url` accepted dangerous URL schemes and rejected the format the placeholder suggested

**Severity:** Medium (latent XSS) + Medium (UX trap)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` EC-05

### What was broken

Two problems with the same field:

1. **Laravel's `url` rule accepted `data:` and `javascript:`.** Both
   schemes pass `filter_var(FILTER_VALIDATE_URL)`. The client's
   `new URL()` check did the same. Since `course_url` is rendered as
   `<a href={event.course_url}>` on the show page, a `javascript:` URL
   would execute when a user clicked it. In WDEMS's single-user case
   this is theoretical. In a multi-user case it is a stored XSS vector.

2. **Scheme-less URLs were rejected.** The placeholder said
   "https://maps.app.goo.gl/... or a Strava link". Pasting
   `maps.app.goo.gl/xyz` — the format mobile share buttons actually
   produce — failed because neither the server nor the client accepted
   a URL without a scheme. Users had to manually prepend `https://`.

### Root cause

The `url` rule uses `filter_var`. It is permissive about schemes and
strict about them at the same time — it accepts `data:` but rejects
`example.com`. Same mismatch on the client side with `new URL()`.

### What changed

| File | Change |
|---|---|
| `app/Concerns/EventValidationRules.php` | Replaced `'url'` with `'regex:/^https?:\/\//i'` on `course_url` |
| `resources/js/lib/event-validation.ts` | Replaced the `new URL()` try/catch with the same regex. Error message changed to `"Course link must start with http:// or https://."` |
| `resources/js/pages/events/step/ScheduleStep.tsx` | Added `normalizeCourseUrl()` helper. Wired it to the input's `onBlur`. If the pasted value is non-empty and has no scheme, `https://` is prepended automatically |

### Evidence

5 feature tests in `EventScheduleRulesTest`:

- `https://` accepted
- `http://` accepted
- no scheme rejected (server side)
- `data:text/html,<script>...` rejected
- `javascript:alert(1)` rejected
- empty accepted

Browser: pasting `maps.app.goo.gl/xyz` and blurring the field produces
`https://maps.app.goo.gl/xyz`.

### Related

- `docs/event-creation-issues.md` EC-05
- SCH-03 (recorded in the same issues file)

---

## FIX-012 — TimePicker clear button left the previous value visible

**Severity:** Medium (data and display disagreed)
**Date fixed:** 2026-09-25
**Related:** FIX-010

### What was broken

After FIX-010, clicking the X on the end_time picker cleared the data
(`end_time` became `''`) but the hour Select still showed the previously
selected value. The minute showed `--`, the hour showed `3`. The user
saw a half-cleared field.

### Root cause

Radix's `<Select>` behaves differently when its `value` prop is
`undefined` versus when it is `''`:

- `value={undefined}` → the Select is **uncontrolled**. It keeps its
  last internal state and ignores the prop.
- `value={''}` → the Select is **controlled** with an empty value. It
  renders the placeholder.

The original code passed `undefined` when a field was empty:

    value={hour !== null ? String(hour) : undefined}

So the transition `"3" → undefined` flipped the component into
uncontrolled mode and froze the display.

### What changed

| File | Change |
|---|---|
| `resources/js/components/time-picker.tsx` | Both Selects now pass empty string `''` instead of `undefined` when the value is null |

Two lines changed.

### Evidence

Browser: pick an end time, click X, the hour, minute, and period all
reset to their empty states.

### Related

- FIX-010

---

## FIX-013 — Errors persisted after the user fixed the field

**Severity:** Low (annoyance, not a data problem)
**Date fixed:** 2026-09-25

### What was broken

Errors were only computed on Next or Submit. If the user clicked Next
with an invalid value and then fixed the field, the error message
stayed until the user clicked Next again. The banner at the top of the
wizard stayed too.

### Root cause

`validateCurrentStep` was only called from `handleNext` and
`handleSubmit`. No effect watched the form data for changes to
re-evaluate.

### What changed

| File | Change |
|---|---|
| `resources/js/components/wizard.tsx` | Added a `useEffect` gated on `[data, currentStep]` that re-runs `validateCurrentStep()` only when `stepErrors` is already non-empty. Fresh forms with no errors do not nag on every keystroke; errors only appear after Next/Submit and are then re-evaluated live |

The effect intentionally omits `stepErrors` and `validateCurrentStep`
from its dependency array to avoid loops. The guard inside handles the
"no visible errors" case.

### Evidence

Browser:

- Pick end time `3:00 AM`, start `5:00 AM`, click Next → error shows
- Change end time to `6:00 AM` → error clears without clicking Next
- Click X on end time → error under field clears, top banner clears
- Type in Event Name on a fresh form → no errors appear until Next is
  clicked

### Related

- FIX-009 (the validation layer this builds on)

---

## Change log addendum

- **2026-09-25** — FIX-008 through FIX-013 added. EC-01, EC-02,
  EC-04, EC-05, EC-06, EC-11 marked fixed in
  `docs/event-creation-issues.md`. New problem SCH-03 recorded and
  fixed in the same session.

---

## FIX-014 — Time picker forced 5-minute snap with no explanation

**Severity:** Low (silent data change on user input)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` SCH-04

### What was broken

`TimePicker` offered every minute in 5-minute intervals
(`0, 5, 10, ..., 55`). If a user entered an off-grid value such as
`12:03` — possible via keyboard — the parser snapped it silently to
`12:05` or `12:00`. No visual indication, no helper text. The user
might not notice until the saved event showed different times.

### Root cause

`MINUTES = Array.from({ length: 12 }, (_, i) => i * 5)` and
`Math.round(m / 5) * 5` in `parse()`. The 5-minute interval was a
design choice, but there was no way for the user to know it existed.

### What changed

| File | Change |
|---|---|
| `resources/js/components/time-picker.tsx` | `MINUTES` reduced to `[0, 15, 30, 45]`. Snap logic updated to `Math.min(45, Math.round(m / 15) * 15)`. Helper text was not needed — the dropdown itself only offers 15-minute options |

**Server rules unchanged.** The `date_format:H:i` rule still accepts
any `HH:MM`. Existing events with arbitrary minute values (e.g.
`12:23`) remain valid. Snapping them on save would be data loss. The
picker is the entry point for new values only.

### Evidence

Browser: the minute dropdown on both start and end time pickers shows
four options — `00`, `15`, `30`, `45`. `03`, `07`, `23` are not
present. `npx tsc --noEmit` silent.

### Related

- `docs/event-creation-issues.md` SCH-04

---

## FIX-015 — `event_date` had no bounds; calendar allowed any date

**Severity:** Medium (permitted stale and typo dates)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` SCH-06, SCH-07

### What was broken

`event_date` accepted any valid date — past, present, or year 2100. No
lower bound, no upper bound. A typo like `2206-09-25` passed silently.
An event could be created with a date fifteen years in the past and
appear on the dashboard as if current.

The calendar picker rendered every day as clickable. Past days and far
future days looked identical to valid days.

### Root cause

`EventValidationRules` had `'event_date' => ['required', 'date']` —
format only, no comparison to the current date. `DatePicker` had no
`minDate`/`maxDate` props to constrain the calendar grid.

### What changed

| File | Change |
|---|---|
| `app/Concerns/EventValidationRules.php` | Added `public const MAX_EVENT_YEARS_AHEAD = 2`. `event_date` rules gained `after_or_equal:today` and `before:+2 years`. New messages in `eventMessages()` for both rules |
| `tests/Feature/Events/EventDateRulesTest.php` | **New.** 8 tests covering: today accepted, tomorrow accepted, yesterday rejected, 5 years ago rejected, 2-years-minus-1-day accepted, 2-years-plus-1-day rejected, 3 years out rejected, 10 years out rejected |
| `resources/js/lib/event-validation.ts` | Added `MAX_EVENT_YEARS_AHEAD` const and `isoToLocalMidnight()` helper. `validateSchedule` compares the selected date against today and today + 2 years |
| `resources/js/components/date-picker.tsx` | Added `minDate` and `maxDate` props. Out-of-range day cells render disabled. The "Today" shortcut is disabled when today falls outside the range |
| `resources/js/pages/events/step/ScheduleStep.tsx` | Added `pickerBounds()` helper. Passed `minDate={bounds.min}` and `maxDate={bounds.max}` to the `<DatePicker>`. Updated helper text to "Today or later, up to 2 years from today." |

### Today is allowed — deliberately

`after_or_equal:today` permits same-day events. Two reasons:

1. Spontaneous community runs happen. Blocking today would force users
   to record them with a fake future date.
2. Same-day events are still useful for logging attendance and
   completing the workflow quickly.

But same-day events bypass the advance-registration window. To flag
this without blocking it, a soft warning was added in the same block.

### Today warning (SCH-06c)

| File | Change |
|---|---|
| `resources/js/pages/events/step/ScheduleStep.tsx` | Added `todayIso()` helper and `isScheduledForToday` check. When the selected date matches today, a small amber warning appears under the field |

Warning text:

    Heads up — events scheduled for today have no advance registration
    window. Participants may not see this in time.

The warning is not a validation error. The form still submits. It is a
heads-up.

### Evidence

Server tests: 8 passed in `EventDateRulesTest`. Existing
`EventScheduleRulesTest` (13 tests) unaffected.

Browser:

- Calendar grid: days before today are disabled (grayed, unclickable)
- Calendar grid: days past today + 2 years are disabled
- Today is clickable
- Selecting today shows the amber warning
- Selecting tomorrow hides the warning
- Clearing the date hides the warning
- Submitting with today's date succeeds

`npx tsc --noEmit` silent.

### Related

- `docs/event-creation-issues.md` SCH-06, SCH-07

---

## Change log addendum

- **2026-09-25** — FIX-014 (time picker intervals) and FIX-015 (date
  bounds + today warning) added. SCH-04, SCH-06, SCH-07 marked fixed
  in `docs/event-creation-issues.md`.

---

## FIX-016b — URL regex tightened to reject bare domains

**Severity:** Medium
**Date fixed:** 2026-09-25
**Related:** FIX-016, `docs/event-creation-issues.md`

### What was broken

FIX-016 introduced a URL regex that required a scheme and a host with
at least one dot. During browser testing, `https://maps.google.com`
was found to pass — it has a valid scheme, a valid host, and one or
more dots. But it points to the Google Maps homepage, not to a
specific location. A participant clicking the resulting "View Map"
link would land on Google Maps' landing page, not the venue.

The same problem applied to `course_url`. `https://maps.google.com`
was technically a URL, but functionally useless as a course link.

### Root cause

The FIX-016 regex made the path/query/fragment section optional:

    /^https?:\/\/[a-z0-9-]+(\.[a-z0-9-]+)+([\/?]\S*)?$/i

The trailing `?` on the group meant "zero or one". Combined with the
`*` inside, both the separator and the content after it could be
absent. A bare domain satisfied every required part.

### What changed

| File | Change |
|---|---|
| `app/Concerns/EventValidationRules.php` | `httpUrlPattern()` regex changed to require a path, query, or fragment with at least one more character after the separator. Messages for both URL rules updated to "must point to a specific location, like https://maps.app.goo.gl/abc123." |
| `resources/js/lib/event-validation.ts` | `HTTP_URL_PATTERN` updated to the same regex. Both error messages updated |
| `tests/Feature/Events/EventUrlRulesTest.php` | Six new tests: bare domain rejected for both fields, bare domain with trailing slash rejected for both fields, query string accepted, fragment accepted |

New regex:

    /^https?:\/\/[a-z0-9-]+(\.[a-z0-9-]+)+[\/?#]\S+$/i

The `([\/?]\S*)?` group became `[\/?#]\S+` — a required separator
followed by one or more characters.

### Behavior before and after

| URL | Before | After |
|---|---|---|
| `https://maps.google.com` | Accepted | **Rejected** |
| `https://maps.google.com/` | Accepted | **Rejected** |
| `https://waze.com/` | Accepted | **Rejected** |
| `https://maps.app.goo.gl/abc123` | Accepted | Accepted |
| `https://maps.google.com/?q=14.5,120.9` | Accepted | Accepted |
| `https://www.openstreetmap.org/#map=16/14.5/120.9` | Accepted | Accepted |

### Evidence

**Event test suite:** 54 passed, 145 assertions, 1.16 seconds. The
six new tests cover all four rejection cases and both new acceptance
cases.

**Browser verified:** on `/events/{id}/edit`, entering
`https://maps.google.com` in Map Link and clicking Next shows
"Map link must point to a specific location, like
https://maps.app.goo.gl/abc123." The wizard does not advance.

### Trade-offs

The stricter rule assumes a legitimate map or course URL will always
have at least a path segment after the domain. This holds for Google
Maps share links, Waze links, OpenStreetMap permalinks, Strava
activity pages, and course files. The only case it excludes is a
deliberate landing-page link — which was never the intent of the
field.

### Related

- FIX-016
- `docs/event-creation-issues.md`

---

## Change log addendum

- **2026-09-25** — FIX-016b recorded. URL regex tightened to reject
  bare domains.

---

## FIX-017 — RSVP flag removed from schema, UI, and validation

**Severity:** Medium (dead data modeling; removal, not a bug fix)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` (RSVP resolution section)

### What was broken

Not a bug. A boolean field carried forward from an earlier design where Community Run had one registration path. Once both pre-registration and walk-in became unconditionally available, the flag stopped deciding anything — but still appeared on the Extras step, still got validated, still got persisted, and still drove a copy line on the show page ("Required" vs "Not required — walk-ins welcome"). Every one of those was a single-branch outcome disguised as a user-facing choice.

### Root cause

Schema evolution without pruning. The wizard, the lifecycle, and the registration model all moved forward — the RSVP flag was never revisited. It survived two refactor passes because it was well-typed (boolean, cast, validated) and did not throw.

### What changed

| File | Change |
|---|---|
| `database/migrations/2026_09_25_170000_remove_rsvp_required_from_events_table.php` | **New** — `dropColumn('rsvp_required')`. `down()` re-adds it at `after('registration_end')` with `default(false)`, matching the original add-column migration |
| `app/Models/Event.php` | Removed `'rsvp_required'` from `#[Fillable]` and from `casts()` |
| `app/Concerns/EventValidationRules.php` | Removed `'rsvp_required' => ['boolean']` |
| `app/Http/Controllers/EventController.php` | Removed 4 references — 2 reads (`show`, `edit`), 2 writes (`store`, `update`) |
| `resources/js/pages/events/create.tsx` | Removed from `fields` array and initial `useForm` payload |
| `resources/js/pages/events/edit.tsx` | Removed from `FormData` type, `fields` array, and initial values |
| `resources/js/pages/events/show.tsx` | Removed from `Event` type and the `<DetailRow label="RSVP">` block |
| `resources/js/pages/events/step/ExtrasStep.tsx` | Rewrote without the Registration section. Removed now-unused `Checkbox` and `Label` imports |

**Deliberately not removed:** the `rsvp_required` reference in the base migration `2026_09_21_093134_add_event_metadata_to_events_table.php`. Base migrations are append-only (AI-CONTEXT §8).

**Deliberately not removed:** three test payloads that still carry `'rsvp_required' => false` (`EventUrlRulesTest`, `EventScheduleRulesTest`, `EventDateRulesTest`). Inert — the controller no longer reads the key. Cosmetic cleanup deferred.

### Evidence

- Test suite before: **54 passed, 145 assertions**
- Test suite after: **54 passed, 145 assertions** — no regression
- Migration ran: `2026_09_25_170000_remove_rsvp_required_from_events_table ............ 3s DONE`
- Browser: Extras step renders without a Registration section

### Related

- `docs/event-creation-issues.md` — walk-in messaging that lived in the RSVP helper text is now gone from the wizard. Whether it reappears on the show page is deferred.

---

## FIX-018 — Partner duplicate rule (client + server)

**Severity:** Medium (silent accept of meaningless duplicates)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` EC-12 (test coverage gap)

### What was broken

`Nike / sponsor` and `Nike / sponsor` passed validation and persisted as two entries. The rules array validated shape (`partners.*.name` required, type in enum) and quantity (`max:20`), but nothing cross-referenced rows.

### Root cause

Laravel's `distinct` rule operates on a single column across array indices, not on a pair of columns. No custom validator existed.

### The design decision — (ii), not (iii)

Two candidates were considered:

| Rule | Meaning |
|---|---|
| **(ii)** | Duplicates iff name AND type match |
| **(iii)** | Duplicates iff name matches, regardless of type |

**Chosen: (ii).** A partner is a **relationship**, not an entity. Nike can be both a sponsor (funds) and a host (venue) of the same event — two contributions with two operational consequences. Forcing the organizer to write "Nike Philippines" and "Nike Inc." to dodge a name-collision rule would be fake differentiation: worse data, not cleaner.

The same entity in the same role twice is a typo. The same entity in two roles is a fact. The pair-key captures the typo without suppressing the fact.

**Normalization:** `trim().toLowerCase()` on both fields. `Nike ` and `nike` collide; `Nike` and `Adidas` do not.

### What changed

**Server — `app/Concerns/EventValidationRules.php`:**

New `validatePartnerDuplicates(Validator $validator)` in the `Validator::after` closure style. Iterates partners, skips rows missing name or type (their own rules report those), builds a `name|type` key, adds `partners.{index}.name` pointing at the first offending row, aggregates a top-level `partners` message.

**Server — `app/Http/Requests/EventRequest.php`:**

`withValidator()` now calls both `validateEventDuration` and `validatePartnerDuplicates`.

**Client — `resources/js/lib/event-validation.ts`:**

New `normalizePartnerPart(value: unknown)` helper mirroring the server. `validateExtras` gained a duplicate pass using a `Map<string, number>`. Same key construction, same messages, same skip-on-missing.

The two sides are intentionally symmetric. This is the same discipline used for `HTTP_URL_PATTERN` and `MIN_EVENT_DURATION_MINUTES`.

### Evidence

- `npx tsc --noEmit` — silent
- `php -l` — clean on both files
- Test suite unchanged: **54 passed, 145 assertions**

### Not covered by tests

**No test currently exercises the duplicate rule.** Recorded as EC-12 in `docs/event-creation-issues.md`.

---

## FIX-019 — Sensory accessibility group merged into Physical Access

**Severity:** Cosmetic (single-item category header)
**Date fixed:** 2026-09-25

### What was broken

The Accessibility grid rendered four category groups. Three contained multiple flags. The fourth — `Sensory` — contained one flag (`quiet_space_available`). A category header over a single item reads as scaffolding that was never finished.

### What changed

| File | Change |
|---|---|
| `resources/js/components/accessibility-grid.tsx` | Removed the fourth `GROUPS` entry (`title: 'Sensory'`). Moved the `quiet_space_available` feature into the `Physical Access` group |

`Physical Access` now contains three features: `stroller_friendly`, `wheelchair_accessible`, `quiet_space_available`.

The `AccessibilityField` union type was not modified — the field survived the move.

### Evidence

- `grep -c "field: '"` = 9 — all fields present, only reparented
- `grep "title: 'Sensory'"` — silent
- `npx tsc --noEmit` — silent
- Browser: Physical Access shows three checkboxes, no Sensory group

---

## FIX-020 — Partner name quality rules via shared `humanNameQualityRules()`

**Severity:** Medium (silent accept of keyboard-mashing partner names)
**Date fixed:** 2026-09-25
**Related:** FIX-018 (duplicate rule), `docs/dev-workflow/README.md` P10

### What was broken

`event_name` was protected by five validation rules (starts-with-letter-or-number, must-contain-vowel, must-contain-consonant, no 3+ repeats, length). `partners.*.name` was protected by two (`required_with`, `max:255`). Keyboard-mashing like `dfdffdfd` or `dfgddgfd` passed partner validation and persisted. Screenshot from this session showed exactly that — two garbage names saved on an event.

### Root cause

The rules for `event_name` were written inline in `eventNameRules()`. There was no shared unit to call from `partners.*.name`. When partner validation was added, the quality checks were simply not copied.

### The design decision — DRY extraction, matching `ProfileValidationRules`

`app/Concerns/ProfileValidationRules.php` already used the pattern: `profileRules()` calls extracted `nameRules()` and `emailRules()`. The fix mirrors that convention — no new pattern invented.

New shared method `humanNameQualityRules()` returns the four quality regex rules. Both `eventNameRules()` and `partners.*.name` call it via spread (`...$this->humanNameQualityRules()`).

Client side mirrors: `humanNameQualityError(value, label)` returns `null` on pass or a message string on failure. Both `validateBasics` and `validateExtras` call it.

### What changed

| File | Change |
|---|---|
| `app/Concerns/EventValidationRules.php` | New `humanNameQualityRules()` method. `eventNameRules()` refactored to use the spread. `partners.*.name` expanded to include `'min:3'` and the spread |
| `resources/js/lib/event-validation.ts` | New `humanNameQualityError()` helper. `validateBasics` and `validateExtras` both call it |

### Evidence

- `npx tsc --noEmit` — silent
- `php -l` — clean
- Test suite unchanged: **54 passed, 145 assertions**

### Untracked-file incident during the fix

The original attempt to apply this change used head/tail splits anchored by `grep -Fn` with double-quoted patterns containing `!`. Bash history expansion fired (`bash: !eventName: event not found`), the `START`/`END` variables went empty, and `event-validation.ts` was corrupted to 644 lines with duplicated functions.

Recovery was complicated by the fact that `event-validation.ts` was **not tracked in git** — `git checkout` had nothing to restore from. The file had to be reconstructed from a known-good snapshot pasted earlier in the session.

Two rules emerged (recorded as P9/P10 in `docs/dev-workflow/README.md`, with an additional shell-safety rule noted below):

1. **Grep patterns in `$(...)` command substitution must be single-quoted** unless shell interpolation is required. `!`, `$`, backticks, and `\` all survive single quotes literally.
2. **Before proposing any split-edit pattern, verify the target is tracked** via `git ls-files <path>`. If untracked, `git add` first or use a non-destructive write.

The second rule was applied in the same session — a broader consolidation commit (`84c33a0`) brought 20 untracked files (including all of `docs/`, the `EnsureEmailIsVerifiedOrAdmin` middleware, and the `venue_map_url` migration) into git.

---

## FIX-021 — Partner errors rendered inline per row (closes EC-03)

**Severity:** Medium (UX dead-end — banner fired but nothing was highlighted)
**Date fixed:** 2026-09-25
**Related:** `docs/event-creation-issues.md` EC-03 (closed)

### What was broken

When a partner was invalid, the wizard correctly routed to the Extras step and the banner said "Please fix the highlighted fields before continuing." No field was highlighted. The `errors['partners.0.name']` message had nowhere to render.

### Root cause

`PartnerEditor.tsx` had no `errors` prop. `ExtrasStep.tsx` called it with only `value` and `onChange`. The errors from `validateExtras` were set — `errors.partners.0.name` existed — but the component had no channel to receive them.

### What changed

| File | Change |
|---|---|
| `resources/js/components/partner-editor.tsx` | Added `errors?: Record<string, string>` prop with default `{}`. Added `InputError` import. Renders `<InputError message={errors[\`partners.${index}.name\`]} />` under the name input, and same for type |
| `resources/js/pages/events/step/ExtrasStep.tsx` | Passes `errors={errors}` to `<PartnerEditor>` |

### Evidence

- `npx tsc --noEmit` — silent after both changes
- Browser: adding `hgsd` (no vowel) as a partner name now shows **"Please enter a valid partner name."** under the field, matching the presentation of the top-level banner
- Browser: adding an empty partner row shows **"Partner name is required."** under the field
- Top-level banner remains — same behavior as before, now with visible inline errors underneath

---

## Change log addendum

- **2026-09-25** — FIX-017 through FIX-021 recorded. Six commits landed this session: `533658e` (RSVP removal + duplicate rule + Sensory merge + humanNameQualityRules), `0d7328f` (partner errors inline + EventController rsvp cleanup), `84c33a0` (consolidation — 20 untracked files added), `c3e1590` (README drift + dev-workflow §9).
- **2026-09-25** — Additional protocols P9 and P10 recorded in `docs/dev-workflow/README.md` §9. Both motivated by commit-message discipline failures during this session. P9 verification addendum appended after confirming all 14 commit-message claims against their diffs.
