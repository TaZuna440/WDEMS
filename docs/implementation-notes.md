# WDEMS — Implementation Notes

**Audience:** Anyone reading WDEMS code and finding a behavior that
isn't explained where they expected to find it.

**Purpose:** A consolidated reference for the non-obvious behaviors
that live in the codebase but aren't surfaced by the doc that
documents the surrounding subsystem. Not a spec, not a plan — a set
of notes about things that are true and important but easy to miss.

**Status:** Live. Every entry describes shipped code. Entries are
append-only: if a behavior changes, a new entry supersedes the old
one, and the old one stays as a historical record.

**Related:**
- `docs/architecture.md` — whole-system overview
- `docs/AI-CONTEXT.md` — paste-me-at-chat-start context
- Subsystem docs — each covers its own area; this file catches
  behaviors that cross subsystem boundaries or don't fit any one

---

## Scope

**What belongs here:**

- A behavior that is load-bearing but not obvious from a casual read
  of the file that implements it.
- A cross-cutting convention that several subsystems depend on but
  no single doc owns.
- A historical artifact (a defect in a commit message, a redundant
  constraint, a renamed method) that would confuse a reader who
  didn't live through the change.
- A safety invariant that would break silently if changed.

**What does not belong here:**

- Anything already documented in a subsystem doc. If
  `authentication.md` explains it, this file does not repeat it.
- Anything recorded as an issue in `known-issues.md`. Issues are
  open work; these notes are facts about the current state.
- Any behavior whose absence would cause an obvious failure. The
  notes are for behaviors whose absence causes a silent or
  non-obvious failure.

Each entry has the same shape:

- **What it is** — the behavior in one sentence.
- **Where it lives** — file path and, where relevant, method name.
- **Why it matters** — what breaks if a reader doesn't know it.
- **What could go wrong if changed** — the trap.

---

## 1. `wizard.tsx` — client errors win over server errors

**What it is.** The wizard merges two error sources — client-side
validation errors and server-side validation errors — into a single
object. When both sources carry an error for the same field, the
**client** error is displayed, not the server error.

**Where it lives.** `resources/js/components/wizard.tsx`, the
`mergedErrors` `useMemo`:

    const mergedErrors = useMemo(
        () => ({ ...errors, ...stepErrors }),
        [stepErrors, errors],
    );

`errors` is the server error bag. `stepErrors` is the client
validation result. Because `stepErrors` is spread last, it wins on
key collision.

**Why it matters.** The client error describes the current field
value. The server error may describe a value that no longer exists
in the form — a leftover from a previous submission. If the server
error wins, the user sees a stale message describing an input they
already changed. That was the exact bug fixed in commit `3ef048e`:
a user changed a duplicate event name to garbage text, and the
"duplicate name" server error persisted instead of the client's
"invalid name" message.

**What could go wrong if changed.** Swapping the spread order back
to `{ ...stepErrors, ...errors }` reintroduces the bug. It's a
one-line change and there is no test that catches it directly —
`wizard.tsx` is frontend-only and the tests that exercise the wizard
run on the server, not the browser. A future refactor that
"tidies" the spread order would silently restore the old behavior.

**Cross-references.** `docs/fixes.md` FIX-013, `docs/progress.md`
2026-10-02 entry, commit `3ef048e`.

---

## 2. `HumanNameQualityRules` — shared across three free-text fields

**What it is.** A trait that returns four regex rules catching
keyboard-mashing input. The same four rules apply to `event_name`,
`partners.*.name`, and the registration form builder's `label`
field.

**Where it lives.** `app/Concerns/HumanNameQualityRules.php`, a
trait method `humanNameQualityRules()`. Consumed by:

- `EventValidationRules::eventNameRules()` — spread into the
  `event_name` rule array
- `EventValidationRules::eventRules()` — spread into
  `partners.*.name`
- `RegistrationFieldValidationRules::validateFieldLabelQuality()`
  — called directly with a length threshold

The client mirror lives in
`resources/js/lib/event-validation.ts::humanNameQualityError()`,
used by both `validateBasics` and `validateExtras`.

**The four rules:**

    regex:/^[A-Za-z0-9]/           — starts with a letter or digit
    regex:/[aeiouyAEIOUY]/         — contains at least one vowel
    regex:/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/
                                    — contains at least one consonant
    not_regex:/(.)\1\1/            — no 3+ identical chars in a row

**Why it matters.** The three consumers have different length
thresholds and different surrounding rules, but the quality checks
themselves are identical. If the rules drift — say, someone
"simplifies" the vowel rule for `event_name` only — the three
fields stop being consistent about what counts as keyboard
mashing.

**What could go wrong if changed.** The most common mistake is
widening a regex to support non-ASCII input (e.g. accented
characters in event names) without updating all three call sites
and the client mirror. Doing it in one place produces a partial
fix: the server accepts the input on `event_name` but still
rejects it on partner names, or the client shows no error while
the server does. The `docs/event-creation-issues.md` EC-10 entry
records this as an open decision.

**Cross-references.** `docs/event-creation.md` Trap #6,
`docs/event-creation-issues.md` EC-10, commits `877901f` and
`533658e`.

---

## 3. Registration slug — 8 chars, 31-character alphabet, 3-retry throw

**What it is.** When an event's registration is opened, a slug is
generated and stamped on the event. The slug is the public
identifier at `/r/{slug}`.

**Where it lives.** `app/Http/Controllers/EventController.php`,
three private constants and two private methods:

    SLUG_ALPHABET   = 'abcdefghjkmnpqrstuvwxyz23456789'
    SLUG_LENGTH     = 8
    SLUG_MAX_ATTEMPTS = 3

    generateUniqueSlug()  — retry loop, throws RuntimeException on
                             three consecutive collisions
    randomSlug()          — single attempt, no uniqueness check

**The alphabet.** 31 characters. Lowercase a–z excluding `i`, `l`,
`o`, plus digits 2–9. Excludes the confusables I, L, O, 0, 1. The
slug is designed to be readable over the phone and paste-able in
chat.

**Collision space.** 31^8 ≈ 8.5 × 10^11. A collision at any real
scale is effectively impossible, but the loop exists so the failure
mode is explicit rather than silent. The `RuntimeException` on
three consecutive collisions makes the invariant testable.

**Why it matters.** The slug is the public URL. It is set once, at
`openRegistration()`, and no code path clears it. Once an event has
a slug, that URL is stable for the life of the event.

**What could go wrong if changed.**

- **Changing the alphabet** invalidates every existing slug if the
  change is not backwards-compatible. Generated slugs are only
  guaranteed to be in the alphabet that was in use at generation
  time.
- **Changing the length** has the same issue plus a UI consequence:
  the URL grows or shrinks and existing shared links keep working
  (they don't validate format, only existence).
- **Removing the retry loop** removes the collision-safety
  guarantee. A silent duplicate slug would break the public route
  binding.

**Cross-references.** `docs/registration-development-plan.md`
(Phase 3), commit `4e6535c`.

---

## 4. `EnsureRegistrationIsOpen` returns 200 with a closed page

**What it is.** The middleware that gates the `/r/{slug}` route
group returns HTTP 200 with a rendered "registration closed" page
when the event is not accepting submissions — not 404, not 403.

**Where it lives.**
`app/Http/Middleware/EnsureRegistrationIsOpen.php`. Renders
`registrations/closed` via `Inertia::render(...)->toResponse()`.

**The three closed cases:**

1. The bound event's `status` is not `RegistrationOpen`.
2. The event has a `registration_end` timestamp that has passed.
3. The route parameter is not an `Event` (defensive — the route
   always binds, but the middleware does not assume).

**Why 200 and not 404/403.**

- **404 is wrong** — the URL is valid. The event exists. The
  participant is supposed to be able to see *why* registration is
  closed.
- **403 is wrong** — no authentication exists to fail. The route
  is anonymous. There is no user to be forbidden.

200 with a clear message is the honest answer. It also means search
engines and link-preview services see a page rather than an error,
which is the desired behavior for a URL that a participant might
have shared.

**Why it matters.** The status code is a design decision, not a
default. A future change that converts this to `abort(404)` would
break the intended UX and produce a link-preview failure for
every shared URL after the event closes.

**What could go wrong if changed.** The middleware uses
`$request->route('event')` to get the bound event. If the route's
binding key ever changes (e.g. from `{event:registration_slug}` to
an explicit controller parameter), the middleware's check would
need to change in lockstep. Grep for `registration_slug` before
changing either side.

**Cross-references.** `docs/registration-monitoring.md` §11,
commit `c351732`.

---

## 5. Rate limiter inventory — five limiters

**What it is.** Five named rate limiters, all registered in
`AppServiceProvider::configureRateLimiters()`. Each is applied to a
specific route by name.

**Where it lives.** `app/Providers/AppServiceProvider.php`.

**The five:**

| Name | Key | Bound | Applied to |
|---|---|---|---|
| `event-deletion-otp-request` | `user_id \| event_id` | 3 / minute | `POST /events/{event}/deletion/request-otp` |
| `event-deletion-otp-verify` | `user_id \| event_id` | 10 / minute | `POST /events/{event}/deletion/verify-otp` |
| `device-verification-send` | `user_id` | 3 / minute | `POST /verify-device/send-code` |
| `device-verification-verify` | `user_id` | 10 / minute | `POST /verify-device/verify` |
| `public-registration-submit` | `ip` | 5 / minute | `POST /r/{event:registration_slug}` |

**Why it matters.** The bound values were chosen deliberately.
`public-registration-submit` at 5/min per IP is tight enough to
stop a script but loose enough that a single participant filling
out one form never hits it. The OTP endpoints use compound keys so
one user's abuse does not lock out another user on a different
event.

**What could go wrong if changed.**

- **Loosening a bound** removes the safety margin without any
  visible symptom until abuse happens.
- **Changing a key** changes what "the same source" means. Moving
  `public-registration-submit` from `ip` to a session key would
  let a script bypass by clearing cookies.
- **Adding a limiter without applying it** — the limiter name is
  registered but no route uses it. Silent dead code. Grep for the
  name across `routes/web.php` after adding one.

**Cross-references.** `docs/authentication.md` §7.1,
`docs/event-deletion.md` §7.1,
`docs/registration-monitoring.md` §3.

---

## 6. `(event_id, participant_id)` UNIQUE pre-exists in the base migration

**What it is.** The `registrations` table has had a UNIQUE
constraint on `(event_id, participant_id)` since the base migration
`2026_09_16_160321_create_registrations_table.php`. It is not new.
It was added alongside the table, before any registration flow
existed.

**Where it lives.**
`database/migrations/2026_09_16_160321_create_registrations_table.php`,
the last statement inside the `Schema::create` closure:

    $table->unique(['event_id', 'participant_id']);

**Why it matters.** The participant-identity Phase 1 plan originally
listed a migration to add this constraint. Phase 0 reconnaissance
(commit `910eff6`) discovered it already existed. The plan was
amended; the migration was not written.

A reader who sees the identity plan's Phase 1 table and wonders why
only four migrations landed instead of five will not find the
answer in the plan body. It is in the Phase 0 findings addendum.

**What could go wrong if changed.** Dropping the constraint would
allow two registrations for the same participant on the same event.
The D5 duplicate check in `PublicRegistrationController::store()`
enforces this at the application layer, but only when email is
present. Without the DB constraint, an email-optional event would
allow duplicate registrations from the same phone.

**Cross-references.**
`docs/participant-identity-and-monitoring-plan.md` (Phase 0
findings, D-1).

---

## 7. `c3b4384` commit message claims four files; three landed

**What it is.** Commit `c3b4384` — "fix(events): reject duplicate
active event names; prioritize client errors" — lists four files in
its message body. The commit itself contains three.

**The missing file.** `resources/js/components/wizard.tsx`. The
commit message describes the client-validation-priority fix that
lives in this file, but the file was unstaged before the commit
ran and did not land. The fix shipped separately, one commit
later, as `3ef048e`.

**Where it lives.** `git show c3b4384 --stat` shows three files.
The commit body lists four.

**Why it matters.** A future `git show c3b4384` or a bisect on the
wizard behavior will find a message describing a change that isn't
in the diff. The natural reaction is confusion — "where's the
wizard change?" — followed by a grep that finds it in `3ef048e`.

**What could go wrong if changed.** This entry is a permanent
record. Do not amend `c3b4384` to remove the discrepancy — the
project's append-only correction policy (P7) means committed
history stays as-is, and the corrective commit (`3ef048e`) already
names the split. A future attempt to "fix" the message by amending
the commit would require a force-push and would break anyone who
had cloned `main` in the interim.

**Cross-references.** Commit `c3b4384`, commit `3ef048e`,
`docs/progress.md` 2026-10-02 entry.

---

## How to add an entry

Copy the shape:

    ## N. Short title

    **What it is.** ...

    **Where it lives.** ...

    **Why it matters.** ...

    **What could go wrong if changed.** ...

    **Cross-references.** ...

Number sequentially. Do not renumber existing entries — the numbers
are referenced from other docs and from commit messages. If an
entry becomes obsolete, add a new entry that supersedes it and
reference the old one by number.

## Changelog

- **2026-10-02** — File created. Seven entries: wizard error merge
  order, shared human-name quality rules, slug generation
  constants, closed-registration status code, rate limiter
  inventory, pre-existing registration constraint, and the
  `c3b4384` commit-message defect.
