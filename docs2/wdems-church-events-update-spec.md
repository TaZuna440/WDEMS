# WDEMS Church Events Update — Registration, Public Link & Attendance

**Status:** Mark's decisions, 2026-10-20. Amends `Church_Events_Update.md` (the recorded
guideline, v1.0) — specifically §6.1 Registration and §6.2 Attendance.
**Grounding:** repo at dc34933 (`~/workspace/wdems`). Line numbers should be re-checked
against Mark's local checkout before handing to Codex — the screenshot Mark supplied
shows a "Scan with camera" button that does not exist at dc34933, so his local tree
may be ahead of this clone.

---

## System definition (Mark, 2026-10-20 — his exact wording)

> "WDEMS is also a centralized church event management system designed to
> help church administrators and ministry leaders organize activities
> across different church ministries. It allows users to create events,
> register participants, maintain attendance records, and generate
> attendance summaries through a consistent and organized workflow."

Consistent with guideline §1/§13. Note "also": WDEMS is one system with two
sides — Community Run (running events) and this church event management
system — sharing the mechanics this spec reuses, with kind isolation
between them.

## 1. The flow (Mark's example, as the acceptance scenario)

1. Organizer creates a church event and its details — e.g. **Sunday Service**.
2. The event's **Registration** section opens with two ways in (Section 2).
3. A friend asks to register → the organizer **adds him manually** in Registration.
4. The friend's **siblings** also want to register. Encoding them all by hand is a
   hassle and the organizer has no time → the organizer gives them the event's
   **public registration link**, they register themselves, and the organizer
   **monitors** the incoming registrations in the same Registration list.
5. On the day, **all registrations — manual and from the link — are already in
   Attendance** as Unmarked. Nobody re-encodes anything.
6. At the door, the assigned organizer identifies each person by their
   **registration code** and marks them Present (Section 3).

Acceptance test: manual add + 2 link registrations for one Sunday Service →
Registration list shows 3 → Attendance shows the same 3 as Unmarked →
typing one registrant's code in Attendance narrows to that person → mark Present →
Summary: Registered 3, Present 1, Absent 0, Unmarked 2.

---

## 2. Registration — one list, two entry channels

Every church event's Registration section is a single registration list fed by
**two channels**. Both channels produce the same kind of record for that event.

**Registration is the full record-keeping section: Add, Edit, Delete.**
The organizer can add a participant, **edit** any registration's details, and
**delete** a registration — for rows from either channel (manual or link).
This amends guideline §6.1, where the demo offered no editing or removal.
Because Registration owns add/edit/delete, Attendance needs no adding or
mode machinery at all (Section 3). Rules: editing a name/contact re-runs the
per-event composite duplicate check; deleting a registration also removes
its row from Attendance and the Summary counts adjust.

### 2A. Organizer manual add (existing behavior, kept)

- The Registration section keeps the existing **Add Participant** record/form.
  This is the organizer's section: staff behind a logged-in account add people
  directly (the friend in the example).
- Fields per the guideline (§6.1 + §7), specific to the event: participant name
  (required), age, contact number, participant type — **Member, First-Timer,
  and Guest only** (Mark, 2026-10-20; the guideline's Graduate and
  Ninong/Ninang types are dropped) — parent/guardian shown for Kids Ministry
  and Youth Camp events.
- Staff-entered: **no OTP** — the staff account is the trust, same as church
  registration works today.
- A completed manual registration generates a **registration code** (see 2C)
  and appears in Attendance as Unmarked, same as a link registration.

### 2B. Public link (new)

- Each specific church event gets its own **authorized public registration
  link**, generated/opened from that event's Registration section. The link is
  scoped to that one event only — a Sunday Service link registers people for
  that Sunday Service, nothing else.
- The public form carries **the same details/fields as that event's
  registration** (2A's field set for that event type) — one form definition
  per event, two entry points.
- **Email is required on the link form and is verified like Community Run**
  (Mark's decision, 2026-10-20): full email OTP via the existing
  `RegistrationVerificationService` — challenge, 100s resend cooldown, 24h
  challenge TTL, 7-day email device trust, composite duplicate guard at begin,
  finalize on verify. (The staff form in 2A does not require email; this
  applies to the public link form only.)
- **Email is the only verification channel — there is no phone verification**
  (Mark, 2026-10-20). No channel-choice screen, no SMS code, no Firebase.
  A contact number may still be *collected* as an input and still works as a
  composite matching key, but it is never verified.
- Link submissions land in the **same Registration list** as manual adds, as
  Unmarked, and the organizer monitors them there as they arrive. The list
  should show each registration's **source** (Manual / Link) so the organizer
  can tell them apart.
- Identity: the same composite rule as running events — normalized name AND
  (email OR normalized phone) — with the per-event duplicate guard
  (same name + same contact on the same event = rejected). Applies to both
  channels. *(Spec is written on the proposed composite identity rule for
  church, presented 2026-10-20; Mark's approval is the remaining gate.)*
- Plumbing note (from earlier grounding): public links today hang only off the
  Event model (`r/{event:registration_slug}`). ServiceOccurrence and
  FellowshipEvent are standalone models with no slug, so links for those
  targets need the challenge/link plumbing generalized or bridged — a cost
  item, not a blocker.

### 2C. Registration code

- Every completed church registration (manual or link) receives a
  **registration code** in the same format as Community Run
  (e.g. `KM4X-P2N7` — the `attendance_code` on the Registration model today).
- The registrant gets their code at the end of the link flow; for manual
  adds, the organizer can read the code off the Registration list and give it
  to the person.
- The code is **typed/spoken at the door — never scanned**. No barcode, no QR.

---

### 2D. Duplicate control — no duplicate details in the same church event (DECIDED 2026-10-20)

Mark's rule, focused on Appendix D problem 1 (duplicate people):

- **Within one church event, a duplicate registration is not allowed.**
  Duplicate is defined precisely — NOT "every field identical" (a re-typed
  age would slip past that), but the composite identity: **same normalized
  name (first + middle + last + suffix) AND same contact (email or
  normalized phone)** on the same event.
- **Which one is taken: the first/existing record wins.** A second
  submission matching an existing registration is rejected; nothing new is
  created. Across events, the existing Participant record is the one taken
  and reused (resolve-or-create via `findByComposite`).
- **Why the check is trustworthy on the link:** email is required and
  verified by OTP, and reCAPTCHA gates the form — so the email in the
  check is proven to belong to the submitter and can't be typed as someone
  else's. (Mark's reasoning, confirmed.)
- **Where it runs:** on the link, at verification *begin* — before any OTP
  is sent — reusing the running side's `assertNoDuplicateRegistration()`
  (RegistrationVerificationService, called from `begin()`). Rejection is
  non-revealing: "This registration cannot be started. If you believe this
  is an error, contact the event organizer." On staff manual adds/edits,
  the same check runs on submit, against the details as entered
  (unverified, so the staff-side check is only as good as the typing —
  and the staff form needs the email field it currently lacks,
  Appendix B #1).
- **Not duplicates (allowed):** same email, *different* name — the family
  case (siblings under a parent's email); same name, *different* email —
  treated as a different person under strict matching. Known limit: one
  person using two different emails is not caught by the exact rule.
- **Likely duplicates are held (DECIDED 2026-10-21; Church MD
  Amendment C):** same email/phone + a highly similar name — including
  one entry omitting the middle name, as in “EJ Rubi Pomasin” vs
  “EJ Pomasin” — is stored as Held / For Review, not as a completed
  registration. The organizer confirms a duplicate (first/master kept),
  confirms a separate person (held registration released), corrects the
  details and reruns the checks, or removes the held submission. Shared
  contact with a clearly different name is not, by itself, a likely
  duplicate. The system never auto-merges people.

Implementation plan for this rule: Appendix E.

## 3. Attendance — monitoring only, copied from Community Run minus five things

The church Attendance section looks the same as the Community Run attendance
page (Mark's screenshot: `resources/js/pages/events/attendance.tsx`) with the
removals below.

### Keep

- **Registration-code lookup** at the top: code input (ticket icon,
  `KM4X-P2N7` placeholder) + **Look up** button. Looking up a code narrows
  the list to the matching registration; clear the code to return to the
  full list. This is how the door organizer identifies who is entering.
- **Search** by name, email, phone.
- **Filter chips:** All / Unmarked / Present / Absent, each with its count.
- The registration list itself, with per-person **Present / Absent** marking.
- Empty state ("No registrations yet…") fed by Registration, not by
  attendance-side adding.
- Attendance Summary stays as the guideline defines it:
  Registered = Present + Absent + Unmarked.

### Remove

1. **Scan with camera** — no barcode/QR scanning anywhere in church
   attendance. Identification is by typed registration code or search only.
2. **Add Walk-In (on the Attendance page)** — nobody is added in Attendance.
   A walk-in is added in **Registration** via Add Participant (2A), then
   appears in Attendance like everyone else.
3. **Live / Deferred encoding modes** — these exist in Community Run only
   for the bulk walk-in batch (`events/attendance.tsx`, PageMode `batch`,
   EncodingMode `live | deferred`). Church has no batch encoding in
   Attendance because there is nothing to encode there — Registration
   already did it. The whole batch mode goes with them.
4. **Late / Excused statuses** — church attendance is Present / Absent /
   Unmarked only, per the guideline's final scope. (Late/Excused exist in
   the running-events status set; they do not carry over.)
5. **The Check-in / Walk-in mode tabs** — the toggle at the top of the
   running attendance page (in Mark's local build: **Check-in** / **Walk-in**;
   at dc34933 the same machinery is `PageMode = mark | confirm | batch`).
   Church Attendance has **no mode tabs and no mode caption** ("Mark
   participants one at a time." goes with them). There is exactly one view:
   the monitoring list, where each person is marked Present/Absent inline.
   Removing the Check-in *tab* does not remove checking people in — marking
   from the list is the check-in. The Walk-in tab is out for the same reason
   as Add Walk-In and Live/Deferred: adding people is Registration's job
   (Add/Edit/Delete, Section 2), never Attendance's.

### What this changes in the current church pages

- Today's Services and Fellowship attendance pages add people **on the
  attendance page** (Add Member / Add First-Timer dialogs in
  `services/attendance.tsx`, same pattern in Fellowship). Under this update
  those add dialogs leave Attendance; adding people happens only in
  Registration. Attendance becomes a pure monitoring/marking screen.
- Registrations flow Registration → Attendance automatically. There is no
  separate step, export, or re-entry between them.

---

## 4. What this supersedes

- The earlier caution "church registrations land in the roster with **no
  codes**" is superseded by Mark's decision here: church registrations
  **do** get a code — what they don't get is barcode/QR **scanning**.
  Kind isolation still applies: the church flow reuses the code, lookup,
  and verification mechanics without entering the running-events lifecycle
  (no running finalize/report pipeline).
- Guideline §6.2 ("attendance list is built from registered participants")
  stands and is strengthened: the list is not just built from Registration,
  it is the *only* source — Attendance adds nobody.

## 5. Still open (not decided by this update)

- **Identity rule approval** — the composite rule for church (Section 2B)
  is the version presented to Mark on 2026-10-20, awaiting his approval.
- **Prototype-vs-client-docs conflict ruling** — prototype wins everywhere,
  vs client docs win where already decided. Still pending; this update does
  not touch the areas in conflict (Youth Camp payments, Timothy progress,
  etc.).
- **Link v1 scope** — Mark's example is a Sunday Service, which points to
  Services first; whether Fellowship events and the guideline modules ship
  in the same phase is still Mark's call.

---

## Appendix B — Participant inputs: definition and what's wrong (2026-10-20)

**Definition.** A participant input is any field a form collects that lands on
the one shared `Participant` record: `first_name`, `last_name`, `middle_name`,
`suffix`, `email`, `contact_number`, `age`, `birthday`, `address`
(Participant model, dc34933). Participant type, guardian, and emergency contact
are NOT participant inputs — they belong to the per-event Registration.

### Input matrix (✓ asked, — not asked)

| Input | Run public form | Church staff add (Services/Fellowship) | Guideline §6.1 | Church link (decided) |
|---|---|---|---|---|
| First name | ✓ required | ✓ | as one full_name | ✓ |
| Last name | ✓ required | ✓ | as one full_name | ✓ |
| Middle name | ✓ optional | — | — | ? |
| Suffix | ✓ optional | — | — | ? |
| Email | ✓ required, OTP | **— no field exists** | — | ✓ required, OTP |
| Contact number | ✓ req/opt per organizer | ✓ | ✓ optional | ? |
| Birthday | ✓ (age derived) | ✓ | — | ? |
| Age | derived | ✓ entered separately | ✓ | ? |
| Address | Barangay & City → address | free-text Address | — | ? |
| Participant type | n/a | implicit (Members vs First-Timers track) | ✓ per registration | ✓ per registration |
| Guardian | n/a | — | Kids / Youth Camp only | Kids / Youth Camp only |

### What's wrong

1. **Email hole (biggest).** Identity = normalized name + (email OR phone),
   and the church link now requires verified email — but the Services and
   Fellowship add dialogs have **no email input at all**. The Services
   dialog's own helper text even says "If the email or phone matches an
   existing participant…" — staff cannot type the thing the matcher uses.
2. **Name shape mismatch.** Identity uses split, normalized
   first/middle/last/suffix with strict exact matching. Staff dialogs collect
   first/last only → a staff-added church participant can never match the
   same person's run registration if that one carried a middle name
   (strict match = different person = duplicate record), and Jr./Sr.
   collisions are unresolvable on the church side. The guideline's single
   `full_name` contradicts the split storage.
3. **Age AND birthday, both stored, both typed.** Participant carries both
   columns; staff dialogs ask for both separately, so they can contradict.
   The run form derives age from birthday; the guideline asks age only.
   No rule says which wins.
4. **Address means three things.** Run: Barangay & City written into
   `address`. Church staff: free-text full address. Guideline: nothing.
   One column, three meanings.
5. **Participant type has no home.** No `participant_type` exists in code.
   Services encodes type structurally (Members vs First-Timers = separate
   tracks/tables); Fellowship has Group instead. The decided type set is
   Member / First-Timer / Guest only (Mark, 2026-10-20) — and even of
   those, **Guest** cannot be expressed at all today.
6. **Guardian has no column.** Required by the guideline for Kids and
   Youth Camp; exists on neither Participant nor Registration. (Emergency
   contact exists, but only on running Registrations — a different concept.)
7. **Church staff can create unmatchable people.** Email can't be entered
   (problem 1) and contact number is optional → a staff-added participant
   with neither has no identity key at all. The composite matcher can never
   find them again; every new event mints a fresh duplicate person.

---

## Appendix C — Date rule: scheduling dates are treated the same everywhere (2026-10-20)

**Mark's rule:** the system's date picker treats dates the same when
scheduling an event — **you can't pick a date in the past.**

There is ONE shared component (`resources/js/components/date-picker.tsx`,
year-first drill-down, min/max bounds, typed entry enforces the same bounds).
The rule for every **scheduling** date:

- Picker: `minDate = today` (past days disabled, typed past dates rejected),
  `maxDate = today + 2 years` — the bounds the generic event wizard already
  uses (`ScheduleStep.tsx` pickerBounds; server MAX_EVENT_YEARS_AHEAD = 2).
- Server: `after_or_equal:today` + the +2 years upper bound, matching the
  picker. UI and server must agree — no picking a date the server then
  rejects, and no server rule the picker doesn't show.

**Current state (dc34933) — deliberately inconsistent, which is the problem:**

| Scheduling date | Picker bounds | Server rule | Past pickable? |
|---|---|---|---|
| Generic event (wizard Schedule) | min today / max +2y | after_or_equal:today, +2y | No ✓ |
| Recurring service first_date | **none passed** | after_or_equal:today, +2y | In UI yes → server rejects |
| Fellowship event_date | **none passed** | +2y only | **Yes, by design** |
| Single service occurrence_date | **none passed** | +2y only | **Yes, by design** |
| Discipleship batch/session dates | **none passed** | (not audited) | In UI yes |

The Fellowship and single-occurrence past-allowance is documented in the
code as intentional: "Past dates are allowed so the organizer can log a
gathering after the fact" (StoreFellowshipEventRequest /
StoreServiceOccurrenceRequest docblocks). **RULING (Mark, 2026-10-20):
no past dates anywhere, NO exception.** The retrospective-logging
allowance is removed: Fellowship event_date and single service
occurrence_date get `after_or_equal:today` like everything else (and
their docblocks get corrected), and every scheduling picker passes the
min/max bounds. A gathering that was not created before it happened
cannot be entered afterwards.

Scope note: this rule is for **scheduling** dates only. The birthdate
picker keeps its own opposite bounds (past required, future not allowed,
120y floor) — same component, same enforcement style, bounds fit the
field's purpose.

---

## Appendix D — Problems that will likely occur because this is "only simple CRUD" (2026-10-20)

CRUD thinks in rows. A church runs on people, time, and trust — plain CRUD
has no rules for any of the three. Problems, most likely first:

1. **Duplicate people (already happening).** CRUD creates rows; it doesn't
   recognize persons. The church resolvers match email-first and name-blind
   (ServiceAttendanceController::resolveParticipant,
   FellowshipAttendanceController::resolveParticipant), the staff add
   dialogs have no email field (Appendix B #1), and Member/First-Timer/
   Guest have no single home (Appendix B #5). Every "Add" risks minting a
   second copy of an existing person; summaries then count copies.
   **Status: per-event duplicate rule DECIDED 2026-10-20 — see §2D.**
   (The resolver/form causes above still need their fixes for the rule
   to bite on the staff side.)
2. **Per-event CRUD, globally shared records.** Registration screens feel
   per-event, but the Participant and Member rows they edit are global and
   shared by every event, ministry, and the running side. A naive delete
   or edit from one event's screen corrupts the person's record in all
   the others ("delete this registration" must delete the registration,
   never the person; the spec's delete rule in §2 covers this — a plain
   CRUD scaffold wouldn't know the difference).
3. **No lifecycle or order (CRUD allows anything, anytime).** Nothing in
   CRUD stops: registering someone after the event ended; marking
   attendance for a service that hasn't happened; editing the event after
   people registered for the old details; deleting a registration that is
   already marked Present (deleting history; totals silently rewrite
   themselves). The running side guards these with its lifecycle
   (open/close, finalize); the church side decided monitoring-only CRUD,
   so each of these needs an explicit rule or it stays possible.
4. **Wrong-person attendance.** Identification at the door is a typed
   registration code or a name search. CRUD lists happily contain two
   "Maria Santos" rows (problem 1 + strict composite matching splitting
   one person into two records); the door organizer picks one, and CRUD
   has no notion that it picked wrong — Present lands on the wrong
   person's history, permanently.
5. **Concurrent editors collide silently.** Two organizers on the same
   event at the same time: last save wins. A Present mark overwritten by
   another screen's stale Absent; double-submitting an add creates a
   duplicate row (the attendance tables have (occurrence, participant)
   unique indexes — svc_attend_members_unique, fellowship_attendances_
   unique — but a new church Registration table needs its own
   (event, participant) unique guard or CRUD double-clicks become
   duplicate registrations). CRUD has no optimistic locking, no
   "someone else changed this" warning.
6. **Delete with no authorization and no undo (already in code).** The
   audit found no authorization in any of the 12 church-module
   controllers: any logged-in staff account can destroy occurrences,
   events, and batches. Add CRUD Delete on registrations and the same
   applies — hard delete, no soft-delete, no audit trail, no recovery.
   One mistaken (or disgruntled) click erases a record permanently.
7. **Recurring reality vs one-row-per-event CRUD.** Sunday Service is
   weekly. Pure CRUD makes each week a separate hand-made record (or a
   duplicated occurrence if two organizers both add it). Histories
   fragment across dozens of same-name events, and "this person's
   attendance over time" — the thing a ministry leader actually wants —
   is exactly what per-event CRUD cannot answer (guideline FR-12 defers
   it, which is where it will stay if CRUD is all there is).
8. **The public link turns Create into an open door.** A public CRUD
   create endpoint with no rules collects spam, joke entries, and
   duplicate floods into the official list. This build's mitigations are
   the decided ones (email OTP, composite duplicate guard, reCAPTCHA) —
   but note the CRUD-shaped limits: one link per event means no
   per-person revocation, and a leaked link stays valid until the event
   passes.

None of these need a bigger architecture — they need the handful of
rules this spec already attaches (composite identity, per-event unique
guard, delete-the-registration-not-the-person, attendance statuses as
the only writes in Attendance) plus two still-undecided ones: an
authorization pass on church controllers, and explicit rules for
registering/marking outside the event's day.

---

## Appendix E — Implementation plan: duplicate participant handling in church events (2026-10-20)

Goal: church registration (manual + link, all modules) produces **zero
duplicate participants and zero duplicate registrations per event**, using
the decided composite rule (§2D).

**Key grounding insight (dc34933): the database backstops already exist.**
`registrations` has `unique(event_id, participant_id)`;
`service_attendance_members` / `_first_timers` have
`unique(service_occurrence_id, participant_id)`;
`fellowship_attendances` has `unique(fellowship_event_id, participant_id)`.
Duplicates today are **different participant rows for the same person**, so
the constraints never fire. The fix is identity *resolution*, not new
constraints.

### Step 0 — Dependency

Codex's middle-name + suffix identity expansion lands first:
`Participant::findByComposite()` gains the extended signature (normalized
middle/suffix columns). All steps below are written against the new
signature.

### Step 1 — Fix the two church resolvers (stops the bleeding in existing modules)

- `ServiceAttendanceController::resolveParticipant` (L236) and
  `FellowshipAttendanceController::resolveParticipant` (L188): delete the
  email-first / phone-only lookups; resolve via
  `Participant::findByComposite($email, $phone, $first, $last, …)` and
  create a new Participant **only** when it returns null.
- Keep the `participant_id` shortcut: when the organizer explicitly picks
  an existing person from search, that choice wins.
- Fellowship's resolver currently documents "No email lookup — the add
  dialog does not collect one." The resolver and request learn to accept
  email; the form change is Step 2.

### Step 2 — Staff Add/Edit Participant form (new Registration section)

- Collect the full identity shape: first / middle / last / suffix,
  **email (new — closes Appendix B #1)**, contact number. Email stays
  optional for staff adds, but if both email and phone are empty, warn
  the organizer (don't block): this person will have no identity key and
  cannot be matched in future events (Appendix B #7).
- On submit — add **and** edit — run the per-event duplicate check (the
  composite logic of `assertNoDuplicateRegistration`, adapted to the
  church storage target; on edit, exclude the registration being edited),
  then resolve-or-create the Participant via the composite resolver.
- One list, both channels: the check covers manual rows and link rows
  together, so manual-then-link (or link-then-manual) duplicates are
  caught cross-channel.

### Step 3 — Public link

- Generic church events (which are Events): reuse
  `RegistrationVerificationService` unchanged — the duplicate check
  already runs at `begin()` before any OTP is sent, and finalize resolves
  through the composite resolver. No new checking code.
- FellowshipEvent / ServiceOccurrence targets: the verification service
  is Event-shaped; the bridge/generalization is the known cost item
  (§2B). Until it lands, those modules are protected staff-side by
  Steps 1–2 and gain link-side protection with the bridge. Sequence the
  bridge inside the link build, not as a separate project.
- Email is the verified strong key (no phone verification); phone is a
  secondary entered key only.

### Step 4 — Likely-duplicate review queue (after the core works)

Build the review layer decided in Church MD Amendment C. Within the same
event/occurrence, cluster submissions by email/phone; when the contact
matches and the normalized name is highly similar (including a
token-subset match where the middle name was omitted), store the new
submission as **Held / For Review** instead of a completed registration.
Show the organizer the candidate pair and match reason, with four
actions: confirm duplicate (keep the first/master), confirm separate
person (release), correct and recheck, or remove the held submission.
Held submissions stay out of confirmed counts and Attendance until
released. Weaker possible matches may be listed in the audit without
being held. Copy the staff-side surfacing pattern from the
`has_shared_phone` flag (RegistrationMonitorController +
monitor/show.tsx), but the held state — not a passive badge — is the
required behavior. The system never auto-merges.

### Step 5 — Acceptance tests (church)

1. Staff adds Maria Santos + email to a Sunday occurrence; adds her again
   with the same details → second rejected; exactly 1 registration,
   1 participant.
2. Manual add first, then a link submission with same name + verified
   email → link rejected at begin; no OTP sent.
3. Siblings under one parent email, different names, same event → both
   accepted; 2 participants.
4. Same person registers for next Sunday's occurrence → accepted;
   participant count unchanged (record reused).
5. Jr. and Sr., same contact → both accepted (suffix distinguishes).
6. "Maria Santos" vs "Maria D. Santos," same email, same event → two
   participants; similarity flag raised for the organizer.
7. Editing a registration's email to collide with another registration in
   the same event → edit rejected.
8. Double-submit of a staff add → the DB unique index stops the second
   row; organizer sees the duplicate message, not an error page.
9. Fellowship, phone-only: same name + same phone again → rejected;
   same phone + different name (child on a parent's number) → accepted
   as a different person — the child no longer lands on the parent's
   record (regression test for the old name-blind resolver).
10. Services: a child encoded with the parent's email + the child's own
    name, parent already on the roster → child gets their own
    participant and row (regression test for the email-first bug).

### Out of scope for this plan

- A merge UI for duplicates that already exist in today's data (future;
  when built, the organizer picks the surviving record — never automatic).
- Authorization on church controllers (Appendix D #6 — the next problem).
- Lifecycle/order rules (Appendix D #3).
