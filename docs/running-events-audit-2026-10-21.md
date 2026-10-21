# Running Events — implementation audit and current contract

Date: 2026-10-21 (Asia/Manila).
Baseline: local commit `39e554f`; this audit records subsequent local corrections.
Nothing in this review has been pushed. This is the current Running Events
reference; older specifications and status entries remain as historical records.

## Source and precedence

Compared Mark's supplied “Running Events — System Update” (sections 1–15) with
models, lifecycle services/middleware, public and staff controllers/requests,
React pages/components, migrations, and regression tests. Later explicit answers
supersede conflicting lines in the pasted snapshot:

- Full identity is case-insensitive first + middle + last + suffix plus verified
  contact. Missing optional name parts are empty components, never wildcards.
- Option A is approved; there is no outstanding client implementation sign-off.
- Newly saved draft forms use email-only version 2. Published version 1 forms and
  pending challenges preserve their original rules. Do not delete legacy channel
  support while those forms remain in use.
- Running Late → Present and Excused → Absent migration excludes church records.
- Quick walk-ins are event-local provisional participants. Linking requires staff
  review; no automatic cross-event merge is allowed.
- Staff-attested paper entry creates immediately without OTP and records encoder.
- On 2026-10-21 Mark confirmed kit claiming is a staff procedure outside WDEMS.
  There is no digital kit inventory/claim/ID-document workflow to implement.
- On 2026-10-21 Mark confirmed the similarity warning belongs on Step 2. This
  supersedes the earlier after-verification placement and pending-question notes.

## Specification traceability

| Spec | Implemented behavior and code evidence |
| --- | --- |
| 1 Identity | `Participant::matchingName/findByComposite/resolveFrom`, full-name normalization migration, public duplicate guard. Name variants remain separate identities. Monitor edits reject matching full-name/contact collisions but allow family contacts. |
| 2 Wizard | `registrations/public.tsx`, `wizard.tsx`, verification/confirmation page: consent → personal → emergency → verification → confirmation. Published v1 rules remain supported. |
| 3 P3/P4 | `AttendanceController`: preserve presence verification timestamp; clear for absence; deferred dateless writes fall back to now. Regression tests cover both. |
| 4 Kind gate | `Event` model guards and `EventWorkflow`, public/verification middleware, monitor, attendance, report controllers enforce running kind. |
| 5 Events list | Default is Running. Only Running/Church tabs; invalid or missing kind defaults to Running. Running table has global search and filters for name/type/date/distance/venue/status/creator. |
| 6 Form redesign | Tiptap H1/H2/bold/italic with server sanitizer; workflow panel; v2 required email/address; structured emergency contact; separate T-shirt choices/photos; type examples; save → event; preview uses public wizard with disabled/inert fields and separate working photo gallery. |
| 7 Barcode | JsBarcode CODE128 encodes only registration code on confirmation. No QR identity payload. |
| 8–9 Attendance | Check-in/Walk-in tabs; Quick and Paper modes; direct Present action; code success clears/refocuses; live total/recent feed; encoder lookup can mark an existing event registration present. Native Code128 camera detection feeds the same lookup. |
| 10 Finalize | Confirmation shows registered, checked-in, walk-in totals. Finalize routes through the lifecycle and redirects to report. Existing password-confirmation requirement is retained. |
| 11 Validation/defense | Client step validators and server intake validation; Step 2 advisory similarity check/acknowledgement; exact composite hard duplicate block; staff live search; event-scoped Possible Duplicates report. Kit checkpoint is manual. |
| 12 Reports | Removed verification/override/physical-total/breakdown sections; source totals; no-shows/show rate; searchable/filterable event list; participant age/birthdate/search/status/source filters; CSV and detailed form data view. |
| 13 Monitor | Open redirects to monitor. Password Close/Reopen preserves link. Pending challenges keep original 24-hour expiry. Monitor includes ongoing events and continues polling closed intake. |
| 14 Isolation | Running-only list/queue, public intake, builder, code check-in, report, duplicate review. Cross-event report history and returning-participant counts explicitly exclude church events. |
| 15 Church scope | Separate specification in `church-events/11-shared-workflow-spec-2026-10-21.md`; not church-module implementation. |

## Corrections discovered during this audit

1. Removed the mixed “All” event tab and added the missing running column filters.
2. Registration lifecycle and intake availability were coupled incorrectly.
   Open events now advance to Ongoing at their start moment without closing
   intake. A previously closed event remains closed when it advances.
3. Close/Reopen on Ongoing changes `registration_end` without rewinding status.
   Public GET/POST and verification intake share `acceptsPublicRegistration()`.
   Completed/Cancelled do not accept new or pending completions.
4. Added live event-wide check-in counts/recent feed, independent of search/code
   filters, refreshed every ten seconds. Full-name search includes all name parts.
5. Encoder matches now offer a direct Present action. Full names display optional
   middle/suffix rather than hiding identity-disambiguating parts.
6. Missing check-in rows display as Absent; Absent filter/count includes them.
   The legacy unmarked query remains compatible but is not a visible status chip.
7. Step 2 similarity check now returns only a boolean advisory, never matching
   names, contacts, registration IDs, or birthdates. The POST is throttled and
   uses the normal CSRF protection. Changes to identity require a fresh review.
8. Fixed remaining contact-only uniqueness checks in monitor editing; sharing
   contact with a different full name is allowed. Exact identity collision still
   requires staff review instead of silently merging records.
9. Paper entry records privacy-version/consent attestation along with encoder and
   contact attestation. The staff form explicitly describes this attestation;
   barcode `verified_at` remains null for paper attendance.
10. Form-save and first-open operations lock the event and recheck draft state,
    protecting the published-form contract when requests overlap.
11. Registration queue now excludes church events and includes ongoing intake.

## Lifecycle and timing

`Draft → RegistrationOpen → Ongoing → Completed` is supported alongside
`RegistrationOpen → RegistrationClosed → Ongoing`. Before start, reopening a
closed event returns it to RegistrationOpen; during Ongoing, toggling intake
leaves status Ongoing. The public link remains unchanged.

`registration_end = null` means no intake deadline while RegistrationOpen or
Ongoing. A past/equal end time means closed. Automatic start of a closed legacy
event with no end time stamps a closure marker instead of unintentionally
reopening intake. Scheduled end timestamps continue to be honored.

Attendance marking opens one hour before start and stays available afterward.
The view also permits the previous evening for an event starting just after
midnight. No scheduled attendance close was added. Finalization normally makes
attendance read-only. **Existing deliberate exception:** administrators can use
`force_open` for attendance corrections, including completed events. Staff cannot;
church kind guards cannot be bypassed. This is existing tested behavior, not a
new authorization model introduced by the redesign.

## Form and identity details

T-shirt field is separate from the five custom fields. Small/Medium/Large remain;
up to three custom options can be reordered. Up to three JPG/JPEG/PNG images may
total 25 MB. Remove attached photos before disabling the field. Public preview
shows sample data and allows step navigation without submissions or verification.

Public v2 OTP issuance requires server-verified reCAPTCHA hostname/token, with
100-second resend cooldown and five incorrect attempts shared by normalized email
across resends/replacement challenges. Lockout lasts two hours. Seven-day trusted
email-device behavior remains. Published v1 phone/channel paths remain for
compatibility; this is intentional, not incomplete v2 migration.

The similarity algorithm compares normalized full names using Unicode edit
distance (one edit or 15% of length, whichever is larger), plus a shared birthday,
email, or normalized phone. It is advisory. Family contacts are not proof of
identity, and a matching suggestion never authorizes a merge. An exact duplicate
is still rejected even when the advisory warning is acknowledged.

Quick walk-in creates a fresh provisional participant with `provisional_event_id`
and flags the registration for review. Provisional records are excluded from
composite matching and church participant pickers. There is no automatic linking
UI; staff must resolve identity through a deliberate review process.

Paper forms are source `form`, identified as Paper Form by their attestation.
Legacy `paper` rows are also reported as Paper Form; `walk_in` is Quick Walk-in.
Staff encoding uses the same event-specific validation as public intake. Deferred
time uses event date + entered submission time, or now if the date is absent.

## Database and deployment notes

Eight 2026-10-20 migrations add normalized optional names, form version, running
attendance conversion, disclaimer, structured emergency contact, T-shirt data,
provisional provenance, and paper attestation. They were applied locally. The
status conversion is lossy; its down migration does not invent original values.
The public storage link is required for T-shirt photos. reCAPTCHA keys are local
configuration, never committed or included in documentation.

No Fortify feature configuration, generated Wayfinder routes, or
`wdems_trusted_device` cookie name was changed. Never run configuration/route/view
cache or optimize commands in development. Run artisan commands serially.

## Verification and honest completion boundary

Code and regression coverage can establish behavior without proving hardware or
external service delivery. Live reCAPTCHA challenge completion, authenticated
visual walkthrough, physical keyboard-wedge scanning, and phone camera scanning
remain acceptance checks. Native camera detection varies by browser/platform;
unsupported environments retain typed/handheld input. Participant photo display
is conditional in the specification; this system does not collect participant
photos, so no photo upload or synthetic portrait was added.

See the appended validation entry below for final test/type-check results. Earlier
833/913/923-pass counts elsewhere are historical snapshots, not current totals.

## Final boundary corrections — 2026-10-21

The attendance list now includes a next-day event when its one-hour check-in
window starts before midnight. It appears in the active/today group rather than
being hidden until its calendar date. The running dashboard now scopes both its
actions and upcoming list to running events, preventing church records from
receiving running lifecycle labels or registration actions. Church category
navigation remains available through the Church events tab.

The registration queue is also running-only and retains ongoing events, grouped
by whether intake is open or closed. Regression tests cover these boundaries.

## Final validation — 2026-10-21

- Full suite after the final corrections: **935 tests; 932 passed, 3 skipped;
  3,815 assertions**. Exit status 0. Log: `storage/logs/codex-audit-final.log`.
- TypeScript: `npm run types:check` passed with exit status 0.
- Focused monitor/toggle/specification checks before the final dashboard boundary:
  34 passed, 265 assertions. The final full run includes the dashboard regression.
- `git diff --check` passed. All 25 modified existing Markdown files preserve
  their original content as a prefix; updates are append-only.
- Protected Fortify configuration and generated Wayfinder files are unchanged.
- Audit fixes and documentation remain uncommitted after local commit `39e554f`.
  Nothing was pushed. The pre-existing untracked `attendance?-` and `verified_at`
  files were left untouched.

These results verify the automated checks, not the live service and device
acceptance checks listed above. Church module implementation remains separate.

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
