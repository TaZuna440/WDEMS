# Running events progress — 2026-10-20

Local implementation audit against the supplied Phase A–F checklist.
Changes below are in the working directory; they are not committed or pushed.

## Phase A

- The four `Event` running-kind guards already exist in the checked-out code.
  `canRecordAttendanceNow()` inherits the guard from `canRecordAttendance()`.
- Fixed null event dates in deferred bulk and single walk-in entry. An admin
  force-open uses `now()` when there is no event date.
- Preserved the existing P3 `verified_at` fix, church status enum, and UI guard.
- Repaired P3 regression tests: freeze time inside the attendance window and
  submit the endpoint's `status` field instead of `attendance_status`.
- Focused lifecycle, force-open, encoding, reverification, and church-status
  checks: 57 passing tests.

## Phase B

- Added the participant workflow explanation panel to the form builder.
- Added field-type examples as hover titles in the type picker, retaining
  the visible example beneath the selected type.
- Added an authenticated saved-form preview using `registrations/public`
  with `preview=true`. Navigation between the first three steps is unrestricted;
  inputs are disabled, sample data is separate from participant drafts, and
  the preview never mounts the persistent submission wizard.
- Added preview links and Create/Edit Registration Form label transitions.
- Church events cannot open the running form builder or preview, and no longer
  see disabled running-form/open-registration actions in the event sidebar.
- Save validation and redirect to event details already existed.
- Builder/preview regression checks: 52 passing tests.
- Still pending: rich-text disclaimer, personal/emergency configuration changes,
  dedicated T-shirt builder and gallery, and email-only verification.

## Other checklist corrections

- Opening registration already redirects to the registration monitor.
- Live/Deferred attendance encoding already exists; its placement in the new
  Check-in/Walk-in interface remains pending.
- The append-only composite identity amendment already exists in
  `participant-identity.md` under “Corrections — Composite identity (2026-10-19)”.
- Public wizard step validators already exist. This does not establish that all
  new Phase F field requirements or similarity defenses are complete.

## Shared-contact lookup repair

The three remaining email/phone-first lookups now delegate to
`Participant::findByComposite()`:

- Bulk walk-in duplicate checks.
- Single walk-in request duplicate checks.
- Service attendance participant resolution (explicit participant IDs retain
  precedence, and contactless service participants can still be created).

This aligns those callers with the existing first-name + last-name + contact
rule. It does not change case normalization or include middle name/suffix.
Two older duplicate tests submitted a different person's name with the same
email; their fixtures now submit the matching name to test actual duplicates.
New regression cases cover family members sharing email/phone and prevent a
second registration for the matching person. Focused checks: 58 passing tests.

## Decisions still needed

- Whether email-only requirements apply to all existing published running forms
  or only to newly saved draft forms. Honor existing verification challenges.
- Whether existing Late/Excused attendance stays historical or is converted to
  Present/Absent when the new entry interface ships.
- Identity policy sign-off: retain the current case-insensitive composite rule,
  or change case/middle-name/suffix matching. Client confirmation of Option A
  concerns the composite identity rule (see `composite-identity.md` §9.4).
- reCAPTCHA v2 site/secret configuration remains outstanding.

Phases C–F are not complete. Church module specifications remain deferred until
running-event work is finished. Existing untracked files and user changes have
been preserved; no commit/stash decision was made.

## Isolation follow-up — same session

Further review found that model guards alone did not cover admin force-open
or automatic status advancement. Added explicit running-kind checks to all
four attendance write actions, the workflow service, automatic advancement,
public registration/verification middleware, and monitor read/write entry
points. Attendance and monitor event lists now filter by running kind.
Regression tests cover admin bypass attempts, church public/monitor access,
and unchanged church statuses. The expanded lifecycle/public verification
selection passes all 91 tests.

## Accepted decisions and implementation follow-up — 2026-10-20

The earlier decisions-needed section is superseded by Mark's answers:

- Option A is approved. Include middle name/suffix, keep case-insensitive matching.
- Newly saved draft forms use version 2, with required email and email-only
  verification. Published version 1 forms retain their original requirements.
- A migration converts running Late to Present and Excused to Absent; church
  attendance is excluded. The lossy status conversion cannot be reversed safely.

Implemented the full-name migration and lookup expansion, versioned forms,
rich-text disclaimer sanitization/editor, structured emergency contact fields,
T-shirt choices/reordering/photos/lightbox, and the running attendance conversion.
Version 2 email issuance requires server-validated reCAPTCHA; failed OTP attempts
are counted across replacement challenges for the same normalized email, with a
two-hour lock after five failures. Real reCAPTCHA site/secret configuration is
still required in the local environment. No credentials are committed.

These changes are still under regression validation. This entry does not mark
Phases C–F complete or claim that local database migrations have been applied.

## Registration control and check-in follow-up

All six migrations have been applied to the local database, and the public
storage link is available for T-shirt photos. Laravel detects the reCAPTCHA
site and secret keys supplied by Mark; live browser verification is still
pending and no key values were displayed.

The confirmation screen now renders a Code 128 barcode containing only the
registration lookup code. Close/Reopen require the current account password,
retain the public link, and lock the event row while transitioning. Existing
verification challenges retain their original 24-hour expiry after closure
(including automatic advancement to ongoing); new submissions remain closed.
Cancelled/completed events do not accept pending completion.

The monitor now polls closed events too, since pending registrations may still
finish, and offers search across its explicitly labeled latest-100 feed.
Code check-in shows full participant name, T-shirt choice, and custom answers;
success clears and refocuses the scanner input. The focused toggle, verification,
and attendance selection passed 68 tests before this latest monitor refinement.

Quick walk-in's name-only identity policy is awaiting clarification: the existing
composite rule requires contact, whereas the requested quick mode does not.
The Check-in/Walk-in tab consolidation, paper-entry parity, camera scanning,
report redesign, similarity warning, and duplicate audit remain unfinished.

## Attendance and paper-entry decisions — follow-up

The full suite passed 913 tests with 3 skipped before the latest provisional
and paper-entry changes. A separate 20-test selection passed provisional
identity, shared-contact lookup, registration-toggle, and finalize checks.

Mark approved event-local provisional Quick walk-ins and staff-attested paper
registration without OTP. Implemented separate endpoints and inputs, encoder
attribution for paper forms, and full public-contract validation. Paper forms
are stored with source `form`; Quick walk-ins are flagged, source `walk_in`,
and excluded from automatic composite matching. Live/Deferred timing remains
available for paper entry. Removed Confirm/Batch from the visible tab bar and
removed the obsolete bulk-mark UI; legacy endpoints remain for compatibility.

Added native Code 128 camera detection with supported-format checks, camera
cleanup, and manual/scanner fallback on unsupported browsers. It has not been
verified with physical camera hardware. Finalize now displays total registered,
checked-in, and walk-in counts. Browser automation timed out during local preview,
so live CAPTCHA and visual end-to-end checks remain outstanding. No keys were
printed, and Wayfinder generation was disabled in the temporary preview config.

## Report and duplicate-defense follow-up — 2026-10-21

Applied both provisional-participant and staff-attestation migrations locally.
Paper entry creates immediately with encoder user ID and contact attestation time.

The report now removes the five superseded cards/sections, lists searchable
cross-events with status filters, and supplies participant search, attendance and
source filters, Age/Birthdate, CSV export, and detailed personal/emergency/T-shirt/
custom-answer data. Paper source is distinguished from online using the recorded
attestation, including legacy `paper` rows. Report routes and cross-event history
queries explicitly require running kind. CSV cells with formula prefixes are
escaped. Missing attendance is displayed as Absent; ongoing reports explain that
figures can still change.

Added staff-only event-scoped encoder name lookup and a Possible Duplicates audit
with match reasons. The advisory comparison uses case-insensitive full names,
Unicode edit distance (one edit or 15%, whichever is larger), and at least one
shared birthdate/email/normalized phone. It does not auto-merge people. Public
warnings reveal no names or match details and require acknowledgement before
completion. Placement is currently after contact verification; Mark's answer is
pending on the specification's earlier Step 2 placement.

The full suite passed 923 tests with 3 skipped (926 total) before the final
Fellowship/provisional-picker follow-up. That follow-up replaces another
phone-only match with full composite identity and excludes provisional records
from church pickers. Its focused validation is tracked in the next entry.

The church expansion specification is recorded in
`church-events/11-shared-workflow-spec-2026-10-21.md`. It is a specification, not
implemented church functionality. Existing documentation is preserved.

Remaining verification: authenticated browser visual checks, live reCAPTCHA,
and physical barcode/camera checks. Camera support currently depends on native
Code 128 detection; unsupported browsers retain manual/handheld input. No commit,
stash, or push has been performed. Existing user changes remain in the checkout.

### Final boundary validation

The Fellowship, Services, Discipleship, and report-service selection passed all
199 tests (805 assertions), including shared-phone family members and rejection
of a provisional participant selected through a church picker. TypeScript checks
passed for the new report, audit, encoder lookup, and public-warning UI. Removed
the temporary preview Vite configuration. The user-created `attendance?-` and
`verified_at` files and pre-existing changes were not removed or stashed.
