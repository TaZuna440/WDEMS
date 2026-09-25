# WDEMS Authentication — Developer Guide

**Audience:** Junior developers who use AI coding tools (Claude, Copilot,
Cursor) but do not yet know this codebase by heart.

**Purpose:** Tell you what the login system does, where every piece
lives, and — most importantly — which parts AI will try to "fix" and
get wrong.

**Read this before you ask AI to change anything under `auth`,
`device.trusted`, or `settings/security`.**

---

## 1. The 60-second overview

WDEMS has **no public sign-up**. Accounts are created by admins.

There are two roles: **admin** and **staff**.

Login is handled by Laravel Fortify (standard email + password). On top
of that, WDEMS adds a **custom email two-factor authentication** layer
that is *not* Fortify's built-in TOTP 2FA. This is the single most
common source of confusion in the codebase.

The flow, in one line:

> Login → Fortify checks password → if the device is not trusted and
> the user is not an admin, WDEMS emails a 6-digit code → user enters
> code → device is remembered for 30 days.

---

## 2. Where things live

Print this list and tape it to your monitor. When AI suggests a change,
find the file here first.

### Backend — the login gate

| File | What it does |
|---|---|
| `config/fortify.php` | Fortify settings. **TOTP 2FA is NOT enabled here.** |
| `config/auth.php` | Session guard, password broker, 3-hour password confirmation timeout |
| `app/Providers/FortifyServiceProvider.php` | Views, login rate limiter, role-based redirect after login |
| `app/Providers/AppServiceProvider.php` | 2FA rate limiters, logout cookie cleanup, password rules |
| `app/Http/Middleware/EnsureDeviceIsTrusted.php` | **The gate.** Sends new devices to the challenge page |
| `app/Http/Middleware/AdminMiddleware.php` | Blocks non-admins from `/admin/*` |
| `bootstrap/app.php` | Registers the two middleware aliases above |

### Backend — the email 2FA system (custom, not Fortify)

| File | What it does |
|---|---|
| `app/Services/TwoFactor/EmailTwoFactorService.php` | **Everything.** Issue codes, verify codes, trust devices, revoke devices |
| `app/Services/TwoFactor/EmailTwoFactorResult.php` | Enum: `Verified`, `Invalid`, `Expired`, `Locked`, `NotFound` |
| `app/Services/Otp/OtpCode.php` | Shared helper for generating/hashing OTP codes |
| `app/Http/Controllers/Auth/DeviceVerificationController.php` | The three endpoints the challenge page talks to |
| `app/Mail/LoginVerificationCodeMail.php` | The email sent with the code |
| `resources/views/emails/login-verification-code.blade.php` | The email body |

### Backend — settings (security + profile)

| File | What it does |
|---|---|
| `app/Http/Controllers/Settings/SecurityController.php` | Password change, 2FA toggle, device revoke |
| `app/Http/Controllers/Settings/ProfileController.php` | Profile update, account deletion |
| `app/Http/Requests/Settings/PasswordUpdateRequest.php` | Validates password change form |
| `app/Http/Requests/Settings/ProfileUpdateRequest.php` | Validates profile form |
| `app/Http/Requests/Settings/ProfileDeleteRequest.php` | Validates account deletion |
| `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php` | Empty request — see §6 trap #4 |
| `app/Concerns/PasswordValidationRules.php` | Shared password rules trait |
| `app/Concerns/ProfileValidationRules.php` | Shared profile rules trait |

### Backend — routes

| File | What it does |
|---|---|
| `routes/web.php` | Device verification routes + all main app routes |
| `routes/settings.php` | Profile, security, appearance |

### Backend — model

| File | What it does |
|---|---|
| `app/Models/User.php` | `isAdmin()`, `homePath()`, casts, fillable |

### Frontend — pages

| File | What it does |
|---|---|
| `resources/js/pages/auth/login.tsx` | Sign-in form |
| `resources/js/pages/auth/verify-device.tsx` | The 6-digit code challenge |
| `resources/js/pages/auth/forgot-password.tsx` | Password reset request |
| `resources/js/pages/auth/reset-password.tsx` | New password form |
| `resources/js/pages/auth/confirm-password.tsx` | Re-enter password gate |
| `resources/js/pages/auth/verify-email.tsx` | Email verification notice |
| `resources/js/pages/settings/security.tsx` | Password, 2FA toggle, device list |
| `resources/js/pages/settings/profile.tsx` | Profile + delete account |

### Frontend — layouts & shell

| File | What it does |
|---|---|
| `resources/js/app.tsx` | Chooses the layout per page name |
| `resources/js/layouts/auth-layout.tsx` | Simple wrapper around `auth-simple-layout` |
| `resources/js/layouts/auth/auth-login-layout.tsx` | The branded split-screen login |
| `resources/js/layouts/auth/auth-simple-layout.tsx` | Default auth page wrapper |
| `resources/js/layouts/settings/layout.tsx` | The three-tab strip (Profile / Security / Appearance) |
| `resources/js/layouts/app-layout.tsx` | The main app shell wrapper |

### Frontend — shared components

| File | What it does |
|---|---|
| `resources/js/components/password-input.tsx` | Input with show/hide toggle |
| `resources/js/components/delete-user.tsx` | Delete account modal |
| `resources/js/components/heading.tsx` | `variant="small"` or `"default"` heading |
| `resources/js/components/input-error.tsx` | Red error text under a field |
| `resources/js/components/wdems-logo.tsx` | Brand SVG logo |
| `resources/js/hooks/use-confirm-dialog.tsx` | Confirm modal hook |

### Frontend — generated (do NOT edit)

| Path | What it is |
|---|---|
| `resources/js/routes/**` | Wayfinder-generated route helpers |
| `resources/js/actions/**` | Wayfinder-generated action helpers |

These regenerate from Laravel routes on every `npm run dev`. **If you
edit them by hand, your edits disappear.**

### Tests

| File | Covers |
|---|---|
| `tests/Feature/Auth/AuthenticationTest.php` | Login, logout, rate limit, role redirects |
| `tests/Feature/Auth/DeviceVerificationTest.php` | Full 2FA challenge flow |
| `tests/Feature/Auth/LogoutDeviceTrustTest.php` | Cookie cleared on logout |
| `tests/Feature/Auth/EmailVerificationTest.php` | Standard Fortify email verification |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | Password confirm screen |
| `tests/Feature/Auth/PasswordResetTest.php` | Password reset flow |
| `tests/Feature/Auth/VerificationNotificationTest.php` | Resend verification email |
| `tests/Feature/Settings/SecurityTest.php` | **3 page tests skip silently — see §6 trap #6** |
| `tests/Feature/Settings/ProfileUpdateTest.php` | Profile update, delete |
| `tests/Unit/Services/EmailTwoFactorServiceTest.php` | Service-level OTP + device tests |

---

## 3. The login flow

### Staff, first time on a device

```
┌────────────────────────────────────────────────────────────┐
│ 1. Browser → GET /login                                    │
│    Fortify renders auth/login.tsx                          │
└────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────┐
│ 2. User submits email + password                           │
│    POST /login (Fortify)                                   │
│    → Session set                                           │
│    → LoginResponse → redirect to homePath()                │
│    → Staff homePath = /dashboard                           │
└────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────┐
│ 3. Browser → GET /dashboard                                │
│    Middleware: EnsureDeviceIsTrusted                       │
│    → User is staff? yes                                    │
│    → 2FA enabled? yes                                      │
│    → Cookie valid? no                                      │
│    → redirect to /verify-device                            │
└────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────┐
│ 4. GET /verify-device renders auth/verify-device.tsx       │
│    No active code → autoSend=true                          │
│    Frontend auto-posts to /verify-device/send-code         │
│    → EmailTwoFactorService::issue()                        │
│    → Email sent with 6-digit code                          │
└────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────┐
│ 5. User enters code, submits                               │
│    POST /verify-device/verify                              │
│    → EmailTwoFactorService::verify()                       │
│    → Verified? → trustDevice() → cookie queued             │
│    → redirect to intended(homePath())                      │
└────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────┐
│ 6. GET /dashboard                                          │
│    Cookie valid → DashboardController renders dashboard    │
└────────────────────────────────────────────────────────────┘
```

### Admin

Same as above, except:

- `homePath()` = `/admin/dashboard`
- Step 3 short-circuits at the admin check inside
  `EnsureDeviceIsTrusted`. The code challenge is skipped entirely.

### Staff with 2FA disabled

Same as admin — step 3 short-circuits at the `email_two_factor_enabled`
check.

### Staff on a trusted device

Step 3 short-circuits at the cookie check. No challenge.

---

## 4. The five moving parts

Each part below has the same shape: **what it does**, **where it lives**,
**safe changes**, **dangerous changes**.

### 4.1 The device-trust middleware

**What:** Every authenticated request passes through
`EnsureDeviceIsTrusted`. It decides whether to let the request continue
or divert the user to the code challenge.

**Where:** `app/Http/Middleware/EnsureDeviceIsTrusted.php`

**Order of checks (memorize this):**

1. No user? Pass.
2. Admin? Pass. **(Deliberate — do not remove.)**
3. `email_two_factor_enabled` is false? Pass.
4. Valid cookie matching a trusted device? Pass.
5. Cookie present but invalid? Forget it, then redirect.
6. Otherwise? Redirect to `/verify-device`.

**Safe changes:**

- Adding a new check *between* existing ones that returns `$next($request)`
  more often.
- Changing the redirect target from `device.verify` to a new route.

**Dangerous changes:**

- Removing or reordering the admin check. Admins would get locked out
  on every new device, including from an emergency break-glass scenario.
- Returning `$next($request)` unconditionally. This silently disables
  the entire 2FA system.
- Removing the `Cookie::forget()` call. Stale cookies would keep
  redirecting the user in a loop.

### 4.2 The email 2FA service

**What:** Generates codes, verifies codes, manages the trusted-device
list on the user row.

**Where:** `app/Services/TwoFactor/EmailTwoFactorService.php`

**Key constants — do not change without a deliberate decision:**

| Constant | Value | Meaning |
|---|---|---|
| `CODE_TTL_SECONDS` | 600 | 10-minute code validity |
| `RESEND_COOLDOWN_SECONDS` | 60 | 1-minute resend window |
| `MAX_ATTEMPTS` | 5 | Wrong-code lockout |
| `DEVICE_COOKIE_NAME` | `wdems_trusted_device` | |
| `DEVICE_TTL_MINUTES` | 43200 | 30 days |
| `MAX_TRUSTED_DEVICES` | 10 | Cap per user |

**Safe changes:**

- Adding a new public method that reads from the service.
- Adding a constant for a new feature (e.g. an SMS variant).
- Adjusting TTL / cooldown values (with a product decision).

**Dangerous changes:**

- Changing `issue()` to not write the resend cache key. Users could
  spam emails.
- Changing `verify()` to skip the `MAX_ATTEMPTS` check. Removes brute-
  force protection.
- Changing `hasTrustedDevice()` to use `in_array` instead of iterating.
  Would match partial hashes.
- Changing the cookie name. **Every existing user gets logged out of
  every trusted device.**

### 4.3 The device verification controller

**What:** HTTP layer between the challenge page and the service.

**Where:** `app/Http/Controllers/Auth/DeviceVerificationController.php`

**Three endpoints:**

| Method | Route | Purpose |
|---|---|---|
| `show()` | GET `/verify-device` | Render the page |
| `sendCode()` | POST `/verify-device/send-code` | Issue a code |
| `verify()` | POST `/verify-device/verify` | Verify a code |

**Safe changes:**

- Adjusting the error messages on invalid codes.
- Adding a new prop to the `show()` Inertia render.
- Adding a new helper private method.

**Dangerous changes:**

- Removing `redirect()->intended()` from `handleVerified()`. Users
  would lose their original destination.
- Changing `$request->boolean('remember', true)` to default `false`.
  Would break the "remember this device" default behavior.
- Changing cookie flags (`secure`, `httpOnly`, `sameSite`). Would
  break cross-environment behavior or introduce a security hole.

### 4.4 The security settings page

**What:** Password change, 2FA toggle, trusted-device list.

**Where:**

- Backend: `app/Http/Controllers/Settings/SecurityController.php`
- Frontend: `resources/js/pages/settings/security.tsx`
- Layout: `resources/js/layouts/settings/layout.tsx`

**Routes:**

| Method | Route | Name |
|---|---|---|
| GET | `/settings/security` | `security.edit` |
| PUT | `/settings/password` | `user-password.update` |
| PUT | `/settings/security/two-factor` | `security.two-factor.toggle` |
| DELETE | `/settings/security/devices/{deviceId}` | `security.devices.revoke` |

**Safe changes:**

- Adding a new card to the page below the existing ones.
- Adding a new field to a form (with a matching rule in the request).

**Dangerous changes:**

- Removing `RequirePassword` middleware from the routes. Users could
  change their security settings after an unattended session.
- Changing the `toggleTwoFactor()` behavior when disabling. Currently
  it also calls `revokeAllDevices()`. Removing that would leave
  trusted-device entries that no longer mean anything.
- Editing `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`
  to add rules. It is intentionally empty — see §6 trap #4.

### 4.5 The User model

**What:** The `isAdmin()` and `homePath()` methods drive the two most
important branching decisions in the login flow.

**Where:** `app/Models/User.php`

**Safe changes:**

- Adding a new cast.
- Adding a new method (e.g. `isStaff()`).

**Dangerous changes:**

- Adding `email_two_factor_enabled` or `trusted_devices` to `#[Fillable]`.
  These are deliberately excluded so mass-assignment can't flip 2FA.
  The service uses `forceFill()` for this reason.
- Changing `homePath()`. The role-based redirect depends on it.

---

## 5. Common tasks

Recipe-style. Each one tells you what to change and what not to touch.

### Add a new field to the security settings page

1. Add a column to `users` via a new migration.
2. Add it to `User::casts()` if it needs a cast.
3. Add it to the Inertia payload in `SecurityController::edit()`.
4. Add the type to the `Props` in `settings/security.tsx`.
5. Render the field.
6. If it is editable: add a route in `routes/settings.php`, add a
   method to `SecurityController`, add a form in the React page.
7. Add a test to `tests/Feature/Settings/SecurityTest.php`.

**Do not** add it to `User::$fillable`. Use explicit assignment in the
controller.

### Add a new rate limiter

1. Add the definition in `AppServiceProvider::configureRateLimiters()`.
2. Apply it to a route with `->middleware('throttle:your-limiter-name')`.

**Do not** invent a new `RateLimiter::for()` inside a controller.

### Add a new auth route

1. Add it in `routes/web.php` inside the correct middleware group.
   - Needs login but not device trust? Use `['auth', 'verified']`.
   - Needs device trust? Use `['auth', 'verified', 'device.trusted']`.
   - Needs password confirmation? Add `RequirePassword` (from
     `Illuminate\Auth\Middleware\RequirePassword`).
2. Run `npm run dev` so Wayfinder regenerates `resources/js/routes/`.
3. Import from `@/routes/...` in the React side.

**Do not** edit `resources/js/routes/**` by hand.

### Change the password rules

1. Edit `AppServiceProvider::configureDefaults()` — the `Password::defaults()` block.
2. That is the single source of truth. Every form and request reads
   from it.

**Do not** hardcode rules in individual requests or in `security.tsx`.

### Send a new kind of email

1. Create a Mailable in `app/Mail/`.
2. Create a Blade template in `resources/views/emails/`.
3. Send with `Mail::to($user->email)->send(new YourMail(...))`.

If the email is time-sensitive (like an OTP), follow the pattern in
`LoginVerificationCodeMail`. **Do not log the code.**

---

## 6. Traps — things AI will try and get wrong

This is the most important section. Read it twice.

### Trap #1 — The Fortify 2FA confusion

**What AI will try:** "Enable Fortify's two-factor authentication" or
"switch to Fortify's built-in 2FA".

**Why it's wrong:** WDEMS does **not** use Fortify's TOTP 2FA. It uses
a custom email-based 2FA in `EmailTwoFactorService`. Fortify's
`twoFactorAuthentication` feature is intentionally **not enabled** in
`config/fortify.php`.

**What to do instead:** If you need to change 2FA behavior, edit
`EmailTwoFactorService`, not `config/fortify.php`.

**Evidence:** `config/fortify.php` `features` array only lists
`resetPasswords()` and `emailVerification()`. If AI suggests adding
`Features::twoFactorAuthentication()`, say no.

### Trap #2 — The admin dashboard file that isn't rendered

**What AI will try:** "Add a widget to the admin dashboard" → AI edits
`resources/js/pages/admin/dashboard.tsx`.

**Why it's wrong:** `DashboardController::__invoke()` renders
`Inertia::render('dashboard', ...)`. The `admin/dashboard.tsx` file
exists but is **never rendered** by any controller I can find.

**What to do instead:** If you want to add an admin-only view, you
have two options:
1. Add conditional logic inside `dashboard.tsx` keyed on the user's role.
2. Create a new controller/route/pages under a distinct path, and
   confirm the render target matches the file path.

**Verify first:** Before editing `admin/dashboard.tsx`, run:

```bash
grep -rn "admin/dashboard" app/ resources/js/pages/
```

If the only hits are the route definition and the file itself, the
page is dead code. Ask before touching it.

### Trap #3 — Editing Wayfinder-generated files

**What AI will try:** "Fix the import in `resources/js/routes/security/index.ts`".

**Why it's wrong:** Every file under `resources/js/routes/` and
`resources/js/actions/` is generated by `@laravel/vite-plugin-wayfinder`
at dev-server startup, based on the `@see` docblocks in the file. Any
edit you make is erased the next time Vite runs.

**What to do instead:** Edit the route in `routes/web.php` or
`routes/settings.php`. Then let Vite regenerate. If you need the new
route immediately, restart `npm run dev`.

**Signal that AI is about to make this mistake:** It will quote a
Wayfinder file and say "the URL is wrong here". The URL is generated —
the fix belongs in the PHP route file.

### Trap #4 — Filling in the empty FormRequest

**What AI will try:** "Add validation rules to
`TwoFactorAuthenticationRequest.php`".

**Why it's wrong:** That request class is empty **on purpose**. It uses
Fortify's `InteractsWithTwoFactorState` trait, which is inert here
(because Fortify 2FA is off). It exists only as a typed carrier for the
`SecurityController` methods.

**What to do instead:** If you need validation on a security route,
create a new request class with a clear name (e.g.
`ToggleTwoFactorRequest`) and use it explicitly.

**Why this matters:** Adding rules here would silently break the
toggle endpoint for every user. No error, just a validation failure
that the frontend does not display.

### Trap #5 — Touching the base `users` migration

**What AI will try:** "Add a `foo` column to the users table" → AI
edits `0001_01_01_000000_create_users_table.php`.

**Why it's wrong:** That migration has already run everywhere. Editing
it does nothing on existing databases and corrupts fresh installs.
The `two_factor_*` columns in that file are a historical artifact —
they were dropped by a later migration and re-added by two more.

**What to do instead:** Create a new migration with
`php artisan make:migration add_foo_to_users_table`. Always append,
never edit.

**Verify:** Run `php artisan migrate:status` to see which migrations
have already run.

### Trap #6 — Assuming skipped tests are passing

**What AI will try:** Run `php artisan test` and report "all green".

**Why it's wrong:** The three page-render tests in
`tests/Feature/Settings/SecurityTest.php` are guarded by:

```php
$this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
```

Fortify's TOTP 2FA is off, so these tests **skip every single run**.
They also assert Fortify-shaped props (`canManageTwoFactor`,
`twoFactorEnabled`) that the current page does not send.

**What to do instead:** If you touch `settings/security.tsx`, do not
trust these tests. Add a new test that does not use the skip guard.

**Signal that AI is about to make this mistake:** It will say
"coverage is complete for the security page." It is not.

### Trap #7 — Flipping `forceFill` to `fill`

**What AI will try:** "Simplify `$user->forceFill([...])->save()` to
`$user->fill([...])->save()`."

**Why it's wrong:** `email_two_factor_enabled` and `trusted_devices`
are deliberately excluded from `User::$fillable`. `fill()` will
silently drop them. The code will run, the UI will look like it
worked, and the change will not persist.

**What to do instead:** Keep `forceFill()`. If you must use `fill()`,
add the field to `#[Fillable]` — but only after checking that no
untrusted input path reaches it.

### Trap #8 — Using `hash_equals` vs `===` in `hasTrustedDevice`

**What AI will try:** "Use `hash_equals()` here to be safe."

**Why it might be right:** The current code uses `===` on hex strings.
Everywhere else in the codebase uses `hash_equals()`. This is a
legitimate consistency fix.

**Why you should ask first:** Changing this code path is security-
sensitive. Even though the timing attack is impractical at 256 bits,
confirm the change with someone who knows why the current code was
written that way. The right answer might be "yes, fix it" or "no, it
was deliberate." Do not guess.

### Trap #9 — Making the mail queued

**What AI will try:** "Add `implements ShouldQueue` to the Mailable."

**Why it might be wrong:** `LoginVerificationCodeMail` is sent
synchronously. Adding `ShouldQueue` changes the delivery timing — a
user might wait 30 seconds for a code that used to arrive in 3.

**What to do instead:** Ask first. If you do queue it, confirm the
queue worker is running (`docker compose ps` shows `wdems-worker`) and
that the OTP TTL covers the added delay.

### Trap #10 — Adding to `#[Fillable]` without thinking

**What AI will try:** "Add `role` to fillable so we can assign it in
the seeder."

**Why it's wrong:** `role` is already in `#[Fillable]`. But if AI
suggests adding `trusted_devices` or `email_two_factor_enabled`, it is
wrong. Those two are excluded for a reason — mass assignment of 2FA
state is a security bug waiting to happen.

**Rule:** Only `name`, `email`, `password`, `role` belong in
`#[Fillable]`. Anything else goes through `forceFill()` in a service
or controller.

---

## 7. Glossary

| Term | Plain meaning |
|---|---|
| **Fortify** | The Laravel package that provides the login/logout/password-reset endpoints. WDEMS uses it for the base login, not for 2FA. |
| **2FA / Two-factor authentication** | Requiring a second proof of identity — in WDEMS, a 6-digit code sent to email. |
| **OTP** | One-time password. Same thing as a 2FA code in this codebase. |
| **Trusted device** | A browser WDEMS has been told "this is okay". Skips the code challenge for 30 days. |
| **Device cookie** | The browser cookie (`wdems_trusted_device`) that proves the browser is trusted. |
| **Middleware** | Code that runs before a controller. `EnsureDeviceIsTrusted` is a middleware. |
| **Guard** | Laravel's name for "how is the user authenticated". WDEMS uses the `web` session guard. |
| **Wayfinder** | The Laravel + TypeScript plugin that generates `resources/js/routes/**` from your Laravel routes. |
| **Inertia** | The bridge between Laravel and React. Server renders props, React renders the page. |
| **`forceFill`** | A model method that bypasses `$fillable`. Used for security-sensitive fields. |
| **`fill`** | A model method that respects `$fillable`. Drops anything not listed. |
| **`RequirePassword`** | Laravel's built-in middleware (`Illuminate\Auth\Middleware\RequirePassword`) that forces the user to re-enter their password before sensitive actions. Not part of Fortify. |
| **Rate limiter** | A throttle that slows down repeated requests. Used on login and 2FA endpoints. |

---

## 8. Running the tests

```bash
php artisan test                                          # everything
php artisan test tests/Feature/Auth                       # just auth
php artisan test --filter="device verification"           # match by name
./vendor/bin/pest tests/Unit/Services/EmailTwoFactorServiceTest.php
```

**Expect some tests to skip.** The three page-render tests in
`tests/Feature/Settings/SecurityTest.php` skip every run because of a
Fortify feature guard (see Trap #6). Run the suite yourself to see the
exact count — do not trust a number quoted in another doc.

---

## 9. Open items (known but not fixed)

These are recorded so you do not accidentally report them as new bugs.

1. **`SecurityTest.php` skips three page tests.** See Trap #6.
2. **The `FlashToast` type exists in `types/ui.ts`** but nothing shares
   it from the backend. The type is a stub.
3. **`HandleInertiaRequests::share()` does not expose flash status.**
   `DeviceVerificationController::sendCode` returns
   `back()->with('status', 'code-sent')` — this value is never seen
   by the frontend. Harmless but dead.
4. **`architecture.md` claims trusted-device tokens are HMAC-hashed.**
   They are SHA-256 hashed, no `APP_KEY`. If you rotate `APP_KEY`,
   OTP codes are invalidated but trusted devices are not.
5. **`architecture.md` claims an `EmailTwoFactorResult::TooSoon` case
   exists.** It does not. The resend cooldown throws a
   `RuntimeException` from `issue()` instead.
6. **`admin/dashboard.tsx` may be dead code.** See Trap #2.

---

## 10. When in doubt

1. **Ask before editing security-sensitive files.** The list of files
   in §2 marked "dangerous changes" — that is your warning list.
2. **Run the tests after every change.** Even if you only touched the
   frontend. The feature tests will catch redirect regressions.
3. **Grep before you rename.** `grep -rn "old_name" app/ resources/js/`
   tells you the blast radius before you commit to a rename.
4. **Do not trust AI on:** anything in `config/fortify.php`, anything
   in `app/Services/TwoFactor/`, anything in
   `app/Http/Middleware/EnsureDeviceIsTrusted.php`, or anything in
   `resources/js/routes/**`.

---

## Corrections

This section supersedes specific claims in the body above. Where an
item below contradicts an earlier section, this section wins.

### Logout no longer clears the trusted-device cookie

The `### Logout cleanup` section states that
`AppServiceProvider::configureLogoutCleanup()` listens for the `Logout`
event and queues `Cookie::forget('wdems_trusted_device')`.

**That listener was removed on 2026-09-25.** Logout no longer clears the
cookie. Trust survives logout until it is revoked or expires.

**Why it changed.** The listener was originally added to prevent a
shared browser from carrying device-trust state into the next user's
session. But the trust check is already per-user — see
`EmailTwoFactorService::hasTrustedDevice()`, which only matches entries
in the *current user's* `trusted_devices` array. A cookie left behind
by a previous user is useless to a different user.

The listener added no security. It only forced the same user to
re-verify after every logout — friction on the common case, no
protection on the edge case.

**Current behavior:**

- Same browser, same user, log out then log in → no 2FA challenge
- Same browser, different user → challenge fires (per-user trust list)
- Cookie expired (30 days) → challenge fires
- Device revoked in Settings → challenge fires
- 2FA disabled and re-enabled (which calls `revokeAllDevices()`) → challenge fires

**Files affected:**

- `app/Providers/AppServiceProvider.php` — method and call removed
- `tests/Feature/Auth/LogoutDeviceTrustTest.php` — assertion inverted

See `docs/known-issues.md` ISSUE-006 for the full record.

---

## Corrections — Email Verification (2026-09-25)

This section extends the earlier `## Corrections` block. Both are
append-only records. Where they contradict the body above, they win.

### Email verification gate was inactive (FIX-002)

The body of this document describes the email verification gate as if
it were operating. It was not.

`app/Models/User.php` did not implement
`Illuminate\Contracts\Auth\MustVerifyEmail`. Laravel's `verified`
middleware only enforces when the authenticated user is an instance of
that interface. Because `User` was not, the middleware short-circuited
and every request passed through — including unverified users reaching
`/dashboard`, `/events`, and `/settings`.

Fixed on 2026-09-25 by adding the interface to the class declaration:

    use Illuminate\Contracts\Auth\MustVerifyEmail;

    class User extends Authenticatable implements MustVerifyEmail

The fix was accompanied by a new test file
`tests/Feature/Auth/EmailVerificationGatingTest.php`. Two of its four
tests failed on the unfixed code — that failure is the confirmation
the bug was real. After the fix, all four pass.

Full record: `docs/fixes.md` FIX-002.

### Admin bypass middleware added (FIX-003)

The `verified` middleware applies to all roles. After FIX-002
activated the gate, an admin with an unverified email could no longer
reach `/admin/dashboard`.

A new middleware was added to restore the admin exemption:

    app/Http/Middleware/EnsureEmailIsVerifiedOrAdmin.php

It extends Laravel's `EnsureEmailIsVerified`, checks
`$request->user()?->isAdmin()` first, and delegates to the parent for
everyone else. Registered in `bootstrap/app.php` as the
`verified.or.admin` alias.

Routes using the new alias:

- `routes/web.php` — the main protected group
  (`auth`, `verified.or.admin`, `device.trusted`)
- `routes/settings.php` — the settings group

Routes still using strict `verified`:

- `routes/web.php` — the device-verification group
  (`auth`, `verified`). An unverified user must verify before touching
  the 2FA challenge. Admins never reach this group because
  `EnsureDeviceIsTrusted` exempts them before redirecting.

The framework's `verified` alias was **not** overridden. Routes that
want strict behavior can still use it. The new alias is explicit — a
grep for `verified.or.admin` finds exactly which routes treat admins
differently.

Full record: `docs/fixes.md` FIX-003.

### Summary table

| Concern | Before 2026-09-25 | After |
|---|---|---|
| Unverified staff reaches dashboard | Yes (gate was a no-op) | No — redirected to `/verify-email` |
| Unverified admin reaches admin dashboard | Yes (gate was a no-op) | Yes (deliberate bypass) |
| Framework `verified` alias | Active but inert | Active, now enforces |
| Custom `verified.or.admin` alias | Did not exist | Registered and in use on two route groups |
