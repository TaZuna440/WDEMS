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
