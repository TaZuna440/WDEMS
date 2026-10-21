# WDEMS — UI Restructure Plan

**Status:** Active plan. Approved by client direction on 2026-10-17.
**Last updated:** 2026-10-17 (Phases 1-3 shipped; Youth in progress)
**Audience:** Developers working on the sidebar/events/dashboard
restructure. Also useful to AI sessions picking up this work.

**Purpose:** Define the plan for restructuring WDEMS around a
5-item sidebar, unifying church event creation through a
category-card front door, and rebuilding the dashboard as a
reports summary. This is separate from the visual design work
that removed the shell image (see `docs/design-system.md`
Corrections 2026-10-17).

---

## 0. Progress

| Phase                            | Status                    | Commit    | Notes                                                |
| -------------------------------- | ------------------------- | --------- | ---------------------------------------------------- |
| 1 — Sidebar restructure          | Complete                  | `42e9930` | 7 items → 5; church modules under `/events/church/*` |
| 2 — Events page + category cards | Complete                  | `dfecbad` | Church tab shows the nine category cards             |
| 3 — Create Event branching       | Complete                  | `fd0be9e` | Entry page splits by event kind                      |
| —                                | Fix: card links, back nav | Complete  | `5373ec5`                                            | Phase 1 sweep missed template literals |
| 4 — Six new church modules       | In progress               | `676d725` | Youth hub + Youth Service shipped; 5 more categories |
| 5 — Dashboard rebuild            | Not started               | —         | 2 sessions                                           |

**Phases 1-3** shipped the coherent visual arc the client
asked for: sidebar → Events → Church cards → category landing
→ create flow → back.

**Phase 4** and **Phase 5** remain. Phase 4 is the long tail —
six separate module builds, one session per module.

**Known gap.** The wizard's localStorage checkpoint uses a
shared key (`wdems-wizard-create`) across all three create
routes. A user who abandons a Community Run draft and starts a
Church draft gets the Community Run data restored into the
Church wizard. Fix is small (unique storage keys per route)
but is not blocking.

**Resolved.** The storage-key gap was fixed in commit
`41766ba`. Each create route now writes to its own key
(`wdems-wizard-create-community-run`,
`wdems-wizard-create-church-{category}`). The edit flow was
already isolated with `wdems-wizard-edit-{id}`.

---

## 0.1 Youth sub-module status

Youth is five sub-modules, not one. Each is its own domain.
Only Youth Service is unblocked.

| Sub-module      | Status   | Blocked by                                                    |
| --------------- | -------- | ------------------------------------------------------------- |
| Youth Service   | Complete | —                                                             |
| Youth Camp      | Blocked  | Payments removed by client Q10; needs a redesign decision     |
| Huddle          | Blocked  | Groups concept (Phase B) not built                            |
| Campus Ministry | Blocked  | Global grade column on `participants` (Q16 answer, not built) |
| Timothy Class   | Blocked  | Discipleship 5D (sessions) + 5E (attendance) not built        |

**Phase 4 revised estimate.** The original "6 sessions, one per
module" assumed each of the six church categories was one
module. The mockup detail shows otherwise:

- **Youth** = 5 sub-modules (1 shipped)
- **Kids Ministry** = 2 sub-modules (VBS, Outreaches with dynamic locations)
- **Young Adult** = 3 sub-modules (Service, Seminar, Partner → GRC → Care Group + Fellowship)
- **Worship & Prayer** = 3 sub-modules
- **Special Occasions** = 1 hub with 6 tabs (Details, Program, Roles, Budget, Tasks, Documents)
- **Church Administration** = 2 sub-modules with 6-tab detail pages

Realistic estimate: **15–25 sessions** for full Phase 4.

**Two strategic options for Phase 4:**

1. **Build unblocked sub-modules first.** Worship & Prayer sub-module
   1 (Outdoor Prayer Meeting) is closest to what shipped. Young Adult
   sub-modules may also be unblocked depending on their shape.
2. **Build Phase B first.** The shared cross-cutting features
   (groups, grade levels, sessions, payments-equivalent) unblock
   multiple sub-modules at once.

Option 2 has higher leverage but is larger. Option 1 keeps shipping
visible progress.

---

## 1. The client's target

Three visible changes:

### 1.1 Sidebar — seven items become five

| Removed as top-level | New home                       |
| -------------------- | ------------------------------ |
| Services             | Events → Church → Services     |
| Fellowship           | Events → Church → Fellowship   |
| Discipleship         | Events → Church → Discipleship |

New sidebar:

    Dashboard
    Events
    Registration
    Attendance
    Reports

### 1.2 Events page — Community Run / Church split

The current list view becomes one of two tabs. The Church tab
presents nine category cards. Picking a card routes to that
category's page.

### 1.3 Create Event — first pick the kind

The wizard no longer opens directly. First screen: **Community
Run** or **Church Event**.

- Community Run → existing 4-step wizard (Details, Schedule,
  Venue, Extras) — unchanged
- Church Event → nine category cards → category page → create form

---

## 2. What stays untouched

These were not in scope for the restructure and must not be
disturbed:

- The running-event wizard (Details, Schedule, Venue, Extras)
- The registration module (form builder, public flow, monitoring)
- The attendance module (three-mode workspace, codes, walk-ins)
- The reporting module
- The event lifecycle state machine
- Authentication, 2FA, roles
- The three shipped church modules' data shapes: `service_occurrences`,
  `fellowship_events`, `discipleship_programs`. These keep their
  own tables and their own admin pages. Only their **navigation
  entry point** changes.

---

## 3. Five phases

Each phase is independently shippable. Phases 1–3 are a coherent
arc that leaves the app fully working at every step. Phases 4–5
extend the shape.

### Phase 1 — Sidebar restructure

**Goal:** Reduce sidebar to 5 items. Move Services, Fellowship,
Discipleship entry points into Events → Church.

**Changes:**

- `app-sidebar.tsx` — remove the three top-level entries
- New routes: `/events/church/services`, `/events/church/fellowship`,
  `/events/church/discipleship` — each renders the existing landing
  page
- Redirects from `/services`, `/fellowship`, `/discipleship` to
  their new URLs
- Breadcrumbs updated on the moved pages
- Wayfinder regenerated after route changes

**What does NOT change:**

- The landing page components themselves
- The three modules' internal pages (`/services/attendance`,
  `/fellowship/attendance`, `/discipleship/programs/{slug}`, etc.)
  — those keep their URLs
- No data migration. No new tables.

**Estimate:** 1 session.

### Phase 2 — Events page tabs + category cards

**Goal:** Church tab on the Events page shows the nine category
cards.

**Changes:**

- `events/index.tsx` — the Church tab content becomes the nine
  cards. The existing filter chips (`All | Running | Church`)
  stay.
- Nine cards, each routing to:
    - Services / Fellowship / Discipleship → the moved landing pages
      from Phase 1
    - Youth / Kids / Young Adult / Worship & Prayer / Special
      Occasions / Church Administration → placeholder list pages
      (Phase 4 fills these in)

**What does NOT change:**

- The running-event list (Community Run tab)
- The event table rendering

**Estimate:** 1 session.

### Phase 3 — Create Event branching

**Goal:** `/events/create` becomes a two-step flow.

**Changes:**

- New first screen: two large cards, "Community Run" and "Church
  Event"
- Community Run → renders the existing wizard unchanged
- Church Event → renders the nine category cards
    - Services / Fellowship / Discipleship → redirect to their
      existing creation flows (occurrence dialog, fellowship event
      dialog, batch dialog)
    - Six unbuilt categories → render the unified church event
      wizard (already built in the last session) with
      `church_category` preset

**What does NOT change:**

- The wizard internals (4-step for Community Run, 3-step for Church)
- The `ChurchCategory` enum
- `EventRequest` derivation logic

**Estimate:** 1 session.

### Phase 4 — Six new church category modules

**Goal:** Youth, Kids Ministry, Young Adult, Worship & Prayer,
Special Occasions, Church Administration each get a working list
page and use the unified workflow.

**Unified workflow for each:**
Event Details → Registration → Record Participants → Attendance → Reports

**Per-category work:**

- A list page showing events with that `church_category` value
- "Create Event" button → the unified church wizard with category
  preset
- Uses the existing Registration / Attendance / Reports modules
- Optionally: a small category-specific config (e.g., Worship &
  Prayer has three sub-types per client Q5; Young Adult has three
  sub-types per Q4; Kids Ministry has outreach locations per Q8)

**Estimate:** 1 session per category × 6 = 6 sessions.

**Not in this phase** (deferred to keep scope honest):

- Gantt timeline for Special Occasions (Q7)
- Auto-generated documents for Special Occasions (Q6)
- Ministry meeting sub-module for Church Administration

### Phase 5 — Dashboard rebuild + three thin sidebar pages

**Goal:** Dashboard becomes the reports-summary mockup. Registration
/ Attendance / Reports sidebar items get landing pages.

**Changes:**

- `dashboard.tsx` — rebuild with widgets:
    - 4 stat cards (Total Events, Total Registrations, Total
      Attendance, Active Categories)
    - Upcoming Events list
    - Church Events Categories panel
    - Recent Events list
    - Event Statistics chart (Community Run + Church Events only —
      Fun Run removed)
    - Calendar widget
    - Quick Actions panel
- New page `/registrations` — global list of events with
  registration open (thin wrapper over the existing
  RegistrationMonitorController::index content)
- New page `/attendance` — global list of events where attendance
  can be recorded today
- New page `/reports` — global list of completed events

**Estimate:** 2 sessions.

---

## 4. Total scope

| Phase                 | Estimate   | Cumulative |
| --------------------- | ---------- | ---------- |
| 1 — Sidebar           | 1 session  | 1          |
| 2 — Events tabs       | 1 session  | 2          |
| 3 — Create branching  | 1 session  | 3          |
| 4 — Six modules       | 6 sessions | 9          |
| 5 — Dashboard + pages | 2 sessions | 11         |

Phases 1–3 land the visible shape and take 3 sessions. Phase 4
is the long haul. Phase 5 finishes the client's mockup.

---

## 5. Sequencing and dependencies

**Phase 1 → Phase 2 → Phase 3** must be in order. Each builds on
the previous.

**Phase 4** depends on Phase 3 (needs the unified church wizard
routing to be in place). Can be split across sessions freely.

**Phase 5** is independent of Phases 2–4 but easier after them
because the dashboard aggregates from all event kinds.

---

## 6. Open questions deferred

These are known but not blocking:

1. **Registration sidebar page** — is it a global list, or does it
   route to a specific event's registration? Assumed global in
   Phase 5.
2. **Attendance sidebar page** — same question. Assumed global.
3. **Special Occasions Gantt + documents** — client answered Q7
   (Gantt-style) and Q6 (auto-generated docs). Not in Phase 4.
   Revisit as its own phase.
4. **Mockup's "Church Events Categories" panel** — the mockup
   shows more sub-items than our nine. Assumed our nine stand.

---

## 7. What changes in the docs

As the phases ship, these docs need updating:

| Doc                                            | When          | What                                |
| ---------------------------------------------- | ------------- | ----------------------------------- |
| `docs/project-definition.md` §7                | After Phase 1 | Sidebar list becomes 5 items        |
| `docs/project-definition.md` §8                | After Phase 4 | Six modules leave the "scoped" list |
| `docs/project-definition.md` §11               | After Phase 5 | Dashboard open question removed     |
| `docs/architecture.md`                         | After Phase 1 | Sidebar section updated             |
| `docs/church-events/10-client-decision-log.md` | After Phase 1 | Q1 finalized                        |

---

## 8. Cross-references

| Document                                           | What it covers                               |
| -------------------------------------------------- | -------------------------------------------- |
| `docs/project-definition.md`                       | The overall project definition               |
| `docs/design-system.md`                            | The visual contract (unchanged by this plan) |
| `docs/church-events/07-scope-and-task-division.md` | Original church-events task breakdown        |
| `docs/church-events/09-mockup-scope-inventory.md`  | Client mockup inventory                      |
| `docs/church-events/10-client-decision-log.md`     | Answers to Q1–Q21                            |

---

_End of plan._

## Running Events audit addendum — 2026-10-21

Running Events now uses only Running/Church tabs, with no mixed All tab. Attendance exposes Check-in/Walk-in; paper encoding lives under Walk-in. Report and monitor UI follow the current running-events audit. This does not claim church expansion UI is implemented.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.
