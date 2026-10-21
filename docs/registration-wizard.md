# WDEMS — Registration Wizard

**Status:** Live. W1–W6 shipped 2026-10-19. W5.4 (server-side
reCAPTCHA) blocked on Google v2 keys. W7 (composite identity) shipped
separately — see `docs/composite-identity.md`.

**Audience:** Whoever touches `PublicRegistrationController`,
`RegistrationVerificationService`, `resources/js/pages/registrations/*`,
or the public challenge flow.

**Purpose:** Document the redesigned 5-step public registration flow —
what each step collects, how it is gated, and where each phase's code
lives.

**Related:**
- `docs/composite-identity.md` — the identity rule the wizard consumes.
- `docs/registration.md` — the original public-flow spec (pre-wizard).
- `docs/registration-contact-verification.md` — the verification
  subsystem, still authoritative for OTP mechanics.
- `docs/audit-2026-10-19.md` — findings M1, M5, L1 touch this flow.
- `docs/known-issues.md` — open items derived from the audit.

---

## 1. Overview

A 5-step wizard replaces the previous single-page public form.

| Step | Name | Gate to advance | Shipped |
|---|---|---|---|
| 1 | Disclaimer & Consent | Both checkboxes checked | W2 |
| 2 | Personal Information | All required fields valid | W3a |
| 3 | Emergency Contact | All fields valid | W4 |
| 4 | Verification | Chosen channel verified | W5.1, W5.2, W5.3 |
| 5 | Confirmation | Acknowledge code | pre-existing |

Progress UI mirrors the event-creation wizard's `wizard-progress`
pattern. The participant cannot advance while the current step is
invalid or incomplete. Form inputs persist across refresh via
`localStorage` (§8); verification state does not — that stays on the
server.

**W6 scope correction.** W6 shipped the shell as a **3-step** wizard on
`public.tsx` (Consent → Personal Info → Emergency Contact), with
WizardProgress on the verify and confirmed pages. The spec's 5-step
model is the long-term target; the currently shipped flow folds
verification and confirmation into the post-submit pages because the
challenge state is server-side. Functionally equivalent to the 5-step
model from the participant's perspective — the "steps" that are not
part of the wizard shell are the pages the flow already lands on after
submit.

## 2. Step 1 — Disclaimer & Consent (W2, shipped)

Two required checkboxes. Both must be checked to advance.

### 2.1 Disclaimer text

Fixed template with `{event_name}` injected at render. Seven paragraphs
covering: voluntary participation and physical risk; rain-or-shine
policy; participant self-responsibility for medical conditions; water
and hydration; drop-bag limits and no responsibility for lost items;
liability exclusion; overall agreement.

Required checkbox: **"I agree and understand the disclaimer."**

### 2.2 Privacy consent text

Fixed template with `{event_name}` injected:

> By submitting this form, you agree that {event_name} may collect and
> use the information you provide for event registration,
> communication, and coordination related to the Community Run. Your
> information will be kept private and handled responsibly.

Required checkbox: **"I agree to the collection and use of my
information for the purposes stated above."**

### 2.3 Consent recording

On finalize, the server writes:

    registrations.consent_accepted_at = now()
    registrations.privacy_notice_version = <build constant>

This closes audit finding **P5** (consent columns existed on the
schema but nothing wrote them). Verified by W2.

### 2.4 Deferred

Per-event editable disclaimer text. v1 uses a single fixed template
for all events. Deferred to a future session; the schema is in place
(`events.privacy_notice` and `events.terms` were never added — if the
client wants per-event text, a new migration is needed).

## 3. Step 2 — Personal Information (W3a, shipped)

| Field | Required | Notes |
|---|---|---|
| Last name | Yes | |
| First name | Yes | |
| Middle name | No | Added W1. Currently **not** in the composite identity key (§6, `composite-identity.md` §7.2) |
| Suffix (Jr., Sr., II, III…) | No | Added W1. Currently **not** in the composite identity key |
| Barangay & City | Yes | Replaces the free-text address field on the public form |
| Contact # | Per event config | PH-only phone input |
| Email | Per event config | |
| Date of birth | Yes | DatePicker, bounded to ages 3–120 |

A **"\* indicates required question"** legend renders above the
YOUR DETAILS section.

### 3.1 Barangay & City replaces free-text address

The public form collects "Barangay & City" into the existing
`participants.address` column. Placeholder: `ex. Gen T. De Leon,
Valenzuela City`.

Walk-in and staff paths (single and bulk) continue to collect
free-form address into the same column. The two paths differ in what
the participant types, not in what is stored.

### 3.2 PH phone input

Accepts `09XXXXXXXXX` and `+639XXXXXXXXX`. Real-time validation as
the participant types. Normalizes on submit to the canonical
`09XXXXXXXXX` form via `Participant::normalizeContactNumber()`
(mirror on the client side).

Non-PH numbers are rejected. This is a scope decision: WDEMS serves
PH community runs and church events; international numbers are not
in the first-release scope.

### 3.3 DatePicker year-first drill-down

Month-by-month navigation from the current date is unusable for a
birthdate field (decades of clicks). The calendar header
("July 2023") is a drill-down control:

- Click the header → year grid, bounded to the selectable 3–120
  range, scrollable.
- Pick a year → month grid → pick a month → day grid.
- Typed entry (MM/DD/YYYY) remains the primary path; the calendar is
  the secondary path.
- When the field is empty and the picker opens, the calendar starts
  at a sensible adult default (~25 years ago) rather than the maximum
  bound, so mouse users are one year-grid jump from any decade
  instead of starting at the edge.

Bounds 3–120 shipped with the birthdate swap (see `docs/progress.md`
2026-10-19 entry).

### 3.4 At-least-one identity rule

Unchanged. The form builder still rejects a save where both
`email` and `contact_number` toggles are off. The public form
guarantees at least one identity channel exists.

### 3.5 Dropped from the public form

The free-text address field. Replaced by Barangay & City (§3.1).
`participants.address` is unchanged on the schema and still collected
on walk-in and staff paths.

## 4. Step 3 — Emergency Contact (W4, shipped)

Required. Three fields:

| Field | Required | Notes |
|---|---|---|
| Last, First name | Yes | Free text |
| Relationship | Yes | Free text (Parent, Spouse, Sibling…) |
| Contact # | Yes | PH mobile quality rules apply |

Stored on **`registrations`**, not on the participant. Rationale: a
participant may list different emergency contacts for different
events (a spouse on one, a parent on another).

New columns (W1 migration):

    registrations.emergency_contact_name
    registrations.emergency_contact_relationship
    registrations.emergency_contact_number

Nullable at the DB level; required at finalize. The nullable DB column
permits the challenge payload to be persisted incrementally during
the pre-finalize flow.

### 4.1 Same-number warning

If the emergency contact number matches the participant's own contact
number (after normalization), a soft warning renders under the field.
**Does not block submission.** The reasoning: a parent registering a
child may legitimately list the parent's number as both the child's
contact and the emergency contact.

## 5. Step 4 — Verification (W5.1-W5.3 shipped, W5.4 blocked)

### 5.1 Channel choice — the stricter variant

The participant may only verify through channels the organizer marked
**required**:

- **Only phone required** → no choice screen; verify phone directly.
- **Only email required** → no choice screen; verify email directly.
- **Both required** → participant picks **one** channel to verify.

A required-but-unverified contact is collected and stored as a
snapshot only (existing semantics for optional contacts). The
at-least-one form-builder rule guarantees at least one channel is
always required, so there is always something to verify.

The verified channel becomes the identity contact for the composite
duplicate rule (see `docs/composite-identity.md`).

### 5.2 Chosen-channel persistence

`registration_verification_challenges.chosen_channel` — new column,
W5.1. Records which channel the participant elected to verify when
both are required. Null when only one channel was required (the
single required channel is implied by the challenge's required flag
snapshot).

### 5.3 Device-scoped email trust (W5.3 shipped)

To avoid re-OTP-ing a family registering together from one browser:

- **On email OTP success**, the server records
  `(email, device-token-hash, verified_at, expires_at)` in
  `email_device_verifications` (new table, W1) and sets an HttpOnly +
  Secure + SameSite=Lax cookie holding the token.
- **On begin()**, a valid trust record for `(device, email)` skips
  OTP issuance.
- **TTL: 7 days** for participants. Organizer/staff device trust
  remains at 30 days — different population, authenticated users.
- **Scope: per email, app-wide** (`/` cookie). Verifying
  `family@email.com` does not skip verification for any other address.
- **"Same device" = same browser.** Different browser or cleared
  cookies → re-verify. Fails toward more verification, never less.
- **Expiry/pruning:** `MassPrunable` on expiry, riding the existing
  prune schedule.
- **Security note:** the OTP gate remains the real security boundary.
  Device trust is convenience. The trust window's residual risk (brief
  access to an unlocked browser) is accepted and bounded by the 7-day
  expiry.

### 5.4 Resend cooldown — 100 seconds (W5.1 shipped)

`RESEND_COOLDOWN_SECONDS` bumped 60 → 100 in both
`RegistrationVerificationService.php` and the TypeScript mirror in the
verification page.

This is a duplicated-constant trap (per `docs/event-deletion.md` §9
trap #3 pattern). Both constants must be changed together. Search both
files for `100` and `RESEND_COOLDOWN_SECONDS` when touching this.

**UI:** after clicking "Send code," the button shows a countdown
("Resend in 87s"). When the countdown reaches zero, the button
re-enables.

### 5.5 P2 fix — non-revealing begin-time message (W5.1 shipped)

`assertNoDuplicateRegistration()` in `begin()` no longer emits the
revealing message ("This email address or contact number is already
registered for this event."). It emits the same non-revealing message
`finalize()` uses:

> This registration cannot be started. If you believe this is an
> error, contact the event organizer.

Closes audit finding **M5** (anonymous registration-state enumeration
oracle).

### 5.6 reCAPTCHA — server-side enforcement (W5.4, blocked)

Currently the reCAPTCHA is a visual checkpoint for email-only events;
the token is not consumed server-side. The new behavior:

- The reCAPTCHA token is **verified server-side** against Google's
  `siteverify` endpoint before any code is issued, for **both**
  channels.
- Email flow: solve reCAPTCHA → click Send code → 6-digit OTP via
  Resend.
- Phone flow: reCAPTCHA → Firebase SMS → ID token (existing flow,
  unchanged).

**Blocked:** waiting on Mark's Google v2 site + secret keys. Once
provided:

- New env vars: `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET`.
- New service: verify against
  `https://www.google.com/recaptcha/api/siteverify` before
  `sendEmail()` and before the Firebase phone challenge.
- New test file covering: valid token accepted, invalid token rejected,
  missing token rejected, expired token rejected.
- Client: fetch a fresh token before each send; a failed send must
  allow a fresh token fetch (the epoch-counter pattern from the
  existing Firebase verifier).

### 5.7 Unchanged behaviors

- Challenge TTL: 24 hours.
- 5-attempt lockout per channel.
- Single-use codes.
- HMAC storage (never plaintext, never logged).
- Row-locked attempt increments.
- Route throttles.
- No PII in logs.
- Non-revealing finalize messages.

## 6. Identity rule interaction

The wizard **consumes** the composite identity rule documented in
`docs/composite-identity.md`. It does not implement it. Key points:

- A submission's `(normalized name + verified contact)` becomes the
  participant key at `finalize()`.
- Per-event duplicate check fires at `begin()` and again as a
  backstop at `finalize()`. Both use the composite key.
- The verified channel (email or phone) is the contact half of the
  key.
- Middle name and suffix are collected (§3) but **not yet part of the
  key**. See `composite-identity.md` §7.2.

## 7. Step 5 — Confirmation (unchanged)

Pre-existing flow. Registration Code issued at finalize, shown on the
confirmation page, must be acknowledged via `code_acknowledged_at`,
confirmation email sent via `DB::afterCommit()`. The wizard's
`localStorage` draft is cleared on acknowledge.

No changes from the pre-wizard state.

## 8. Wizard mechanics (W6, shipped with 3-step shell)

### 8.1 Progress UI

`WizardProgress` component (shared with the event-creation wizard).
Clickable back-navigation to completed steps only; forward navigation
blocked until the current step validates client-side.

Shipped as 3 steps on `public.tsx` (Consent → Personal Info →
Emergency Contact). WizardProgress also renders on `verify.tsx` and
`confirmed.tsx`, so the participant sees their progress across the
full post-submit flow, not just the wizard shell.

### 8.2 Persistence

`localStorage` key: `wdems-registration-{event_slug}`. Holds per-step
input only. Restored on page load and refresh. Cleared on successful
acknowledge.

Verification state is **not** in `localStorage`. The challenge cookie
and server-side challenge row are the sole source of truth for
verification.

### 8.3 Gating

Each step validates client-side before Next enables. The server
re-validates everything at `begin()` (`validatePending`) and
`finalize()`. Client validation is UX; server validation is the gate.

Step 1→2 transition: checking both boxes enables Next. Unchecking
re-locks.

## 9. Data model changes

| Table | Change | Phase |
|---|---|---|
| `participants` | `+ middle_name` (nullable), `+ suffix` (nullable varchar 16) | W1 |
| `participants` | `DROP UNIQUE` on `email` and `contact_number_normalized` | W7 |
| `participants` | `+ first_name_normalized`, `+ last_name_normalized` (nullable) | W7 |
| `registrations` | `+ emergency_contact_name`, `+ emergency_contact_relationship`, `+ emergency_contact_number` (nullable at DB, required at finalize) | W1 |
| `registrations` | `consent_accepted_at` and `privacy_notice_version` written at finalize (columns pre-existed) | W2 |
| `registration_verification_challenges` | `+ chosen_channel` (nullable) | W5.1 |
| `email_device_verifications` | New table: `email`, `device-token SHA-256 hash`, `verified_at`, `expires_at`; `MassPrunable` on expiry | W1, used by W5.3 |

Full schema details in `docs/composite-identity.md` §3 (identity
columns) and the migration files themselves.

## 10. Build phase status

| Phase | Scope | Status | Notes |
|---|---|---|---|
| W1 | Schema migrations (additive) | Shipped | `middle_name`, `suffix`, emergency contact columns, device trust table |
| W2 | Step 1 disclaimer & consent | Shipped | Closes P5 |
| W3a | Step 2 personal info | Shipped | Middle name, suffix, PH phone, DatePicker drill-down |
| W4 | Step 3 emergency contact | Shipped | Same-number soft warning |
| W5.1 | Chosen channel + 100s cooldown + P2 fix | Shipped | Closes M5 |
| W5.2 | Channel choice backend + UI | Shipped | Email OR phone verification |
| W5.3 | Device trust (7-day cookie) | Shipped | OTP skip on trusted devices |
| W5.4 | reCAPTCHA server-side | **Blocked** | Awaiting Google v2 keys |
| W6 | Wizard shell (3-step) | Shipped | Progress on verify + confirmed pages |
| W7 | Composite identity | Shipped | See `docs/composite-identity.md` |

**Test suite at W7 close:** 833 passed, 3 skipped (pre-existing
Fortify 2FA env skips), 0 failed. `npx tsc --noEmit` silent.

## 11. Case coverage matrix (from the wizard spec)

Every case from the Ana/mother/Joy scenario is answered by a phase or
documented as a known limitation.

| # | Case | Answered by | How |
|---|---|---|---|
| A1 | Ana registers, verifies | W5 | OTP → row + trust record |
| M2 | Mother, same device+email, different name | W5 + W7 | Trust skips OTP; composite allows |
| J3 | Joy, different device, Ana's exact details | W5 + W7 | OTP required; duplicate blocked |
| J4 | Joy, different device, different name, same email | W5 | Inbox OTP is the gate |
| M5 | Mother on her own phone | W5 | No trust → OTP (she has inbox) |
| E1 | Name variant ("Maria D." vs "Maria") | W7 | Splits — documented limitation |
| E2 | Same name, no suffix, shared email | W7 | Blocked — documented; suffix mitigates once §12.1 is closed |
| E3 | Jr./Sr. with suffix | W7 | **Does not work yet** — suffix not in key (§12.1) |
| E4 | Same key, new event | W7 | Same row — returning history |
| E5 | Same key, same event | W7 | UNIQUE blocks — duplicate |
| E6 | Organizer edits name | W7 | Detaches future matches — documented |
| E7 | Trust expires / cookies cleared | W5 | Re-verify — safe fallback |
| E8 | Channel choice (1 vs 2 required) | W5 | Skip choice vs pick |
| E9 | Emergency = own number | W4 | Warn |
| E10 | reCAPTCHA / 100s cooldown / P5 / P2 | W5 / W2 | E10 splits: cooldown + P5 + P2 shipped; reCAPTCHA blocked |

## 12. Known limitations

### 12.1 Suffix mitigation does not work yet

Wizard spec §6 says the composite rule "extends to the new fields"
(middle name + suffix). W7's `normalizeName()` covers first + last
only. The Jr./Sr. mitigation for "two Juans in the same family
sharing an email" is therefore not yet effective — they will be
blocked as duplicates on the same event unless their first or last
names differ.

See `docs/composite-identity.md` §7.2 for the full statement of the
gap.

### 12.2 Name variants split identity

"Maria Santos" vs "Maria D. Santos" sharing one email → two rows. See
`composite-identity.md` §7.1.

### 12.3 Cross-event impersonation is bounded by inbox ownership

Composite cannot stop Joy on Event F if she holds the inbox. Email
ownership — the OTP — is the boundary. See `composite-identity.md`
§7.5.

### 12.4 Per-event editable disclaimer deferred

v1 uses a fixed template. See §2.4.

### 12.5 Address split by path

Public form collects Barangay & City into `participants.address`.
Walk-in and staff paths collect free-form address into the same
column. Same column, two different input shapes — participants may
appear to have inconsistent address formats across paths.

Documented. Accepted cost of preserving staff ergonomics while
tightening the public field.

## 13. Open items

| Item | Owner | Status |
|---|---|---|
| W5.4 reCAPTCHA keys | Mark | Blocked — Google v2 site + secret |
| Middle name + suffix in composite key | Dev | Not started (`composite-identity.md` §9.2) |
| Name warning UI | Dev | Not started (`composite-identity.md` §9.1) |
| Case sensitivity note fix | Dev | Doc-only (`composite-identity.md` §8) |
| Client confirmation on Option A | Client | Pending |
| Doc amendment on `participant-identity.md` §3 | Dev | This merge covers it (Block 4) |

## 14. Cross-references

| Document | Purpose |
|---|---|
| `docs/composite-identity.md` | The identity rule the wizard consumes |
| `docs/registration.md` | Original public-flow spec (pre-wizard) |
| `docs/registration-contact-verification.md` | Verification subsystem; OTP mechanics |
| `docs/registration-development-plan.md` | Original phased plan (pre-wizard) |
| `docs/registration-monitoring.md` | Post-submit organizer view |
| `docs/audit-2026-10-19.md` | Findings M1, M5, L1 touch this flow |
| `docs/known-issues.md` | Open items ISSUE-014+ |

## 15. Changelog

- **2026-10-19** — File created. Consolidates the wizard spec, the
  W1–W6 progress report, and the case coverage matrix. Records the
  3-step-vs-5-step reconciliation (W6 shell vs spec target). Documents
  known limitations (suffix gap, name variant splits, per-event
  disclaimer deferral, address split). Lists blocked W5.4 and open
  items.


## Running Events audit addendum — 2026-10-21

W5.4 reCAPTCHA is implemented and keys are configured locally; live completion remains an acceptance check. Editable sanitized disclaimers, structured emergency contacts, T-shirt choice/photos, and preview are implemented. Middle name/suffix participate in identity. Step 2 now checks for similar registrations and permits explicit continuation. Confirmation includes a Code 128 barcode.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.

## Participant form sequence update — 2026-10-21

Following Mark's participant walkthrough: the public birthdate control is now one
numeric MM/DD/YYYY input (month, then day, then year), with calendar validation;
existing age limits remain enforced. Partial typed dates are saved with the draft.
Personal and emergency mobile inputs have a fixed +63 prefix and normalize pasted
09 numbers. Version 2 server validation also enforces Philippine mobile numbers.
Version 2 emergency contacts retain separate first/last names and now offer Parent,
Guardian, or Other radio choices; Other requires 2–15 letters/spaces/apostrophes/
hyphens including at least one letter. Stored legacy relationship choices remain
accepted for existing records and pending challenges.

Email-only verification displays reCAPTCHA, then Send code, then the OTP input
after delivery. Reloading that page restores the server-side challenge state;
reCAPTCHA itself must be completed again when a fresh token is required. Legacy
published verification contracts remain supported. Disclaimer and confirmation
code/barcode content are unchanged. Confirm acknowledges the code, expires only
the event's verification cookie, clears the event's local draft, and returns to
the form. It does not sign out staff or clear other events' drafts/device trust.
The wizard retains its autosaved draft during submission failures and verification;
it is cleared by the explicit final Confirm action.

Participant update validation: full PHP suite **937 tests: 934 passed, 3 skipped,
3,837 assertions**; TypeScript passed; the frontend input-formatting test file
passed via `node --test tests/frontend/participant-input.test.mjs`; whitespace
check passed. The report-controller open-event fixture now uses a future date so
automatic event start cannot make it depend on the time of day. These changes
remain local and uncommitted. Live reCAPTCHA/browser acceptance is not claimed by
these automated checks.
