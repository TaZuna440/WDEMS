# WDEMS -- Church Events -- Client Decision Log

**Status:** Open. Tracking questions 1-20 from
`docs/church-events/09-mockup-scope-inventory.md`.

**Audience:** The developer and the client. One entry per
question, updated as answers arrive.

**Purpose:** A single place where the client's answers to the
twenty scope questions are recorded, dated, and cross-referenced
to the code and docs that depend on them. When a question is
answered, the entry gains an **Answer** section and a **Date**
line. The question text stays as written -- per P7, the log is
append-only at the entry level.

**Related:**
- `docs/church-events/09-mockup-scope-inventory.md` -- the full
  inventory of what the mockups show vs what exists. This file
  is the answer-tracking companion.
- `docs/church-events/TASKS.md` -- the task board. Blocked
  items reference the questions here.
- `docs/progress.md` -- chronological record.

---

## How to use this file

Each question has three parts:

- **Question** -- the text as sent to the client.
- **Context** -- why the developer cannot answer this alone.
- **Answer** -- a placeholder until the client responds. When
  the client answers, replace `_Pending._` with the response and
  add a **Date** line.

Do not edit the question text. If the question was unclear and
the answer reveals a better phrasing, append a **Clarification**
block below the Answer.

---

## Status at a glance

| # | Theme | Blocks | Status |
|---|---|---|---|
| Q1  | Services in the new sidebar | Services doc, sidebar rework | Answered |
| Q2  | Flat vs expandable sidebar | Navigation rework | Answered |
| Q3  | Timothy Class nav placement | Youth module | Answered |
| Q4  | Young Adult module contents | Young Adult module | Answered |
| Q5  | Worship & Prayer module contents | Worship & Prayer module | Answered |
| Q6  | Special Occasions Documents tab | Special Occasions module | Answered |
| Q7  | Church Planning Timeline tab | Church Administration module | Answered |
| Q8  | Outreaches location entity | Kids Ministry module | Answered |
| Q9  | Ministry list (fixed vs editable) | Church Administration module | Answered |
| Q10 | Payment tracking real vs bookkeeping | Youth Camp, Kids Ministry, Special Occasions | Answered |
| Q11 | Payment gateway choice | Same as Q10 | Answered |
| Q12 | Budget approval chain | Special Occasions module | Answered |
| Q13 | Task assignment scope | Special Occasions, Church Planning | Answered |
| Q14 | Progress calculation rule | Discipleship confirmation, Timothy Class, Campus Ministry | Answered |
| Q15 | Discipleship "Not Started" status | Discipleship enum | Answered |
| Q16 | Grade/Year level scope | Youth Camp, Kids Ministry, Campus Ministry, Timothy Class | Answered |
| Q17 | Members / First-Timers pages | Navigation and first-timers promotion | Answered |
| Q18 | Cell Group vs Services overlap | Cell Group module | Answered |
| Q19 | Design system replacement | Every shipped page | Answered |
| Q20 | Multi-tab URL semantics | Special Occasions, Ministry Meeting, VBS | Answered |
| Q21 | Final role model | Four-role proposal, approval workflow | Answered |

**Coverage:** 21 questions, 21 answered, 0 pending.

---

## Navigation and structure

### Q1 -- Services in the new sidebar

**Question.** The mockup sidebar drops Services, Registration,
and Attendance as top-level items. Are those modules being
removed, renamed, or folded into another section? If folded,
into which?

**Context.** Services shipped in Phase 3 and is currently
top-level. Removing or folding it changes route prefixes,
breadcrumbs, and the eventual Services subsystem doc. If
Services is being removed, writing `docs/services.md` is wasted
effort.

**Answer.** Attendance nests under Services in the sidebar.

**Date.** 2026-10-16

**Clarification.** Registration's placement in the new sidebar was not addressed. Follow-up pending.

---

### Q2 -- Flat vs expandable sidebar

**Question.** The shipped sidebar has seven items. The mockup
has fourteen, with six expandable groups. Is the flat sidebar
replaced entirely by the mockup version, or is the mockup
sidebar one possible view among several?

**Context.** An expandable sidebar is a different UX pattern
from the current flat list. It changes how every route's
breadcrumb is computed and how the active state is tracked.
Affects the navigation rework effort — significant if the answer
is "replaced entirely."

**Answer.** The mockup sidebar is the final design for the entire system. The flat 7-item shipped sidebar is replaced entirely. The navigation rework target is the 14-item expandable structure shown in the mockup.

**Date.** 2026-10-16

---

### Q3 -- Timothy Class nav placement

**Question.** The Youth hub shows five sub-module cards (Youth
Camp, Youth Service, Huddle, Campus Ministry, Timothy Class),
but the expanded sidebar in the same mockup only lists four —
Timothy Class is missing. Which is correct?

**Context.** The sidebar and hub need to agree on what's inside
the Youth module. If Timothy Class is a fifth sub-module, the
sidebar expands to include it. If not, the hub card is wrong.

**Answer.** Yes - Timothy Class is included in the Youth Module. The Youth hub shows 5 sub-modules (Youth Camp, Youth Service, Huddle, Campus Ministry, Timothy Class). The sidebar sub-nav should list all 5.

**Date.** 2026-10-16

---

## Module scope

### Q4 -- Young Adult module contents

**Question.** Young Adult — listed in the sidebar, but no detail
page shown in any mockup. What are its sub-modules and their
contents?

**Context.** Without knowing what the module contains, no
scoping or estimation is possible.

**Answer.** Young Adult has three top-level sub-modules: Service, Seminar, and Partner. Under Partner there is a nested GRC. Under GRC there are two sub-items: Care group and Fellowship. The module uses the same attendance/registration pattern as Services and Fellowship.

**Date.** 2026-10-16

---

### Q5 -- Worship & Prayer module contents

**Question.** Worship & Prayer — listed in the sidebar, but no
detail page shown. Same as Q4: what are its sub-modules and
contents?

**Context.** Same as Q4.

**Answer.** Worship and Prayer has three sub-modules: Outdoor Prayer Meeting, Worship Night, and Prayer and Fasting. Same attendance/registration pattern as Services and Fellowship.

**Date.** 2026-10-16

---

### Q6 -- Special Occasions Documents tab

**Question.** Special Occasions → Documents tab — what does this
tab store? File uploads (PDFs, images)? Auto-generated
documents? Links to external files (Google Drive, etc.)?

**Context.** "Documents" could mean three different
implementations: file storage, generated PDFs, or external
links. The choice determines whether a new storage disk is
needed, whether generation logic exists, or whether a simple URL
column suffices.

**Answer.** Auto-generated documents. The Documents tab stores system-generated documents, not uploads and not external links.

**Date.** 2026-10-16

---

### Q7 -- Church Planning Timeline tab

**Question.** Church Planning → Timeline tab — what does it
show? Gantt-style bars with dependencies? Milestones only?
Free-form chronological list?

**Context.** A Gantt chart is a significant frontend component.
A milestone list is a simple table. The two are very different
effort-wise.

**Answer.** Client answered Option A. The question as sent did not use A/B/C labels -- it listed three options in sequence (Gantt-style bars with dependencies, Milestones only, Free-form chronological list). Option A most likely means the first option, but this should be confirmed before implementation.

**Date.** 2026-10-16

**Clarification (2026-10-16).** Client confirmed: "Option A" means Gantt-style bars with dependencies. The Milestones-only and Free-form chronological list interpretations are rejected.

---

### Q8 -- Outreaches location entity

**Question.** Kids Ministry → Outreaches — are the four location
cards (Wawang Pulo, Pasolo, Balubaran, Coloong) a fixed list, or
created by staff as new outreaches happen? Are more expected in
the future?

**Context.** A fixed list is a hardcoded enum. A dynamic list is
a new table with CRUD, plus the frontend to manage it.

**Answer.** Dynamic. Locations are created by staff. An Add Outreach button on the Outreaches hub lets staff add new locations. The four shown in the mockup (Wawang Pulo, Pasolo, Balubaran, Coloong) are seed data.

**Date.** 2026-10-16

---

### Q9 -- Ministry list (fixed vs editable)

**Question.** Church Administration → Ministry Meeting — the
seven ministries shown (Worship, Technical, Multimedia, Social
Media, Finance Team, Light Kids, Young-adult) are fixed or
editable? Who manages the list — admins only, or any staff?

**Context.** Fixed is a hardcoded list. Editable is a table
plus a management page. Permissions on the edit action depend on
the answer to "who manages."

**Answer.** Add, edit, and delete are all allowed. Managed by Authorized staff. The seven shown are seed data.

**Date.** 2026-10-16

---

## Cross-cutting features

### Q10 -- Payment tracking real vs bookkeeping

**Question.** Payment tracking — is this real payment processing
(Stripe, GCash, Maya, PayPal) or internal bookkeeping only? The
mockup shows "Paid / Partial / For Payment" badges but no
checkout screen. Does a participant pay online, or does staff
mark payment received?

**Context.** Real online payment means integration with a
payment gateway, handling refunds, webhooks, and compliance.
Bookkeeping means a status column and a manual toggle. Very
different scope. This is the single largest gating question for
the mockup expansion.

**Answer.** Remove payment tracking entirely. No gateway integration. No per-participant payment status. The Paid / Partial / For Payment badges shown in the mockup are not built. Budget becomes a simple record field -- a name and an amount, used for select special occasions such as the church anniversary.

**Date.** 2026-10-16

---

### Q11 -- Payment gateway choice

**Question.** If real payments: which gateway? The Philippine
market typically implies GCash, Maya, or a card processor. No
gateway is currently configured in WDEMS.

**Context.** The gateway choice affects payment flow, refunds,
fees, testing, and compliance. Applies only if Q10 = "real".

**Answer.** No gateway applies. Payment tracking is removed. See Q10.

**Date.** 2026-10-16

---

### Q12 -- Budget approval chain

**Question.** Budget Approval status ("For Approval") — who
approves? Is there a role that must approve expenses, or is
"For Approval" just a state the organizer sets for themselves?

**Context.** An approval chain is a workflow with roles and
notifications. A self-set status is a dropdown. The two are
different feature scopes.

**Answer.** No budget approval chain. Budget is recorded, not approved. The For Approval status shown in the mockup is not built.

**Date.** 2026-10-16

---

### Q13 -- Task assignment scope

**Question.** Tasks — can a task be assigned to any user, or
only to users with a specific role? The mockup shows a name in
the "Assigned To" column.

**Context.** Any-user assignment needs a user picker and
per-user notifications. Role-only assignment is simpler.

**Answer.** Tasks can be assigned to any registered user. Not restricted by role.

**Date.** 2026-10-16

---

### Q14 -- Progress calculation rule

**Question.** Progress calculation — what makes a session
"applicable"? Total sessions in the batch? Sessions the
participant was enrolled for? Do present + excused + absent all
count, or only present?

**Context.** Discipleship already implements a default:
applicable = sessions between enrolled_at and today; attended =
present + excused. The client should confirm this or override.
If the wider product needs a different rule, the change is a
one-line edit inside `DiscipleshipProgressService::progress()`.

**Answer.** Two statuses only -- Present and Absent. Excused is dropped. Applicable sessions = all sessions in the batch, not bounded by enrollment date. Only Present counts toward progress; Absent does not. Changes shipped Discipleship behavior in three ways. See the Migration note below.

**Migration note.** The shipped Discipleship module (Phase 5E/5F) uses three statuses (present, absent, excused), bounds applicable sessions to enrollment date, and counts excused as attended. This answer changes all three. Two follow-up questions before the code changes:

1. Existing attendance rows with status = excused -- convert to absent (preserve history as absence), delete the rows, or leave them? Recommendation: convert to absent.
2. Sessions before enrollment -- under the new rule these count as applicable. A mid-batch joiner will show partial progress immediately. Confirm this is intended.

**Date.** 2026-10-16

---

### Q15 -- Discipleship "Not Started" status

**Question.** Discipleship status "Not Started" — is this a
sixth status value, or a UI label for the initial state before a
participant has been marked as started? The current
implementation has five values: Enrolled, In Progress,
Completed, Withdrawn, On Hold.

**Context.** A sixth enum value changes the schema and status
transition rules. A UI label is a rendering choice.

**Answer.** Not Started is dropped. Not added as a sixth enum value. Not rendered as a UI label. Enrollments that have no attendance yet just show their enrolled status as-is.

**Date.** 2026-10-16

---

## Data and structure

### Q16 -- Grade/Year level scope

**Question.** Grade/Year level — does this apply to participants
globally (add a column to the participants table), or only per
event (Youth Camp participants have grades, but adult
community-run participants don't)?

**Context.** A global grade column touches every existing
participant. A per-event approach keeps it scoped to the modules
that need it. Affects migration shape and future reporting.

**Answer.** Option A -- Global Participant Information. A column on the participants table, not per-event. Add edit and delete action functions to the participant information UI.

**Original (Tagalog).** Need lang sa particular event. Option A. Global Participant Information. Lagay nalang function na actions, for edit and delete.

**Date.** 2026-10-16

---

### Q17 -- Members / First-Timers pages

**Question.** Should the existing Members and First-Timers
tables (built in an earlier phase, currently backend-only) get
top-level pages matching the mockup sidebar?

**Context.** The data exists but has no UI. Adding pages is
straightforward once confirmed. Also unblocks the deferred
first-timer promotion feature.

**Answer.** Yes -- dedicated pages for Members and First-Timers.

**Original (Tagalog).** Yes sariling pages for Members and First-timers

**Date.** 2026-10-16

---

### Q18 -- Cell Group vs Services overlap

**Question.** Cell Group — listed in the mockup sidebar, no
detail page shown. Is this the same concept as the existing
church Services module (which already has a "Youth" service
type), or is it a distinct concept that needs its own domain
model?

**Context.** Reusing Services is cheap. A new domain model is a
full module's worth of work. Note that this overlaps with Q4
from the original doc-06 question set (Cell Group placement).

**Answer.** Remove Cell Group from scope entirely. Not a module, not a category, not a sidebar item.

**Original (Tagalog).** Tanggalin na Cell Group

**Date.** 2026-10-16

---

## Visual and interaction

### Q19 -- Design system replacement

**Question.** The mockups use a different visual style — flat
cards, lighter palette, different iconography — from the shipped
WDEMS design (glass-panel surfaces, lime accent, current icon
set). Is the design system being replaced, or is the mockup
style just a prototype artifact?

**Context.** Replacing the design system means restyling every
shipped page. Keeping the current system means applying it to
the new pages. Very different amounts of work. Affects every
existing page's `resources/css/app.css` and every `.tsx` color
class.

**Answer.** Keep the current WDEMS design system. Apply it consistently -- every design feature should be connected across all pages.

**Original (Tagalog).** Current WDEMS. Make every design features connected.

**Date.** 2026-10-16

---

### Q20 -- Multi-tab URL semantics

**Question.** Multi-tab detail pages — several mockups (Special
Occasions, Ministry Meeting, Vacation Bible School) show
multi-tab detail pages. Any preference for how tabs work —
URL-persisted (each tab is a bookmarkable URL) or local state
(tabs are just a UI convenience and the URL doesn't change)?

**Context.** URL-persisted tabs are bookmarkable and shareable.
Local-state tabs are simpler but a refresh always returns to the
first tab. A new UX pattern either way — no existing page in
the codebase uses tabs.

**Answer.** Option A -- URL-persisted tabs. Each tab is a bookmarkable URL.

**Original (Tagalog).** Option A. Url persisted tabs

**Date.** 2026-10-16

---

## Changelog

- **2026-10-16** -- File created. Twenty questions from
  `docs/church-events/09-mockup-scope-inventory.md` transcribed
  with context and status tracking. All answers pending.

- **2026-10-16** -- 14 answers recorded from the client (Q2-Q15).
  Two flagged for clarification: Q7 (Option A ambiguous -- the
  question was sent without A/B/C labels) and Q14 (migration path
  for existing excused rows before the code changes). Remaining
  unanswered: Q1, Q16, Q17, Q18, Q19, Q20.

---

### Q21 -- Final role model

**Question.** Should WDEMS adopt the four-role model proposed on
2026-10-16 (System Administrator, Church Leadership, Event
Organizer, Attendance Encoder), or remain with the current two-role
structure (Admin, Staff)?

**Context.** The four-role model was proposed via a ChatGPT output
shared by the client. It implies an approval workflow (Church
Leadership approves events before they go live) and per-event
scoping for attendance encoders. Both are significant additions to
the current architecture: a new approval state on events, a new
event_user pivot table, three new middleware gates, and a data
migration for existing Staff users.

**Answer.** Two roles confirmed. The four-role model is rejected.
WDEMS stays with Admin and Staff.

No approval workflow is required. Events move to Registration Open
when the organizer is ready, with no separate sign-off step.

**Date.** 2026-10-16
