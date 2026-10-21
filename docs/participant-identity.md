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

## Corrections — public contact verification (2026-10-13)

The descriptions above of immediate public `Participant::resolveFrom()` calls
and deferred contact verification are historical. Public registration now creates
an encrypted pending challenge and verifies every required email/phone channel
before creating participant, registration, or answer records. Optional contacts
are registration snapshots only. Verified identifiers belonging to different
participants cause a safe conflict instead of choosing email or merging records.

`Participant::resolveFrom()` remains unchanged for attendance walk-in and bulk
paper intake. Public finalization uses a separate transactional verification
service and preserves first-write-wins and all existing uniqueness constraints.
See [Participant contact verification](registration-contact-verification.md)
for the complete flow, staff authorization, limits, migrations, and operations.

## Correction to §Corrections — public contact verification (2026-10-14)

The cross-reference at the end of the previous corrections block reads:

> See [Participant contact verification](registration-contact-verification.md)
> for the complete flow, staff authorization, limits, migrations, and
> operations.

"Staff authorization" referred to the removed staff phone queue.
Commit `b552cbb` (2026-10-14) replaced that channel with Firebase
Phone Auth. There is no staff authorization step for phone
verification any longer — the participant's own phone is the
authenticator.

Everything else in the previous corrections block still describes the
shipped flow accurately. The linked document's
`## Corrections (2026-10-14)` block is the authoritative description of
the current phone channel.

---

## Correction — `participants.birthday` and derived age (2026-10-19)

§4 documents the identity schema but never mentions the `birthday`
column. It exists (added in the original Phase 3 migration),
nullable, cast to `date`, mass-assignable — and as of 2026-10-19 it
is the primary demographic field:

- **Write.** Public registration, single walk-in, and bulk walk-in
  all accept `birthday` (preferred) or legacy `age`, bounded to
  ages 3–120. When both arrive, `birthday` wins and the raw `age`
  column stays null.
- **Read.** `Participant::getAgeAttribute()` derives age from
  `birthday` when present, falls back to the stored `age` column
  for legacy rows, and returns null when neither exists.
- **Bounds change.** The previous effective range was 1–120 (age
  input); it is now 3–120 (birthdate picker). Rows with stored ages
  1–2 written before this change keep their values; the accessor
  still reads them.

Identity semantics (§2, §5, §6) are unchanged: email and normalized
phone remain the identity keys, first-write-wins still applies,
and the demographic fields never participate in identity
resolution.

---

## Corrections — Composite identity (2026-10-19)

Append-only. The 2026-10-19 composite identity rule (shipped W7)
reverses several decisions recorded in the body above. Where this
section disagrees with any earlier text — including the
`## Correction — participants.birthday` block, which lands earlier on
the same calendar day — this section wins.

Full reference: `docs/composite-identity.md`.

### §3 — reversed

Body reads:

> **Email + name.** Current behavior. Handles shared emails by
> disambiguating on name. But name typos split identity, and minor
> name variations ("Maria" vs "Maria D.") produce different people.
> Rejected because names are not identifiers.

Correction. Name **is** now an identifier. The 2026-10-19 rule is
composite: person key = normalized name + verified contact. Names
were rejected in 2026-10-02 because the concern at that point was
that adding name to the match would fragment identity on typos. The
2026-10-19 change accepts that fragmentation as the cost of
representing families sharing one contact. See
`composite-identity.md` §2 (motivation) and §7.1 (accepted
limitation).

### §4.1 — unique email dropped

Body reads:

> ### 4.1 `participants.email` — add UNIQUE on non-null values
>
> MySQL permits multiple NULL values in a unique index. Phone-only
> participants have `email = NULL` and do not collide with each
> other. Participants with an email do not share it with any other
> participant.

Correction. The UNIQUE was added 2026-10-02 and removed 2026-10-19
(migration `2026_10_19_170000`). Multiple participants may share the
same email — this is what makes the family case work. The same
reasoning applies to `contact_number_normalized`: UNIQUE added
2026-10-02, removed 2026-10-19 (migration `2026_10_19_170100`). Both
columns remain nullable and non-unique. The base migration's
non-unique indexes on `email` and `(last_name, first_name)` remain.

See `composite-identity.md` §3.

### §5 — normalization extended to names

Body describes phone normalization only (`contact_number_normalized`
via `setContactNumberAttribute` mutator). The 2026-10-19 rule adds
name normalization: `first_name_normalized` and
`last_name_normalized`, written by `setFirstNameAttribute` and
`setLastNameAttribute` mutators, computed by
`Participant::normalizeName()` using
`mb_strtolower(trim(collapse_whitespace(...)))` — Unicode-aware.
Case-insensitive matching; display preserves case.

See `composite-identity.md` §4 (normalizer) and §8 (case
sensitivity decision).

### §6 — resolution rule rewritten

Body reads:

> ### The resolution rule
>
> 1. If `$email` is non-empty → `firstOrCreate(['email' => $email], $attributes)`.
>    Match on email only.
> 2. Else if `normalize($rawPhone)` returns a value →
>    `firstOrCreate(['contact_number_normalized' => $normalized], $attributes)`.
>    Match on phone only.
> ...
> On existing match, `$attributes` are **discarded**. First-write-wins.

Correction. `resolveFrom()` is now composite. It matches on
`(normalized first name, normalized last name, email)` OR
`(normalized first name, normalized last name, normalized phone)`.
First-write-wins is preserved within a key.

Required because under the non-unique email rule, matching on email
alone and discarding attributes would silently merge a kid's row
into the parent's row — the kid would be registered as the parent.

See `composite-identity.md` §5.3.

### §6 docblock — the "Email is the primary identity key" line

The `resolveFrom()` docblock in `app/Models/Participant.php` (~L155-170)
still reads "Email is the primary identity key." That is no longer
true. Composite is. Do not treat the docblock as authoritative — the
composite rule is.

### The birthdate correction (2026-10-19, earlier same day)

The `## Correction — participants.birthday and derived age (2026-10-19)`
block above closes with:

> Identity semantics (§2, §5, §6) are unchanged: email and
> normalized phone remain the identity keys, first-write-wins still
> applies, and the demographic fields never participate in identity
> resolution.

That was true on the morning of 2026-10-19. The composite work landed
later the same day. Email and normalized phone are no longer the
identity keys — name + contact is. First-write-wins within a key is
unchanged. The final sentence stands: neither birthday nor any other
demographic field participates in identity resolution.

### What did NOT change

- `normalizeContactNumber()` — the PH phone normalizer. Unchanged.
- `IdentityRequiredException` — still thrown when both name and
  contact are absent.
- The at-least-one rule (form builder + public request).
- First-write-wins semantics — preserved within a key.
- Per-event duplicate prevention — still enforced by
  `UNIQUE(event_id, participant_id)` after
  `assertNoDuplicateRegistration` runs.
- The participant identity model's core promise: one row per
  distinct identity, `resolveFrom()` as the sole writer.

### Changelog addendum

- **2026-10-19** — Correction block added. §3 (name as identifier),
  §4.1 (unique email), §5 (normalization), §6 (resolution rule)
  reversed by the composite identity rule. Authoritative reference:
  `docs/composite-identity.md`.

## Full-name identity amendment — 2026-10-20

Mark approved Option A and case-insensitive matching, including middle name and
suffix. This supersedes earlier descriptions limited to first and last name.
The name portion now contains first name, middle name, last name, and suffix;
each is trimmed, internal whitespace collapsed, and lowercased. Null or blank
optional parts normalize to the empty string. An omitted middle name or suffix
is not a wildcard for an existing populated name.

The migration backfills normalized columns from existing raw name values.
Verified contact plus the full normalized name resolves the person; matching
names with different contacts and different names sharing a contact remain
allowed. A second registration for the same resolved person on the same event
remains rejected. Birthday and other demographics remain outside the key.
Public finalization and duplicate checks, walk-in entry, and service attendance
resolution use this full-name rule. Service/event-kind boundaries remain intact.

## Provisional Quick walk-ins — 2026-10-20

Mark approved event-local provisional records for name-only Quick walk-ins.
Quick entry always creates a fresh participant with `provisional_event_id`,
flags the registration for staff review, and marks attendance Present. It does
not infer identity from the name. Composite lookup excludes provisional records,
even if contact data is later added; staff review is required before linking.
No automatic cross-event merge or provisional-to-established conversion is made.

For paper registration Mark approved staff attestation instead of participant
email OTP. The encoder's user ID and attestation time are stored separately on
the registration. This is not an OTP verification or barcode verification marker.
The full event form contract and per-event duplicate check still apply.

## Follow-up — advisory duplicate defense and church pickers (2026-10-21)

The full case-insensitive composite rule also applies to Fellowship's previous
phone-only lookup. Provisional running participants are excluded from explicit
Services/Fellowship picker validation and Discipleship enrollment/search until
staff linking has a dedicated review flow.

Possible-duplicate matches are event-scoped suggestions, not identity resolution.
Similar normalized full names require a shared birthdate or contact to appear in
the audit. Staff name lookup is advisory and permits an encoder to review an
existing registration instead of creating another. A family sharing a contact
remains valid when the full names identify different people.


## Running Events audit addendum — 2026-10-21

The current key includes normalized first, middle, last, and suffix plus verified contact; empty optional name parts are not wildcards. Staff-attested paper contacts resolve immediately. Quick walk-ins remain provisional/event-local. Monitor editing now follows composite collision checks instead of contact-only uniqueness. Similarity warning is on Step 2 per Mark; kit claiming remains a staff procedure.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.
