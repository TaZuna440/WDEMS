# Church Events Update — WDEMS

**Project:** WDEMS — Church Event Management System  
**Document:** `Church_Events_Update.md`  
**Version:** 1.0 — Scope and interactive-prototype specification  
**Updated:** October 20, 2026  
**Status:** Interactive front-end demonstration / requirements reference

**Implementation status note (2026-10-22):** This document defines the
canonical Church scope and Amendments B–D define the binding common-workflow
requirements. It is not by itself the complete implementation task list:
specialist module requirements and answered decisions are tracked in
`docs/church-events/10-client-decision-log.md`,
`docs/church-events/11-shared-workflow-spec-2026-10-21.md`, and the operational
task register `docs/spec-implementation-plan-2026-10-22.md`. Shared-workflow
category and Youth submodule pages remain “Coming soon” placeholders. This
input-placeholder-only change does not implement module workflows or change
page architecture. Specialist data/workflows and manual acceptance remain
pending. This note does not change the scope or priority of the requirements
below.

## 1. System Overview

WDEMS is a centralized **Church Event Management System** for planning church activities, recording event information, registering participants, checking attendance, and viewing attendance summaries across multiple ministries. The system is intended for church administrators, ministry leaders, and authorized event organizers.

The interactive prototype demonstrates a **single, consistent workflow** for every ministry while maintaining **separate event and participant records** for each activity. Services, Fellowship, and Discipleship are available as top-level activity modules in the current demo; similar activities may also exist within particular ministries (for example, Young Adult Service or GRC Fellowship). These are distinct event contexts, not automatically the same records.

### Main goals

- Keep church events organized by **ministry → activity/category → event**.
- Reduce dependence on paper forms and manual attendance lists.
- Let organizers register participants and record event-specific attendance.
- Give leaders a quick summary of registrations and attendance.
- Use an understandable, repeatable interface across every ministry.

## 2. Scope at a Glance

### In scope for the interactive demonstration

1. A ministry dashboard with clickable module cards and icons.
2. Activity/category cards within each ministry.
3. Viewing event lists filtered by the selected ministry and category.
4. Creating an event with basic details.
5. Opening and editing an existing event's details.
6. Registering participants for a selected event.
7. Marking registered participants **Present** or **Absent** for that event.
8. Viewing event-level totals: **Registered, Present, Absent, and Unmarked**.
9. Keeping the modules' event lists and the events' registrations and attendance logically separate within the demo's temporary state.
10. Sample event and participant data for trying the workflow.

### Not currently implemented in the interactive demonstration

- Permanent database storage, backend services, real user accounts, or role-based access enforcement.
- Login, password management, approval workflows, or auditing.
- Payments, donations, accounting, payroll, inventory, or facility booking.
- SMS/email messaging, automatic reminders, or notifications.
- File uploads, exports, printable reports, dashboards with historical analytics, or integration with external platforms.
- Event or participant deletion, advanced search, bulk import, and formal duplicate detection.
- Full specialized forms for each activity (see Section 7 for suggested future extensions).

**Important:** The demo runs with **temporary in-session front-end state**. Entered information may be lost on refresh, reset, or when the prototype is closed; it is **not** a production data system.

## 3. Ministry and Activity Coverage

The current interactive demo exposes **nine top-level modules**. The categories below match the navigation demonstrated in the prototype.

| Module                    | Icon concept     | Activity categories in the current demo                                                                                                                                                                                                                       | Purpose                                                                                                                              |
| ------------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| **Services**              | Church           | Sunday Service; Special Service                                                                                                                                                                                                                               | Regular and special worship services.                                                                                                |
| **Fellowship**            | People / group   | Church Fellowship; Ministry Fellowship                                                                                                                                                                                                                        | Community-building and fellowship activities.                                                                                        |
| **Discipleship**          | Open book        | Bible Study; Discipleship Class; Leadership Training                                                                                                                                                                                                          | Bible instruction, discipleship, and leadership development.                                                                         |
| **Youth Ministry**        | Tent             | **Youth Camp; Huddle; Campus Ministry; Timothy Class**                                                                                                                                                                                                        | Youth gatherings, camp, outreach, and training. **Youth Service is intentionally excluded** from this ministry's current categories. |
| **Kids Ministry**         | Child            | Kids Service; Vacation Bible School; Outreach · Wawang Pulo; Outreach · Pasolo; Outreach · Balubaran; Outreach · Coloong                                                                                                                                      | Children's services, VBS, and location-based outreach.                                                                               |
| **Young Adult**           | People           | Young Adult Service; Seminar; GRC Care Group; GRC Fellowship                                                                                                                                                                                                  | Young-adult services, seminars, care groups, and fellowship.                                                                         |
| **Worship & Prayer**      | Prayer / heart   | Outdoor Prayer Meeting; Worship Night; Prayer & Fasting                                                                                                                                                                                                       | Prayer-focused gatherings, worship events, and fasting activities.                                                                   |
| **Church Administration** | Clipboard        | Church Planning; Ministry Meeting · Worship; Ministry Meeting · Technical; Ministry Meeting · Multimedia; Ministry Meeting · Social Media; Ministry Meeting · Finance Team; Ministry Meeting · Light; Ministry Meeting · Kids; Ministry Meeting · Young Adult | Planning and ministry-team meetings.                                                                                                 |
| **Special Occasions**     | Calendar / heart | Church Anniversary; Thanksgiving Celebration; Pastor's Appreciation; Dedication; School of Leaders 1; School of Leaders 2; School of Leaders 3                                                                                                                | Church-wide celebrations, dedications, and graduation activities.                                                                    |

### Notes on hierarchy and related concepts

The demo simplifies navigation by listing activities as cards beneath each module. The fuller envisioned system can preserve nested groups without changing the event workflow:

- **Young Adult → Partners → GRC → Care Group / Fellowship.** Earlier designs also included **+ Add Partners** to create partner/group names. The current full-system demo flattens GRC Care Group and GRC Fellowship into selectable Young Adult categories; partner management is **not** implemented there.
- **Kids Ministry → Outreaches → Wawang Pulo / Pasolo / Balubaran / Coloong.** The demo shows each outreach site as its own category card.
- **Special Occasions → School of Leaders Graduation → School of Leaders 1 / 2 / 3.** The demo displays the three levels as separate category cards.
- **Church Administration → Ministry Meeting → specific ministry team.** The demo displays each team meeting as a separate category card.
- **Services**, **Fellowship**, and **Discipleship** are broad top-level modules in the full-system prototype. A ministry-specific service, fellowship, or discipleship event may also be managed in its ministry module; it must belong to a clearly chosen category to avoid accidental duplication.

## 4. Navigation and Shared Event Workflow

All modules use the same interaction pattern:

```text
WDEMS Ministry Dashboard
    |
    +-- Select Ministry (e.g., Youth Ministry)
           |
           +-- Select Activity (e.g., Youth Camp)
                  |
                  +-- Category Event List
                         |
                         +-- + Add Event -> Event Details Form -> Save
                         |
                         +-- Open Existing Event
                                |
                                +-- Event Details (view/edit)
                                +-- Registration (add/list participants)
                                +-- Attendance (Present/Absent)
                                +-- Attendance Summary (totals/list)
```

Users can also select **+ Add Event** directly from a ministry page, then select its activity category in the form.

### 4.1 Ministry dashboard

- Displays icon cards for all nine modules.
- Shows high-level demo counts: **Modules, Events, and Registrations**.
- Includes **System Scope**, a page describing covered modules, included functionality, and limitations.
- Selecting a ministry opens only that ministry's activity categories.

### 4.2 Ministry page

- Shows the ministry's category cards and **+ Add Event**.
- Selecting a category opens the event list for **that ministry and category**.

### 4.3 Category page

- Shows category event counts and registration counts.
- Provides **+ Add Event**, preselecting the category.
- Lists existing events with name, date, and venue.
- Opening an event leads to its own management menu.

### 4.4 Event management page

Each event has four clickable options:

1. **Event Details** — Review and update event information.
2. **Registration** — Add participants and see that event's registered list.
3. **Attendance Record** — Mark each registered participant Present or Absent.
4. **Attendance Summary** — View totals and the per-participant status list.

The earlier prototypes referred to this as **Event Details → Registration → Attendance → Attendance Summary**. The full-system demo also allows navigation among these four sections after the event is created; the user does not have to repeat the entire sequence to review a record.

## 5. Event Details and Event Creation

### Fields supported by the demo

| Field                | Required when creating an event? | Description                                             |
| -------------------- | -------------------------------- | ------------------------------------------------------- |
| Ministry             | Determined by selected module    | The event's top-level owner.                            |
| Activity Category    | Yes                              | One category belonging to that ministry.                |
| Event Name           | Yes                              | Descriptive name of the event.                          |
| Event Date           | Yes                              | The scheduled date.                                     |
| Event Time           | No                               | Free-text time input in the demo.                       |
| Venue / Location     | Yes                              | Where the event will take place.                        |
| Leader / Coordinator | No                               | Person or team coordinating the activity.               |
| Description / Notes  | No                               | Event theme, speaker, purpose, logistics, or reminders. |

**Create-event flow:** Enter information → select **Save Event** → event appears in the selected category → open its management options.

**Edit-event flow:** Open event → select **Event Details** → modify fields → **Save Details**. These edits update demo state only.

**Current validation:** The demo requires category, event name, event date, and venue when creating an event. This is a prototype-level check, not a complete production validation and authorization system.

## 6. Registration, Attendance, and Summaries

### 6.1 Registration

An organizer opens **Registration** for a specific event and can enter:

- **Participant Name** — required.
- **Age** — optional.
- **Contact Number** — optional.
- **Participant Type** — selectable as **Member**, **First-Timer**, **Guest**, **Graduate**, or **Ninong / Ninang**.
- **Parent / Guardian** — shown conditionally in the demo for **Kids Ministry** events and **Youth Camp**.

After **Register Participant**, the person is added to the selected event's participant list. The current demo does **not** offer removal or editing of a registered participant.

### 6.2 Attendance record

- The attendance list is built from the registered participants of the active event.
- Each participant can be marked **Present** or **Absent**.
- A participant not yet marked is **Unmarked**.
- Attendance selections update the current demo state. **Save & View Summary** opens the summary; it does not send data to a backend.
- Attendance belongs to an **event**, not globally to the participant's name. Two events do not share attendance statuses by default.

### 6.3 Attendance summary

For a selected event, the demo calculates:

| Metric         | Meaning                                                         |
| -------------- | --------------------------------------------------------------- |
| **Registered** | Total participant registrations linked to the event.            |
| **Present**    | Number of linked participants marked Present.                   |
| **Absent**     | Number of linked participants marked Absent.                    |
| **Unmarked**   | Number of linked participants with no Present/Absent selection. |

The summary also lists individual participants with their statuses and offers **Edit Attendance**.

**Counting rule:** `Registered = Present + Absent + Unmarked` for each event in the current demo.

## 7. Activity-Specific Information: Full-System Requirements vs. Demo

The common fields above work for every category. Earlier ministry prototypes also discussed optional information tailored to certain activities. These are **recommended extended requirements**, **not implemented as separate working fields in the current nine-module full-system demo** unless noted.

| Area                                 | Optional fields or specialized behavior for a later build                                                                                                                                               |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Youth Camp                           | Guardian/parent consent, emergency contact, camp details, dietary and medical notes. The current demo shows only a basic guardian field during Youth Camp registration.                                 |
| Huddle / Timothy Class               | Small-group leader, lesson/topic, class level, or meeting series.                                                                                                                                       |
| Campus Ministry                      | Campus/school name, outreach contact, campus coordinator.                                                                                                                                               |
| Kids Service                         | Child's name, parent/guardian and contact; date-by-date attendance for recurring weekly services and child attendance history. The demo currently treats every service date as a separate event record. |
| Vacation Bible School                | Program day/session, age grouping, assigned teacher.                                                                                                                                                    |
| Kids Outreaches                      | Outreach site, local coordinator, specific venue, and attendance per activity.                                                                                                                          |
| Young Adult Service / Seminar        | Speaker, lesson/theme, facilitator, partner/group details.                                                                                                                                              |
| Young Adult Partners / GRC           | Editable partner list, care-group leader, nested partner/group navigation. Not present as a separate partner-creation screen in the current full-system demo.                                           |
| Outdoor Prayer Meeting               | Outdoor location/meeting point.                                                                                                                                                                         |
| Worship Night                        | Theme, worship leader, speaker.                                                                                                                                                                         |
| Prayer & Fasting                     | Fasting start/end dates and prayer focus.                                                                                                                                                               |
| Church Planning / Ministry Meeting   | Meeting agenda, ministry/team, chairperson and decisions/notes.                                                                                                                                         |
| Dedication                           | Child's name, mother and father, officiating pastor, Ninong/Ninang.                                                                                                                                     |
| School of Leaders Graduation         | Graduation level, graduate list, guests.                                                                                                                                                                |
| Special Occasions                    | Special guest or graduate distinctions, relevant officiants and celebration details.                                                                                                                    |
| Services / Fellowship / Discipleship | Service leader, fellowship group, speaker/facilitator, session topic or training level.                                                                                                                 |

All extended fields should be **optional or context-sensitive** so that adding an ordinary event stays simple.

## 8. Proposed Data Structure

A production implementation can use the following logical records. This is a **recommended model**, not a claim that the demo has a database.

### Ministry

`ministry_id`, `name`, `description`, `icon`, `is_active`

### Category

`category_id`, `ministry_id`, `name`, `parent_category_id` _(optional for nested groups)_, `is_active`

### Event

`event_id`, `ministry_id`, `category_id`, `event_name`, `event_date`, `start_time`, `end_time`, `venue`, `coordinator`, `description`, `created_by`, `created_at`, `updated_at`

### Participant

`participant_id`, `full_name`, `age_or_year_level`, `contact_number`, `participant_type`, `guardian_name`, `guardian_contact` _(if needed)_

### Registration

`registration_id`, `event_id`, `participant_id`, `registered_at`, `registration_notes`

### Attendance

`attendance_id`, `event_id`, `registration_id`, `status` _(Present/Absent/Unmarked, with Unmarked also representable as no record)_, `recorded_at`, `recorded_by`

### Modeling principles

- **One ministry contains many categories.**
- **One category contains many events.**
- **One event contains many registrations.**
- **Each registration has at most one attendance status per event occurrence.**
- **A person may register for multiple events** without sharing attendance status across events.
- Store service occurrences by actual **event date** or introduce an `event_series`/`event_occurrence` model for recurring services; attendance must always be tied to the specific occurrence.
- Avoid using a participant's name as a unique key; assign stable identifiers.
- Treat guardian, minor, and other sensitive personal data according to applicable privacy and consent requirements, with restricted authorized access in any production build.

## 9. Interface and Usability Guidance

The interactive prototype uses a **simple icon-led card layout** with a muted forest-green theme.

- **Homepage:** All ministry modules are visible as cards, rather than one long form.
- **Progressive navigation:** Ministry → category → event → one management section.
- **Back navigation:** Users can return from an event to its category, then to the ministry dashboard.
- **Simple inputs:** Reuse the same date, event name, venue, coordinator, and registration fields.
- **Context-aware forms:** Show specialized questions only if relevant (for example, guardian for children or camp participants).
- **Clear counters:** Show counts for registered and attendance statuses.
- **Separate records:** A Youth Camp's participants must not appear under a Kids Service or another Youth Camp unless separately registered.
- **Mobile-friendly interaction:** Cards, buttons, and concise labels should remain usable on narrower screens.

## 10. Interactive Demo Data and Behavior

The demonstrated WDEMS prototype starts with sample records to make navigation testable:

| Module         | Category       | Example event                     |
| -------------- | -------------- | --------------------------------- |
| Services       | Sunday Service | Sunday Worship Gathering (Sample) |
| Youth Ministry | Youth Camp     | Youth Camp (Sample)               |
| Kids Ministry  | Kids Service   | Kids Service (Sample)             |
| Young Adult    | GRC Fellowship | GRC Fellowship (Sample)           |

The demo also contains a few fictional registered participants and attendance marks in sample events. They are **illustrative**, not real church records.

### What can be tested in the demo

1. Click **Youth Ministry** and confirm that the only Youth categories are **Youth Camp, Huddle, Campus Ministry, and Timothy Class**.
2. Open **Youth Camp** and select the sample event.
3. Open **Event Details** and review or edit the sample information.
4. Open **Registration**, enter a new participant, and see the participant added to that event only.
5. Open **Attendance**, mark the participant **Present** or **Absent**.
6. Open **Attendance Summary** and confirm totals are updated.
7. Return to the ministry dashboard and repeat this workflow in **Kids Ministry**, **Young Adult**, or any other module.
8. Create a new event via **+ Add Event** and verify that it appears only under its chosen ministry/category.

### Prototype limitations to remember

- Demo form controls and state are intended to show interaction, **not persistent storage**.
- No server-side validation, authentication, data security controls, or multi-user synchronization are implemented.
- The demo's event dates/times and participants are examples. Do not treat them as verified church schedules or real people.
- This demo is a **scope and UI reference**, not a deployed church management service.

## 11. Functional Requirements for Implementation

| ID    | Requirement                                                                           | Priority                                 |
| ----- | ------------------------------------------------------------------------------------- | ---------------------------------------- |
| FR-01 | Display all nine ministry/activity modules on the main dashboard.                     | Must                                     |
| FR-02 | Display categories filtered to the selected ministry.                                 | Must                                     |
| FR-03 | Provide **+ Add Event** within a ministry and category.                               | Must                                     |
| FR-04 | Create and edit events with the core Event Details fields.                            | Must                                     |
| FR-05 | Show events only in their assigned ministry/category.                                 | Must                                     |
| FR-06 | Register participants for a specific event.                                           | Must                                     |
| FR-07 | Display the registration list for the currently selected event.                       | Must                                     |
| FR-08 | Record attendance as Present or Absent for each registered participant.               | Must                                     |
| FR-09 | Show Registered, Present, Absent, and Unmarked totals and participant statuses.       | Must                                     |
| FR-10 | Retain event, registration, and attendance records in persistent storage.             | Required for production; **not in demo** |
| FR-11 | Authenticate organizers and limit access by ministry/role.                            | Required for production; **not in demo** |
| FR-12 | Handle recurring services with attendance by date and participant history.            | Later enhancement                        |
| FR-13 | Support optional nested categories, partner management, and activity-specific fields. | Later enhancement                        |
| FR-14 | Provide printable/exportable reports and historical statistics.                       | Later enhancement                        |
| FR-15 | Provide validation, duplicate handling, data protection, and audit logging.           | Required for production; **not in demo** |

## 12. Acceptance Scenarios

- **Ministry isolation:** Selecting **Kids Ministry → Kids Service** must never list **Youth Ministry → Youth Camp** events.
- **Category isolation:** Events filed under **Huddle** must not appear in **Timothy Class**.
- **Event registration isolation:** Adding a participant to Event A must not automatically register that participant in Event B.
- **Attendance accuracy:** For an event with three registrants, one Present, one Absent, and one unmarked, the summary must show `Registered = 3`, `Present = 1`, `Absent = 1`, `Unmarked = 1`.
- **Youth category requirement:** Youth Ministry must show **Youth Camp, Huddle, Campus Ministry, Timothy Class**, with **no Youth Service** category.
- **Common navigation:** Each category supports event creation, and each opened event exposes **Event Details, Registration, Attendance Record, and Attendance Summary**.
- **Scope transparency:** The prototype clearly indicates that the current implementation is a temporary demo without permanent storage.

## 13. Final Scope Statement

**WDEMS covers church event management across Services, Fellowship, Discipleship, Youth Ministry, Kids Ministry, Young Adult, Worship & Prayer, Church Administration, and Special Occasions.** Its core purpose is to centralize event information, simplify participant registration, and record attendance with event-by-event summaries.

The interactive demonstration implements this idea using **nine module cards, activity cards, a shared Add Event flow, Event Details, Registration, Attendance, and Attendance Summary**. Additional advanced features and production infrastructure are future implementation work and are deliberately separated from what the prototype currently demonstrates.

---

## Amendment A — 2026-10-20 (Mark's decisions; amends §6.1 and §6.2)

Appended, not edited into v1.0 above. Full spec:
`~/workspace/wdems-church-events-update-spec.md`.

1. **Registration has two entry channels into one list per event.**
   (a) The organizer's existing **Add Participant** — staff manual add, kept.
   (b) A **public registration link scoped to that specific church event**,
   carrying the same fields/details as that event's registration form.
   Example: organizer adds a friend manually for a Sunday Service; the
   friend's siblings self-register through the link instead of the organizer
   encoding them all; the organizer monitors link registrations in the same
   list. The link form requires email, **verified like Community Run**
   (email OTP, decided 2026-10-20).
2. **Every completed registration gets a registration code** (same format as
   Community Run, e.g. `KM4X-P2N7`), whether added manually or via the link.
3. **Registrations flow into Attendance automatically** as Unmarked.
   Attendance adds nobody — no re-encoding there.
4. **Attendance is monitoring only**, visually the same as the Community Run
   attendance page: registration-code input + **Look up**, search by name /
   email / phone, filter chips **All / Unmarked / Present / Absent**.
   Removed for church: **Scan with camera** (identification is by typed
   code, no barcode/QR), **Add Walk-In** on the attendance page (walk-ins
   are added in Registration), and the **Live / Deferred** encoding modes
   (and the bulk walk-in batch behind them). Statuses stay Present /
   Absent / Unmarked — no Late, no Excused.

### Amendment A.1 — 2026-10-20 (addendum to Amendment A)

5. **Registration owns Add / Edit / Delete.** The organizer can add a
   participant, edit a registration's details, and delete a registration in
   the Registration section (this overrides §6.1's demo note that removal
   and editing are not offered). Because Registration can do all of that,
   Attendance needs no adding machinery at all.
6. **No Check-in / Walk-in mode tabs in church Attendance.** The mode toggle
   at the top of the Community Run attendance page (Check-in / Walk-in, and
   the "Mark participants one at a time." caption) must not appear. Church
   Attendance is a single monitoring list — code Look up, search, All /
   Unmarked / Present / Absent filters, mark inline — with no modes to
   switch between.

### Amendment A.2 — 2026-10-20 (participant types)

7. **Participant types are Member, First-Timer, and Guest only.** This
   replaces the five types listed in §3/§6.1 — **Graduate** and
   **Ninong/Ninang** are removed as participant types.

### Amendment A.3 — 2026-10-20 (duplicate participant handling — implementation)

8. **Duplicate participants are handled by the composite identity rule,
   implemented as Appendix E of the update spec.** In one church event,
   the same normalized name + same email/phone may register only once —
   the first record is kept, the second is rejected (on the public link,
   before the verification code is sent; the message reveals nothing
   about the existing record). The same email under different names
   (family members) is allowed. Across events and service occurrences,
   the existing participant record is reused instead of creating a new
   person. Similar-but-not-exact records are flagged for the organizer,
   never automatically blocked or merged. The church resolvers
   (Services, Fellowship) are switched to the same composite matching
   used by Community Run, and the staff registration form gains the email
   field it currently lacks.

---

## Amendment B — Consolidated conclusions (2026-10-21)

This amendment **secures and concludes all planning and proposals** made
for the Church Events update (sessions of 2026-10-20, plus the state of
Codex's local running-events work reported 2026-10-21). After this
amendment, this document is the single record for the church build:
**where any other document conflicts with this MD on church events, this
MD prevails.** Detail and analysis live in the companion spec
(`~/workspace/wdems-church-events-update-spec.md`); conclusions live here.

### B.1 Official definition

"WDEMS is also a centralized church event management system designed to
help church administrators and ministry leaders organize activities
across different church ministries. It allows users to create events,
register participants, maintain attendance records, and generate
attendance summaries through a consistent and organized workflow."
One system, two sides (Community Run + church), kind-isolated: church
events never enter the running-events lifecycle.

### B.2 Verification — email only

- The church public link verifies **email by OTP** like Community Run
  (challenge, 100s resend cooldown, 24h challenge TTL, 7-day email
  device trust, server-side reCAPTCHA).
- **There is no phone verification anywhere in WDEMS** (Mark, 2026-10-20).
  The earlier channel-choice design (email _or_ phone) is superseded:
  no channel-choice screen, no SMS code, no Firebase. A contact number
  is still collected and still serves as a matching key — it is simply
  never verified. Firebase's only role was phone verification; it is
  dead code for new forms and is removed once published forms offering
  it finish their lifecycle.

### B.3 Date rule — no past dates, no exception

- When scheduling an event, **past dates cannot be picked — anywhere,
  with no exception** (Mark's ruling, 2026-10-20). This applies to every
  scheduling date: generic events, Fellowship events, single service
  occurrences, recurring-service first dates, Discipleship dates.
- Mechanics: the shared date picker gets `min = today`, `max = today
    - 2 years`, and the server enforces the same bounds
(`after_or_equal:today` + the 2-year cap) — picker and server must
      agree. The previous deliberate allowance of past dates for Fellowship
      events and single service occurrences ("log a gathering after the
      fact") is **removed**, including its docblocks: a gathering not created
      before it happened cannot be entered afterwards.
- The birthdate picker is out of scope (its bounds are opposite by
  purpose: past required, future disallowed).

### B.4 Participant identity and inputs — conclusions from the audit

- **Identity is one and shared:** the `participants` table owns identity
  for running and church. The rule is the composite: normalized full
  name (first + middle + last + suffix) AND (email OR normalized phone).
  Participant type and guardian live on the **per-event registration**,
  not on the person; church membership data stays on the Member record.
- **Participant inputs** (fields landing on the person) are exactly:
  first name, last name, middle name, suffix, email, contact number,
  age, birthday, address. Every church form — staff Add/Edit and the
  public link — collects this same identity shape for its event,
  including the **email field the Services/Fellowship staff dialogs
  currently lack** and the middle name/suffix they currently omit.
- The audit's seven input problems (companion spec, Appendix B) are
  concluded as build requirements: one shared input set; age is derived
  from birthday on public forms (no contradictory double entry);
  address on public forms is Barangay & City; participant type and
  guardian gain real storage on the registration (guardian has no
  column today); a staff-added person with no email and no phone has
  no identity key — the form warns the organizer instead of silently
  creating an unmatchable person.

### B.5 Duplicate handling — implementation conclusions (extends A.3)

Build order (full plan: companion spec, Appendix E):

0. The middle-name + suffix identity expansion lands first; all church
   work uses the final composite signature.
1. `ServiceAttendanceController::resolveParticipant` and
   `FellowshipAttendanceController::resolveParticipant` switch from
   email-first / phone-only matching to the composite resolver;
   a new Participant is created only when there is genuinely no match.
   An organizer's explicit pick of an existing person still wins.
2. Staff Add/Edit runs the per-event duplicate check on submit (edit
   excludes itself), across the event's whole list — manual and link
   rows are checked against each other.
3. The public link runs the same check at verification begin, before
   any OTP is sent, with the non-revealing rejection message.
4. The similarity warning is the soft layer (similar name, different
   record → organizer is flagged; never an automatic block or merge).
5. The database backstops already exist (`unique(event, participant)`
   on registrations and the Services/Fellowship attendance tables) —
   duplicates today are duplicate _people_, so the fix is resolution,
   not new constraints. Any new church registration storage keeps an
   (event, participant) unique guard.
6. Acceptance: the 10 church test cases in companion spec Appendix E
   Step 5 (cross-channel block, siblings on one email, Jr./Sr.,
   next-occurrence reuse, edit collision, double-submit, and the two
   resolver regression tests).

### B.6 Alignment with Codex's local running-events work (2026-10-21)

Codex's local tree is ahead of the last pushed commit (923 tests
passing, 3 skipped, changes uncommitted at report time) and includes:
email-only flow with live reCAPTCHA keys configured, staff-attested
paper registration, name-only Quick walk-ins, camera scanning in
running Check-in, a possible-duplicates audit, and its own church doc
(`docs/church-events/11-shared-workflow-spec-2026-10-21.md`).
Conclusions for the church build:

- **Reconcile first.** Codex's church doc is an input, not a rival:
  where it and this MD disagree, this MD (Mark's decisions) prevails,
  and Codex should list the disagreements it finds before building.
- **Provisional walk-ins stay isolated.** Name-only Quick walk-ins are
  flagged, event-local provisional participants. Church matching,
  resolvers, and participant pickers must never select, match, or merge
  a provisional record.
- **Staff attestation is the pattern for church manual adds.** As with
  paper-form encoding (Mark's ruling in Codex's work): staff entry
  creates the registration immediately without participant OTP, and the
  system records **who encoded it and when**.
- **Do not copy running-only features into church.** Camera scanning,
  Check-in/Walk-in tabs, Live/Deferred modes, Late/Excused statuses
  exist (or are being added) on the running side and are all excluded
  from church by Amendments A/A.1 and B.3 — their presence in fresh
  code is the most likely copying accident.
- **Similarity-warning placement — OPEN, with a recommendation.**
  Codex placed the public warning after contact verification so
  anonymous visitors cannot probe who is registered; staff get
  immediate lookup while encoding. Recommendation on record: keep
  Codex's placement (it matches this MD's non-revealing principle).
  Mark's confirmation is required before the church link copies either
  behavior.

### B.7 The CRUD-risk conclusions (companion spec, Appendix D)

Eight problems were identified for a plain-CRUD church design. Their
dispositions are concluded here: (1) duplicate people — solved by
B.4/B.5; (2) per-event screens over globally shared records — delete
and edit target the **registration, never the person**; (3) no
lifecycle/order rules — **deferred** (registering or marking outside
the event's day remains possible until Mark rules on it); (4)
wrong-person attendance — mitigated by B.5 and by code-based lookup;
(5) concurrent editors — the (event, participant) unique guard is the
backstop (B.5.5); last-save-wins on marks is accepted for v1;
(6) delete with no authorization and no undo — **deferred** (an
authorization pass on the church controllers is a known, separate
task); (7) recurring services vs per-event records — accepted for v1
(per-occurrence records; cross-time history remains a later
enhancement, guideline FR-12); (8) the public link as an open door —
mitigated by OTP + duplicate guard + reCAPTCHA; residual limit
accepted: one link per event, no per-person revocation.

### B.8 Pre-build checks (before the church build starts)

1. Codex's uncommitted local work is committed, so the build starts
   from a known state; the test baseline is **923 passed / 3 skipped**
   and the suite must stay green.
2. All file/line references in the companion spec are re-verified
   against the local tree (the assistant's clone is behind it), and
   Appendix E Step 0/Step 1 are checked for work Codex already did.
3. The live reCAPTCHA flow is tested end-to-end once (keys are
   configured for localhost; the production hostname must be
   authorized in Google's settings before launch).
4. The possible-duplicates audit is run against existing church data
   so the scale of pre-existing duplicate people is known **before**
   any cleanup is decided. A merge screen for existing duplicates is
   out of scope for this build; when built, the organizer picks the
   surviving record — merging is never automatic.

### B.9 Still open (explicitly NOT concluded)

- Prototype vs client-confirmed docs conflict ruling (where the two
  disagree beyond this MD — e.g., Youth Camp payments/progress
  features in the client docs). This MD governs what it covers; the
  rest awaits Mark's ruling.
- Church link v1 scope: Mark's example points to Services (Sunday
  Service) first; whether Fellowship events ship in the same phase is
  his call. Discipleship self-enrollment is a separate question.
- Similarity-warning placement (B.6) — awaiting Mark's confirmation.
- Authorization pass and outside-the-event-day rules (B.7 items 6
  and 3) — deferred by design, not forgotten.

---

## Amendment C — Likely duplicates are held for organizer review (2026-10-21)

This amendment refines Amendments A.3/B.5 after a live example:
**“EJ Rubi Pomasin”** and **“EJ Pomasin”** were both accepted under the
same email minutes apart. They are the same person, but the strict exact
rule treated them as different because one entry included the middle
name and the other omitted it. Participant input cannot be perfectly
controlled, so exact-match prevention alone is not enough.

### The three outcomes

1. **Exact duplicate — block.** Same normalized name + same email/phone
   in the same church event or service occurrence: the second completed
   registration is rejected and the first record is kept.
2. **Likely duplicate — HOLD for organizer review.** Same email or phone
   **and** a highly similar name — including a token-subset match where
   one entry omits the middle name, or another minor name variation,
   with birthday as a corroborating signal when available — is a likely
   duplicate. It is neither rejected outright nor accepted as a clean,
   completed registration.
3. **Possible/shared-contact registration — do not treat as a duplicate
   on contact alone.** The same email or phone with a clearly different
   name may be a legitimate family or proxy registration. It may be
   grouped or flagged as shared-contact, but shared contact alone does
   not make it a likely duplicate.

### What “held” means

- A held submission is stored separately as **Held / For Review**, linked
  to the candidate existing registration, with the match reason shown to
  the organizer.
- It is **not yet a completed registration**: it does not enter the
  confirmed registration count, does not flow into Attendance as
  Unmarked, and does not create an active attendance/kit entitlement
  until the organizer releases it.
- Public-link detection happens **after email verification**, so an
  anonymous visitor cannot use the review response to probe whether
  someone else is registered. The submitter receives only a generic
  message that the registration is being reviewed by the organizer; no
  existing participant details are revealed.
- Staff encoding shows the candidate match to the organizer, who may
  resolve it immediately or leave it in the review queue.

### Organizer resolution

The organizer chooses one action — the system never auto-merges people:

- **Confirm duplicate:** keep the first/existing (master) registration;
  close the held submission as a duplicate, preserving an audit record.
- **Confirm separate person:** release the held submission as a normal
  completed registration for its own participant.
- **Correct the details:** edit the name/contact, rerun the exact and
  likely-duplicate checks, then release it if it passes.
- **Remove held submission:** discard it when it was entered in error,
  while retaining the organizer/action audit trail.

This policy applies to Running Events and Church Events because they
share the same Participant identity. Similar records that do not meet
the likely-duplicate threshold may still appear in the
possible-duplicates audit for human review; they are not automatically
blocked, held as duplicates, or merged.

## Amendment D — Finalized feature definition + participant separation (2026-10-21)

Approved by Mark on 2026-10-21, after a scenario check (one person joining
both a Community Run and church events) and confirmation of the duplicate
outcomes. This amendment finalizes the Church Events feature definition
and corrects one earlier conclusion.

### D.1 Participant separation (supersedes the shared-identity part of B.4)

Church Events participants are **separated** from Running Events
participants. The two sides have different workflows, so there are two
participant pools — one per side:

- Identity matching, duplicate detection, monitoring, and history are
  scoped **per side**. Within a side, the composite identity rule applies
  (normalized full name + email or phone), and duplicates are judged per
  event (per occurrence for Services).
- The same person — same name, email, and phone — may exist once as a
  running participant and once as a church participant. That is **by
  design, not a duplicate**: the sides never resolve-or-create into each
  other, never match across, and never merge across.
- This supersedes B.4's "same shared composite identity across running
  and church" and Amendment C's closing rationale ("because they share
  the same Participant identity"). Amendment C's hold policy itself
  stands; it now applies **within each side separately**.
- Why duplicates matter under this model: each side monitors its own
  people's history. A duplicate record inside one side splits one
  person's history in two and corrupts the counts (see D.3 item 6).
- Panel line, replacing the old one: **"Duplicate is judged per event;
  identity is shared within each side."**

Scenario (checked and approved): Maria Santos registers for Community
Run 1 and Community Run 2 — one running record, history "attended 2
Community Runs"; a second registration for Run 2 is blocked. The same
Maria registers for a Sunday Service and a Church Fellowship — one
church record (created independently of her running record), history
"attended 2 church events: Services, Fellowship"; a second registration
for the same Sunday occurrence is blocked.

### D.2 Duplicate outcomes (per side; restates Amendments A.3 and C)

1. **Exact duplicate → block.** Same normalized full name + same
   email/phone in the same event: rejected, first record kept.
2. **Same contact + name with additions or variation → warn + hold for
   review.** An added or omitted middle name, an added suffix, or a
   minor variation (the EJ Rubi Pomasin / EJ Pomasin case) is a likely
   duplicate: held per Amendment C — not counted, not in Attendance —
   until the organizer confirms a duplicate, releases it as a separate
   person, corrects it, or removes it.
3. **Same contact + clearly different name → allow.** The family/proxy
   case; flagged at most, never blocked on contact alone.
   Nothing is ever auto-merged, and review only ever compares records
   within the same side.

### D.3 Final feature list — Church Events

1. **Coverage:** the nine modules of §3 (Services; Fellowship;
   Discipleship; Youth Ministry without Youth Service; Kids Ministry
   with the four outreach sites; Young Adult; Worship & Prayer; Church
   Administration; Special Occasions).
2. **Shared workflow per event:** select ministry → select category →
   Event Details → Registration → Attendance → Summary. Records are
   per event.
3. **Event management:** create/edit events with basic details; the
   no-past-dates rule (B.3) applies to every scheduling date, picker
   and server.
4. **Registration (Amendment A/A.1):** one list per event fed by staff
   Add Participant (no OTP; encoder + time recorded) and a per-event
   public link (same fields; email required and OTP-verified with
   reCAPTCHA; no phone verification). Fields per B.4, with
   participant_type (Member / First-Timer / Guest, Amendment A.2) and
   guardian stored per registration. Every completed registration gets
   a registration code (lookup, not scanning). Full Add / Edit / Delete;
   edit re-runs the duplicate check; delete removes the attendance row
   and adjusts counts; deleting removes the registration, never the
   person.
5. **Identity:** per-side composite identity (D.1). Within the church
   side, a returning participant is recognized and reused across church
   events; participant_type and guardian remain per registration.
6. **Participant history (NEW — in scope):** the church side monitors
   each participant's history — how many times he/she attended and
   what types of church events he/she joined. (This supersedes the
   guideline's FR-12 deferral of cross-time history for the church
   side. The running side likewise reports how many Community Runs a
   participant attended.)
7. **Attendance (Amendment A/A.1):** monitoring only — registrations
   flow in as Unmarked; code input + Look up; name/email/phone search;
   filter chips All / Unmarked / Present / Absent with counts; statuses
   Present / Absent / Unmarked only. No camera scanning, no walk-in
   mode, no Live/Deferred modes, no Check-in/Walk-in tabs.
8. **Summary:** Registered = Present + Absent + Unmarked, per event,
   with the participant list and statuses and Edit Attendance.
9. **Module specifics retained:** Services are recurring (service →
   occurrences); Discipleship keeps programs → batches → sessions →
   enrollments; Fellowship keeps its groups.

**Out of scope:** payments, donations and church finance; payroll;
inventory; facility booking; SMS, notifications and automatic
reminders; phone verification; camera scanning on the church side;
automatic merging; participant accounts.

### D.4 Build consequences

- Participant matching must be **side-scoped** in the church build
  (a participant kind/scope). Note: the code pushed at 5755c75 matches
  across the whole participants table (church resolvers use the global
  findByComposite); the scope must be added when the church build lands.
- The church participant-history views (D.3 item 6) are build items.

### D.5 Still open (not part of the finalized definition)

- The birthday clause in Amendment C (keep / strike) — pending ruling.
- Public-link v1 scope (Services first, or Fellowship in the same phase).
- Prototype-vs-client-docs conflict ruling (§§1–13 vs client documents).
- Authorization and outside-the-event-day rules — deferred (B.7).
- Similarity-warning placement on the public form (recommendation:
  after verification, as part of the hold flow).
- CSV / print export for the church summary.

## Amendment E — 2026-10-22

Church Events — Definition (Amendment E to the Church Events Update guideline)

_Drafted 2026-10-22 at Mark's request. This states what Church Events must contain, in one place, consolidating Amendment A/A.1 (registration & attendance shape), Amendment B (identity), Amendment C/D (hold policy, per-side pools, participant history), the 2026-10-22 Option B ruling (verified-email-only identity at public finalization), and the 2026-10-22 theme ruling (§9). Where this definition and any older document conflict, the Church Events Update MD still prevails for workflow detail; §9 of this definition supersedes the dashboard spec's color split._

1. Scope — the nine modules Church Events covers:

**Services, Fellowship, Discipleship, Youth Ministry, Kids Ministry, Young Adult, Worship & Prayer, Church Administration, and Special Occasions.**

Module specifics retained: Services are recurring: service → occurrences. Each occurrence is its own event for registration and duplicate purposes — registering for this Sunday is separate from next Sunday. Fellowship keeps its groups. Discipleship keeps programs → batches → sessions → enrollments. Category and Youth submodule pages (Kids Ministry, Young Adult, Worship & Prayer, Special Occasions, Church Administration; Youth Camp, Huddle, Campus Ministry, Timothy Class) remain standardized placeholders until the client rules on their vertical slices.

2. Workflow per event

Every church event follows one shared workflow: **select ministry → select category → Event Details → Registration → Attendance → Summary.** Records are per event. Event create/edit carries basic details, and the no-past-dates rule applies to every scheduling date, picker and server — no exceptions.

3. Registration must contain

One list per event, fed by exactly two entry channels:

- Staff Add Participant — manual add by the organizer/encoder, no OTP; the encoder and time are recorded (staff attestation).
- A per-event public registration link — same fields as the event's registration form; email is required and verified by email OTP with reCAPTCHA, exactly like Community Run. There is **no phone verification** anywhere in Church Events.
- A publication switch per event opens/closes the public link.

Fields: first / middle (optional) / last name and suffix; email; contact number; birthday (age is derived); Barangay & City address; **participant_type — Member, First-Timer, or Guest only — and guardian, stored per registration, not on the person**; emergency contact as decided for the form.

Every completed registration gets a registration code (same format as Community Run, e.g. KM4X-P2N7), whether staff-added or via the link. Codes are for lookup, not scanning.

Full Add / Edit / Delete on the Registration list. Add and edit re-run the per-event duplicate check (edit excludes itself). Delete removes the registration and its attendance row and adjusts counts — deleting removes the registration, never the person.

4. Identity and duplicate protection

Church participants live in the church pool, fully separate from Running Events participants (D-10). The same person may exist once in each pool; that is by design, never a duplicate. Panel line: "Duplicate is judged per event; identity is shared within each side."

A participant is the composite identity: normalized full name (first + middle + last + suffix) AND (email OR normalized phone), matched within the same event (per occurrence for Services).

- Exact duplicate → blocked. The first/existing record is kept; the second submission is rejected.
- Likely duplicate → held for organizer review (same email/phone + highly similar name, including an added or omitted middle name): stored as Held / For Review — excluded from confirmed counts, no attendance flow, no kit entitlement — until the organizer confirms a duplicate (terminal Duplicate status, never Cancelled), confirms a separate person (release), corrects and rechecks, or removes it. Every resolution is audit-logged. The system never auto-merges.
- Shared contact + clearly different name → allowed (the family case: several people registering under one email or phone).

At public finalization, the verified email is the identity contact. An unverified submitted phone is a stored snapshot on the registration only — never used for identity (Option B, ruled 2026-10-22; the church phone-override in finalize() was removed in commit 57797a8). Staff flows keep the full composite rule.

5. Attendance must contain

Monitoring only. Registrations flow into Attendance automatically as Unmarked; Attendance adds nobody — no walk-ins, no re-encoding.

Code input + Look up; search by name / email / phone; filter chips All / Unmarked / Present / Absent with counts.

Statuses are Present / Absent / Unmarked only — no Excused, no camera scanning, no Live/Deferred modes, no Check-in/Walk-in tabs.

6. Summary must contain

Registered = Present + Absent + Unmarked, per event, with the participant list and statuses, and Edit Attendance.

7. Participant history must contain

The church side monitors each church participant's history: **how many times he/she attended and what types of church events he/she joined** (in scope per D.3 item 6; supersedes the guideline's earlier FR-12 deferral). Surfaced on the participant history page and the dashboard's church-side history panel.

8. Legacy Services & Fellowship (the bridge)

Pre-existing Services and Fellowship records keep their legacy data. Each legacy occurrence/event activates into the generic church event model: activation creates the church Event, copies registrations/attendance/groups, preserves the legacy rows, and from then on adding and editing participants happens **only in Registration** under §§3–5. Activation is per occurrence for Services.

9. Visual theme — the purple theme is REMOVED

Church Events must not use a purple/violet theme. Church surfaces use the platform's standard theme — the same dark UI and **lime accent** as Running Events. On the split dashboard, the side is identified by the Running | Church switcher and its labels, not by a separate color family; the calendar and statistics for the active side use the same lime accent (the calendar already shows only the active side's events, so color carries no information there). This supersedes the dashboard spec's earlier "lime = running / violet = church" accent split.

Verified scope at branch tip 57797a8 (all violet usages in the frontend): resources/js/pages/dashboard.tsx — the church theme token set (dot/border/text/button), the Church switcher pill's active state, church stat subtext, today's-date calendar cell, calendar event dots, tooltip link, and the church participant-history chips; resources/js/pages/events/church/participants/history.tsx — the History icon color; resources/js/pages/reports/index.tsx — the church side chip. The church module pages themselves (registration, attendance, category cards) carry no violet.

10. Church Events must NOT contain

Payments, donations, or church finance; payroll; inventory; facility booking; SMS or phone verification; automatic reminders; camera scanning; automatic merging of participants; participant accounts; the Excused attendance status; a purple/violet color theme.

_Still-open decisions are unchanged (see the Master Document): O-1 birthday clause, O-3 prototype-vs-client-docs, O-4 authorization and outside-the-event-day rules, O-6 kit checkpoint (running side), O-7 church CSV export._

## Amendment F — 2026-10-23

Church Events implementation completion record:

- Verified the Amendment E workflow across generic church events and bridged Services and Fellowship: publication, email OTP, duplicate blocking and review, staff entry, code lookup, attendance totals, and participant history.
- Verified church/running participant-pool separation, no-past-date validation on event scheduling paths, active-registration counts excluding Held records, legacy bridge lockdown, the Church Summary workflow, and the specialist placeholder routes and labels.
- Fixed suffix-period identity normalization; removed unreachable Firebase phone verification and phone-channel UI; excluded running-pool participants from Church Registration; added Church Reports confirmed-registration totals and Summary links; corrected the Kids Ministry placeholder label and removed the prohibited payments mention.
- Verification commits, in order: `5004330` suffix-period identity normalization; `00ed83d` email-only verification cleanup; `1e734f6` church participant-pool isolation; `5a32538` generic and bridged full-cycle tests; `07df6ba` placeholder integrity and Church Reports totals; `63f1eb8` Church phone-channel regression guard.
- Cycle evidence: `ChurchEventCycleTest` passes all three cases (generic church event, bridged Service occurrence, bridged Fellowship event). Each covers public email OTP, exact-duplicate rejection, likely-duplicate hold and organizer release, manual registration, code lookup, attendance, Registered = Present + Absent + Unmarked, and participant history.
- Gates at completion: `npm run check` passed; `php vendor/bin/pint --parallel --test` passed; PHPStan reported 0 errors and `phpstan-baseline.neon` was unchanged; `php artisan test` passed with 999 passed and 3 skipped.
- Final guards: `resources/js` and `resources/css` contain no violet/purple references; Excused remains display-only and disabled for grandfathered legacy records, not settable on Church attendance; phone verification is unavailable to Church registration and the verification route accepts only email.

Still open and untouched pending client/environment decisions: specialist-module vertical slices; Discipleship self-enrollment via public link; O-1 birthday clause; O-3 prototype-vs-client-docs; O-4 object-level authorization; O-7 church CSV/print export; production Resend delivery and reCAPTCHA hostname acceptance.

## Amendment G — 2026-10-23

Mark ruled that v1.0 module coverage (§§3–4, FR-01–FR-09) is the requirement for the six specialist modules: Youth Ministry, Kids Ministry, Young Adult, Worship & Prayer, Church Administration, and Special Occasions. This slice delivers their category pages and event lists, category-preselected creation through the shared Church Event wizard, and links into the existing registration, attendance, and summary workflow.

Specialized activity fields described in §7 remain later enhancements under FR-13. O-3 is closed for specialist-module coverage by Mark's ruling; other prototype-versus-client-document conflicts remain outside this ruling.

Implementation commits: `50f9f46c761d0750063c913fda01b78a917b4304` (shared specialist workflow) and `da7d434cc60a90605da9e5311dc44718f4412c3a` (category isolation and workflow tests). The Youth Camp acceptance flow covers specialist creation, staff registration, Present attendance, the summary total, and public email OTP registration with a registration code. Full suite: 1,000 passed, 3 skipped.

### Amendment G — proposal-fidelity follow-up (2026-10-23)

Mark's 2026-10-23 ruling makes v1.0 module coverage (§§3–4, FR-01–FR-09) the requirement for the six specialist modules and closes O-3 for module coverage. The six modules use the shared generic Church event workflow; Services, Fellowship, and Discipleship internals remain unchanged. Activity-specific fields under §7 remain later enhancements (FR-13).

This follow-up adds the optional Leader / Coordinator field to Church Event Details and retains venue as a required event field without changing the existing Running Event venue contract. Church server validation accepts the proposal's five participant-type values. Following the later UI-only direction, public and staff selectors display only Member, First-Timer, and Guest; Graduate and Ninong/Ninang remain accepted server-side and existing saved values remain readable.

An explicitly invoked, idempotent `ChurchSampleDataSeeder` provides Youth Camp, Kids Service, and GRC Fellowship sample events with church-pool registrations and mixed attendance. It is not called by the default seeder. A Church System Scope page is linked from the Church dashboard and documents the nine modules, shared workflow, and deferred specialist fields, exports, and object-level authorization.

Implementation commits: `e28a13a764655dafdeac8abfe3e82c6ed6a7aa93` participant-type server support; `ba9040325942435ef282fdd17a39a3f4d0e6a920` coordinator field; `164d04db165028591cdf875ee3c05def23a690d3` explicit sample seeder; `f499440f62cad57f918a4a957cd7685447d36aab` System Scope page; `b78cf899d4a3dad36a0df86dd51ee09b61fbb8c2` acceptance tests; `a3b5d5b874c0fa99dd236cabba385c2b5ca72405` warning-color audit fixes.

Verification: the full Pest suite passed with 1,007 passed and 3 skipped (baseline 1,000 passed and 3 skipped); `npm run check`, Pint, PHPStan (0 errors; baseline unchanged), and the hard design-audit rules passed. The audit retains only its informational raw-white utility warnings. Local `types:check` reports the 11 known `sharedPageProps`/`auth` type errors in pre-existing files, with no errors in the changed files. The proposal's production acceptance checks remain environment-dependent.

## Amendment H — 2026-10-23

The first FR-13 specialized activity-fields slice is implemented for Church Events. Dedication optionally captures child, mother, father, and officiating pastor names; Youth Camp and Vacation Bible School optionally capture emergency contact, medical notes, and parental consent; Vacation Bible School and Discipleship optionally capture assigned teacher and program day/session. The fields are nullable, shown in the shared create/edit wizard only for matching Church activity contexts, persisted only for those contexts, and displayed on Event Details when populated. Running Event requests exclude these fields. This does not complete the specialist-module workflows that remain deferred.

Verification: dedicated create/persistence/detail tests pass for Dedication and Vacation Bible School; generic Sunday Service remains valid with specialized data unset, and a running event does not save Church-only fields. `npm run check`, `npm run types:check`, Pint, PHPStan (0 errors), and the full PHP suite pass (1,037 passed, 3 skipped).
