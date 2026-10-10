# WDEMS — Participant Identity

**Audience:** Whoever implements, modifies, or reasons about how
WDEMS identifies a participant across events.

**Purpose:** Describe the participant identity model. This is a spec —
nothing here describes current code. When the model lands, this doc
converts to a reference (like `authentication.md`).

**Status:** Planned. No code exists yet.

**Related:**
- `docs/participant-identity-and-monitoring-plan.md` — the phased build plan
- `docs/registration.md` — the registration feature this sits behind
- `docs/known-issues.md` — ISSUE-008 (predecessor)

---

## 1. The problem this solves

WDEMS already stores participants. It does not decide who they are.

Today, a `Participant` row exists per submission. Two rows that
describe the same human are indistinguishable from two rows that
describe two humans. There is no email uniqueness constraint, no phone
normalization, and no resolver. The only identity logic in the
codebase is `Participant::firstOrCreate` inside
`PublicRegistrationController::store()`, keyed on
`(email, first_name, last_name)`.

That is enough when:

- Email is required
- Names are consistent across events
- No two participants share an email

It fails in every other case. The recent
`registration_common_field_requirements` feature explicitly permits
disabling email-required — which is exactly the case that breaks the
current model.

## 2. The decision — model E

WDEMS adopts a two-key identity model:

| Field | Role |
|---|---|
| Email | Primary identity key |
| Contact number (normalized) | Secondary identity key |
| Name | Descriptive only — never used for identity |
| Age, address | Descriptive only |

**Rule:** at least one of email or contact number must be provided.
Email wins when both are present.

Name is captured for display. Address and age are captured for
operational needs (safety, coordination, accommodation). None of them
participate in matching.

## 3. Why this model — the trade-offs

Compared to the alternatives considered:

- **Email only.** Simpler. Works for events that require email.
  Blocks every event that wants email optional. Rejected because
  required/optional is a deliberate feature.
- **Email + name.** Current behavior. Handles shared emails by
  disambiguating on name. But name typos split identity, and minor
  name variations ("Maria" vs "Maria D.") produce different people.
  Rejected because names are not identifiers.
- **Phone only.** Strong in the Philippine context. Format-variable.
  Rejected as the sole key because email is more stable and more
  common.
- **No cross-event identity.** Every submission is a fresh
  participant. Loses history. Rejected because monitoring depends on
  returning-participant flags.
- **E (email + phone).** Best coverage. Requires normalization rules
  and honest acceptance of the limitations in §9.

The two decisions that made E viable:

- **At-least-one.** Without it, an organizer could disable both
  toggles, and submissions would have no identity at all.
- **First-write-wins.** A subsequent registration never mutates an
  existing participant record. Corrects a name typo? No — the first
  value stays. Rationale: the participant record is identity, not a
  mutable profile.

## 4. Schema changes

### 4.1 `participants.email` — add UNIQUE on non-null values

MySQL permits multiple NULL values in a unique index. Phone-only
participants have `email = NULL` and do not collide with each other.
Participants with an email do not share it with any other
participant.

This is the first time the email column carries an integrity
constraint. Before this migration, two `Participant` rows could hold
the same email.

### 4.2 `participants.contact_number_normalized` — new column

- `varchar(11)`, nullable
- Unique on non-null values
- Indexed
- Canonical form: `09XXXXXXXXX` (11 digits)

The existing `contact_number` column is preserved for display. It
holds whatever the participant typed. `contact_number_normalized`
holds the value after the mutator in §5 runs, or NULL if the raw
value could not be parsed into a PH mobile format.

### 4.3 `registrations (event_id, participant_id)` — add UNIQUE

One registration per participant per event. Currently enforced only
by a controller-level check. Becomes a database guarantee.

### 4.4 `registrations.consent_accepted_at` — new column

- `timestamp`, nullable
- Set to `now()` when the participant checks the consent checkbox

Currently the consent checkbox is HTML5-only — no server-side record
exists. This column closes that gap. Referenced in
`docs/known-issues.md` as part of the compliance work.

### 4.5 `registrations.privacy_notice_version` — new column

- `varchar(32)`, nullable
- Records which version of the notice the participant agreed to

Initially set to a build-time constant. Per-event versions are a
later concern.

## 5. Phone normalization

A single mutator on the `Participant` model. All writes go through it.

Input formats accepted:

- `09171234567` — 11 digits, leading `0`
- `+639171234567` — 12 digits, leading `+63`
- `639171234567` — 12 digits, leading `63`
- `9171234567` — 10 digits, leading `9`

Output for all four: `09171234567`.

Rules:

    strip all non-digits
    if starts with '63' and length 12 → prepend '0', strip first two digits
    if starts with '9' and length 10  → prepend '0'
    if starts with '09' and length 11 → keep
    else → null (unrecognized)

When normalization returns null:

- The raw `contact_number` is still stored for display
- `contact_number_normalized` stays NULL
- The at-least-one rule treats the participant as having no phone

## 6. The resolution rule

A single method:

    Participant::resolveFrom(
        ?string $email,
        ?string $rawPhone,
        array $attributes,
    ): Participant

Algorithm:

1. If `$email` is non-empty → `firstOrCreate(['email' => $email], $attributes)`.
   Match on email only.
2. Else if `normalize($rawPhone)` returns a value → `firstOrCreate(['contact_number_normalized' => $normalized], $attributes)`.
   Match on phone only.
3. Else → throw `IdentityRequiredException`.

On existing match, `$attributes` are **discarded**. First-write-wins.
A name correction on a subsequent registration does not change the
participant record.

Called from `PublicRegistrationController::store()`. Replaces the
current inline `firstOrCreate` keyed on
`(email, first_name, last_name)`.

## 7. The at-least-one rule

Enforced in two places:

**Public form** — `PublicRegistrationRequest::rules()`. If both
`email` and `contact_number` are empty, reject with a form-level
error. The rejection runs after format validation, so a malformed
email or phone would already have failed.

Message: *"Please provide at least an email address or a contact
number."*

**Form builder** — `RegistrationFieldRequest::withValidator()`. If
`common_field_requirements.email === false` AND
`common_field_requirements.contact_number === false`, reject the save.
An organizer cannot configure a form where both identity fields are
optional.

Message: *"At least one of email or contact number must be required."*

## 8. What "duplicate" means — and what it does not

A duplicate in WDEMS is:

- A second registration for the same event by the same participant
  (email or normalized phone match)

Prevented by the existing D5 duplicate check plus the new
`(event_id, participant_id)` unique index.

A duplicate is **not**:

- The same participant registering for two different events
- The same email used by two different people on two different events

The model's guarantees are: one row per distinct email (or
normalized phone) globally, and one registration per participant per
event.

## 9. Limitations — stated honestly

The model does not solve:

- **Mobile number recycling.** PH telcos reassign numbers after
  roughly six months of deactivation. A new owner inherits the
  previous owner's registrations.
- **Shared family emails.** `family@example.com` used by three
  people collapses to one participant.
- **International and landline numbers.** Only PH mobiles in the four
  accepted formats normalize. Everything else falls back to
  email-only identity or fails the at-least-one rule.
- **Cross-key drift.** Maria registers in January with phone only.
  Registers in June with email only. Two `Participant` rows, no
  automatic merge. The organizer sees two "Maria Santos" entries.
- **Name-based matching.** Deliberately not attempted. Two different
  people named "Maria Santos" are two participants, and no heuristic
  should try to merge them.

These are accepted costs. They are the price of an identity model
that works across events, respects the DPA's spirit (one person, one
record), and does not require an approval workflow.

## 10. Out of scope for this model

- Merging two participant records. Deferred.
- Splitting one participant record into two. Deferred.
- Participant-facing profile editing. WDEMS has no participant
  login.
- Email or phone verification. Deferred — WDEMS does not send
  outbound email to participants in v1.
- Cross-organization identity. WDEMS is single-organization.

## 11. Open items

- **Legacy backfill.** Existing participants need their
  `contact_number_normalized` backfilled, or the unique index fails
  on the first attempt to add it. A single migration handles it, but
  it needs a dry-run before it lands. See the plan, Phase 2.
- **Unparseable phones on existing rows.** Some legacy
  `contact_number` values may not fit the four accepted formats.
  Those get `contact_number_normalized = NULL` and are identified by
  email only. If a legacy row also lacks email, it violates the new
  at-least-one rule — a data cleanup is needed before the constraint
  migration runs.
- **Duplicate emails on existing rows.** If the current `participants`
  table holds two rows with the same email, the UNIQUE index will
  fail to apply. Verify with the query in the plan, Phase 0, before
  Phase 1.

## Changelog

- **2026-10-02** — File created. Identity model E, at-least-one rule,
  resolution algorithm, normalization rules, and limitations
  recorded.

---

## Status — Partially Implemented (2026-10-02)

The header's §Status line reads:

    **Status:** Planned. No code exists yet.

That was accurate when this file was created on 2026-10-02. It is
no longer accurate. Two of the ten phases in
`docs/participant-identity-and-monitoring-plan.md` have shipped.

This is a status block, not a spec rewrite. The body above remains
the authoritative description of the intended model. Where the body
says "will be" or "will land", read the shipped state below.

### What exists

**Phase 1 — schema and constraints** (commit `cf6d4df`, 2026-10-02)

- `participants.contact_number_normalized` — varchar(11), nullable,
  unique on non-null. Populated by the Phase 3 mutator.
- `registrations.consent_accepted_at` — timestamp, nullable.
  Not yet written by any code path — the public form's consent
  checkbox is HTML5-only (see ISSUE-010).
- `registrations.privacy_notice_version` — varchar(32), nullable.
  Same status as `consent_accepted_at`.
- `participants.email` — UNIQUE on non-null. Multiple NULLs
  permitted.
- `participants.contact_number_normalized` — UNIQUE on non-null.
  Multiple NULLs permitted.
- `registrations (event_id, participant_id)` — UNIQUE. Pre-existed
  in the base migration; verified, not added.
- `Participant::$fillable` updated to include
  `contact_number_normalized`.

**Phase 2 — legacy backfill** — skipped. Phase 0 reconnaissance
confirmed zero existing phone values (commit `910eff6`). No
backfill was required. The phase is closed as a no-op.

**Phase 3 — model** (commit `22e603c`, 2026-10-02)

- `Participant::setContactNumberAttribute` — mutator. Writes both
  `contact_number` (raw, for display) and
  `contact_number_normalized` (canonical, for identity).
- `Participant::normalizeContactNumber` — private static helper.
  Accepts the four PH mobile formats in §5. Returns null for
  unparseable input.
- `Participant::resolveFrom` — the resolver described in §6. Email
  primary, normalized phone fallback, throws
  `IdentityRequiredException` when neither is present.
  First-write-wins on match.
- `App\Exceptions\IdentityRequiredException` — new class in a new
  `app/Exceptions/` namespace.

### What does not yet exist

- **Phase 4** — the at-least-one rule. `PublicRegistrationRequest`
  and `RegistrationFieldValidationRules` do not yet reject
  submissions or form-builder saves where both identity fields
  are absent. The resolver's throw is currently the only defense.
- **Phase 5** — the controller rewrite.
  `PublicRegistrationController::store()` still uses the
  pre-identity `firstOrCreate` on
  `(email, first_name, last_name)` and a plain `create()` when
  email is absent. The resolver is not called from any live code
  path yet.
- **Phases 6–10** — attendance sidebar, monitoring pages,
  monitoring actions, delete-registration OTP, docs close.

### Known gap introduced by Phase 1 + Phase 3

The `contact_number_normalized` UNIQUE constraint (Phase 1) is
live, but the controller still writes participants the old way
(Phase 5 has not landed). Result: in an email-optional event, a
second submission sharing a phone number with the first raises a
`QueryException` and returns a 500 to the user. Recorded as
ISSUE-009 in `docs/known-issues.md`. Phase 5 converts this into a
proper duplicate-detection error by routing through
`resolveFrom()`.

Until Phase 5 lands, an organizer running an email-optional event
is exposed to this 500 on duplicate-phone submissions. The
exposure is bounded — it requires two submissions with the same
phone but no email — but it is real.

### What "hidden" behaviors exist in the shipped code

Two behaviors of the shipped pieces are non-obvious and worth
naming here so they are not lost:

1. **The mutator runs on every write to `contact_number`.** Not
   just create. Any `$participant->update(['contact_number' => ...])`
   or `->fill()` write recomputes `contact_number_normalized`.
   This is intentional — the normalized value can never drift from
   the raw value — but it means a manual edit of the raw phone
   silently changes the identity key. A future "edit participant"
   action (monitoring Phase 8) must be aware.

2. **First-write-wins applies to attributes, not to the identity
   keys themselves.** If a participant matched by email has a
   different phone in the incoming payload, the phone is discarded.
   The identity is stable; the descriptive data is frozen at first
   write. Editing a participant's phone therefore requires an
   explicit `update()`, not a re-resolve.

### Reference

- Plan: `docs/participant-identity-and-monitoring-plan.md`
- Issues: `docs/known-issues.md` ISSUE-009 (duplicate-phone 500)
- Commits: `cf6d4df` (Phase 1), `22e603c` (Phase 3)

## Changelog addendum

- **2026-10-02** — Status block added. Phases 1 and 3 marked
  shipped. Phase 2 closed as a no-op. Phases 4–10 listed as not
  yet started. Known gap ISSUE-009 (duplicate-phone 500) named.
  Two hidden behaviors of the shipped code documented for the
  first time.

---

## Status addendum (2026-10-10)

The status block above (added 2026-10-02) records Phase 4 and
Phase 5 as not yet started. Both have since shipped. Per the
append-only rule (P7), the earlier block stands as a historical
record. This addendum corrects the current state.

### Phase 4 - at-least-one rule (shipped)

The at-least-one rule exists in two places:

- `PublicRegistrationRequest::withValidator()` Pass 3 adds the
  `identity` error when both email and phone are blank.
- `RegistrationFieldValidationRules::validateCommonFieldRequirementsIdentity()`
  rejects form-builder saves where both `email` and
  `contact_number` toggles are off.

Test coverage:

- `tests/Feature/Public/PublicRegistrationIdentityRuleTest.php`
- `tests/Feature/Events/RegistrationFormIdentityRuleTest.php`
- `tests/Feature/Events/EventCommonFieldRequirementsTest.php`

### Phase 5 - controller rewrite (shipped)

`PublicRegistrationController::store()` now calls
`Participant::resolveFrom($email, $phone, $attributes)` instead
of the pre-identity `firstOrCreate`. The D5 duplicate check
runs against the resolved participant, uniform across email and
phone identities.

ISSUE-009 (duplicate-phone 500) is closed by this change.

### What Phase 4 did not cover

The at-least-one rule is a **presence** rule: is there an email
or a phone? It is not a **quality** rule: does the phone look
like a phone?

FIX-027 (2026-10-10) adds the missing quality layer via
`ContactAndAddressQualityRules`. A garbage phone with a valid
email present is now rejected on both the public form and the
walk-in dialog. See `docs/fixes.md` FIX-027.

### Cross-references

- FIX-027 - contact_number and address quality rules
- ISSUE-012 - the gap FIX-027 closes (fixed)
