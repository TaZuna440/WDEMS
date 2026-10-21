# WDEMS — Composite Participant Identity

**Status:** Shipped 2026-10-19. Supersedes `docs/participant-identity.md`
§3, §4.1, §5, §6 for the current state. This file is the authoritative
reference for how a participant is identified.

**Audience:** Whoever touches `Participant::resolveFrom()`,
`RegistrationVerificationService::assertNoDuplicateRegistration()`, the
participant schema, or any code path that resolves a participant from
user input.

**Purpose:** Document the switch from contact-only identity to
name + contact composite identity — what shipped, what didn't, and what
remains open.

**Related:**
- `docs/participant-identity.md` — original spec. §3 ("Email + name
  rejected") is reversed by this file.
- `docs/registration-wizard.md` — the public flow that consumes the
  composite resolver.
- `docs/audit-2026-10-19.md` — findings L1, M4, M5 touch this subsystem.
- `docs/known-issues.md` — ISSUE-014+ derived from the audit.

---

## 1. The rule

A person is identified by:

    (normalized name) + (contact)

where:

- **normalized name** = `mb_strtolower(collapse_whitespace(trim(name)))`,
  Unicode-aware. Currently covers `first_name` + `last_name` only — see
  §7.2.
- **contact** = verified email OR verified normalized phone. If both are
  supplied, the verified one wins.

Two submissions are the same person if and only if **both** name and
contact match. They are a **duplicate** if and only if both match **on
the same event**.

Anything else — same name / different contact, same contact / different
name — is allowed. The first case is two people with the same name; the
second case is the family case the rule exists for.

## 2. Why Option A — the family case

The motivating case, recorded 2026-10-19:

> A parent wants to register themselves and their kids for a community
> run using the parent's email for everyone. The system blocks this —
> one email could only belong to one person globally.

Contact-only identity ("one email = one person, globally") cannot
represent a family. The two options presented:

- **Option A (chosen)** — composite. Same name + same contact =
  duplicate. Different names sharing one contact = allowed. Cross-event
  history follows the composite key.
- **Option B** — quick signup, no cross-event memory for kids. Simpler
  but loses the returning-participant signal that the end-of-event
  report depends on.

Mark chose A. Client has not confirmed.

### 2.1 What "cross-event history works" means

Under the composite key, "Maria Santos + family@email.com" registering
for Event A and Event B resolves to the same `Participant` row. The
`total_appearances` count in `ReportService::participants()` includes
both events.

"Maria Santos + family@email.com" and "Jose Santos + family@email.com"
are two different rows with two different histories. Each is a
returning participant in its own right if it has appeared more than
once under its own key.

## 3. Schema changes

Three migrations, all landed 2026-10-19.

| Migration | Change |
|---|---|
| `2026_10_19_170000` | Drop UNIQUE on `participants.email` |
| `2026_10_19_170100` | Drop UNIQUE on `participants.contact_number_normalized` |
| `2026_10_19_170200` | Add `participants.first_name_normalized` + `last_name_normalized` (nullable strings) |

The UNIQUE indexes being dropped were added 2026-10-02:

- `2026_10_02_122000_add_unique_email_to_participants_table.php`
- `2026_10_02_123000_add_unique_normalized_phone_to_participants_table.php`

The base `participants` migration's non-unique indexes on `email` and on
`(last_name, first_name)` remain — the drops remove the constraint, not
the lookup index.

Both `email` and `contact_number_normalized` remain nullable and
non-unique. Multiple participants may share either, both, or neither.

The two normalized name columns are backfilled from the raw
`first_name` / `last_name` using the new normalizer, chunked. The
backfill runs in PHP, not SQL, because MySQL's `LOWER()` is not reliably
Unicode-safe.

## 4. The normalizer

    Participant::normalizeName(?string $value): ?string

Static, public, sole source of truth for "same name". Three operations,
in order:

1. `trim()`
2. Collapse internal whitespace to a single space
3. `mb_strtolower(..., 'UTF-8')`

`mb_strtolower` was chosen over `Str::lower()` for Unicode safety. Q7
(non-English event names) is still open; if the answer is "yes,"
accented characters will appear and the ASCII-only path would fail.
`mb_strtolower` handles ASCII identically to `Str::lower` and handles
the full Unicode range — zero cost, forward-compatible.

Null input returns null. Empty string returns empty string; the two are
not the same and the resolver treats them differently (§5.3).

## 5. Model changes

### 5.1 Mutators

`setFirstNameAttribute(?string $value)` and
`setLastNameAttribute(?string $value)` both write the raw value and the
normalized value. Mirrors the existing `setContactNumberAttribute()`
pattern for `contact_number` + `contact_number_normalized`.

Every write to `first_name` or `last_name` — including direct
`$participant->update(['first_name' => ...])` from the monitor edit
dialog — recomputes the normalized column. There is no path that
writes the raw name without updating the normalized form.

### 5.2 Fillable

`first_name_normalized` and `last_name_normalized` are in
`#[Fillable]`. Application code should not write them directly — the
mutator is the sole writer. They are fillable so the backfill loop and
admin tooling can write them.

### 5.3 resolveFrom()

Signature unchanged:

    Participant::resolveFrom(?string $email, ?string $rawPhone, array $attributes): self

Behavior changed. Old: email-first `firstOrCreate`, discard attributes
on match. New: composite.

1. Normalize the name from `$attributes['first_name']` and
   `$attributes['last_name']`.
2. If name is empty → throw `IdentityRequiredException`.
3. If `$email` is non-empty → look for a participant matching
   `(email, first_name_normalized, last_name_normalized)`.
4. If no email match and `normalizeContactNumber($rawPhone)` is
   non-empty → look for a participant matching
   `(contact_number_normalized, first_name_normalized, last_name_normalized)`.
5. On match → return the existing row (first-write-wins on attributes).
6. On no match → create a new row.
7. If neither email nor phone is present → throw `IdentityRequiredException`.

**Why the name is in the query, not just in attributes.** Under the old
rule, first-write-wins was safe because email was globally unique. Under
the composite rule, two submissions with the same email but different
names are different people. Matching on email alone and discarding
attributes on match would silently merge the second person into the
first person's row. A kid registering under a parent's email would be
registered **as** the parent.

### 5.4 What resolveFrom does NOT do

- **Does not verify.** Verification happens in
  `RegistrationVerificationService::finalize()`, which calls
  `resolveFrom` only after the challenge has been verified.
- **Does not enforce per-event uniqueness.** That is
  `assertNoDuplicateRegistration()` (§6).
- **Does not cross-check email against phone.** If both are supplied and
  each matches a different existing participant, `resolveFrom` matches
  email-first and ignores the phone conflict. See §7.3.

## 6. Duplicate check

`RegistrationVerificationService::assertNoDuplicateRegistration()`.
Called at `begin()` and again as a backstop at `finalize()`.

**Old:** any registration on this event with the same email OR phone =
duplicate.

**New:** any registration on this event whose participant matches
**both** the normalized name **and** the contact. Two clauses:
`(email AND name) OR (normalized phone AND name)`.

The message stays non-revealing (per W5.1):

> This registration cannot be started. If you believe this is an error,
> contact the event organizer.

- Different names + same contact on the same event → **allowed** (the
  family case).
- Same name + same contact on the same event → **rejected**. The
  `UNIQUE(event_id, participant_id)` constraint does the blocking once
  the resolver finds the participant row; the assertion runs first to
  produce the friendly error.

## 7. Known limitations

### 7.1 Name variants split identity

"Maria Santos" and "Maria D. Santos" sharing one email → two rows. Typo
fragmentation is the accepted cost of the composite rule.

Mitigation: name-similarity warning UI (open, §9.1). Not built.

### 7.2 Middle name and suffix are collected but not in the key

W1 added `middle_name` and `suffix` (nullable) to `participants`. W7's
`normalizeName()` covers `first_name` + `last_name` only.

**Consequence:** "Juan Cruz" and "Juan Cruz Jr." sharing one email are
currently the same person (same first + last, same contact → same key).
The wizard spec §6 says the composite rule "extends to the new fields" —
that extension is **not yet shipped**.

**Consequence for the Jr./Sr. mitigation:** it does not yet work. Two
Juans in the same family sharing an email, distinguishable only by
suffix, will be blocked as duplicates on the same event unless their
first or last names differ.

Open item: add `middle_name_normalized` and `suffix_normalized` to the
composite key. Touches `normalizeName()`, both mutators, the resolver,
the duplicate check, and the tests. Small but not trivial — the key
shape changes and existing rows need re-resolution.

### 7.3 Cross-contact conflict is ignored

`resolveFrom(email, phone, attrs)` where the email matches participant
A and the phone matches participant B (different people): matches
email, ignores phone. First-write-wins on attributes means B's phone is
not attached to A and A's email is not attached to B — the conflict is
simply not surfaced.

`finalize()` treats the identical case as an identity conflict and
stops. Walk-in and bulk-walk-in intake (both call `resolveFrom`
directly) do not. See audit L1.

Open item (§9.3): either add the cross-check to `resolveFrom` (matching
`finalize()`'s behavior) or document the divergence as intentional.

### 7.4 Organizer name edits detach future matches

The monitor's edit-participant action rewrites the shared `Participant`
row. If the organizer corrects "Maria Santos" to "Maria D. Santos",
existing registrations keep their `participant_id` (history survives)
but future submissions under the old spelling resolve as a new
participant row.

Documented in `participant-identity.md` and in the wizard spec §11.

### 7.5 Cross-event impersonation is bounded by inbox ownership

Composite cannot stop Joy on Event F if she holds the inbox. Email
ownership — the OTP — is the identity boundary. Composite is a
duplicate-prevention rule, not an authentication rule.

Per-event, OTP + duplicate check both apply: Joy cannot register for
the same event under Ana's name + Ana's email because the duplicate
check blocks her. On a different event, Joy needs the inbox to pass
OTP, which is a stronger compromise than the composite rule was
designed to defend against.

## 8. Case sensitivity — decided

Spec (§6 of the wizard spec) and code agree: **case-insensitive
matching**.

`normalizeName()` uses `mb_strtolower()`. "ANA" and "ana" normalize to
the same value. "Ana Santos" and "ana santos" are the same key.

A note in the 2026-10-19 progress report reads: *"Mark said 'it is case
sensitive'."* That comment is not reflected in code. Two readings:

1. Mark meant **display** — names render as entered, matching is
   case-insensitive. Both are true in the current code.
2. Mark meant **matching** — the code contradicts his intent.

Recommendation: **keep the code as-is**. Case-sensitive matching would
defeat the purpose of the composite rule — case variants are the most
common duplicate-avoidance miss, more common than typos. Amend the note
to mean "display is case-sensitive, matching is case-insensitive."

No code change recommended. This section fixes the record.

## 9. Open items

### 9.1 Name-similarity warning UI

Requested by Mark. Not built. Two candidate scopes:

- **Option 1** — warning on the public form. Leaks information ("someone
  with this name exists") to anonymous users. Inconsistent with the
  deliberate non-revealing begin/finalize messages.
- **Option 2 (recommended)** — warning on the monitor card, alongside
  the existing `has_shared_phone` flag. Same controller method, same
  query pattern, same rendering, same test file.

Option 2 adds `has_shared_name` to `RegistrationMonitorController::show()`
and renders the same amber triangle used by `has_shared_phone`.

### 9.2 Middle-name + suffix extension (§7.2)

Not started. Touches key shape, both mutators, the resolver, the
duplicate check, and existing rows.

### 9.3 Cross-contact conflict (§7.3)

Two paths: enforce or document-as-intentional. Enforcing makes
`resolveFrom` match `finalize()`'s behavior; documenting preserves the
current asymmetry.

### 9.4 Client confirmation on Option A

Mark chose A. Client has not confirmed. If the client rejects A in
favor of B, W7's schema changes (dropped UNIQUEs, added normalized
columns) would need to reverse and the public flow would lose the
per-event composite rejection.

### 9.5 Ambiguous email-first lookups (checklist I, J, K)

The 2026-10-19 implementation checklist identified three call sites
that used `.where('email', ...)->first()` under the old
globally-unique-email assumption. Now that email is non-unique, these
return **arbitrary** rows when two participants share an email.

- `app/Http/Controllers/ServiceAttendanceController.php:250` — service
  attendance marking
- `app/Http/Controllers/AttendanceController.php:591` — read-only
  pre-lookup
- `app/Http/Requests/WalkInRegistrationRequest.php:209` — Pass 5
  ("not already registered")

The W7 Progress Report does not list these as updated. **Verify before
the next session that reads or modifies these files.** If they are
still using `.where('email')->first()`, they may attach attendance or
walk-in rows to the wrong participant when a family shares an email.

## 10. Test coverage

W7 updated the identity suites and added new cases. Full suite at
close: **833 passed, 3 skipped, 0 failed.**

| File | Change |
|---|---|
| `tests/Feature/Participants/ParticipantIdentityResolverTest.php` | Rewritten for composite matching; added "name variant = different person" |
| `tests/Feature/Participants/ParticipantIdentityConstraintsTest.php` | Two UNIQUE-constraint tests rewritten — duplicates now allowed and verified to coexist |

Listed in the checklist but **not confirmed** in the W7 close:

- `tests/Feature/Public/PublicRegistrationIdentityResolutionTest.php`
  (item M)
- `tests/Feature/Public/RegistrationSubmissionTest.php` — 18 tests
  (item N)
- `tests/Feature/Events/EventDuplicateNameTest.php` (item O)
- `tests/Integration/RegistrationVerificationConcurrencyTest.php`
  (item P — unique-violation retry backstop under composite key)

Verify these four are green against the composite rule before treating
the suite as authoritative for the identity rule.

## 11. Docs amendment (checklist item L)

`docs/participant-identity.md` §3 records:

> Email + name. Current behavior. Handles shared emails by
> disambiguating on name. But name typos split identity... Rejected
> because names are not identifiers.

That decision was reversed on 2026-10-19. The amendment lands in
`participant-identity.md` as an append-only correction block titled
`## Corrections — Composite identity (2026-10-19)`.

## 12. Cross-references

| Document | Purpose |
|---|---|
| `docs/participant-identity.md` | Original spec; §3 reversed by this file |
| `docs/registration-wizard.md` | Public flow that consumes the composite resolver |
| `docs/audit-2026-10-19.md` | Findings L1, M4, M5 touch this subsystem |
| `docs/known-issues.md` | ISSUE-014+ derived from the audit |
| `docs/registration-contact-verification.md` | Verification flow that precedes finalize |

## 13. Changelog

- **2026-10-19** — File created. Documents the shipped composite identity
  rule, the case-insensitivity decision, the known limitations
  (middle-name/suffix gap, cross-contact conflict, name-variant splits),
  and the open items (name warning UI, checklist I/J/K verification,
  client confirmation on Option A).


## Running Events audit addendum — 2026-10-21

The optional middle-name and suffix expansion is implemented and backfilled. Matching remains case-insensitive. Family members may share contacts across public, staff, and monitor-edit paths. Similarity is advisory, separate from the exact composite duplicate guard; no automatic provisional linking is performed.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.
