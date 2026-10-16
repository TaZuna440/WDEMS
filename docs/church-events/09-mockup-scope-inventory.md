# WDEMS -- Expanded Scope Inventory from Client Mockups

**Status:** Working draft. Inventory only. No decisions recorded.
Everything below is what the mockups show versus what exists,
with open questions surfaced for the client conversation.

**Audience:** The developer, for the eventual client scope
review. Not a plan, not an approval, not a spec.

**Purpose:** Reconcile seven client mockup screenshots against
the current WDEMS build state. Identify (a) what already exists,
(b) what is shown that does not exist, (c) what is implied but
undecided, and (d) what questions to ask the client before any
of the new work is scoped or sequenced.

**Limitation notice:** The inventory below is derived from seven
screenshots at the resolution provided. Some text in the
screenshots is unreadable or ambiguous. Where a claim depends on
text that could not be read confidently, it is flagged. No code,
migration, model, or test was changed to produce this document.

**Related:**
- `docs/church-events/01-current-system-audit.md` -- the
  original system audit
- `docs/church-events/02-product-scope-and-requirements.md` --
  the earlier scope. Several USR-XX items below are refinements
  of the questions in that document.
- `docs/church-events/06-open-questions-and-implementation-roadmap.md`
- `docs/church-events/07-scope-and-task-division.md` -- the
  current five-workstream plan (Services, Fellowship,
  Discipleship, Youth, Cell Group)
- `docs/progress.md` -- the shipped state as of the most recent
  session

---

## 1. Source materials

Seven screenshots, likely from a single Figma or design-tool
prototype. In reading order:

1. **Discipleship detail** -- Life Class program page with
   participant table, Program Overview sidebar, Batch Summary,
   Completion Rate donut, Quick Actions panel.
2. **Youth hub + expanded sidebar** -- Youth Ministry landing
   with five sub-module cards. Full navigation visible.
3. **Youth sub-pages** (composite) -- Youth Camp, Youth Service,
   Huddle, Campus Ministry, Timothy Class. Five detail views in
   a single grid.
4. **Kids Ministry hub + sub-pages** (composite) -- Vacation
   Bible School list, Outreaches hub, Wawang Pulo location
   detail.
5. **Special Occasions hub + Church Anniversary detail**
   (composite) -- landing with five occasion cards, plus the
   multi-tab event detail.
6. **Special Occasions sub-events** (composite) -- Thanksgiving,
   Pastor's Appreciation, Dedication, PEPSOL Graduation.
7. **Church Administration** (composite) -- Ministry Meeting
   list with seven ministries, Worship Team meeting minutes,
   Church Planning with plan list.

---

## 2. What WDEMS currently has

Shipped as of commit `e859fe1` (2026-10-16).

### 2.1 Modules

| Module | Status | Doc |
|---|---|---|
| Auth (Fortify + custom email 2FA + trusted devices + passkeys) | Shipped | `authentication.md` |
| Events (Community Run model) | Shipped | `event-creation.md`, `event-creation-wizard.md` |
| Event deletion (admin + staff OTP) | Shipped | `event-deletion.md` |
| Public registration + contact verification | Shipped | `registration.md`, `registration-contact-verification.md` |
| Attendance (mark / confirm / batch + code lookup + live/deferred encoding) | Shipped | `attendance-redesign.md`, `attendance-code.md` |
| Event finalize + end-of-event report | Shipped | `attendance-encoding-and-reporting.md` |
| Services (Morning, Midweek, Youth, Other) | Shipped (Phase 3) | (no dedicated doc) |
| Fellowship (six types, groups, attendance) | Shipped (Phase 4) | (no dedicated doc) |
| Discipleship programs | Shipped (Phase 5A) | `progress.md` entry |
| Discipleship batches | Shipped (Phase 5B) | `progress.md` entry |
| Discipleship enrollment | Shipped (Phase 5C) | `progress.md` entry |

### 2.2 Sidebar (as currently shipped)

Seven items, flat:

- Dashboard
- Services
- Fellowship
- Discipleship
- Events
- Registration
- Attendance

Plus a user menu and settings.

### 2.3 Participants as data

A `participants` table exists. It is shared across all modules.
Fields: first_name, last_name, contact_number,
contact_number_normalized, email, age, birthday, address.

There is no `members` table concept at the user-facing level.
There is a `members` table (Phase 3, church membership records
with a UNIQUE on participant_id) but no Members *page*.

There is a `first_timers` table (Phase 3) but no First-Timers
*page*.

### 2.4 What WDEMS does NOT have

Cross-cutting features absent from the entire codebase:

- **Payment tracking.** No fee structure. No paid/partial/for
  payment status anywhere. No payment records. No gateway.
- **Budget tracking.** No budget line items. No budget amount
  vs actual spent.
- **Task assignment.** No task table. No assigned-to. No due
  date. No task status.
- **Roles & Ministries per event.** No per-event role
  assignment table. No responsibility text.
- **Document attachments.** No file upload for events.
- **Multi-tab detail pages.** Every shipped page is a flat
  layout. No tabbed detail pattern.
- **Progress percentage service.** Enrollment exists. No
  attended/applicable/percentage computation yet.
- **Grade/year grouping.** Participants have `age` but no
  `grade_level` or `year_level`.
- **Location-based outreach structure.** No location entity.
- **Ministry-level meeting tracking.** No ministry table. No
  meeting minutes. No action items.
- **Church-wide plan tracking.** No plan table.

---

## 3. What the mockups show

### 3.1 Sidebar (mockup target)

Full top-level list from screenshots 2-7:

Dashboard
Members
Cell Group
First-Timers
Fellowship
Discipleship
Youth (expandable: Youth Camp, Youth Service,
Huddle, Campus Ministry)
Kids Ministry (expandable: Vacation Bible School,
Outreaches)
Young Adult
Worship & Prayer
Special Occasions (expandable: Church Anniversary,
Thanksgiving Celebration,
Pastor's Appreciation Month,
Dedication, PEPSOL Graduation)
Church Administration (expandable: Church Planning,
Ministry Meeting)
Events
Reports
Settings

14 top-level items. 6 are expandable modules with sub-pages.

Note: **the mockup sidebar does not show `Services` or
`Registration` or `Attendance` as top-level items.** Those
currently exist. Question below.

### 3.2 Modules and detail pages

#### 3.2.1 Discipleship (extension of shipped)

Mockup shows:

- **Program selector row** -- six program cards in a horizontal
  strip at the top of the page (Life Class, Encounter God
  Retreat, School of Leaders 1, 2, 3, Cell Convergence).
  Clicking swaps the detail below.
- **Program header** -- icon + name + subtitle ("Enrollments,
  attendance, progress, and completion status").
- **Search + filters** -- "Search participant name", "All
  Batches" dropdown, "All Status" dropdown, "+ Add Enrollment".
- **Participant table columns** -- Name (with avatar),
  Batch, Enrolled Date, Attendance (X/Y), Progress (bar + %),
  Status (badge), Actions (edit / kebab menu).
- **Batch Summary card** -- per-batch progress bars with
  enrolled count.
- **Completion Rate card** -- donut chart with
  "N of M completed" text.
- **Quick Actions panel** -- View Program Details, Generate
  Report, Manage Batches.

**Attendance (X/Y) column.** WDEMS has enrollment. It does not
have sessions or session attendance. The X/Y in the mockup is
attended sessions / total sessions -- which requires 5D and 5E
that are not built.

**Progress % and bar.** Requires a progress service. Not built.

**Status badge values shown.** Enrolled, In Progress,
Completed, Not Started. The shipped enum has five values:
enrolled, in_progress, completed, withdrawn, on_hold. The
mockup shows "Not Started" as a status. That is a *sixth*
status. Question below.

#### 3.2.2 Youth (new module)

Sub-modules per the hub card set (screenshot 2):

| Sub-module | What the detail page shows |
|---|---|
| **Youth Camp** | Participants table (Name, Grade/Year, Contact No, Payment, Status) with tabs: Participants, Payments, Requirements, Attendance. "+ Register Participant" button. Payment badge (Paid / For Payment / Partial). Status badge (Confirmed). |
| **Youth Service** | Schedule table (Date, Topic, Speaker, Status, Attendees, Actions). Tabs: Schedule, Attendance. "+ Add Session" button. |
| **Huddle** | Huddle Groups table (Group Name, Leader, Day & Time, Members). Tabs: Huddle Groups, Schedule, Attendance. "+ Add Huddle" button. |
| **Campus Ministry** | Grade tabs (Grade 7, Grade 8, Grade 9, Grade 10, Grade 11, Grade 12, College). Each grade has its own participant list. Tabs: Participants, Schedule, Attendance, Progress. "+ Add Participant" button. |
| **Timothy Class** | Participants table (Name, Grade/Year, Email, Progress, Status, Actions). Tabs: Participants, Schedule, Attendance, Progress. Status badge: Completed, In Progress, Upcoming. "+ Enroll Participant" button. |

Consistency issues visible in the mockups:

- The Youth hub shows **five cards**. The expanded sidebar
  sub-nav (screenshot 2, left rail) shows only **four** items
  (Youth Camp, Youth Service, Huddle, Campus Ministry).
  **Timothy Class is in the hub but missing from the nav.**
- Spelling: the hub card reads "**Timoty Class**" in
  screenshot 2. The correct spelling is "Timothy Class". Fix
  in implementation.

#### 3.2.3 Kids Ministry (new module)

Two sub-modules:

| Sub-module | What it shows |
|---|---|
| **Vacation Bible School** | Participants table with Name, Age/Grade, Contact No., Registration Date, Status (Registered, Attended). Tabs: Participants, Sessions, Attendance, Details. "+ Register Participant" button. |
| **Outreaches** | Location cards (Wawang Pulo, Pasolo, Balubaran, Coloong). Each card shows Participant count and Activity count. Location detail page (Wawang Pulo shown) has: hero image, Total Participants, Activities, Last Activity, tabs (Participants, Activities, Attendance, Details). Participant table with Name, Age/Grade, Contact No., Last Attended, Status (Attended, In Progress, Registered). "+ Add Activity" button. |

#### 3.2.4 Young Adult (new module)

Listed in sidebar. **No detail page shown in the mockups.**
Contents unknown. Question below.

#### 3.2.5 Worship & Prayer (new module)

Listed in sidebar. **No detail page shown in the mockups.**
Contents unknown. Question below.

#### 3.2.6 Special Occasions (new module)

Hub with five occasion cards (Church Anniversary, Thanksgiving
Celebration, Pastor's Appreciation Month, Dedication, PEPSOL
Graduation).

Each occasion has a detail page with **six tabs**:

| Tab | Contents |
|---|---|
| **Details** | Event Title, Theme, Date, Time, Venue, Overall Organizer, Description. Status dropdown (Planning / Upcoming / Ongoing / Completed). |
| **Program** | Table of Order, Time, Program Item, In-Charge, Team. "+ Add Program Item" button. |
| **Roles & Ministries** | Table of Role, Assigned To, Ministry, Responsibilities, Status. "+ Add Role" button. |
| **Budget** | Table of Category, Description, Budget Amount, Actual Spent, Status (Paid / Partial / For Approval), Actions. "+ Add Expense" button. Shows Total Budget, Total Spent, and a "View Detailed Budget" link. |
| **Tasks** | Table of Task, Assigned To, Due Date, Status (Completed / Upcoming / Overdue). "+ Add Task" button. |
| **Documents** | Tab present in mockups. Contents not shown. Question below. |

Sidebar "Quick Info" panel on the details page shows: activities
count, people assigned count, total budget, tasks count.

#### 3.2.7 Church Administration (new module)

Two sub-modules:

| Sub-module | Detail page shape |
|---|---|
| **Church Planning** | List of plans with Title, Type (Vision Plan, Facility Development, Outreach Expansion, Strategic Plan, Leadership Development, Community Engagement), Start Date, End Date, Status (Ongoing / In Progress / Upcoming). Tabs: Plans, Timeline, Tasks, Documents. "+ Add Plan" button. |
| **Ministry Meeting** | Seven ministry cards (Worship, Technical, Multimedia, Social Media, Finance Team, Light Kids, Young-adult). Each ministry has its own detail page. Worship Team page shown: Meetings table (Date, Time, Agenda, Facilitator, Status, Actions). Tabs: Meetings, Agenda, Minutes, Action Items, Team Members, Documents. "+ Add Meeting" button. |

---

## 4. The delta -- what's missing

### 4.1 Net-new top-level modules

- Youth (with 5 sub-modules)
- Kids Ministry (with 2 sub-modules)
- Young Adult (contents unknown)
- Worship & Prayer (contents unknown)
- Church Administration (with 2 sub-modules)

### 4.2 Net-new cross-cutting features

Each requires a schema and a controller and a UI surface:

| Feature | Used by |
|---|---|
| Payments (fee, paid/partial/for-payment, status) | Youth Camp, potentially Kids Ministry and Special Occasions |
| Budget (line items, amount, actual spent, approval status) | Special Occasions |
| Tasks (assignment, due date, status) | Special Occasions, Church Planning |
| Roles & Ministries (per-event role assignment) | Special Occasions |
| Documents (file attachments per record) | Special Occasions, Church Planning, Ministry Meeting |
| Progress percentages (attended / applicable) | Discipleship, Timothy Class, Campus Ministry |
| Grade/Year level | Youth Camp, Kids Ministry, Campus Ministry, Timothy Class |
| Location entity | Kids Ministry Outreaches |
| Ministry entity (7 fixed or editable) | Church Administration Ministry Meeting |
| Plan entity | Church Planning |
| Meeting minutes + agenda + action items | Ministry Meeting |

### 4.3 UX patterns not yet in the codebase

- **Multi-tab detail pages.** Special Occasions shows six tabs;
  Ministry Meeting shows six tabs; VBS shows four tabs.
  Currently every shipped page is flat.
- **Program/batch selector strips** at the top of a page
  (Discipleship mockup).
- **Donut / progress-bar charts** (Completion Rate card).
- **Expandable sidebar sub-navigation.** Current sidebar is
  flat.
- **Grade-level tabs within a single page** (Campus Ministry).

### 4.4 Sidebar reconciliation

The mockup sidebar and the shipped sidebar disagree on every
item except Dashboard, Fellowship, Discipleship, Events,
Settings.

Items in the shipped sidebar but not in the mockup sidebar:

- Services
- Registration
- Attendance

Items in the mockup sidebar but not in the shipped sidebar:

- Members
- Cell Group
- First-Timers
- Youth
- Kids Ministry
- Young Adult
- Worship & Prayer
- Special Occasions
- Church Administration

The mockup sidebar is a strict superset only if Services,
Registration, and Attendance are folded into other modules.
That folding is not shown. Question below.

---

## 5. Open questions for the client

Numbered for reference. Each is a decision the developer cannot
make unilaterally.

### Navigation and structure

**Q1.** The mockup sidebar drops Services, Registration, and
Attendance as top-level items. Are those modules being removed,
renamed, or folded into another section? If folded, into which?

**Q2.** The shipped sidebar has seven items; the mockup has
fourteen with expandable groups. Is the flat sidebar replaced
entirely, or is the mockup sidebar one view among several?

**Q3.** Youth hub shows five sub-modules but the sidebar
sub-nav shows four (Timothy Class missing). Which is correct?

### Module scope

**Q4.** Young Adult module -- no detail page shown. What are
its sub-modules and their contents?

**Q5.** Worship & Prayer module -- no detail page shown. What
are its sub-modules and their contents?

**Q6.** Special Occasions Documents tab -- what does it store?
File uploads? Generated PDFs? Links to external documents?

**Q7.** Church Planning "Timeline" tab -- what does it show?
Gantt-style bars? Milestones? Free text?

**Q8.** Kids Ministry Outreaches -- are locations a fixed list
or created by staff? The mockup shows four Philippine
barangays. Are more expected?

**Q9.** Ministry Meeting -- the seven ministries shown (Worship,
Technical, Multimedia, Social Media, Finance Team, Light Kids,
Young-adult) are fixed or editable? Who manages the list?

### Cross-cutting features

**Q10.** Payment tracking -- is this real payment processing
(Stripe / GCash / Maya / PayPal) or internal bookkeeping only?
The mockup shows "Paid / Partial / For Payment" badges but no
gateway UI. Does a participant pay online, or does staff mark
payment received?

**Q11.** If real payments: which gateway? Philippine market
implies GCash, Maya, or a card processor. No gateway is
configured in the codebase today.

**Q12.** Budget Approval status ("For Approval") -- who
approves? Is there a role that must approve expenses, or is
"For Approval" just a state the organizer sets?

**Q13.** Tasks -- can a task be assigned to any user, or only
to users with a specific role? The mockup shows a name in the
"Assigned To" column.

**Q14.** Progress calculation -- what makes a session
"applicable"? Total sessions in the batch? Sessions the
participant was enrolled for? Attended + excused + absent
combined, or attended only?

**Q15.** Discipleship status "Not Started" -- is this a sixth
status value, or a UI label for the empty/initial state before
enrollment? The current enum has five values.

### Data and structure

**Q16.** Grade/Year level -- does this apply to participants
globally (add a column to `participants`), or only per event
(Youth Camp participants have grades, but adult community-run
participants do not)?

**Q17.** Should the existing Members and First-Timers tables
(Phase 3, currently backend-only) get top-level pages matching
the mockup sidebar?

**Q18.** Cell Group -- listed in the mockup sidebar. No detail
page shown. Is this the same as Phase 3's `ServiceOccurrence`
under ServiceType=Youth, or a distinct concept?

### Visual and interaction

**Q19.** The mockups use a different visual style (flat cards,
lighter palette, different iconography) from the shipped
WDEMS glass-panel design. Is the design system being replaced?

**Q20.** Multi-tab detail pages are a new UX pattern. Any
preference for tab rendering (URL-persisted tabs vs local
state)?

---

## 6. Proposed build order (subject to client answers)

If the client confirms the full scope, a phased build order
follows. This is a proposal, not an approval.

### Phase A -- Navigation and structure

Sidebar redesign. Members and First-Timers pages surfacing the
existing backend tables. Does not build any new domain --
surfaces what already exists.

Effort: 1 session.

### Phase B -- Cross-cutting features

Payments, Budget, Tasks, Roles & Ministries, Documents,
Grade/Year level, Progress service.

Building these once and reusing across modules is cheaper than
building them per-module. Payment tracking is the largest of
these and the one most likely to need external integration.

Effort: 3-5 sessions depending on Q10's answer.

### Phase C -- Discipleship extension

Sessions (5D) and session attendance (5E) complete the
Discipleship module against the mockup. Progress percentages
and completion rate land here. The Program Overview sidebar,
Batch Summary card, and Completion Rate donut are all UI on
data that already exists or that 5D/5E add.

Effort: 2-3 sessions. Continues from the shipped 5A/5B/5C.

### Phase D -- Youth module

Five sub-modules. Each is its own domain. Youth Camp needs
payments (Phase B). Youth Service is close to the existing
Services pattern. Huddle needs a groups concept. Campus Ministry
needs grade levels. Timothy Class is close to Discipleship.

Effort: 3-5 sessions.

### Phase E -- Kids Ministry

Two sub-modules. Outreaches needs a location entity.
Effort: 2-3 sessions.

### Phase F -- Special Occasions

Five occasion types, six tabs each. Depends on Budget, Tasks,
Roles, Documents from Phase B.
Effort: 3-4 sessions.

### Phase G -- Church Administration

Church Planning and Ministry Meeting. Depends on Tasks and
Documents from Phase B.
Effort: 2-3 sessions.

### Phase H -- Young Adult, Worship & Prayer

Contents unknown. Estimate after Q4 and Q5.
Effort: TBD.

**Total (rough):** 16-26 sessions if all modules are built as
shown, and if the client answers Q10 in favor of real payment
processing.

---

## 7. What this document does NOT do

- It does not decide any of the open questions.
- It does not commit to a build order.
- It does not modify any existing module.
- It does not supersede `docs/church-events/07-scope-and-task-
  division.md`. That document remains the current authoritative
  plan until the client answers the questions here.
- It does not estimate the value or priority of any module --
  only the size.

---

## 8. Recommended next step

Send this document to the client. Ask for written answers to
Q1-Q20 before any code is written for the new modules.

The Discipleship module shipped in 5A/5B/5C is not invalidated
by the mockup. It is a subset of what the mockup shows.
Continuing 5D and 5E is safe and does not depend on any of the
answers.

---

## Changelog

- **2026-10-16** -- File created. Seven mockup screenshots
  inventoried against WDEMS build state at commit `e859fe1`.
  Twenty open questions raised. Proposed build order added.
  No decisions recorded.
