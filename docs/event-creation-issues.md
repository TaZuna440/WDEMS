# WDEMS — Event Creation Issues

**Scope:** Problems found in the event-creation subsystem — wizard,
step components, server validation, and edit flow.

**Audience:** Whoever picks up the next round of event-creation work.

**Relationship to other docs:**

- `docs/known-issues.md` — the cross-subsystem backlog. Issues
  involving auth, dashboard, RBAC, etc.
- `docs/event-creation.md` — the code reference for how event creation
  works today.
- `docs/event-creation-wizard.md` — the feature view of the wizard.
- This file — the specific problems in event creation and how to fix
  them, in priority order.

**Status:** Open. Issues below are documented, not fixed. Each entry
gets a `**Status:** Fixed — <date>` line when resolved. The file is
append-only for fixes; the entry body is not rewritten.

---

## Priority order

Fix in this order. Each item lists why it comes before the next.

| # | ID | Title | Severity | Blocks |
|---|---|---|---|---|
| 1 | EC-01 | `venue_latitude.toFixed()` crashes on edit | **High** | Editing any event with coordinates set |
| 2 | EC-02 | Partner sub-errors never route to Step 4 | **High** | Saving any event with a partially-filled partner row |
| 3 | EC-03 | Partner errors never displayed in ExtrasStep | **Medium** | — (blocks EC-02's UX fix) |
| 4 | EC-04 | No `end_time > start_time` check | **Medium** | — |
| 5 | EC-05 | `course_url` rule rejects scheme-less URLs | **Medium** | — |
| 6 | EC-06 | No cap on `partners` array size | **Low** | — |
| 7 | EC-07 | No trimming of text inputs | **Low** | — |
| 8 | EC-08 | `clear()` fires before submit — draft lost on server rejection | **Low** | — |
| 9 | EC-09 | No past-date check on `event_date` | **Low** (needs decision) | — |
| 10 | EC-10 | `event_name` regexes reject non-ASCII names | **Low** (likely intentional) | — |
| 11 | EC-11 | Client-side validation is emptiness-only | **Note** (documented as deliberate) | — |

### Why this order

- **EC-01 first** — it is a hard crash. Nothing else in the edit flow
  matters if the page does not render.
- **EC-02 second** — it silently blocks saves. Any event with a partner
  name blank cannot be submitted, and the user gets no indication why.
- **EC-03 third** — the display half of EC-02. Without it, fixing
  EC-02 alone would jump the user to Step 4 with no visible error.
- **EC-04, EC-05** — server-side rule additions, low risk, clear
  symptom.
- **EC-06, EC-07** — hardening.
- **EC-08** — data-loss risk but low frequency. Needs a small
  decision about the `clear()` placement.
- **EC-09, EC-10** — both need a product or design decision before
  code.
- **EC-11** — informational only. Documented as intentional.

---

## Severity definitions

| Level | Meaning |
|---|---|
| **High** | Breaks a core flow, or causes silent data loss |
| **Medium** | Noticeable UX problem, or a missing safety check |
| **Low** | Polish, or an edge case unlikely to be hit |
| **Note** | Recorded for completeness — no fix expected |

---

## EC-01 — `venue_latitude.toFixed()` crashes on edit

**Severity:** High
**Blocks:** Editing any event that has coordinates set
**Status:** Open
**Found in:** `resources/js/pages/events/step/VenueStep.tsx`

### Symptoms

Open `/events/{id}/edit` for any event that already has a venue picked
on the map. The page crashes. The React error is:

    TypeError: data.venue_latitude.toFixed is not a function

### Root cause

`VenueStep.tsx` renders the coordinates like this:

    {data.venue_latitude?.toFixed(6)},{' '}
    {data.venue_longitude?.toFixed(6)}

The optional chaining (`?.`) guards against `null` and `undefined`,
but not against **a string**. And on the edit page, the values are
strings.

The `Event` model casts both columns as `decimal:7`:

    'venue_latitude' => 'decimal:7',
    'venue_longitude' => 'decimal:7',

Laravel's decimal cast returns a **string**, not a float. So
`data.venue_latitude` is `"14.5995000"` on edit, not `14.5995`. Strings
do not have a `toFixed` method. Calling it throws.

On create, the values start as `null` (from `create.tsx`) and become
numbers when the user picks a location on the map (the map picker
returns JS numbers). So the crash is edit-only — the create path never
sets them to strings.

The same problem was already solved once in the same subsystem:
`edit.tsx` has a `normalizeDistance()` helper that converts the
`decimal:2` string back to a number. Latitude and longitude did not
get the same treatment.

### Fix outline

Two options — either works, pick one:

**Option A — Normalize in `edit.tsx`.** Add a `normalizeCoordinate()`
helper next to the existing `normalizeDistance()`, and wrap
`event.venue_latitude` and `event.venue_longitude` when building the
initial form state. Symmetric with the existing distance fix.

**Option B — Normalize in `VenueStep.tsx`.** Convert to number before
calling `.toFixed()`. Keeps the fix local to the component that has
the problem.

Recommendation: **Option A**. It matches the existing pattern in the
same file, and it keeps the string→number conversion in one place.

### Decisions before implementing

- **Which option?** A or B above.
- **Should `distance_value` normalization also be audited?** It is
  currently handled. Confirm no other decimal-cast field has the same
  problem. Grep for `.toFixed` and for `data.` reads of decimal casts.

### Verification when fixed

1. Open an event that has coordinates set
2. Confirm the edit page renders
3. Confirm the coordinate block shows the values with 6 decimal places
4. Confirm the map picker opens with the marker in the correct place
5. Change the location, save, reload — new coordinates persist

---

## EC-02 — Partner sub-errors never route to Step 4

**Severity:** High
**Blocks:** Saving any event with a partially-filled partner row
**Status:** Open
**Found in:** `resources/js/components/wizard.tsx` (error-jumping
effect), `resources/js/pages/events/create.tsx` (step config),
`resources/js/pages/events/edit.tsx` (step config)

### Symptoms

Add a partner on Step 4 but leave the name blank. Submit the form. The
server rejects with a validation error on `partners.0.name`. Nothing
on the screen changes — the wizard does not jump to Step 4, and no
error text appears. The form appears stuck.

### Root cause

The server returns errors with **dotted keys** for array items:

    partners.0.name => "The partners.0.name field is required."
    partners.0.type => "..."

Inertia flattens these into a single errors object. The keys are
literally `"partners.0.name"` and `"partners.0.type"`.

The wizard's error-jumping effect looks for a step whose `fields`
array contains a failing key:

    const failingIndex = steps.findIndex((s) =>
        s.fields.some((f) => f in errors),
    );

The Extras step's `fields` array contains `'partners'` — the bare
name. But `'partners' in errors` returns `false` because the actual
error keys are `'partners.0.name'` and `'partners.0.type'`. The prefix
does not match.

The wizard therefore finds no matching step, and does not jump.

### Fix outline

Two approaches:

**Approach A — Prefix matching in the wizard.** Change the check to
compare against the error key's **prefix**:

    s.fields.some((f) =>
        Object.keys(errors).some((k) => k === f || k.startsWith(f + '.'))
    )

This is generic. Any future array-of-objects field (say, an FAQ editor
later) gets the same treatment for free. It also slightly slows the
loop — the errors object is small in practice, so this is a no-op for
performance.

**Approach B — Add the dotted forms to the step's `fields` array.**
List `'partners.0.name'`, `'partners.1.name'`, etc. in the fields
array. This is wrong — the number of partners is variable, and the
step would need to know every index in advance.

Recommendation: **Approach A**. It is the correct general fix.

### Decisions before implementing

- **Is `startsWith(f + '.')` safe here?** It could cause a false
  match if one field's name is a prefix of another (e.g. `venue` and
  `venue_address`). Confirm no such pair exists in the current step
  configs. Grep `fields` arrays in both `create.tsx` and `edit.tsx`.
  If a collision exists, the check needs refinement (e.g. match only
  on `f` or `f + '.'`).

### Verification when fixed

1. Open the create wizard, go to Step 4
2. Add a partner, leave the name blank
3. Click Create Event
4. Confirm: wizard jumps back to Step 4
5. Confirm: the server error message appears (this requires EC-03 to
   also be fixed for it to be *visible*, but the jump should work
   independently)
6. Fill in the name, submit again, confirm the event is created

---

## EC-03 — Partner errors never displayed in ExtrasStep

**Severity:** Medium
**Blocks:** EC-02's full UX fix
**Status:** Open
**Found in:** `resources/js/pages/events/step/ExtrasStep.tsx`

### Symptoms

Even after EC-02 is fixed and the wizard jumps to Step 4, there is no
visible error text on the partner row. The user sees a blank name
field and does not know what to fix.

### Root cause

`ExtrasStep.tsx` receives an `errors` prop but never reads it. The
component has no `<InputError>` usage for any field, including
`partners`. Compare with `BasicsStep.tsx`, which renders
`<InputError message={errors.event_name} />` under every field.

### Fix outline

Two pieces:

1. **Component change.** `PartnerEditor.tsx` should accept an
   `errors` prop (or a `rowErrors` prop indexed by row) and render an
   `<InputError>` under each row's name field when the server returns
   `partners.{index}.name`. Similarly for the type field.

2. **Wiring.** `ExtrasStep.tsx` passes the `errors` object down to
   `PartnerEditor`.

Alternative: render a single summary error above the partner list
("Some partner fields are invalid") rather than per-row errors. Less
precise, simpler. Decide which.

### Decisions before implementing

- **Per-row errors, or a single summary?** Per-row is better UX.
  Summary is faster to implement.
- **Should the summary link to the first failing row?** Optional
  polish.
- **Should the type select show a default highlight if invalid?**
  Optional.

### Verification when fixed

1. Trigger EC-02's flow
2. Confirm the wizard jumps to Step 4
3. Confirm the invalid partner row shows an error message under the
   name field (or above the list, whichever approach is chosen)
4. Fix the name, submit, confirm success

---

## EC-04 — No `end_time > start_time` check

**Severity:** Medium
**Blocks:** Nothing, but allows nonsense data
**Status:** Open
**Found in:** `app/Concerns/EventValidationRules.php`

### Symptoms

A user can set the event to start at 18:00 and end at 06:00. The form
accepts it. The event shows "18:00 – 06:00" on the show page. There is
no indication this is wrong.

### Root cause

The rule for `end_time` is:

    'end_time' => ['nullable', 'date_format:H:i'],

There is no `after:start_time` or equivalent comparison. The rule
validates the format but not the ordering.

### Fix outline

Add `after:start_time` to the `end_time` rule:

    'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],

Laravel's `after` rule accepts a field name. When `end_time` and
`start_time` are both times on the same day, `after:start_time`
compares them as times.

**Edge case:** events that span midnight (e.g. an overnight relay).
If any event type in the roadmap could span midnight, `after` will
incorrectly reject it. Check whether the project has such events. If
yes, the fix needs a "spans midnight" boolean or a different rule.

### Decisions before implementing

- **Do any events span midnight?** If yes, this rule needs to be
  conditional. If no, `after:start_time` is correct.

### Verification when fixed

1. Try to create an event with `start_time` = 18:00, `end_time` = 06:00
2. Confirm: server rejects with an error on `end_time`
3. Try a valid pair (18:00 → 20:00), confirm it saves

---

## EC-05 — `course_url` rule rejects scheme-less URLs

**Severity:** Medium
**Blocks:** Nothing, but a UX trap
**Status:** Open
**Found in:** `app/Concerns/EventValidationRules.php`,
`resources/js/pages/events/step/ScheduleStep.tsx`

### Symptoms

The `course_url` field's placeholder text says:

    https://maps.app.goo.gl/... or a Strava link

A user pastes `maps.app.goo.gl/xyz` — the format that Google Maps
share links actually produce on mobile. Server rejects it: "The
course url field must be a valid URL."

### Root cause

The rule is:

    'course_url' => ['nullable', 'url', 'max:500'],

Laravel's `url` rule requires a scheme (`https://`). It does not
accept bare hostnames.

The placeholder suggests a format that the rule rejects.

### Fix outline

Two approaches:

**Approach A — Loosen the rule.** Accept both scheme and scheme-less
URLs. Use a regex instead of Laravel's `url` rule. Then normalize by
prepending `https://` before save.

**Approach B — Normalize on input.** In `ScheduleStep.tsx`, if the
pasted value does not start with a scheme, prepend `https://`
automatically. The server rule stays strict.

Recommendation: **B**. It fixes the UX at the point of input, keeps
the rule honest, and does not require loosening validation.

### Decisions before implementing

- **A or B?**
- **Should the same treatment apply to any other URL field?** Grep
  for `'url'` in the codebase. Currently only `course_url` uses it.

### Verification when fixed

1. Paste `maps.app.goo.gl/xyz` into the Course Link field
2. Submit
3. Confirm: the value is stored as `https://maps.app.goo.gl/xyz`
4. Confirm: the show page renders the link and clicking opens it

---

## EC-06 — No cap on `partners` array size

**Severity:** Low
**Blocks:** Nothing, but a minor hardening gap
**Status:** Open
**Found in:** `app/Concerns/EventValidationRules.php`

### Symptoms

The `partners` field accepts any number of rows. A malicious request
could submit 10,000 partner entries, each of which passes the
`required_with:partners` rule.

### Root cause

The rule is:

    'partners' => ['nullable', 'array'],

No `max` constraint on the array itself.

### Fix outline

Add a reasonable cap:

    'partners' => ['nullable', 'array', 'max:20'],

20 is generous — a real Community Run is unlikely to have more than
five or six partners.

### Decisions before implementing

- **What cap?** 20 is the proposal. Confirm.

### Verification when fixed

1. Add 21 partners to a form
2. Submit
3. Confirm: server rejects with "The partners field must not have
   more than 20 items."
4. Reduce to 20, confirm it saves

---

## EC-07 — No trimming of text inputs

**Severity:** Low
**Blocks:** Nothing
**Status:** Open
**Found in:** `app/Http/Requests/EventRequest.php`

### Symptoms

A user types `"Sunrise Run "` with a trailing space. The event is
created with the trailing space in the name. Display shows `"Sunrise
Run "`. Sorting and searching treat it as a different string from
`"Sunrise Run"`.

Same for `description`, `venue`, `venue_address`, `course_url`.

### Root cause

Laravel has a `prepareForValidation()` method on FormRequests, but it
is not implemented here. Input is stored as typed.

### Fix outline

Add a `prepareForValidation()` method to `EventRequest`:

    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_name' => trim((string) $this->event_name),
            'venue' => trim((string) $this->venue),
            'venue_address' => trim((string) $this->venue_address),
            'course_url' => trim((string) $this->course_url),
            // ...
        ]);
    }

**Alternative:** use Laravel's `TrimStrings` middleware globally.
This is already part of the default Laravel middleware stack for
`web` routes — check whether it is enabled. If it is, the issue
does not exist and this entry is informational only.

### Decisions before implementing

- **Is `TrimStrings` middleware active?** Check
  `bootstrap/app.php` — the web group. If yes, EC-07 is a no-op.

### Verification when fixed

1. Enter `"Sunrise Run "` as the name
2. Submit
3. Confirm the stored value is `"Sunrise Run"` (no trailing space)

---

## EC-08 — `clear()` fires before submit — draft lost on server rejection

**Severity:** Low
**Blocks:** Nothing, but a data-loss risk on rejected submissions
**Status:** Open
**Found in:** `resources/js/components/wizard.tsx`

### Symptoms

The user fills out the wizard on Step 4 and clicks Create. If the
server rejects the submission (validation error), the localStorage
draft is already cleared. The user has to re-enter everything from
scratch.

### Root cause

The `handleSubmit` function calls `clear()` before `onSubmit()`:

    const handleSubmit = useCallback(() => {
        if (!validateCurrentStep()) return;
        clear();
        onSubmit();
    }, [validateCurrentStep, clear, onSubmit]);

If the server rejects after this, the draft is gone.

### Fix outline

Move `clear()` to a success callback. The `Wizard` shell does not
currently receive an `onSuccess` prop. Two options:

**Approach A — Pass `onSuccess` from the caller.** `create.tsx` and
`edit.tsx` pass a callback to `Wizard` that fires after the POST
resolves successfully. Wizard calls `clear()` inside that callback.

**Approach B — Delay clear.** Call `clear()` inside `onSubmit` after
the request settles. This requires the wizard to know when the
request finished, which it does not currently.

Recommendation: **A**. It matches Inertia's `useForm` `onSuccess`
pattern.

### Decisions before implementing

- **Should the draft be cleared on any error, or only on 422
  validation errors?** Clearing on 500s too would be a mistake if the
  server is having problems — the user would want the draft back.

### Verification when fixed

1. Fill the wizard with data that will be rejected (e.g. event name
   "x")
2. Submit
3. Confirm: the server rejects
4. Confirm: the wizard still has the filled-in data
5. Fix the field, resubmit
6. Confirm: on success, the draft is cleared (open a new Create page,
   no pre-filled data)

---

## EC-09 — No past-date check on `event_date`

**Severity:** Low
**Blocks:** Nothing, but permits nonsense data
**Status:** Open — needs a product decision
**Found in:** `app/Concerns/EventValidationRules.php`

### Symptoms

A user can set `event_date` to `1990-01-01`. The form accepts it. The
event appears on the list with a past date.

### Root cause

The rule is:

    'event_date' => ['required', 'date'],

No `after_or_equal:today`.

### Fix outline

Depends on the answer to the product question below.

**If only future events are allowed:**

    'event_date' => ['required', 'date', 'after_or_equal:today'],

**If historical events should be recorded:**

Leave the rule as-is. Past dates are legitimate.

**If past dates are allowed but only recently:** use
`after_or_equal:-30 days` or similar.

### Decisions before implementing

- **Should WDEMS support historical events?** If a Community Run
  happened last week and is being logged retroactively, the rule
  needs to permit past dates. If every event is created before it
  happens, block past dates.

### Verification when fixed

Depends on which behavior is chosen. Both are simple to test.

---

## EC-10 — `event_name` regexes reject non-ASCII names

**Severity:** Low — likely intentional
**Blocks:** Nothing
**Status:** Open — needs a decision
**Found in:** `app/Concerns/EventValidationRules.php`

### Symptoms

An event named `Ångström Run` fails validation because `Å` is not in
`[A-Za-z0-9]`. Same for any event starting with a non-Latin character.

### Root cause

Five regex rules in `eventNameRules()`. Two of them are explicitly
ASCII:

    'regex:/^[A-Za-z0-9]/',
    'regex:/[aeiouyAEIOUY]/',
    'regex:/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/',

These were written to catch keyboard mashing ("dfdsfdsfd"). They are
strictly ASCII by design, but that means legitimate non-English event
names fail.

### Fix outline

Either:

**Approach A — Widen the character classes.** Use Unicode properties
in the regexes (Laravel supports `\p{L}`, `\p{N}`, etc.). This
preserves the "must contain a letter" check while allowing non-Latin
scripts.

**Approach B — Leave as-is.** If all events are English-named,
the strictness is fine. Document the limitation.

Recommendation: **B** unless the project expects non-English events.
The regexes catch a real category of mistake; loosening them may
reduce that protection.

### Decisions before implementing

- **Will WDEMS ever host non-English event names?** If yes, Approach
  A. If no, Approach B.

### Verification when fixed

Not applicable if Approach B.

---

## EC-11 — Client-side validation is emptiness-only

**Severity:** Note
**Status:** Documented as intentional — no fix expected
**Found in:** `resources/js/components/wizard.tsx` (`validateCurrentStep`)

### Description

The client-side `validateCurrentStep` function checks only whether
required fields are empty. It does not check formats, ranges, or
enums. This is documented as intentional in
`docs/event-creation.md` Trap #6, and again in
`docs/event-creation-wizard.md` §7.1.

The consequence: user sees the first format error only after a
server round-trip. For small forms this is fine. For slow connections
it means each fix requires a new submit.

### Not a bug — recorded for completeness

Nothing to fix. If the product decides the UX is too slow, the
alternative is to add Zod (or a similar client-side validator) that
mirrors `EventValidationRules`. That is a larger project and would
require keeping the two rule sets in sync. Not recommended at
current scale.

---

## How to add a new issue

Copy the template:

    ## EC-NN — Short title

    **Severity:** High | Medium | Low | Note
    **Blocks:** <what it prevents> | Nothing
    **Status:** Open
    **Found in:** <file paths>

    ### Symptoms
    ### Root cause
    ### Fix outline
    ### Decisions before implementing
    ### Verification when fixed

Update the priority table at the top. Do not reorder existing issues
without a note in the changelog.

---

## Changelog

- **2026-09-25** — File created. EC-01 through EC-11 recorded.
  Priority order set: EC-01, EC-02, EC-03 first (blocking), then
  EC-04, EC-05 (rule additions), then EC-06 through EC-08 (hardening
  and UX), then EC-09 and EC-10 (need decisions), then EC-11
  (informational).

---

## Fixed items (2026-09-25)

The entries above stay as written — their `**Status:** Open` lines
reflect the state when the file was created. The items below are the
ones that have since been fixed. Where an entry above says "Open" and
appears here as fixed, this section is authoritative.

| # | Title | Fixed by | Evidence |
|---|---|---|---|
| EC-01 | `venue_latitude.toFixed()` crashes on edit | `normalizeCoordinate()` in `edit.tsx` | Browser: edit page renders for a coordinate-bearing event |
| EC-02 | Partner sub-errors never route to Step 4 | Prefix matching in the wizard's error-jump effect | Code review; browser test pending final confirmation |
| EC-04 | No `end_time > start_time` check | Server rule + `withValidator` hook + client mirror | 13 feature tests in `EventScheduleRulesTest` |
| EC-05 | `course_url` rejects scheme-less URLs | Auto-prepend `https://` on blur + strict scheme regex on both ends | 5 feature tests + browser test |
| EC-06 | No cap on `partners` array size | `max:20` added to server rule + client already capped | Same test file |
| EC-11 | Client-side validation is emptiness-only | New `event-validation.ts` + `validate?` hook in the wizard | Browser: `fdhgfhgfhgfhfgfhdfgfr` rejected with a professional message |

### New problem found and fixed during the session

Not listed above because it was not in the original eleven:

- **SCH-03** — Laravel's `url` rule and the client's `new URL()` both
  accepted `data:` and `javascript:` schemes. `course_url` is rendered
  as `<a href>`, so this was a latent XSS vector on the show page. Fixed
  by switching both ends to `regex:/^https?:\/\//i`. Covered by two of
  the five URL tests in `EventScheduleRulesTest`.

### Still open

| # | Title | Status |
|---|---|---|
| EC-03 | Partner errors not displayed in ExtrasStep | Open — wizard now routes to the step, but the step does not show per-row errors |
| EC-07 | No trimming of text inputs | Open — client trims for validation; server stores raw |
| EC-08 | `clear()` fires before submit | Open |
| EC-09 | No past-date check on `event_date` | Open — needs a product decision |
| EC-10 | `event_name` regexes reject non-ASCII | Open — needs a decision |
| SCH-04 | Time picker 5-minute snap is invisible | Open — cosmetic |
| SCH-05 | Distance unit conversion drift | Open — cosmetic |

## Change log addendum

- **2026-09-25** — EC-01, EC-02, EC-04, EC-05, EC-06, EC-11 marked
  fixed. SCH-03 recorded as a new problem found and fixed during the
  session.

---

## Additional fixed items (2026-09-25, second pass)

The `## Fixed items` section above was written at the end of the first
development block. The Schedule step was completed in a second block
after that. This section records the additional closures.

| # | Title | Fixed by | Evidence |
|---|---|---|---|
| SCH-04 | Time picker 5-minute snap is invisible | `MINUTES` array reduced to 15-minute intervals. No snap message needed — the dropdown only offers the four valid options | Browser: minute dropdown shows `00`, `15`, `30`, `45` |
| SCH-06 | No past-date check on `event_date` | `after_or_equal:today` on the server, mirrored on the client and in the calendar grid | 8 tests in `EventDateRulesTest` |
| SCH-06c | Today allowed but no heads-up about lack of advance registration | Amber warning in `ScheduleStep` shown only when `event_date === todayIso()` | Browser: warning appears on today, hides on tomorrow or on clear |
| SCH-07 | No future bound on `event_date` | `before:+2 years` on the server, mirrored on the client and in the calendar grid | 8 tests in `EventDateRulesTest` |

### Still open

| # | Title | Status |
|---|---|---|
| EC-03 | Partner errors not displayed in ExtrasStep | Open — wizard routes to the step, but the step does not show per-row errors |
| EC-07 | No trimming of text inputs | Open — needs `TrimStrings` middleware check before any fix is proposed |
| EC-08 | `clear()` fires before submit | Open |
| EC-10 | `event_name` regexes reject non-ASCII | Open — needs a decision |
| SCH-05 | Distance unit conversion drift | Open — needs a decision |

### Closed since the file was created

All of these were listed as open at file creation and have since been
fixed. This is a summary — full entries are in the sections above.

EC-01, EC-02, EC-04, EC-05, EC-06, EC-11, SCH-01a, SCH-01b, SCH-01c,
SCH-02, SCH-03, SCH-04, SCH-06, SCH-07, plus the newly discovered
SCH-03 (`data:`/`javascript:` URLs) and SCH-06c (today warning).

## Change log addendum

- **2026-09-25** — SCH-04, SCH-06, SCH-06c, SCH-07 marked fixed.
  Consolidated list of every closed issue added.

---

## EC-03 — Partner errors not displayed in ExtrasStep (CLOSED)

**Severity:** Medium
**Status:** **Fixed — 2026-09-25** — see FIX-021
**Found in:** `resources/js/components/partner-editor.tsx`,
`resources/js/pages/events/step/ExtrasStep.tsx`

### What was broken

Partner sub-errors (`partners.0.name`, `partners.0.type`) triggered the
wizard's top-level banner ("Please fix the highlighted fields before
continuing") but no field was highlighted and no inline error text
rendered. The wizard correctly routed to Extras; the fields were blank
of any indication what was wrong.

### Root cause

`PartnerEditor.tsx` had no `errors` prop. `ExtrasStep.tsx` passed only
`value` and `onChange`. The error values existed in
`errors['partners.0.name']` — set by `validateExtras` on the client or
by `validatePartnerDuplicates` on the server — but the component had
no channel to receive them.

### Fix

Two files:

- `partner-editor.tsx` — added `errors?: Record<string, string>` prop
  (default `{}`). Added `InputError` import. Renders inline errors
  under the name and type fields for each row.
- `ExtrasStep.tsx` — passes `errors={errors}` to `<PartnerEditor>`.

Full record: `docs/fixes.md` FIX-021.

### Verification

Browser: adding a partner with name `hgsd` (no vowel) shows inline
**"Please enter a valid partner name."** Adding an empty partner row
shows **"Partner name is required."** Both render under the field, on
the same row as the input. The top-level banner remains and now
actually corresponds to visible errors.

---

## EC-12 — No test coverage for partner duplicate + quality rules

**Severity:** Low
**Status:** Open
**Found in:** `app/Concerns/EventValidationRules.php`,
`resources/js/lib/event-validation.ts`
**Related:** FIX-018, FIX-020

### Symptoms

Two partner validation rules — duplicate detection (name+type pair)
and human-name quality checks (via `humanNameQualityRules`) — are
written, wired, and type-check. Nothing proves they fire. A future
change to normalization logic, key format, or the skip-on-missing
condition could silently break either rule.

### Root cause

Both rules were added mid-session without a matching test file. Same
class of gap as `EventDeletionService::delete()` (see
`docs/event-deletion.md` §11) — a real behavior left unpinned.

### Fix outline

New test file `tests/Feature/Events/EventPartnerRulesTest.php`. Follow
the pattern in `EventUrlRulesTest.php` — local `payload()` and
`staff()` helpers, `$this->actingAs($user)->post(route('events.store'), ...)`.

Cases to cover:

**Duplicate rule:**
- Two identical name+type pairs → rejected with `partners.N.name`
- `nike/sponsor` + `Nike/sponsor` → rejected (case-insensitive)
- `Nike /sponsor` + `Nike/sponsor` → rejected (trim)
- `Nike/sponsor` + `Nike/host` → accepted (different type)
- `Nike/sponsor` + `Adidas/sponsor` → accepted (different name)
- Three rows, third duplicating the first only → rejected on the third
- Two rows with missing name → shape errors, no duplicate error fires

**Quality rule:**
- `dfdffdfd` (no vowels) → rejected
- `aeiou` (no consonants) → rejected
- `aaaa` (3+ repeats) → rejected
- `-abc` (bad first char) → rejected
- `Barangay San Roque` → accepted

### Verification when fixed

    php artisan test tests/Feature/Events/EventPartnerRulesTest.php

All tests pass. Then `php artisan test tests/Feature/Events` — nothing
regresses.

### Cross-references

- FIX-018 — duplicate rule
- FIX-020 — partner name quality rules
- FIX-021 — inline error display

---

## Change log addendum

- **2026-09-25** — EC-03 marked closed (fixed via FIX-021). EC-12
  opened — no test coverage for duplicate + quality rules.
