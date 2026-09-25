# WDEMS Event Deletion — Developer Guide

**Audience:** Junior developers who use AI coding tools (Claude, Copilot,
Cursor) but do not yet know this codebase by heart.

**Purpose:** Tell you how events are deleted, where every piece lives,
and — most importantly — which parts AI will try to "fix" and get wrong.

**Read this before you ask AI to change anything under
`EventDeletionController`, the two `EventDeletion*` services, or
`event-deletion-otp-dialog.tsx`.**

---

## 1. The 60-second overview

Deleting an event has **two paths** depending on the user's role:

| Role | Path | Extra step |
|---|---|---|
| **Admin** | Direct delete | None |
| **Staff** | OTP-gated delete | Emailed 6-digit code, entered in a dialog |

Both paths funnel through the same service call:
`EventDeletionService::delete()`. That service deletes the event and
every record that belongs to it, in a strict order, inside a database
transaction.

**Participants are never deleted.** They are shared across events, so
they survive the event's removal.

Deletion is **permanent**. There is no soft-delete, no trash, no
restore. The confirm dialog says so.

---

## 2. Where things live

### Backend

| File | What it does |
|---|---|
| `app/Http/Controllers/EventDeletionController.php` | Three actions: `destroy`, `requestOtp`, `verifyOtp` |
| `app/Services/EventDeletion/EventDeletionService.php` | The ordered delete in a transaction |
| `app/Services/EventDeletion/EventDeletionOtpService.php` | OTP issue / verify / invalidate |
| `app/Services/EventDeletion/OtpVerificationResult.php` | Enum: `Verified`, `Invalid`, `Expired`, `Locked`, `NotFound` |
| `app/Services/EventDeletion/OtpResendTooSoonException.php` | Thrown when the resend cooldown is active |
| `app/Services/Otp/OtpCode.php` | Shared OTP helper (generate / hash / matches) |
| `app/Mail/EventDeletionOtpMail.php` | The email mailable |
| `resources/views/emails/event-deletion-otp.blade.php` | The email body |

### Frontend

| File | What it does |
|---|---|
| `resources/js/components/event-deletion-otp-dialog.tsx` | The OTP dialog |
| `resources/js/pages/events/show.tsx` | Has the Danger Zone button that opens the dialog, and calls `router.delete()` for admins |

### Routes

All in `routes/web.php`:

| Method | Route | Name | Middleware |
|---|---|---|---|
| DELETE | `/events/{event}` | `events.destroy` | `auth, verified, device.trusted` |
| POST | `/events/{event}/deletion/request-otp` | `events.deletion.request-otp` | `+ throttle:event-deletion-otp-request` |
| POST | `/events/{event}/deletion/verify-otp` | `events.deletion.verify-otp` | `+ throttle:event-deletion-otp-verify` |

### Tests

| File | Covers |
|---|---|
| `tests/Feature/Events/EventDeletionAdminTest.php` | Admin direct delete, cascade, participant preservation, staff/guest blocked |
| `tests/Feature/Events/EventDeletionStaffTest.php` | Full OTP flow, cooldown, lock-out, admin blocked from OTP endpoints, guests blocked |
| `tests/Unit/Services/EventDeletionOtpServiceTest.php` | Service-level: issue, verify, expire, lock, invalidate |

---

## 3. The two paths

### 3.1 Admin — direct delete

1. Admin clicks "Delete Event" on `events/show.tsx`.
2. A confirm dialog appears listing what will be deleted (counts of
   options, registrations, registration options, attendance records).
3. On confirm, the frontend calls `router.delete('/events/{id}')`.
4. `EventDeletionController::destroy()` checks `isAdmin()`. Admin →
   proceeds.
5. `performDeletion()` runs the ordered delete via
   `EventDeletionService::delete()`.
6. Redirect to `/events`.

**No OTP is sent.** Admins are trusted at a higher level (they control
the server). This is a deliberate scope decision — see the admin
exemption note in `authentication.md` and the middleware
`EnsureDeviceIsTrusted`.

### 3.2 Staff — OTP-gated delete

1. Staff clicks "Delete Event" on `events/show.tsx`.
2. The confirm dialog appears (same as admin).
3. On confirm, `requires_otp` is true (passed from
   `EventController::show()` as `! $request->user()->isAdmin()`), so
   instead of calling `router.delete()`, the frontend opens
   `EventDeletionOtpDialog`.
4. The dialog auto-requests an OTP on open (see §6).
5. Staff receives the 6-digit code by email.
6. Staff enters the code and clicks "Verify & Delete".
7. `EventDeletionController::verifyOtp()` verifies the code. On
   success, calls `performDeletion()`.
8. Redirect to `/events`.

**No way for staff to bypass the OTP.** The direct `destroy()` endpoint
checks `isAdmin()` and aborts 403 for staff.

---

## 4. The OTP flow

### 4.1 Issue

`EventDeletionController::requestOtp()` calls
`EventDeletionOtpService::issue($user, $event)`.

The service:

1. Checks the resend cooldown key. If present, throws
   `OtpResendTooSoonException`.
2. Generates a 6-digit code (`random_int(0, 999999)`, zero-padded).
3. Hashes the code with `OtpCode::hash()` (HMAC-SHA256 with `APP_KEY`).
4. Writes the OTP cache key with `{ code_hash, attempts: 0, issued_at,
   expires_at }`, TTL 600 seconds.
5. Writes the resend cache key with the current timestamp, TTL 60
   seconds.
6. Sends `EventDeletionOtpMail` to the user's registered email.

### 4.2 Verify

`EventDeletionController::verifyOtp()` calls
`EventDeletionOtpService::verify($user, $event, $code)`. Returns one
of:

| Result | Meaning | What the controller does |
|---|---|---|
| `Verified` | Correct code | Calls `performDeletion()` |
| `Invalid` | Wrong code, attempts remain | Back with `code` error |
| `Expired` | TTL passed | Back with `code` error |
| `Locked` | 5+ wrong attempts | Back with `code` error |
| `NotFound` | No active OTP | Back with `code` error |

### 4.3 Invalidate

`EventDeletionOtpService::invalidate($user, $event)` forgets both cache
keys. Called from `requestOtp()` when mail sending fails (see §5.3)
so the user is not locked out by an OTP that was never delivered.

---

## 5. The deletion algorithm

`EventDeletionService::delete()` runs an explicit ordered delete
inside a transaction:

```
1. Collect registration IDs for the event
2. Delete attendances      (via registration_id)
3. Delete registration_options  (via registration_id)
4. Delete registrations    (via event_id)
5. Delete event_options    (via event_id)
6. Delete the event
```

**Why not rely on cascade?** Because
`registration_options.event_option_id` has a `RESTRICT` foreign key.
MySQL cannot cascade-delete a registration option when its parent
event option is being cascaded — it sees the RESTRICT and refuses.
Explicit ordering avoids this. See `architecture.md` for the full
foreign-key table.

**Participants are never touched.** They belong to no single event and
can have registrations on multiple events.

**After deletion**, the service logs an `event.deleted` info entry with
counts, actor, and timestamp. This happens *outside* the transaction —
if logging fails, the deletion has already committed.

### 5.1 Controller error handling

The controller has two try/catch blocks:

- **`requestOtp()`** — catches `OtpResendTooSoonException` and returns
  a user-facing "please wait" message. Catches any other `\Throwable`,
  calls `invalidate()` to roll back the partial OTP state, logs
  `event.deletion.otp_request_failed` with structured context, and
  returns a generic "could not send" message.
- **`verifyOtp()`** — catches any `\Throwable` from the verification
  step, logs `event.deletion.verify_failed`, returns a generic error.

The `destroy()` action for admins has no try/catch — a failure there
bubbles up as a 500. This is a gap; see §9.

---

## 6. The dialog

**File:** `resources/js/components/event-deletion-otp-dialog.tsx`

**Props:**

```ts
type Props = {
    eventId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};
```

**The dialog is self-contained.** It does not know about the event's
status, role, or the confirm dialog that opened it. It only knows:

- The event ID (for building the two URLs)
- Whether it is open

**Auto-request on open.** When `open` becomes true, the dialog
immediately calls `POST /events/{id}/deletion/request-otp` (guarded by
a `hasRequested` state to prevent re-firing). When `open` becomes
false, `hasRequested` resets, so re-opening the dialog sends a new
code.

**Cooldown.** Same constant as the backend: 60 seconds. After a
successful request, the Resend button is disabled and shows a
countdown. The countdown resets to 0 whenever a server error appears
on the `code` field.

**Locked state.** When the server error message contains the substring
"too many" (case-insensitive), the dialog hides the input field and
replaces the footer with a single "Dismiss" button. See §9 trap #1 —
this detection is fragile.

**Error display.** The dialog reads `usePage().props.errors.code`. This
is Inertia's shared error bag. As long as no other feature on the same
page uses `code` as a validation error key, this works.

**After success.** The server returns a redirect to `events.index`.
Inertia follows it, the parent page (`events/show.tsx`) unmounts, and
the dialog disappears along with it.

---

## 7. Rate limits, cache keys, constants

### 7.1 Rate limiters

Registered in `AppServiceProvider::configureRateLimiters()`, applied
in `routes/web.php`.

| Name | Key | Limit | Applies to |
|---|---|---|---|
| `event-deletion-otp-request` | `user_id\|event_id` | 3 / min | `requestOtp` |
| `event-deletion-otp-verify` | `user_id\|event_id` | 10 / min | `verifyOtp` |

The `destroy` route is not throttled — it either succeeds quickly
(admin path) or aborts 403.

### 7.2 Cache keys

| Key | Shape | TTL |
|---|---|---|
| `event-delete-otp:{user_id}:{event_id}` | `{ code_hash, attempts, issued_at, expires_at }` | 600s |
| `event-delete-otp-resend:{user_id}:{event_id}` | unix timestamp | 60s |

`code_hash` is `OtpCode::hash($code)` = HMAC-SHA256 with `APP_KEY`.
The plaintext code is never stored or logged.

### 7.3 Constants

From `EventDeletionOtpService`:

| Constant | Value | Meaning |
|---|---|---|
| `CODE_TTL_SECONDS` | 600 | 10-minute validity |
| `RESEND_COOLDOWN_SECONDS` | 60 | 1-minute cooldown |
| `MAX_ATTEMPTS` | 5 | Wrong-code lockout |

From `event-deletion-otp-dialog.tsx`:

| Constant | Value | Note |
|---|---|---|
| `RESEND_COOLDOWN_SECONDS` | 60 | Must match the backend value |

The two `RESEND_COOLDOWN_SECONDS` constants are **not shared** — they
are duplicated in PHP and TypeScript. If you change one, change the
other. See §9 trap #3.

---

## 8. Tests

### 8.1 What is covered

**Admin path:**

- Direct delete with no related records
- Cascade removes options, registrations, registration options,
  attendances
- Participants preserved
- Staff blocked from direct delete (403)
- Guests redirected to login

**Staff path:**

- OTP request sends mail
- Second request within cooldown rejected
- Correct code deletes the event
- Wrong code rejected, event survives
- Lock after 5 wrong attempts
- Verify without requesting first returns "no active code"
- Staff blocked from direct delete (403)

**Admin blocked from OTP endpoints:**

- Admin cannot hit `request-otp` (403)
- Admin cannot hit `verify-otp` (403)

**Guests blocked from all three endpoints.**

**Service unit tests** (`EventDeletionOtpServiceTest`):

- Issue stores hash, not plaintext
- Resend cooldown enforced
- Correct code verifies and clears cache
- Wrong code increments attempts
- Expired payload returns Expired
- 5 wrong attempts → Locked
- `invalidate()` clears both keys

### 8.2 What is not covered

- `performDeletion()` error handling — no test for what happens when
  the transaction fails
- Rate limiter interaction with the resend cooldown (the UI disables
  Resend, but a direct API call could bypass)
- `EventDeletionService::delete()` called outside the two entry points
  (it is only called from the controller)

---

## 9. Traps — things AI will try and get wrong

### Trap #1 — The `locked` state detection is fragile

**What AI will try:** "Refactor the locked check to use an enum or a
dedicated prop."

**Why that might be right but risky:** The dialog currently determines
the locked state like this:

```ts
const locked = serverError?.toLowerCase().includes('too many') ?? false;
```

This depends on the **exact wording** of the server error message. The
controller returns:

> 'Too many incorrect attempts. Please request a new code later.'

If any future change alters that string — rewording, translating,
adding a prefix — the locked state silently stops working. The input
field reappears, the Dismiss button is no longer the only option, and
the user sees a normal error they can keep trying against.

**What to do instead:** If you touch this, do not just swap the string
match for another string match. The right fix is to make the server
return a distinguishing signal — a structured error, a status flag, or
a dedicated field. That is a real change, not a refactor. Discuss
before doing it.

**Signal that AI is about to make this mistake:** It will say the
string-match is "brittle" (correct) and propose a "cleaner" check
against a new error field that does not exist yet.

### Trap #2 — Resetting the countdown on server error

**What AI will try:** "Remove the countdown reset — it lets the user
spam the Resend button."

**Why it is deliberate:** When a server error appears, the code was
either not sent, or the resend cooldown is being enforced. In the
first case (send failure) the user should be able to retry
immediately. In the second case (server cooldown active) the server
will reject the resend anyway, so the reset is harmless.

**What to do instead:** If you want to tighten this, the right fix is
to have the server return a distinguishing field for "cooldown active"
vs "send failed" so the dialog can choose to reset or not. Do not
just delete the reset — it would break recovery from send failures.

### Trap #3 — The duplicated `RESEND_COOLDOWN_SECONDS` constant

**What AI will try:** "There are two constants with the same name and
value — consolidate them."

**Why you cannot consolidate them:** One is PHP
(`EventDeletionOtpService::RESEND_COOLDOWN_SECONDS`), one is TypeScript
(`event-deletion-otp-dialog.tsx`). They live in different runtimes.
They must be kept in sync by hand.

**What to do instead:** If you need to change the cooldown, change
both. Add a comment linking them. Do not attempt to "extract" one
from the other — there is no build-time mechanism to share a constant
across PHP and TypeScript in this project.

**Signal that AI is about to make this mistake:** It will say "these
duplicate constants should be shared" without noticing they are in
different languages.

### Trap #4 — Trying to reuse `verify-device.tsx`

**What AI will try:** "The device verification page and the event
deletion dialog look similar — extract a shared component."

**Why it is the wrong direction:** They are superficially similar
(both are 6-digit code inputs with a resend cooldown) but their
lifecycles are different:

| Aspect | `verify-device.tsx` | `event-deletion-otp-dialog.tsx` |
|---|---|---|
| Presentation | Full-page Inertia page | Dialog inside `events/show.tsx` |
| Props | email, autoSend, initialCountdown | eventId, open, onOpenChange |
| Auto-send | Only when server says no active code | Always on open |
| Locked handling | None | String-match on "too many" |
| Post-success | Redirect to home | Parent page unmounts via server redirect |
| Storage key | Server-driven | None |

Forcing them into a shared component would require the shared component
to know about both contexts. That is worse than the current duplication.

**What to do instead:** Leave them separate. If they diverge further,
so be it. The duplication is 60 lines of similar logic — cheaper than
a leaky abstraction.

### Trap #5 — Removing the admin check in `destroy()`

**What AI will try:** "Simplify — the route is already behind
`auth, verified, device.trusted`; the controller does not need to
re-check the role."

**Why it is wrong:** The middleware chain does **not** include an
admin gate. `EnsureDeviceIsTrusted` exempts admins from 2FA but does
not require admin. Staff pass through the same chain.

`destroy()` deliberately aborts for non-admins:

```php
if (! $request->user()->isAdmin()) {
    abort(403, 'Staff must verify event deletion via email OTP.');
}
```

Remove this and staff can delete any event in one request with no OTP.

**What to do instead:** Keep the check. If you want a more elegant
version, extract it to a policy or a form request — but do not remove
it.

**Signal that AI is about to make this mistake:** It will say "the
route middleware already covers this."

### Trap #6 — Moving the deletion into a queued job

**What AI will try:** "Deleting an event touches many tables — move it
to a queue so the request returns faster."

**Why it is wrong here:** Deletion must be synchronous because:

1. The controller redirects to `events.index` immediately after. If
   the deletion is queued and the queue worker is delayed, the user
   sees the event still present on the list, and thinks the deletion
   failed.
2. The transaction boundary matters. A queued job has no HTTP request
   context, so user-facing errors are lost.
3. The current flow is fast — a handful of deletes inside one
   transaction. There is no performance reason to queue it.

**What to do instead:** If the delete gets slow at scale, revisit. For
now, keep it synchronous.

### Trap #7 — Touching the ordered delete sequence

**What AI will try:** "The delete order can be simplified — just
delete the event and let cascade handle the rest."

**Why it is wrong:** The `RESTRICT` on
`registration_options.event_option_id` blocks cascading. That is the
entire reason the ordered delete exists. See §5.

**What to do instead:** Leave the sequence alone. If you ever remove
the RESTRICT constraint, that is a schema change, not a controller
change — and it has downstream implications for event option editing.

### Trap #8 — Assuming the delete endpoint can be called from a form

**What AI will try:** "Wrap the delete button in an HTML form with
`@method('DELETE')`."

**Why it is wrong:** This is a React + Inertia app, not a Blade app.
The delete is triggered via `router.delete()` from JavaScript, not via
a server-rendered form. `@method()` does not exist here.

**What to do instead:** Use `router.delete()` — which is already what
the show page does. See `events/show.tsx` for the pattern.

### Trap #9 — Adding OTP to admin's path

**What AI will try:** "For consistency, admins should also verify."

**Why it is against the design:** The admin exemption is deliberate and
appears in three places:

1. `EnsureDeviceIsTrusted` — admins skip device challenge
2. `EventDeletionController::destroy()` — admins delete without OTP
3. `EventDeletionController::requestOtp()/verifyOtp()` — admins are
   actively *blocked* from those endpoints (403)

Making admins go through OTP would require changing all three. The
reason for the exemption is that admins control the server itself, so
the OTP would protect against nothing they could not already do. If
that reasoning changes, this is a product decision, not a code fix.

**What to do instead:** Discuss before touching.

### Trap #10 — Logging the OTP code

**What AI will try:** "Add a log line to help debug OTP failures."

**Why it is wrong:** The code is a shared secret. It must never appear
in logs. The current controller logs `event.deletion.otp_request_failed`
with `user_id`, `event_id`, and `error` — but never the code itself.

**What to do instead:** If you need debugging visibility, log the fact
that a code was issued (with user/event ID) but not the code. The
mail service already does not log the code.

**Signal that AI is about to make this mistake:** It will say "log the
code so we can see it in dev." Say no.

---

## 10. Glossary

| Term | Plain meaning |
|---|---|
| **OTP** | One-time password. The 6-digit code emailed to staff. |
| **Resend cooldown** | The 60-second window during which no new OTP can be issued for the same user+event. |
| **Attempts** | How many times a wrong code has been submitted for the current OTP. Capped at 5. |
| **Locked** | The state after 5 wrong attempts. The OTP is invalidated and a new one is needed. |
| **`performDeletion()`** | Private controller helper that calls the deletion service. Both the admin path and the staff success path go through it. |
| **Ordered delete** | The explicit sequence of deletes inside a transaction. Required because of the RESTRICT foreign key. |
| **`requires_otp`** | A prop on `events/show.tsx` — `! $request->user()->isAdmin()`. Tells the frontend which delete path to use. |
| **`OtpVerificationResult`** | Enum returned by `verify()`. Five cases. |
| **Wayfinder** | Generates `resources/js/routes/**` from PHP routes. Do not edit those files. |

---

## 11. Open items (known but not fixed)

1. **No try/catch in `destroy()`.** The admin delete path calls
   `performDeletion()` with no exception handling. A database failure
   would surface as a 500 page. See §9 trap #5.
2. **The `locked` detection depends on the error message text.** See
   §9 trap #1.
3. **`RESEND_COOLDOWN_SECONDS` is duplicated across PHP and TS.** See
   §9 trap #3.
4. **No autoFocus on the OTP input in the dialog.** `verify-device.tsx`
   autofocuses; this dialog does not. Minor UX inconsistency.
5. **The countdown interval is recreated on every tick.** Same pattern
   as `verify-device.tsx`. Works, but wasteful. Not a bug.
6. **No test for transaction failure.** If `EventDeletionService::delete()`
   throws mid-transaction, the rollback behavior is not covered by any
   test.

---

## 12. When in doubt

1. **Read `EventDeletionService` before changing the delete sequence.**
   That file is the source of truth for the ordering.
2. **Do not remove the admin check from `destroy()`.** It is the only
   thing preventing staff from bypassing OTP.
3. **Do not touch the ordered delete.** The RESTRICT constraint is why
   it exists.
4. **Do not log the OTP code.** Ever.
5. **Do not trust AI on:** `EventDeletionService::delete()`,
   `EventDeletionController::destroy()`, or the `locked` check in
   `event-deletion-otp-dialog.tsx`.
