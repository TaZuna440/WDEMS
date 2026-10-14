# WDEMS — Attendance Code and Encoding Modes

**Audience:** Whoever implements, modifies, or reasons about the
attendance workspace, the walk-in encoding flows, or the
`verified_at` distinction in the eventual end-of-event report.

**Purpose:** Reference for the per-registration attendance code and
the live / deferred encoding modes. This is a reference, not a
spec -- every claim describes shipped code. Companion to
`attendance-encoding-and-reporting.md`, which remains the forward
spec for the finalize-event and end-of-event-report features.

**Status:** Shipped 2026-10-14. Commit `d05c7e7`.

**Related:**
- `docs/attendance-encoding-and-reporting.md` -- original three-
  feature spec. Feature A (encoding workspace) is now partly
  superseded by this doc; Features B and C remain unbuilt.
- `docs/attendance-redesign.md` -- the three-mode attendance
  workspace. Its window behavior and search semantics are
  unchanged; the encoding modes described here sit on top.
- `docs/registration-contact-verification.md` -- the verification
  flow that produces the challenge this feature hooks into.
- `docs/implementation-notes.md` section 3 -- the event slug
  constants, whose alphabet the attendance code mirrors.
- `docs/AI-CONTEXT.md` section 18 -- the phone-required change
  this feature works around when delivery falls back to email.

---

## 1. What this closes

Two problems, addressed together because they share one axis -- how
attendance is recorded differs for the two categories of
participant.

**Pre-registered attendance was recorded on staff's word.** A
volunteer at the table could look up anyone by name and click
Present. There was no proof the person in front of them was the
person whose registration they had found. An imposter who knew a
name could be marked as that person.

**Walk-in arrival time was wrong whenever encoding happened late.**
Whether a walk-in was entered live at the table or transcribed
from a paper form three days after the event, `attendance_time` was
stamped with `now()` -- the moment the operator typed the data, not
when the participant actually arrived. A Sunday paper sheet entered
the following Wednesday produced Wednesday attendance records.

## 2. The two categories

The system has three participant sources (`form`, `walk_in`,
`paper`) but treats them as two categories for attendance.

| Category | Sources | How attendance is known |
|---|---|---|
| Pre-registered | `form` | Unknown until the participant shows up. Must be verified at the table. |
| Walk-in | `walk_in`, `paper` | Present by definition. They physically appeared -- the paper form or the live dialog is the record. |

That split drives every decision below:

- **Only pre-registered participants receive an attendance code.**
  Walk-ins do not, because their presence is a fact of their
  encoding, not a claim that needs verification.

- **Only pre-registered marks can carry `verified_at`.** Walk-in
  attendance is present by definition; there is nothing to verify.

- **Only walk-in encoding has a live / deferred distinction.**
  Pre-registered attendance is always live -- staff mark the row as
  the person stands there. Walk-in encoding can be either.

## 3. The attendance code

### 3.1 Format

`KM4X-P2N7`. Eight characters, dash-separated into two groups of
four. Uppercase. Drawn from the same 31-character alphabet as the
event slug:

    ABCDEFGHJKMNPQRSTUVWXYZ23456789

The alphabet excludes the confusables I, L, O, 0, 1 so the code
reads cleanly over a phone line. Uppercase so it is visually
distinct from the lowercase slug at `/r/{slug}`.

The alphabet is a deliberate duplicate of
`EventController::SLUG_ALPHABET`, uppercased. Extracting the
constant would have touched a file unrelated to this feature. If
the alphabet ever changes, both sites must be updated together --
see `implementation-notes.md` section 3 for the same warning about
the slug.

### 3.2 Generation

Generated inside `RegistrationVerificationService::finalize()`, in
the same transaction that creates the `Registration` row.

`generateAttendanceCode(Event $event)` retries up to three times on
collision before throwing a `RuntimeException`. The collision space
(31^8, roughly 8.5 times 10 to the 11th, per event) makes a real
collision impossible; the retry loop makes the failure mode
explicit rather than silent. This mirrors
`EventController::generateUniqueSlug()`.

The caller holds the event row lock (`finalize()` takes it at the
top of the transaction), so concurrent finalizations for the same
event are serialized. The unique index on `(event_id,
attendance_code)` is a defense-in-depth backstop, not the primary
collision defense.

### 3.3 Storage

One column on `registrations`:

    attendance_code    varchar(16)    nullable    unique with event_id

`varchar(16)` rather than `varchar(9)` for the same reason
`registration_slug` is `varchar(32)` for an 8-character value: the
format is allowed to grow without a schema change. A second dash,
a check digit, or an extended group would all fit.

Nullable, because walk-ins never receive a code. MySQL treats NULL
as distinct in unique indexes, so multiple walk-ins per event
coexist without collision.

### 3.4 Delivery

Two channels, neither optional:

**1. Confirmation page.** `RegistrationVerificationService::publicState()`
returns the code on the `finalized` branch. `verify.tsx` renders an
"Your attendance code" panel with the code in large monospace and a
Copy button. This is the immediate, in-browser delivery.

**2. Email.** `RegistrationConfirmationMail` is sent at `finalize()`
via `DB::afterCommit()`, addressed to
`registration.submitted_email`. Sent only when the participant
provided an email -- the field is optional on some events. Fails
soft: a transport error never rolls back the registration.

`DB::afterCommit()` is deliberate. Sending inside the transaction
would either roll back the registration on a Resend failure (wrong
-- the registration is real and should not be undone by an email
problem) or require a swallow that hides the failure. The callback
fires after the outer transaction commits; the registration stands
regardless of what the mail transport does.

If the mail fails silently, the participant still has the code on
the confirmation page and in the browser cookie.

### 3.5 Retrieval

The participant who finalized registration holds an encrypted,
HttpOnly cookie scoped to `/r/{slug}`, valid for 24 hours. The
cookie is stamped at `RegistrationVerificationService::begin()`
with an explicit lifetime matching `CHALLENGE_TTL_HOURS` -- not the
session default.

Any GET on `/r/{slug}/verification` in that browser during the
window returns the challenge's current state. When the state is
`finalized`, the response carries the attendance code.

No new route. No new rate limiter. The cookie that authorized
submission authorizes retrieval, for the same 24 hours. The
`ProtectRegistrationVerification` middleware sets the privacy
headers; it does not need to re-issue anything.

After 24 hours the cookie expires. The code is then reachable only
via the confirmation email. Staff lookup by name remains the
fallback.

## 4. Lookup at the table

The attendance workspace (`/events/{event}/attendance`) accepts a
`?code=` query parameter:

    GET /events/{event}/attendance?code=KM4X-P2N7

### 4.1 Normalization

The server accepts and normalizes five typographic variants:

| Input | Normalized |
|---|---|
| `KM4X-P2N7` | `KM4X-P2N7` |
| `km4x-p2n7` | `KM4X-P2N7` |
| `KM4XP2N7` | `KM4X-P2N7` |
| `KM4X P2N7` | `KM4X-P2N7` |
| `km4x p2n7` | `KM4X-P2N7` |

Any input that does not reduce to 8 alphanumerics normalizes to the
empty string and is treated as no code. Volunteer typos stay cheap
to fix.

`normalizeAttendanceCode()` in `AttendanceController` is the single
implementation. The same helper is reused in `mark()` to normalize
the `verified_code` before comparison.

### 4.2 Precedence

When a code is active, `q` (search) and `filter` are ignored. The
operator's mental model is "show me this person," not "filter the
list by this code." The workspace's search input and filter chips
are visually dimmed and disabled while a code is active.

### 4.3 Counts

The filter chip counts still reflect the code-scoped result set,
consistent with how they behave under search. With a code active,
`counts.all` reads 1 (match) or 0 (no match). This matches the
existing behavior tested in `AttendanceTest`: the counts always
reflect the current query, never the whole-event universe.

### 4.4 Empty state

No 404 when the code matches nothing. The workspace renders its
normal list with an inline message: "No registration matches
KM4X-P2N7. Check the code, or search by name." Volunteers at a
table get a recoverable state, not an error page.

### 4.5 Admin `force_open`

The `force_open=1` query parameter continues to work alongside the
code. The frontend's `sharedQuery()` helper preserves `force_open`
across code lookups, searches, and pagination. The backend checks
`$isAdmin && $request->boolean('force_open')` as before.

## 5. Verification semantics

New column on `attendances`:

    verified_at    timestamp    nullable    indexed

Set only when a mark satisfies **all three** of:

1. The request carried a `verified_code`.
2. The code normalized to match the registration's stored
   `attendance_code`.
3. The status being set is a presence state (Present or Late).

Anything else leaves `verified_at` null.

### 5.1 Scenario table

`verified_at` records whether the system could independently
confirm the participant's presence. It is set when the code the
participant carried matched the code the system issued.

| Scenario | `verified_at` |
|---|---|
| Participant speaks their code, staff enters it, marks Present | Set |
| Participant speaks their code, staff enters it, marks Late | Set |
| Participant speaks their code, staff marks Absent | Null -- a code proves presence, not absence |
| Participant gives their name only, staff searches, marks Present | Null |
| Bulk mark from a paper sheet | Null |
| Admin `force_open` mark outside the window | Null |
| Any walk-in | Null -- walk-ins have no code |

### 5.2 Why absence never sets it

A code is proof of physical presence. "I was here" is the claim it
verifies. "I was absent" is not a claim a code can support -- if
the participant was standing at the table with their code, they
were present. Absent and Excused never set `verified_at`, even
when a matching code is submitted. The frontend also omits
`verified_code` for absence statuses, keeping the payload honest
about intent.

### 5.3 How it is set

In `AttendanceController::mark()`, after validation:

    $verifiedAt = null;
    $submittedCode = $this->normalizeAttendanceCode(
        (string) ($validated['verified_code'] ?? ''),
    );

    if (
        $shouldRecordTime
        && $submittedCode !== ''
        && $registration->attendance_code !== null
        && $submittedCode === $registration->attendance_code
    ) {
        $verifiedAt = now();
    }

`$shouldRecordTime` is the existing "is Present or Late" boolean.
The code is checked against the registration's stored code, not
against a global lookup -- the mark is scoped to one registration.

## 6. Live and deferred encoding

Walk-in encoding has two modes:

| Mode | When used | `attendance_time` |
|---|---|---|
| Live | The operator is registering a walk-in at the table, right now | `now()` -- automatic |
| Deferred | The operator is transcribing a paper form after the fact | The arrival time typed by the operator, on the event's date |

**Live is the default.** Requests that omit `encoding_mode` fall
into the live branch. This preserves the pre-existing behavior --
existing callers and tests are unaffected.

### 6.1 Single walk-in

`walk-in-dialog.tsx` presents an "Encoding mode" toggle above the
form. Live shows no arrival-time input; Deferred reveals one.
`WalkInRegistrationRequest` requires `arrival_time` when
`encoding_mode` is deferred (checked in `withValidator()` Pass 3 so
the error lands under the `arrival_time` key rather than the
generic `identity` key).

The controller computes `$attendanceTime` outside the transaction,
so the value is stable across the closure:

    if ($encodingMode === 'deferred' && $arrivalTime !== null) {
        $attendanceTime = $event->event_date->copy()
            ->setTimeFromTimeString($arrivalTime);
    } else {
        $attendanceTime = now();
    }

### 6.2 Bulk walk-in

`bulk-walk-in-table.tsx` uses the same vocabulary. The mode is a
single top-level field for the whole batch -- you are either
transcribing a stack of forms (deferred) or entering live arrivals
back-to-back (live). Per-row modes would be over-engineering.

In deferred mode, each row carries its own `arrival_time`, because
different people on the same paper stack arrived at different
times.

`BulkWalkInRegistrationRequest` validates arrival time per row
(Pass 3), and the controller computes a per-row `$attendanceTime`
before persisting each success.

### 6.3 The CarbonInterface lesson

`persistBulkWalkInRow()` type-hints its `$attendanceTime` parameter
as `Carbon\CarbonInterface`, not as `Illuminate\Support\Carbon` or
`Carbon\CarbonImmutable`.

The reason: the two callers produce different concrete classes.

- `now()` returns a mutable `Illuminate\Support\Carbon`.
- `$event->event_date->copy()` returns a `Carbon\CarbonImmutable`,
  because `event_date` is cast as `immutable_datetime`.

Neither concrete class is the parent of the other.
`CarbonInterface` is the tightest common supertype.

**Rule for future work:** when a method receives a datetime from
two different callers and one of them derives from a model cast,
type the parameter as the interface, not the concrete class. Check
the cast in the model's `casts()` method before picking a type.

## 7. Schema summary

Two new columns. Two migrations.

| Table | Column | Type | Nullable | Notes |
|---|---|---|---|---|
| `registrations` | `attendance_code` | varchar(16) | yes | Unique with `event_id`. Null for walk-ins. |
| `attendances` | `verified_at` | timestamp | yes | Set only by the code-lookup mark path. Indexed. |

Migrations:

    database/migrations/2026_10_14_100000_add_attendance_code_to_registrations_table.php
    database/migrations/2026_10_14_100001_add_verified_at_to_attendances_table.php

Both are additive. No existing columns were dropped or altered. No
backfill needed -- existing registrations simply have a null
`attendance_code`, and existing attendances have a null
`verified_at`, which is the correct reading: the system did not
verify them because the mechanism did not exist yet.

## 8. Test coverage

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/Events/AttendanceVerificationTest.php` | 10 | `verified_at` semantics |
| `tests/Feature/Events/AttendanceEncodingModeTest.php` | 7 | Single walk-in live/deferred |
| `tests/Feature/Events/AttendanceBulkEncodingModeTest.php` | 7 | Bulk live/deferred with per-row times |
| `tests/Feature/Public/RegistrationConfirmationMailTest.php` | 3 | Mail rendered, sent, and not sent |

27 new tests. Full suite at close: 537 passed, 3 skipped, 1957
assertions. Every pre-existing attendance test continues to pass
unchanged.

## 9. What is not yet built

The larger spec in `attendance-encoding-and-reporting.md` describes
two features that will use the attendance data this work produces.

**Feature B -- Finalize event.** A password-gated close that moves
the event from `ongoing` to `completed` and locks the data. Not
built. The `canEncode()` guard mentioned in the spec does not
exist; the current equivalent is `canRecordAttendanceNow()`.

**Feature C -- End-of-event report.** The single-page report
showing this-event summary, cross-event comparison, and
participant history. Not built. The `verified_at` column will be
the source of the report's distinction between code-verified and
staff-override attendance.

When Feature C is built, its "This event summary" section should
include a verification count:

    Registered (form):              142
      Attended (code-verified):      98
      Attended (staff override):     21
    Walk-ins encoded:                12
    ------------------------------------
    Total physical attendance:      131
    Show rate (form):               119 / 142 = 84%
    Verification rate:               98 / 119 = 82%

The last line is what makes the report honest. A 100% show rate
where most marks are overrides is a weaker claim than a 90% show
rate that is mostly code-verified. `verified_at` is what lets the
report say which is which.

## 10. What deliberately did not ship

- **Download-as-image button on the confirmation page.** The code
  appears in the browser and in the confirmation email. A download
  button was considered and deferred. Participants who want an
  image can screenshot.

- **Code regeneration.** The code is issued once and never changes.
  If it is lost, staff lookup by name is the fallback. A regenerate
  path would require invalidating the old code and handling the
  case where the old code was already used to check in.

- **QR code on the confirmation page.** Code-only for v1. A QR is
  a v2 nicety; the workspace input accepts typed codes, which is
  what a volunteer can read aloud or hear.

## 11. Files touched

Backend:

    app/Services/RegistrationVerification/RegistrationVerificationService.php
    app/Http/Controllers/AttendanceController.php
    app/Http/Requests/WalkInRegistrationRequest.php
    app/Http/Requests/BulkWalkInRegistrationRequest.php
    app/Models/Registration.php
    app/Models/Attendance.php
    app/Mail/RegistrationConfirmationMail.php                          (new)

Frontend:

    resources/js/pages/events/attendance.tsx
    resources/js/pages/registrations/verify.tsx
    resources/js/components/walk-in-dialog.tsx
    resources/js/components/bulk-walk-in-table.tsx
    resources/js/lib/attendance-bulk-validation.ts

Migrations, views, tests:

    database/migrations/2026_10_14_100000_add_attendance_code_to_registrations_table.php  (new)
    database/migrations/2026_10_14_100001_add_verified_at_to_attendances_table.php        (new)
    resources/views/emails/registration-confirmation.blade.php                            (new)
    tests/Feature/Events/AttendanceVerificationTest.php                                   (new)
    tests/Feature/Events/AttendanceEncodingModeTest.php                                   (new)
    tests/Feature/Events/AttendanceBulkEncodingModeTest.php                               (new)
    tests/Feature/Public/RegistrationConfirmationMailTest.php                             (new)

## 12. Cross-references

- `docs/attendance-encoding-and-reporting.md` -- original spec for
  the encoding workspace, finalize event, and end-of-event report.
  Feature A's encoding-workspace half is now partly superseded by
  this doc. Features B and C remain unbuilt.

- `docs/attendance-redesign.md` -- the earlier three-mode
  attendance design. Window behavior and search semantics are
  unchanged.

- `docs/registration-contact-verification.md` -- the verification
  flow that produces the challenge this feature hooks into.

- `docs/implementation-notes.md` section 3 -- the event slug
  constants.

- `docs/AI-CONTEXT.md` section 18 -- the phone-required change.

- `docs/progress.md` -- dated entry for the shipping session.
