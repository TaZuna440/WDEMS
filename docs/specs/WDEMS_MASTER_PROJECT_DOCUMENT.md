# WDEMS — Master Project Document

**Compiled:** 2026-10-21 · **Repo state:** github.com/TaZuna440/WDEMS, main @ **5755c75** ("Complete running event audit and participant registration flow", 2026-10-21 15:30 PHT)
**Purpose:** This is the single consolidated record of the WDEMS project. It combines every document produced so far (the Running Events update MD, the Church Events guideline MD with Amendments A–D, the companion church spec, the panel prep, and the code audits), applies every decision made by Mark through 2026-10-21, and states — based on the actual code at 5755c75 — what is implemented, what is partial, and what remains to build or update.
**Precedence:** Where this document conflicts with an older working document, this document and the Church MD (for church matters) prevail. Source documents are indexed in Part 9.

---

# Part 1 — Project Overview

## 1.1 What WDEMS is

WDEMS is a web-based event management system with **two sides in one system**:

- **Running Events** — community fun runs (e.g., the JRV Community Run): organizers create events, open online registration, verify participants, record attendance on event day, and produce reports.
- **Church Events** — a centralized church event management system. In Mark's official wording: _"WDEMS is also a centralized church event management system designed to help church administrators and ministry leaders organize activities across different church ministries. It allows users to create events, register participants, maintain attendance records, and generate attendance summaries through a consistent and organized workflow."_

The two sides share one codebase and one database but are **strictly kind-isolated**: their workflows, lifecycles, and participant pools are separate (see Decision D-03 and D-10).

## 1.2 Users

- **Organizers / staff** — church administrators, ministry leaders, and Community Run organizers (authenticated accounts).
- **Participants** — runners and church attendees who register through a public per-event link. **No participant account is required.**

## 1.3 The MVP (defined 2026-10-21)

The MVP is **one complete, usable event cycle**: an organizer can create an event, open registration, receive verified participants, record attendance, and produce the final summary or report — without paper forms or a separate spreadsheet.

**In the MVP:** staff login; event creation with kind isolation; public per-event registration link plus manual staff registration; participant + emergency-contact information; email-OTP verification protected by reCAPTCHA (no phone verification); composite participant identity with exact duplicates blocked and likely duplicates held for review; per-registration registration codes; attendance monitoring (registrations enter as Unmarked; mark Present/Absent); Registered/Present/Absent/Unmarked totals; participant list, event report, CSV export (running side).

**Out of the MVP:** payments, donations, and finance; facility booking, payroll, inventory; SMS and phone verification; automatic participant merging; advanced analytics; specialized ministry features (e.g., Youth Camp payments, Timothy Class progress tracking); participant accounts and per-person revocable links; a native mobile app; kit claiming (a staff procedure outside the app).

## 1.4 Technology

Laravel 13 (PHP) + React 19 / Inertia, TypeScript frontend, Pest test suite. The pushed schema export (`docs/wdems-schema.sql`) documents **30 tables**. Repository docs live under `docs/` in the repo; planning/spec documents live in the project workspace (Part 9).

---

# Part 2 — Architecture and Data Model

## 2.1 Database normalization

The schema is normalized to roughly **Third Normal Form**. Each fact is stored once, in its own table:

- `participants` — the person (name parts, email, phone, birthday), stored once per pool.
- `events` — the event (name, date, venue, kind, status).
- `registrations` — only the link (`event_id` + `participant_id`, unique pair) plus registration-specific data (source, emergency contact, T-shirt choice, consent stamps, attestation fields). No person or event details are copied in.
- `attendances` — hangs off `registration_id` (unique).
- `members` — church standing data only (membership status, cell group, ministry), referencing `participant_id`; the person's identity is not duplicated.
- Custom form fields are normalized: definitions in `registration_fields`, answers in `registration_field_responses`.
- Foreign keys enforce the relationships.

**Deliberate exceptions:** (a) derived matching columns on `participants` (`first_name_normalized`, `last_name_normalized`, `middle_name_normalized`, `suffix_normalized`, `contact_number_normalized`) — stored, indexed copies used by the identity matching, maintained by model mutators; (b) legacy `age` column kept alongside `birthday` — age is derived from birthday on read, the stored column is a fallback for old rows.

## 2.2 Participant identity model

- A person is identified by a **composite key**: normalized full name (first + middle + last + suffix; case-folded, trimmed, whitespace-collapsed; suffix periods stripped; empty parts normalized to empty string) **AND** (email OR normalized phone).
- Matching is **strict exact** on that composite. "Maria Santos" vs "Maria D. Santos" are different people; "Jr." vs "Sr." are different people.
- **Two participant pools (Decision D-10):** running participants and church participants are separate populations. The same real-world person may exist once in each pool — by design. Matching, duplicate detection, monitoring, and history never cross pools.
- Church standing data (membership) lives on the `members` record, not on the person. `participant_type` and `guardian` are **per-registration** data, not person data.

## 2.3 Kind isolation

Every running-workflow gate in `Event` checks `isRunningEvent()` (open/close/reopen registration, finalize, record attendance, form builder, barcode/attendance pages, report). Church events use their own module controllers and their own status lifecycle (`ChurchEventStatus`: Upcoming/Ongoing/Completed/Cancelled) and never enter the running lifecycle. The Events list shows only **Running** and **Church** tabs (no "All" tab).

## 2.4 Verification architecture

Public registration uses `RegistrationVerificationService`: a challenge is created at `begin()` (100-second resend cooldown, 24-hour challenge TTL), the composite duplicate guard runs **before any OTP is sent**, OTP attempts are capped (5, persistent across resends) with a **2-hour lockout** per email, a verified device is trusted for **7 days** (participants), and `finalize()` writes the registration with `consent_accepted_at` + `privacy_notice_version`. Server-side **reCAPTCHA v2** gates the email channel. Verification is **email-only** across the whole system (Decision D-04).

---

# Part 3 — Master Decision Log

Every product decision made by Mark, with its status. "Secured in" points to the document that carries the decision.

| #    | Decision                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | Date                                  | Secured in                                                                 |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------- | -------------------------------------------------------------------------- |
| D-01 | **Composite identity** (Option A): a person = normalized full name + (email OR phone). Global UNIQUE(email)/UNIQUE(phone) dropped. Duplicate names allowed; duplicate name+contact on the same event forbidden.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | 2026-10-19                            | Running MD §1; docs/participant-identity.md                                |
| D-02 | **Identity includes middle name + suffix** in the composite key; matching stays case-insensitive; strict exact match; NULL → empty string.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | 2026-10-19                            | Running MD §1/§11; Church MD B.4                                           |
| D-03 | **Kind isolation is the system's major structural rule**: running and church workflows are walled off (model guards + UI separation). Shared traits between the two sides were identified by Mark as "the major problem of our system right now."                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | 2026-10-19                            | Running MD §14                                                             |
| D-04 | **Verification is EMAIL-ONLY.** "There is no more phone verification" — no channel-choice screen, no SMS, no Firebase flow on new forms. Phone is still collected and still a composite matching key; it is never verified. reCAPTCHA v2 stays on the email channel.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | 2026-10-20                            | Church MD B.2                                                              |
| D-05 | **No past dates anywhere, no exception.** Every scheduling date: picker min = today, max = +2 years, AND server rule `after_or_equal:today`. The former Fellowship/occurrence retrospective-logging allowance is removed. Birthdate picker is out of scope (opposite bounds by purpose).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | 2026-10-20                            | Church MD B.3; spec Appendix C                                             |
| D-06 | **Church Registration shape**: ONE list per church event, fed by staff Add Participant (no OTP) + a per-event public link (same fields, email required, OTP-verified). EVERY completed church registration gets a registration code (code YES, scanning NO).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | 2026-10-20                            | Church MD Amendment A                                                      |
| D-07 | **Church Registration owns Add/Edit/Delete** (overrides guideline §6.1). Edit re-runs the per-event duplicate check (excluding itself); delete removes the attendance row and adjusts counts. Delete removes the registration, never the person. **Church Attendance is monitoring only** — no Check-in/Walk-in tabs, no camera scan, no Live/Deferred modes; statuses Present/Absent/Unmarked only.                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | 2026-10-20                            | Church MD Amendments A, A.1                                                |
| D-08 | **Church participant types: Member, First-Timer, Guest only** (Graduate and Ninong/Ninang dropped). Stored per registration, with guardian.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | 2026-10-20                            | Church MD Amendment A.2                                                    |
| D-09 | **Duplicate handling — three outcomes** (both sides, inside each pool): (1) exact duplicate in the same event → **BLOCK**, first record kept, non-revealing message, check runs before OTP; (2) **likely duplicate** (same contact + highly similar name, incl. additions/omissions) → **HOLD for organizer review**: stored as Held/For Review, excluded from confirmed counts, does not flow to Attendance, no kit entitlement, until the organizer confirms duplicate (first/master kept), confirms separate person (release), corrects + rechecks, or removes it; public detection after email verification with a generic message; (3) same contact + clearly different name → **allow** (family/proxy), flag at most. **Nothing is ever auto-merged.** Logic modeled on Corsizio/Mailchimp/Google Forms/Salesforce patterns — Salesforce's _logic_ only, no integration. | 2026-10-20 (rule) / 2026-10-21 (hold) | Church MD Amendments A.3, C; Running MD §11                                |
| D-10 | **Participant separation by side** (supersedes B.4's cross-side shared identity and the old panel line). Two pools — running and church. Same person in both pools is by design, never a duplicate, never matched or merged across. Rationale: each side monitors its own people's history (running: how many Community Runs attended; church: attendance count + types of church events joined), and duplicates matter because a duplicate record inside a pool splits that person's history. New panel line: _"Duplicate is judged per event; identity is shared within each side."_                                                                                                                                                                                                                                                                                         | 2026-10-21                            | Church MD Amendment D                                                      |
| D-11 | **Church participant HISTORY is in scope** (attendance count + church event types per participant), superseding the guideline's FR-12 deferral for the church side.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | 2026-10-21                            | Church MD Amendment D                                                      |
| D-12 | **Quick walk-in = provisional person**: name-only walk-ins create a flagged, event-local provisional participant — never auto-matched or merged across events; staff review before linking.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | 2026-10-21                            | Running MD §9 (as built); Church MD B.6 (provisional isolation for church) |
| D-13 | **Paper forms = staff attestation**: encoder creates the registration immediately without participant OTP, recording encoder user ID + attestation time (+ consent timestamp and privacy-notice version); public-form validation applies; Live/Deferred timing.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | 2026-10-21                            | Church MD B.6; repo attendance docs                                        |
| D-14 | **Running UX specifics**: Create Event and View Event unchanged; end-of-event report Participants section shows Age + Birthdate with Export CSV and a "View Detailed Participants" button; opening a form goes straight to the registrations monitor; close/reopen requires the organizer's password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | 2026-10-19                            | Running MD §§5, 12, 13                                                     |
| D-15 | **The Church MD is THE guideline** for the Church Events update; where any document conflicts with it on church events, it prevails. One system, two sides, kind-isolated (official definition, Part 1.1).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | 2026-10-20/21                         | Church MD Amendment B                                                      |
| D-16 | **Firebase is dead code** for new forms (its only role was phone verification). Removal planned once published forms offering phone verification finish their lifecycle.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | 2026-10-20                            | Church MD B.2                                                              |

---

# Part 4 — Feature Specification: Running Events

The finalized feature list (reviewed manually with Mark, 2026-10-21).

## 4.1 Event management

- Create / view / edit running events (name, date, time, venue, distance, type).
- Lifecycle: Draft → Registration Open → Registration Closed → Ongoing → Completed.
- No past dates (D-05). Events list: Running/Church tabs only; Running tab has search + column filters (name, type, date, distance, venue, status, created by).
- **Finalize Event**: confirmation popup with totals (registered / checked in / walk-ins) before confirming; organizer password re-authentication; event becomes read-only.

## 4.2 Registration form builder (organizer)

- Displays the participant workflow (Disclaimer → Personal Info → Emergency Contact) with explanations.
- **Editable disclaimer**: rich-text popup editor (H1/H2/bold/italic); a default disclaimer exists if not edited.
- Personal details config: email always required; contact number toggleable required/optional.
- Emergency contact block: all required; relationship dropdown (Parent, Sibling, Spouse, Friend, Guardian, Other) with "Other → specify" (max 15 chars).
- **T-shirt field**: special field outside the 5-custom-field limit; label set by organizer; Small/Medium/Large locked defaults + up to 3 custom choices (6 max), drag to reorder; 1–3 photos (JPG/JPEG/PNG, ≤25 MB total) displayed above the selector with lightbox; cannot be unchecked once it has data.
- **Custom fields**: max 5, existing types, hover tooltip with a use-case example per type.
- Form cannot save until validation passes; after save → redirect to View Event; actions become Edit Registration Form / View Registration Form / Open Registration.
- **View Registration Form**: read-only preview reusing the public wizard (preview mode), free step navigation, inputs disabled, sample data shown, submit replaced by Close Preview.

## 4.3 Public registration — 5-step wizard

- **Step 1 Disclaimer & Consent**: fixed template with `{event_name}` injected + exact Data Privacy Consent text; single agreement checkbox; consent + privacy version recorded at finalize.
- **Step 2 Personal Information**: first/last required; middle name and suffix optional; date of birth via year-first drill-down DatePicker (ages 3–120); Barangay & City (replaces free-form address); email; PH phone input with real-time validation (`09…` / `+639…`); "* indicates required question" legend; draft kept in browser storage.
- **Step 3 Emergency Contact** (required): name, relationship, number; amber **warning only** if it equals the participant's own number; stored on the registration.
- **Step 4 Verification** (email only, D-04): reCAPTCHA v2 must pass before Send Email; OTP 6 digits; 100-second resend cooldown; 5 attempts max persistent across resends → 2-hour lockout per email; 7-day device trust.
- **Step 5 Confirmation**: registration **code + Code 128 barcode** shown (barcode encodes only the code; screenshot/print for check-in; no QR); draft cleared on acknowledge; 5-step progress indicator throughout.
- All inputs validated before advancing; no step advance on any error (Part 4.8).

## 4.4 Identity and duplicate protection (Duplicate Defense Stack)

1. **Composite duplicate check** — exact normalized name + contact in the same event → blocked, non-revealing message, before OTP (D-01/D-02).
2. **Likely-duplicate hold** — same contact + highly similar name (incl. name additions/omissions) → Held / For Review with organizer resolution actions (D-09).
3. **Encoder live search** — typing a name surfaces similar existing registrations; the encoder decides.
4. **Kit claiming checkpoint** — valid-ID check at distribution, one kit per verified person. _(Status note: implemented as a staff procedure outside the app per the HEAD README; see Part 6.)_
5. **Possible Duplicates audit** — per-event report of suspicious pairs with match reasons; high-confidence pairs feed the review queue.

- A returning participant re-registering for another run reuses their record within the running pool; reports show per-participant appearances.

## 4.5 Registration monitor and open/close

- Opening registration redirects to the registration monitor (`/registrations/monitor/{id}`): count, copy link, refresh, export CSV.
- Close / Reopen: Google-Forms-style confirmation modal + **organizer account password**.
- While closed, the public form shows "no longer accepting responses"; in-progress challenges remain honored (24h TTL); no new submissions.

## 4.6 Attendance

- Marking window opens **1 hour before** event start; stays open after start (paper encoders); organizer closes manually.
- **Check-in mode**: large auto-focused code input; barcode scanner (keyboard-wedge) or in-page **camera scanning of Code 128** (phone-as-scanner via app is the no-code alternative); valid code → participant card (name, photo if available, event, T-shirt size, custom answers) + big **Present** button (no confirm step) → green flash, input clears and refocuses; invalid code → error + suggestion to use walk-in; side panel with live counter (X / Y) and recent check-ins feed.
- **Walk-in mode** (two entry types): **Quick walk-in** — name only, provisional record (D-12), marked present on the spot; **Encode paper form** — condensed single-page staff form with all public-form inputs (disclaimer auto-accepted), staff attestation (D-13), creates a proper registration. Live search while typing: match found → mark present on the existing record (no duplicate created).
- **Live / Deferred** encoding toggle (timestamp = now vs paper submission time).
- Tabs are **Check-in / Walk-in only** (Confirm and Batch tabs removed).
- Statuses simplified to **Present / Absent** in reporting (Late/Excused/Unmarked removed on the running side); re-marking preserves the original `verified_at`.

## 4.7 Reports

- Cards kept: **Registered (Form)**, **Walk-ins by source** (Online / Paper Form / Quick Walk-in), **No-shows**, **Show Rate**. Removed: Verification Rate, Attended—Verified, Attended—Override, Total Physical Attendance, Attendance Breakdown section.
- Cross-events section: heading "CROSS-EVENTS"; simple searchable/filterable event list (summary-details line and detail table removed).
- Participants table: search + filters; **Age and Birthdate** columns added; "Last Appearance" column removed; **Export CSV**; **View Detailed Participants** — combined view of report data (status, source, appearances) + full form data (personal info, emergency contact, T-shirt, custom answers).

## 4.8 Validation summary

Checkbox required (Step 1). Names: required, min 2 / max 50, letters/spaces/hyphens/apostrophes. Suffix: optional, max 10. Email: required, valid format. Contact: per organizer toggle; PH format when provided. DOB: required, valid, not future, age 3–120. Barangay & City: required, min 3 / max 255. Emergency: names required; relationship from the fixed list; Other-specify required ≤15; number required PH format; same-as-own = warning only. T-shirt choice required if the field is required. Custom fields validated per type + required flag. reCAPTCHA must pass before Send Email; OTP rules per 4.3.

---

# Part 5 — Feature Specification: Church Events

The finalized definition (Church MD Amendment D, approved 2026-10-21).

## 5.1 Coverage — nine modules

Services (Sunday Service; Special Service) · Fellowship (Church Fellowship; Ministry Fellowship) · Discipleship (Bible Study; Discipleship Class; Leadership Training) · Youth Ministry (Youth Camp; Huddle; Campus Ministry; Timothy Class — **Youth Service intentionally excluded**) · Kids Ministry (Kids Service; Vacation Bible School; Outreach · Wawang Pulo; Outreach · Pasolo; Outreach · Balubaran; Outreach · Coloong) · Young Adult (Young Adult Service; Seminar; GRC Care Group; GRC Fellowship) · Worship & Prayer (Outdoor Prayer Meeting; Worship Night; Prayer & Fasting) · Church Administration (Church Planning; Ministry Meeting per team — Worship, Technical, Multimedia, Social Media, Finance Team, Light, Kids, Young Adult) · Special Occasions (Church Anniversary; Thanksgiving Celebration; Pastor's Appreciation; Dedication; School of Leaders 1/2/3).

## 5.2 Shared workflow

Select ministry → select category → Event Details → **Registration → Attendance → Summary**, for every church event. Records are kept per event; for Services, per **occurrence**. Counting rule: `Registered = Present + Absent + Unmarked`.

## 5.3 Event management

Create/edit events with basic details (name, date, time, venue, description). Church events run on their own lifecycle (`ChurchEventStatus`). No past dates (D-05) applies to every church scheduling date, pickers and server rules alike.

## 5.4 Registration (D-06, D-07, D-08)

- **One list per event**, two channels:
    - **Staff Add Participant** — manual, no OTP; system records who encoded it and when (attestation pattern, D-13).
    - **Per-event public link** — same fields; email required and verified by email OTP + reCAPTCHA via the running side's verification service (Fellowship/ServiceOccurrence targets need the challenge bridged); **no phone verification** (D-04).
- Fields: first/middle/last name + suffix; email; contact number; birthday (age derived); Barangay & City address; emergency contact.
- **Participant type per registration: Member / First-Timer / Guest** (D-08). Guardian field shown for Kids Ministry and Youth Camp.
- Every completed registration gets a **registration code** (e.g., KM4X-P2N7) — for lookup only, **no scanning** on the church side.
- Full **Add / Edit / Delete**: edit re-runs the per-event duplicate check (excluding itself); delete removes the attendance row and adjusts counts; delete never touches the person record.
- The Add Member / Add First-Timer dialogs move from the attendance pages into Registration.

## 5.5 Identity and duplicates (D-09, D-10)

- Church pool is **separate** from the running pool (D-10). Within the church pool, the composite rule applies; a returning church participant is recognized and reused across church events.
- "Same event" for Services = the same occurrence (this Sunday vs next Sunday = a new registration, same church participant).
- Three outcomes exactly as D-09: exact → block; likely (same contact + similar name incl. additions) → **hold for organizer review**; same contact + clearly different name → allow/flag.
- Staff adds/edits run the same duplicate check; when both contacts are empty, warn, don't block.

## 5.6 Attendance (D-07) — monitoring only

- Registrations flow in automatically as **Unmarked**; Attendance adds nobody.
- Code input + Look up; search by name/email/phone; filter chips **All / Unmarked / Present / Absent** with counts; inline marking from the single list.
- Statuses: **Present / Absent / Unmarked only** (no Late, no Excused).
- **Removed vs the running page:** camera scanning, Add Walk-In, Live/Deferred modes, bulk batch entry, Check-in/Walk-in mode tabs.

## 5.7 Summary and participant history (D-11)

- Per event: Registered / Present / Absent / Unmarked totals; participant list with statuses; Edit Attendance to correct marks.
- **Participant history** across the church pool: how many times a participant attended and what types of church events they joined.

## 5.8 Module specifics retained

Services are recurring (service → occurrences, one attendance record per occurrence). Fellowship keeps its events + groups structure. Discipleship keeps programs → batches → sessions → enrollments → per-session attendance.

## 5.9 Explicitly out of scope (church)

Payments, donations, church finance; payroll; inventory; facility booking; SMS/notifications/automatic reminders; phone verification; camera scanning; automatic merging; participant accounts; per-person revocable links.

---

# Part 6 — Implementation State (verified against code @ 5755c75, 2026-10-21)

Verification method: static audit of the pulled clone, item by item (controllers, models, services, form requests, migrations, frontend pages). The test suite was **not** run in this environment (no PHP runtime); 859 Pest test definitions counted across 92 test files. Codex reported 923 passed / 3 skipped locally before the push.

## 6.1 Implemented — Running Events (13 of 15 Running MD sections complete)

- **Identity (§1)**: composite rule live — `findByComposite()`/`resolveFrom()` with middle/suffix expansion (`Participant` scopeMatchingName L263, findByComposite L297); global UNIQUEs dropped; normalized columns in place.
- **Wizard (§2)**: 5-step flow live; consent + privacy version written at finalize (RegistrationVerificationService L554-555); browser draft cleared on acknowledge.
- **Attendance fixes (§3)**: P3 (`verified_at` preserved) live with `AttendanceReverificationTest`; **P4 dateless-event guard FIXED** (null-date guards with `now()` fallback, AttendanceController L528/L787/L846) — the Running MD still marks it PENDING (stale text).
- **Lifecycle guard (§4)**: `canOpenRegistration()` guarded by `isRunningEvent()` (Event.php L404).
- **Events list (§5)**: All tab removed; Running/Church tabs; search + column filters on the Running tab.
- **Form builder (§6)**: disclaimer editor; email required; emergency relationship dropdown + Other ≤15; T-shirt builder exactly to spec (locked defaults, ≤6 choices, drag reorder, ≤3 photos ≤25 MB JPG/PNG, gallery); preview mode endpoint; OTP 5-attempt + 2-hour lockout; reCAPTCHA service wired; email-only flow (`usesEmailOnlyRegistration()`, Event.php L284).
- **Barcode (§7)**: Code 128 component on confirmation; code-only bridge.
- **Attendance UI (§§8-9)**: modes are only Check-in / Walk-in (attendance.tsx L34); participant card; live check-in summary + recent feed; invalid code suggests walk-in; camera scanning component; Quick walk-in provisional (`provisional_event_id`, "Never resolve or merge it", AttendanceController L796-809); Encode paper with attestation (`encoded_by`, `contact_attested_at`, `consent_accepted_at`, `privacy_notice_version`, L750-773); Live/Deferred toggle; 1-hour-before marking window (`canRecordAttendanceNow()`).
- **Finalize (§10)**: confirmation popup with registered/checked-in/walk-ins summary; password re-auth via middleware.
- **Validation (§11)**: spot-checked rules conform (names, PH regex, DOB 3–120, relationship enum, warning-only same-number). **Exception:** suffix max is 16 in one rule branch vs spec 10 (Part 7, item U-3).
- **Report (§12)**: removed cards absent; kept cards present (Registered (Form), No-shows, Show Rate — report.tsx L66-69); statuses simplified; cross-events = searchable simple list; Participants table with Age + Birthdate, no Last Appearance column, Export CSV, View Detailed Participants (L87-107).
- **Monitor & close/reopen (§13)**: open redirects to `registration-monitor.show`; close/reopen validate `current_password` (EventController L506/L524).
- **Kind isolation (§14)**: all lifecycle gates guarded (`canOpen/canClose/canReopen/canFinalize/canRecordAttendance/canRecordAttendanceNow`); running-only endpoints abort for church events.
- **Church foundation (§15)**: `ChurchEventStatus` enum and the existing Services/Fellowship/Discipleship modules exist in their current (pre-update) form. **The resolvers in Services and Fellowship already use `findByComposite`** (ServiceAttendanceController L250, FellowshipAttendanceController L203) — spec Appendix E Steps 0–1 are done ahead of the church build.

## 6.2 Partial / advisory only

- **Likely-duplicate layer (§11 layer 2 + D-09 outcome 2)**: `DuplicateReview` service is **advisory only** (its docblock: "Advisory matches never merge identities"). Public form shows a similarity warning with an acknowledgment flag (route throttled, web.php L59); staff get a view-only Possible Duplicates page (search + pair audit with reasons; `DuplicateReview::reasons()` uses similar name + shared birthday/email/phone). **No Held state, no exclusion from counts/Attendance, no organizer resolution actions, no review queue.** The policy (D-09) was decided 2026-10-21 12:20; the bulk commit predates it and the HEAD commit implemented the advisory interpretation.
- **Kit checkpoint (§11 layer 4)**: spec lists it as a system layer; the HEAD README states kit claiming remains a staff procedure **outside the app**. The two records conflict (Part 8, O-6).

## 6.3 Not implemented

1. **The HOLD + organizer review queue** (both sides) — D-09 outcome 2. Includes: Held/For Review state, exclusion from confirmed counts and Attendance, no kit entitlement while held, the four organizer actions (confirm duplicate / release / correct + recheck / remove) with audit trail, and the audit page feeding the queue (§11 layer 5's second half).
2. **The entire Church Events update build** (Part 5): unified Registration step for church events; per-event public link for church events (`acceptsPublicRegistration()` is running-only, Event.php L411-413) incl. the Fellowship/ServiceOccurrence verification-challenge bridge; church registration codes; `participant_type` + `guardian` columns (absent from all migrations); Guest type; the simplified monitoring-only church Attendance (Excused still present in Services/Fellowship/Discipleship attendance pages); church participant history views (D-11).
3. **Side-scoped participant matching** (D-10): current resolvers match across the whole participants table (global `findByComposite`). The church build must add the pool scope so church registrations never resolve into running participants (and vice versa).
4. **No-past-dates enforcement on church scheduling** (D-05): Fellowship `event_date` and single service `occurrence_date` still carry only `before_or_equal:+2 years` — no `after_or_equal:today` server rule; their pickers are unbounded. (Generic events and recurring-service `first_date` comply.)
5. **Authorization on church controllers**: no `authorize()`/Gate checks in the church module controllers (deferred in Church MD B.7; unchanged since the 2026-10-19 audit).
6. **Firebase dead-code removal** (D-16): `config/firebase.php`, `firebase-phone-verifier.tsx`, and references in the verification controller/service and verify page are still in the tree.
7. **Merging tooling for pre-existing duplicates**: the audit lists pairs only; no master-record merge flow (deliberately scoped out of the duplicate plan; the duplicate-data audit on existing church/running data has not been run yet).

## 6.4 Built but not yet proven live

- **Live acceptance**: real reCAPTCHA end-to-end on the production hostname (hostname not yet authorized in Google settings), camera scanning, and physical barcode-scanner checks — listed as outstanding in the HEAD README.
- **Green suite at HEAD**: not verified in this environment; **GitHub Actions shows a failing check on main** (0/1 on the latest commit, seen 2026-10-21) and on the Dependabot branch (1/2). Cause not yet investigated.
- **Workflow note**: all current code reached main by direct push. The only pull request in repo history is Dependabot PR #1 (a CI action-pins bump, still open and stale — 14 behind / 1 ahead). CodeRabbit has therefore never reviewed application code. A leftover branch `coderabbit/project-goals-documentation-review` (136 behind / 0 ahead) contains nothing unique.

---

# Part 7 — Build and Update Backlog

Ordered by dependency and Mark's stated priorities (duplicate integrity first, then the church build, then hardening).

## Phase 1 — Duplicate integrity completion (running side first)

- **B-1. Implement the HOLD** (D-09): held state on registrations; exclusion from counts/Attendance/kit; organizer review queue with the four resolution actions + audit trail; public detection after email verification with the generic review message; staff encoding shows the candidate with resolve-now or queue options. Upgrade the Possible Duplicates page from view-only audit to the queue front-end; high-confidence audit pairs feed the same queue.
- **B-2. Acceptance tests for the hold**: EJ-case (omitted middle name) held; exact still blocked pre-OTP; siblings released correctly; held row absent from counts/Attendance; resolve actions audited.
- **B-3. Run the possible-duplicates audit** on existing running + church data to size pre-existing duplicates before any cleanup decision (Church MD B.8).

## Phase 2 — Church Events build (Part 5; Church MD Amendments A–D prevail)

- **B-4. Participant pool scoping** (D-10): add the side scope (kind) to participant matching; scope `findByComposite`/`resolveFrom` per pool; migrate existing rows to their correct pool; regression tests that cross-pool matches never resolve.
- **B-5. Church Registration step**: unified Registration per church event (staff Add/Edit/Delete with duplicate check on add and edit; attestation fields); `participant_type` + `guardian` columns; Guest type; registration codes for church registrations; move Add Member/Add First-Timer dialogs from attendance pages into Registration.
- **B-6. Church public link**: per-event link reusing `RegistrationVerificationService` for Event-based church events; bridge the challenge for FellowshipEvent/ServiceOccurrence targets; email required on the link form.
- **B-7. Church Attendance reshape**: monitoring-only list (code lookup, search, chips, Present/Absent/Unmarked); remove Excused from church modules, camera/walk-in/mode UI; registrations flow in as Unmarked.
- **B-8. Church summary + history**: per-event summary per the counting rule; participant history views (attendance count + church event types joined) (D-11).
- **B-9. Church acceptance tests**: the spec Appendix E Step 5 set (cross-channel block with no OTP sent, siblings, Jr/Sr, next-occurrence reuse, edit collision, double-submit, resolver regressions).

## Phase 3 — Hardening and cleanup (updates to existing code)

- **U-1. Date-rule completion** (D-05): add `after_or_equal:today` to Fellowship event and single-occurrence server rules; bound their date pickers (min today / max +2y); correct the retrospective-logging docblocks.
- **U-2. Church authorization pass**: authorization checks across the church module controllers (deferred from B.7; pairs with the outside-the-event-day rules once ruled, Part 8 O-4).
- **U-3. Suffix max alignment**: make all public rule branches max 10 per spec §11.
- **U-4. Firebase removal** (D-16): delete `config/firebase.php`, the phone-verifier component, and dead references once legacy phone-verification forms finish their lifecycle.
- **U-5. Documentation consolidation**: bring RUNNING_EVENTS_UPDATE.md current (email-only as built, provisionals, attestation, hold policy + separation rule, P4 fixed, guards applied, 923/3 baseline, Still Open table corrected); mirror Church MD Amendment D into the companion spec (its §2D/Appendix E predate the separation rule); reconcile Codex's `docs/church-events/11-shared-workflow-spec-2026-10-21.md` against the Church MD (MD prevails); fix the Running MD §2 Step 4 channel-choice text.

## Phase 4 — Acceptance and workflow

- **A-1. Investigate the failing GitHub Actions check on main**; get the suite green and recorded at the current HEAD (`php artisan test` on Mark's machine + CI).
- **A-2. Live acceptance pass**: reCAPTCHA end-to-end on the production hostname (authorize the hostname in Google settings first); camera scanning on a phone/tablet; physical Code 128 scanner test.
- **A-3. Adopt the branch → PR → CodeRabbit review → merge workflow** for all new changes (so CodeRabbit actually reviews application code); close stale PR #1 and let Dependabot recreate it against current main; delete the leftover coderabbit branch.
- **A-4. Panel readiness**: panel prep file is current (MVP, scope, duplicate rationale incl. separation line); keep status claims honest — running side implemented, church update specified and decided, build pending.

---

# Part 8 — Open Decisions (rulings still needed from Mark / the client)

| #   | Question                                                                                                                                                                                                                                                                                                                                                                  | State                    |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------ |
| O-1 | **Birthday clause** in Church MD Amendment C — "with birthday as a corroborating signal when available" was added without a ruling. **Keep** (and mirror into the other three policy documents) or **strike**? Note: the pushed `DuplicateReview::reasons()` already treats birthday as a corroborating key in the advisory layer, so "keep" aligns the MD with the code. | Pending since 2026-10-21 |
| O-2 | **Church link v1 scope** — Services first, or Fellowship in the same phase? Discipleship self-enrollment is a separate question.                                                                                                                                                                                                                                          | Open (Church MD B.9)     |
| O-3 | **Prototype vs client-docs conflict ruling** — where the ChatGPT-prototype guideline and the client-confirmed church documents (docs/church-events/02/07/09/10: Youth Camp payments + per-day attendance, Timothy progress, Campus grade levels, Church Planning Timeline, Special Occasions Documents, Youth Service in client scope) disagree, which wins?              | Open (Church MD B.9)     |
| O-4 | **Authorization + outside-the-event-day rules** — in scope for the church build, or formally deferred? (CRUD-risk Appendix D problems 6 and 3.)                                                                                                                                                                                                                           | Deferred (Church MD B.7) |
| O-5 | **Similarity-warning placement** — when the hold is built (B-1), public detection after email verification is the Amendment D recommendation; confirm, or keep the current form-side advisory placement for the warning layer.                                                                                                                                            | Open                     |
| O-6 | **Kit-claiming checkpoint record** — Running MD §11 layer 4 (system feature) vs HEAD README (staff procedure outside the app). One record must be amended.                                                                                                                                                                                                                | Open                     |
| O-7 | **Church summary export** — CSV/print export for the church summary: in or out? (Running side has CSV; the church definition is silent.)                                                                                                                                                                                                                                  | Open                     |

---

# Part 9 — Documentation Index and Verification Notes

## 9.1 Workspace documents (planning, specs, decisions)

| Document                                                    | Role                                                                                                                                                                                                                                                                                                       |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `WDEMS_MASTER_PROJECT_DOCUMENT.md` (this file)              | Consolidated record: decisions applied, feature specs, code state, backlog.                                                                                                                                                                                                                                |
| `user/files/Church_Events_Update.md` (763 lines)            | **THE church guideline** — v1.0 (§§1–13) + Amendment A (registration/attendance shape), A.1 (CRUD, no mode tabs), A.2 (participant types), A.3 (duplicates), B (consolidated conclusions B.1–B.9), C (hold policy), D (finalized feature definition + participant separation). Prevails on church matters. |
| `wdems-church-events-update-spec.md` (538 lines)            | Companion church spec: flow, Appendices B (input audit), C (date rule), D (CRUD risks), E (duplicate implementation plan, Steps 0–5). Predates Amendment D — mirror pending (U-5).                                                                                                                         |
| `wdems-composite-rule/RUNNING_EVENTS_UPDATE.md` (423 lines) | Running-events update spec, §§1–15 + Duplicate Defense Stack. Predates part of the pushed code — consolidation pending (U-5).                                                                                                                                                                              |
| `wdems-registration-workflow-spec.md`                       | The 5-step wizard spec (2026-10-19); its channel-choice verification design is superseded by D-04 (email-only).                                                                                                                                                                                            |
| `wdems-panel-duplicate-participants.md` (254 lines)         | Panel prep: 60-second duplicate script, worked examples (incl. the EJ case), MVP one-liner + in/out scope, separation one-liner, Q&A.                                                                                                                                                                      |
| `wdems-running-md-gap-audit-2026-10-21.md`                  | Section-by-section audit of the Running MD against code @ 5755c75 (evidence for Part 6.1–6.2).                                                                                                                                                                                                             |
| `wdems-audit/AUDIT_REPORT.md`                               | Full docs + static code audit @ dc34933 (2026-10-19): kind leakage, church authorization absence, Q14/Q16/Q17 gaps.                                                                                                                                                                                        |
| `wdems-composite-rule/` (checklist + progress files)        | Composite-rule implementation checklist and progress records (W1–W7).                                                                                                                                                                                                                                      |

## 9.2 Repository documents (in the repo, `docs/`)

Architecture (`architecture.md`, `AI-CONTEXT.md`), identity (`participant-identity.md`, `composite-identity.md`, `participant-identity-and-monitoring-plan.md`), registration (`registration.md`, `registration-wizard.md`, `registration-monitoring.md`, `registration-contact-verification.md`, `registration-development-plan.md`), attendance (`attendance-redesign.md`, `attendance-code.md`, `attendance-encoding-and-reporting.md`), running status (`running-events-progress-2026-10-20.md`, `running-events-audit-2026-10-21.md`), schema export (`wdems-schema.sql`, 30 tables), and the church series `docs/church-events/01–11` + `TASKS.md` — of which **doc 11 (Codex's shared-workflow spec) must be reconciled against the Church MD, MD prevailing** (U-5).

## 9.3 Verification notes (read before quoting status)

- Code state claims in Part 6 were verified statically against the clone pulled to 5755c75 on 2026-10-21. Line references are from that tree.
- The test suite cannot run in the compiling environment used for this document (no PHP runtime). "Implemented" means the code paths, rules, and tests exist and read correctly — not that the suite was executed here. Suite-green at HEAD is backlog item A-1.
- Live behaviors (reCAPTCHA on the production hostname, camera/physical scanning, email delivery) are unverified by definition until the A-2 acceptance pass.
- GitHub state (commits, branches, PR #1, check marks) was read from github.com/TaZuna440/WDEMS on 2026-10-21.

---

_End of the Master Project Document — compiled 2026-10-21. Append corrections as dated notes; do not silently rewrite decided sections._
