# WDEMS — Project Definition

**Status:** Living document. Updated as features ship and scope evolves.
**Last updated:** 2026-10-16
**Audience:** Client, project stakeholders, developers, and AI sessions
picking up this codebase.

---

## 0. How to read this document

This is the single-file, plain-language definition of what WDEMS is
and what it does. It is not a technical spec — for that, see the
subsystem docs listed in §12.

Three sections are marked `[internal]`. They describe implementation
detail that a non-technical reader does not need. Strip them before
sharing with a client.

---

## 1. What WDEMS is

**WDEMS** — Workflow-Driven Event Management System — is a web
application for organizing, running, and reporting on events.

It serves two distinct organizer audiences through one platform:

- **Running-event organizers** — for community runs and similar
  running events.
- **Church organizers** — for nine categories of church activity:
  Services, Fellowship, Discipleship, Youth, Kids Ministry, Young
  Adult, Worship & Prayer, Special Occasions, and Church
  Administration.

Both audiences share one login, one dashboard, one participant
database, and one reporting system. What differentiates a community
run from a church event is which fields apply when creating it.
Everything downstream — the registration page, the attendance view,
the report — shares the same shape.

The system manages the complete lifecycle of an event: creation,
configuration, registration, attendance, and end-of-event reporting.
No other tool is required.

---

## 2. Who uses WDEMS

### 2.1 Roles

WDEMS has two roles.

An expanded four-role model was considered on 2026-10-16 (System
Administrator, Church Leadership, Event Organizer, Attendance
Encoder) and rejected. The two-role structure stands.

No approval workflow is required. Events move to Registration Open
when the organizer is ready, with no separate sign-off step.

**Admin** — full system access. Can create, edit, and delete anything.
Bypasses two-factor authentication because they control the server
itself and already have equivalent access through other means.

**Staff** — the day-to-day operator. Creates events, opens and closes
registration, records attendance, exports reports. Must verify with an
emailed 6-digit code before performing destructive actions like
deleting an event.

**Participants** are not users. They have no account and cannot log
in. They receive a public registration link, submit a form, verify
their contact details, and receive a registration code. That code is
what they present at the door.

### 2.2 Account creation

There is **no public self-service sign-up**. Accounts are created by
admins. This is deliberate — the system is designed for a known set
of organizers, not open registration.

## 3. The two event worlds

### 3.1 Running events

The primary running-event type is **Community Run**. A secondary type
**Fun Run** existed in early versions and was removed in a recent
cleanup. Community Run is the only running-event type that remains.

### 3.2 Church events

Nine church event categories are defined. Each is a distinct module
with its own workflow, attendance model, and reporting shape:

1. Services
2. Fellowship
3. Discipleship
4. Youth
5. Kids Ministry
6. Young Adult
7. Worship & Prayer
8. Special Occasions
9. Church Administration

The nine categories were confirmed by the client on 2026-10-16 (doc
02, CAT-01). The category list is fixed in code. If administrator-
managed categories are needed later, the values migrate into a
database table additively.

---

## 4. Running events — full feature list

### 4.1 Event creation (Community Run)

Creating a community run walks the organizer through a four-step
wizard:

**Step 1 — Details**

- Event Type (Community Run; locked after creation)
- Event Name (3–100 chars, quality-checked against keyboard mashing)
- Description (optional, up to 2,000 chars)
- Accessibility flags (9 flags, all default to on):
    - walkers welcome
    - all paces welcome
    - all ages welcome
    - stroller friendly
    - wheelchair accessible
    - sweeper present
    - service animals allowed
    - leashed pets allowed
    - (quiet space was removed on 2026-10-15)

**Step 2 — Schedule**

- Event Date (custom calendar picker, no past dates, max 2 years ahead)
- Start Time (5-minute intervals, 24-hour storage)
- End Time (optional, minimum 60-minute duration when set)
- Distance (number + unit, KM or Miles with live conversion)
- Venue Map Link (Google Maps URLs only — validated)

**Step 3 — Venue**

- Venue Name
- Venue Address
- Interactive map picker (Leaflet, OpenStreetMap tiles, reverse geocoding)
- Coordinates captured from the map, editable

**Step 4 — Extras**

- Partners (up to 20 rows, each with a name and a type from nine
  predefined categories: host, sponsor, food, beverage, medical,
  organization, media, logistics, other)

**Wizard features:**

- Draft checkpoint: progress is saved to the browser every step and
  restored on return within 24 hours
- Error jumping: server-side validation errors route to the correct
  step automatically
- Progress bar with clickable previously-visited steps
- Client-side emptiness checks before advancing

### 4.2 Event lifecycle

Running events move through a defined sequence:

Draft → Configured → Registration Open → Registration Closed → Ongoing → Completed

Cancellation is reachable from any non-terminal state.

| State                   | What happens                                              |
| ----------------------- | --------------------------------------------------------- |
| **Draft**               | Event exists but is not yet ready to accept registrations |
| **Configured**          | Organizer has prepared the registration form              |
| **Registration Open**   | Public link is live; participants can register            |
| **Registration Closed** | Registration is closed; no new submissions                |
| **Ongoing**             | Event day; attendance can be recorded                     |
| **Completed**           | Event is over; data is read-only                          |

The `Registration Closed → Ongoing` transition happens automatically
once the event's start moment passes. Every other transition is
explicit.

### 4.3 Registration form builder

Before opening registration, the organizer configures a form for
participants to fill in.

**Common fields (always present):**

- First Name
- Last Name
- Contact Number
- Email Address
- Age
- Address

Three of the six — Contact Number, Email Address, Address — can be
toggled required or optional per event. First Name, Last Name, and
Age are always required.

**Custom fields (up to 5 per event):**

- Text
- Textarea
- Number
- Email
- Date
- Select (single choice from a list)
- Radio (single choice, inline)
- Checkbox (multiple choice)

Custom fields are drag-to-reorder. Each has a label and, for choice
fields, up to 10 options.

**Label quality rules:**

- Strict vowel + consonant checks at 5+ characters
- Relaxed rules below 5 characters
- Collision guard against the six common field names (with
  normalization to catch formatting variants)
- Synonym and misspelling detection (e.g., "contact", "phone",
  "mobile" all rejected as duplicates of the common Contact Number
  field)

**Form lock:** Once the event opens registration, the form is locked.
It cannot be edited while participants are registering.

### 4.4 Public registration

Participants receive a short URL of the form `/r/{slug}` — an
8-character slug using a 31-character alphabet that excludes
confusable characters (no I, L, O, 0, 1).

The registration flow has three phases:

**Phase 1 — Submit.** The participant fills in the form. The
submission is held — no Participant or Registration record is created
yet.

**Phase 2 — Verify.** The participant must verify their contact
details:

- Email: a 6-digit code sent by email, entered on the verification page
- Phone: an SMS challenge completed in the browser via Firebase

Which channels are required depends on the event's configuration. At
least one channel must be verifiable.

**Phase 3 — Finalize.** Once all required channels are verified, the
Participant, Registration, and custom field responses are committed
in a single transaction. The participant receives:

- A **Registration Code** — a unique identifier they present at the
  door
- A **confirmation email** with the code and event details
- A **confirmation page** showing their code (which they must
  acknowledge having saved)

**Duplicate prevention:** A submission whose email or normalized phone
number already exists on the same event is rejected before a challenge
is created.

**The closed page:** If a participant visits the URL after
registration closes, they see a clear "registration closed" page —
not an error.

### 4.5 Attendance recording

Attendance is recorded on event day through a dedicated page with
three modes:

**Mark mode** — the default. Each participant appears as a row with
their name, contact, and current attendance status. A Present button
marks the participant present with one click; a dropdown offers the
other statuses.

**Confirm mode** — designed for paper-sheet reconciliation. Adds a
checkbox column and a bulk-mark toolbar. The operator can select
multiple rows and mark them all present, absent, or excused in one
action.

**Batch mode** — designed for late data entry. An empty table where
the operator enters multiple rows of walk-in participants in one
submission. Each row is committed independently; if one fails, the
others still succeed.

**Attendance statuses:**

- Present
- Absent
- Excused
- Late

**Attendance codes:** Every finalized registration receives a
unique code. When a participant arrives, the operator can enter their
code into a search field. If the code matches, the attendance is
marked as **verified** — proof that the person at the door is the
person who registered. Attendance marked without a code is recorded
but unverified.

**The attendance window:** Attendance can only be recorded starting
60 minutes before the event's start time. The window stays open after
the event starts. If no start time is set, the window opens at
midnight on the event date.

**Walk-in registration:** Participants who arrive without
pre-registering can be added on the spot. The system creates a
Participant, Registration, and Attendance record in one step, and
matches the walk-in against existing participants by email or
normalized phone.

### 4.6 Event deletion

Two deletion paths, determined by the user's role:

**Admin path.** Direct deletion from the event detail page. A
confirmation dialog lists the counts of related records (options,
registrations, attendance records). No second factor.

**Staff path.** Staff must complete a 6-digit email OTP challenge
before deletion. The code is sent to the staff user's registered
email, valid for 10 minutes, with a 60-second resend cooldown and a
5-attempt lockout.

Both paths funnel through the same ordered delete inside a database
transaction. Participants are **never deleted** — they are shared
across events and survive the event's removal.

### 4.7 Registration monitoring

A live monitoring page shows the flow of registrations in real time.
Organizers can:

- See the total submissions, the last-hour count, and the pace
- Filter by status (new, returning, flagged)
- Edit participant details inline
- Flag a registration for follow-up
- Add a note to a registration
- Delete a registration (with OTP for staff)
- Export to CSV

The CSV export includes one column per custom field.

### 4.8 End-of-event report

After the event is finalized, an end-of-event report shows:

- Total registrations
- Total attendance (broken down by verified vs. override)
- Show rate (attended ÷ registered)
- Verification rate (verified ÷ attended)
- Per-participant history: how many prior events they attended, when
  their last appearance was
- Cross-event comparison: how this event's numbers compare against
  similar events

The report is exportable to CSV with a UTF-8 BOM (Excel-compatible).

---

## 5. Church events — full feature list

Church events share the `events` table and the same wizard, but
branch into their own workflows.

### 5.1 Church event creation

The creation wizard offers **Church Event** as an event type. When
selected:

- A second dropdown appears for **Church Event Type** with the nine
  categories.
- The wizard uses three steps instead of four: **Details**, **Schedule**,
  **Venue**. The Extras step is hidden.
- Running-only fields — distance, partners, accessibility flags — are
  hidden.
- The event is created with status **Upcoming** by default.

### 5.2 Services

**Purpose:** recurring worship services with attendance tracking.

**Structure:**

- Service types: Morning, Midweek, Youth, Other
- Each service type has its own occurrence list
- Occurrences can be created one at a time or generated as a recurring
  batch (weekly or fortnightly, up to 52 occurrences)

**Attendance:**

- Two tracks per occurrence: **Members** and **First-Timers**
- A participant cannot be on both tracks for the same occurrence
- First-Timer records are created automatically the first time a
  participant attends as a first-timer; subsequent first-timer
  attendance reuses the same record

**Status:** Fully shipped and tested.

### 5.3 Fellowship

**Purpose:** named gatherings grouped by type.

**Structure:**

- Fellowship types: Family, Church, Pastors, Leaders, Women's, Men's
- Each event has a name, description, and date
- Events can have their own set of **groups** (Parents, Youth,
  Children, etc.)
- Group assignment is optional per participant

**Attendance:**

- One unified roster (not two tracks like Services)
- Per-group attendance counts
- Each attendance row can be assigned to a group

**Status:** Fully shipped and tested.

### 5.4 Discipleship

**Purpose:** structured programs with enrollment, sessions, and
progress tracking.

**Structure:**

- **Programs** — six seeded (Life Class, Encounter God Retreat,
  School of Leaders 1–3, Cell Convergence). Programs are seeded, not
  created through the UI.
- **Batches** — cohorts within a program. A program has many batches.
- **Enrollments** — one row per participant per program, for the
  participant's lifetime. Withdrawal is a status change, not a row
  deletion.
- **Sessions** — meetings within a batch. Each session has a date and
  topic.

**Enrollment statuses:**

- Enrolled
- In Progress
- Completed
- Withdrawn
- On Hold

**Attendance matrix:**

- One row per enrollment, one column per session
- Per-cell status: Present, Absent, Excused
- Bulk upsert — the operator marks all changed cells and saves once
- Progress column shows attended / applicable and a percentage

**Progress calculation:**

- Attended = count of present + excused sessions
- Applicable = sessions between the enrollment's start date and today
- Completion criteria: nullable `required_sessions` per program; if
  null, completion requires 100% of applicable sessions

**Dashboard features:**

- Program overview with completion rate donut
- Search by participant name, email, or phone
- Filter by status and batch
- Export to CSV

**Status:** Fully shipped and tested. A behavioral change is pending
from client Q14 (drop excused, count only present, expand applicable
window). See §11.

### 5.5 Youth

**Purpose:** youth activities across five sub-modules.

**Sub-modules (per client Q3):**

- Youth Camp
- Youth Service
- Huddle
- Campus Ministry
- Timothy Class

**Status:** Scoped. Not yet built.

### 5.6 Kids Ministry

**Purpose:** kids activities tracked per outreach location.

**Structure:**

- Dynamic outreach locations (staff can add new ones)
- Four locations seeded in the mockup: Wawang Pulo, Pasolo,
  Balubaran, Coloong

**Status:** Scoped. Not yet built.

### 5.7 Young Adult

**Purpose:** young adult ministry activities.

**Sub-modules (per client Q4):**

- Service
- Seminar
- Partner
    - Nested: GRC
        - Care Group
        - Fellowship

**Status:** Scoped. Not yet built.

### 5.8 Worship & Prayer

**Purpose:** worship and prayer activities.

**Sub-modules (per client Q5):**

- Outdoor Prayer Meeting
- Worship Night
- Prayer and Fasting

**Status:** Scoped. Not yet built.

### 5.9 Special Occasions

**Purpose:** church celebrations and one-off events.

**Features (per mockup and client Q6, Q7, Q20):**

- Multi-tab detail page (URL-persisted tabs)
- Gantt-style planning timeline
- Auto-generated documents (system-generated, not uploads, not
  external links)
- Budget recording (simple name + amount, not approval workflow)
- Task tracking (assignable to any user)

**Status:** Scoped. Not yet built.

### 5.10 Church Administration

**Purpose:** administration and planning activities.

**Sub-modules:**

- Ministry Meeting — editable list of seven ministries (Worship,
  Technical, Multimedia, Social Media, Finance Team, Light Kids,
  Young-adult)
- Church Planning — Gantt timeline

**Status:** Scoped. Not yet built.

---

## 6. Cross-cutting platform features

These apply to both event worlds.

### 6.1 Authentication

- Email + password login
- Email two-factor authentication on new devices (6-digit code)
- Trusted devices remembered for 30 days (up to 10 per user)
- Password reset via email link
- Email verification for new accounts
- Admin bypass for both 2FA and email verification

### 6.2 Participant identity

- A participant is identified by email address OR normalized phone
  number
- Two participants cannot share a non-null email
- Two participants cannot share a non-null normalized phone
- Two registrations cannot share the same event and participant
- Participants are never deleted when an event is deleted
- Duplicate submissions are rejected before a verification challenge
  is created

### 6.3 Contact verification

Two channels:

- **Email** — 6-digit code sent via transactional email service
- **Phone** — Firebase Phone Auth (SMS challenge in the browser)

Which channels are required depends on the event's configuration. At
least one must be verified.

### 6.4 Dashboard

One dashboard shows events the user can act on. Events are grouped by
urgency, and quick actions let the user jump directly into the next
step for each event.

### 6.5 Settings

- Password change
- Two-factor authentication toggle
- Trusted device list with per-device revoke
- Profile update
- Account deletion

### 6.6 Reporting

Every event kind produces a report at the end of its lifecycle. The
report is exportable to CSV.

### 6.7 Notifications

Transactional email:

- Registration confirmation with the participant's attendance code
- Email verification link
- 2FA login code
- Event deletion OTP
- Registration deletion OTP

### 6.8 Design system

A consistent visual language across every page:

- Four-layer model (base, image, glass surfaces, content)
- Three glass tiers (structural, content panels, internal rows)
- Semantic colors with light/dark mode variants
- A documented warning-yellow / warning-navy rule for status colors
- Automated audit script that catches common violations

---

## 7. What's shipped today

### 7.1 Running events

- Event creation wizard with checkpoint restore
- Event lifecycle (draft through completed)
- Registration form builder with custom fields
- Public registration with email and phone verification
- Attendance recording (three modes)
- Per-registration attendance codes
- Walk-in registration (single and batch)
- Event deletion with admin bypass / staff OTP
- Registration monitoring dashboard
- End-of-event report with CSV export
- Automatic event status advancement

### 7.2 Church events

- Church event schema on the shared events table
- **Services** — complete
- **Fellowship** — complete
- **Discipleship** — complete
- Church category selection in the creation wizard
- Nine-category dropdown with server-side validation

### 7.3 Platform

- Authentication with email 2FA and trusted devices
- Admin / staff roles
- Settings pages for password, security, profile
- Shared dashboard and navigation
- Design system with automated audit

### 7.4 Test coverage

The system has **791 automated tests** covering all shipped features.

---

## 8. What's scoped and coming

Modules confirmed in scope but not yet built:

| Module                           | Scope                                                             |
| -------------------------------- | ----------------------------------------------------------------- |
| **Youth**                        | Five sub-modules                                                  |
| **Young Adult**                  | Three sub-modules plus nested GRC                                 |
| **Worship & Prayer**             | Three sub-modules                                                 |
| **Kids Ministry**                | Dynamic outreach locations                                        |
| **Special Occasions**            | Gantt timeline + auto-generated documents                         |
| **Church Administration**        | Editable ministry list, Ministry Meeting, Church Planning         |
| **Members / First-Timers pages** | Dedicated top-level pages for existing backend models             |
| **Services sidebar restructure** | Services becomes a parent; Attendance nests beneath it            |
| **Participant grade tracking**   | Global column on the participant record, plus edit/delete actions |

---

## 9. What's explicitly out of scope

To keep the project focused, the following are not part of WDEMS:

- **Payments and bookings.** No gateway integration, no per-
  participant payment status. Confirmed by client on 2026-10-16.
- **Public self-service sign-up.** Accounts are created by admins.
- **Cell Group.** Removed from scope by client decision (Q18).
- **SMS-based two-factor authentication.** Email codes only.
- **Google Forms integration.** Previously built, then removed in
  favor of the in-house registration flow.
- **Budget approval workflows.** Budget is a recorded field, not an
  approval chain (Q12).
- **Real-time payment tracking.** Confirmed out by Q10.

---

## 10. Technical foundations [internal]

### 10.1 Stack

- **Backend:** Laravel 13, PHP 8.5
- **Frontend:** React 19, TypeScript 5.7, Inertia 3
- **Styling:** Tailwind CSS 4, Radix UI primitives
- **Database:** MySQL (development and production), SQLite in-memory
  (tests)
- **Testing:** Pest 5
- **Build:** Vite 8
- **Authentication:** Laravel Fortify + custom email 2FA
- **Email:** Transactional email service
- **Phone verification:** Firebase Phone Auth
- **Route generation:** Wayfinder (generates TypeScript route helpers
  from PHP routes)

### 10.2 Architecture

Controllers handle HTTP. Services hold domain logic. No controller
touches a domain rule directly.

**Service layer:**

- Event workflow — lifecycle state machine
- Event deletion — ordered delete with OTP
- Registration verification — three-phase public flow
- Reporting — end-of-event aggregation
- Two-factor — email codes and device trust
- Discipleship progress — completion calculator

### 10.3 Data model

Core tables:

- `users` — accounts with roles
- `events` — both running and church events
- `participants` — shared across events
- `registrations` — participant ↔ event links
- `attendances` — per-registration attendance
- `registration_fields` — form builder fields
- `registration_field_responses` — custom field answers
- `registration_verification_challenges` — pending public submissions

Church-event tables:

- `members`, `first_timers`
- `service_occurrences`, `service_attendance_members`,
  `service_attendance_first_timers`
- `fellowship_events`, `fellowship_groups`, `fellowship_attendances`
- `discipleship_programs`, `discipleship_batches`,
  `discipleship_enrollments`, `discipleship_sessions`,
  `discipleship_attendances`

### 10.4 The `event_kind` discriminator

Running events and church events share one `events` table. They are
distinguished by an `event_kind` column (`'running'` or `'church'`).
Two additional columns hold church-specific values:

- `church_category` — the nine categories
- `church_status` — the church lifecycle (Upcoming, Ongoing,
  Completed, Cancelled)

Running events use `event_type` and `status` (the running lifecycle).
Church events use `church_category` and `church_status`. The two
systems are independent.

---

## 11. Open questions

Two items from the client decision log remain unresolved and should
be confirmed before their modules are built.

**1. Registration's placement in the new sidebar.** Services stays
top-level with Attendance nested beneath it. Registration's exact
nesting is pending.

**3. Discipleship progress calculation change.** The client has
requested three behavioral changes:

- Drop the "excused" status; use only Present and Absent
- Expand the applicable-session window to all sessions in the batch,
  not bounded by enrollment date
- Count only "present" toward progress

Existing data will need migration before the change ships.

---

## 12. Documentation map

The repository has additional subsystem docs for readers who need
more detail.

| Document                          | What it covers                                     |
| --------------------------------- | -------------------------------------------------- |
| `docs/architecture.md`            | System-wide overview and data model                |
| `docs/authentication.md`          | Login, 2FA, trusted devices                        |
| `docs/event-creation.md`          | Event creation code reference                      |
| `docs/event-creation-wizard.md`   | Wizard as a feature                                |
| `docs/event-deletion.md`          | Two deletion paths                                 |
| `docs/attendance-code.md`         | Per-registration codes and encoding modes          |
| `docs/attendance-redesign.md`     | The three-mode attendance workspace                |
| `docs/registration.md`            | Public registration feature spec                   |
| `docs/registration-monitoring.md` | Live submission monitoring                         |
| `docs/fellowship.md`              | Fellowship module                                  |
| `docs/discipleship.md`            | Discipleship module                                |
| `docs/design-system.md`           | Visual contract                                    |
| `docs/church-events/`             | Client engagement docs (scope, decisions, mockups) |
| `docs/known-issues.md`            | Open backlog                                       |
| `docs/fixes.md`                   | Fixed problems reference                           |
| `docs/progress.md`                | Chronological changelog                            |

---

## 13. Glossary

| Term                  | Plain meaning                                                                 |
| --------------------- | ----------------------------------------------------------------------------- |
| **2FA**               | Two-factor authentication — a second proof of identity beyond the password    |
| **OTP**               | One-time password — a code valid for a single use                             |
| **Trusted device**    | A browser remembered by the system for 30 days                                |
| **Event**             | An activity managed in WDEMS — either a community run or a church event       |
| **Event kind**        | The top-level discriminator: `running` or `church`                            |
| **Event type**        | For running events: Community Run. For church events: one of nine categories. |
| **Participant**       | A person who registers for events; not a user                                 |
| **Registration**      | A participant's link to a specific event                                      |
| **Attendance**        | The record of whether a participant showed up                                 |
| **Registration code** | A per-registration identifier presented at the door                           |
| **Slug**              | The short 8-character identifier in a public registration URL                 |
| **Wayfinder**         | A Laravel tool that generates typed route helpers from PHP routes             |
| **Inertia**           | The bridge between the backend and the React frontend                         |

---

_End of project definition._
