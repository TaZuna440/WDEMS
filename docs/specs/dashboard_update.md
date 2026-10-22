# WDEMS — Dashboard Update

**Date:** 2026-10-21 · **Requirements status:** Fully planned and decided. **Implementation status:** Partially implemented locally; manual acceptance remains pending (status snapshot 2026-10-22 below).
**Scope:** The staff Dashboard (`/dashboard`). This document is the complete record of the dashboard redesign: the current state, every feature planned, the layout, the calendar behavior, all decisions, and the build staging. It consolidates the dashboard addendum in `WDEMS_MASTER_PROJECT_DOCUMENT.md` and the interactive prototypes (`~/workspace/wdems-dashboard-prototype/prototype-v3.html`).

---

## 1. Why this update exists

The current dashboard is not a dashboard — it is a single alert list.

Verified against the code at HEAD `5755c75`:

- `DashboardController` loads **running events only** (`event_kind = KIND_RUNNING`) and produces two widgets: **Action Items** (Needs Attention) and **Upcoming** (next 3 future running events).
- `recentActivity` is hardcoded to an **empty array** — the feed renders nothing.
- There are **no stat cards, no totals, no charts, and no church content at all** — no church events, no today's services, no summaries, no duplicate/review indicators.
- The separate admin dashboard (`admin/dashboard.tsx`) is a bare stub ("Welcome to WDEMS").
- The repo's own UI restructure plan (`docs/ui-restructure-plan.md`, **Phase 5 — Dashboard rebuild**) specified this work — stat cards, Church Events Categories panel, Recent Events, statistics chart, calendar widget, Quick Actions — and Phase 5 was **never started**.

Mark's verdict on the current page (2026-10-21, with screenshot): _"it is not appropriate to call as dashboard."_ It answers only "what's wrong." A dashboard must also answer **"how are we doing"** (totals) and **"what's ahead / what just happened"** (upcoming, calendar, activity).

Nothing on the current page is thrown away: the existing Needs Attention feed survives as one panel of the new design.

---

## 2. The design: a split dashboard

**One Dashboard route, two complete dashboards.** A segmented switcher under the header — **Running Events | Church Events** — flips between two self-contained dashboards. This is the participant separation rule (Master Document D-10) expressed as UI: the two sides have different workflows and different participant pools, so their numbers are never blended.

Rules of the split:

- Each side has **its own** stat cards, Needs Attention, Coming Up, Calendar, and Recent Activity. No widget mixes running and church data, and no total is ever summed across sides.
- Each side wears **one accent color**: **lime = Running**, **violet = Church** (switcher state, stat deltas, calendar dots, badges).
- **Remembered side (DECIDED):** the dashboard persists each user's last-viewed side and opens there. A first-ever visit defaults to **Running**. A deep link may force a side via query parameter (e.g. `?side=church`) without overwriting the remembered preference.
- The page reuses the app's existing visual language — sidebar layout, white/glass panels, uppercase panel labels, urgency groups — no new design system.
- **Every number is a link** to its filtered source page (stat card → filtered Events list; Needs Review count → review queue; category chip → Events · Church tab filtered by module; calendar day → that day's events).

---

## 3. Layout

An **aligned row grid** — not independent stacked columns (the first prototype stacked panels single-file "like a queue"; Mark flagged it, and the grid below is the fix). Panels paired in a row stretch to the same height; on phone-sized screens only, the grid stacks to one column.

```
┌──────────────────────────────────────────────────────────────┐
│ Dashboard            [ Running Events | Church Events ]      │
│ date · "What needs your attention right now."                │
│ [Quick Actions: Create · Open Registration · Attendance…]    │
├───────────────┬───────────────┬───────────────┬──────────────┤
│  Stat card 1  │  Stat card 2  │  Stat card 3  │  Stat card 4 │   (per side)
├───────────────────────────────┬──────────────────────────────┤
│  NEEDS ATTENTION              │  COMING UP                     │   row 1
├───────────────────────────────┼──────────────────────────────┤
│  RECENT ACTIVITY              │  CALENDAR                      │   row 2
├───────────────────────────────┼──────────────────────────────┤
│  Running: EVENT STATISTICS    │  (full-width band)             │   row 3 (Running)
│  Church:  PARTICIPANT HISTORY │  CHURCH CATEGORIES           │   row 3 (Church)
└───────────────────────────────┴──────────────────────────────┘
```

- Left column ≈ 7/12, right rail ≈ 5/12. The **right rail is the "future" column**: Coming Up on top, Calendar directly below it (placement DECIDED by Mark).
- Header row: title, today's date, subtitle, switcher on the right; Quick Actions beneath.
- Empty states reuse the current patterns: "All clear." (Needs Attention), "No upcoming events." (Coming Up), "No activity yet." (Recent Activity).

---

## 4. Shared features (both sides)

### 4.1 Quick Actions

Buttons in the header area, contextual to the active side: **Create Event** (side-specific label: "+ Create Running Event" / "+ Create Church Event"), **Open Registration**, **Record Attendance**, **Reports**.

### 4.2 Needs Attention (kept + extended)

The existing action-items feed, preserved with its semantics and extended per side:

- Urgency groups in order: **NEEDS REVIEW → OVERDUE → URGENT → ACTION → WAITING**, each with its colored dot and the item's action button (e.g. "Mark as completed", "Configure event", "View event").
- Reasons carry **live counts** (e.g. "Registration open — 128 registered · event in 4 days" instead of a bare status line).
- Running items derive from event status + days-until as today (overdue ongoing events, drafts near their date, open/closed registrations, ongoing events awaiting completion).
- Church items (with the Phase 2 church build): today's occurrences with participants still Unmarked, events whose summary has not been reviewed, church events whose registration link is not yet opened.

### 4.3 Needs Review group (new; depends on the duplicate hold, Phase 1 B-1)

Top group inside Needs Attention, per side:

- Count of **held likely duplicates** awaiting organizer action, with a "Review queue" button to the review page.
- Before the hold ships, this group shows the **suspicious-pairs count** from the existing Possible Duplicates audit instead.
- Held registrations are excluded from every count on the dashboard until resolved (they are not confirmed registrations).

### 4.4 Coming Up

The next upcoming events **of the active side only**, each with date, status (and module, on the church side), and a "View all" link to the Events page. Running rows keep their status tags (OPEN / CLOSED / DRAFT).

### 4.5 Calendar (right rail, below Coming Up)

Month grid for the active side:

- **Today** is filled with the side's accent.
- **Event days carry dots** in the side's color; **two events on one day = two dots**.
- **Hover tooltip (feature added by Mark, 2026-10-21):** hovering a dotted day shows a small card with the date and **the names of that day's events** (e.g. "Sunday Service — AM", "Sunday Service — PM"). On touch screens, tapping the day toggles the same card. Tooltips near the panel's edges shift inward so they never clip.
- **Clicking a day** opens that day's events.
- Month navigation (previous/next) included; the calendar shows only the active side's events.

### 4.6 Recent Activity (implement the feed)

Replaces the hardcoded empty array. Newest first, with relative timestamps ("2m ago"). Per side, drawn from that side's records only. Event types:

- New registration (with participant name + event)
- Check-in / attendance recorded (with counts)
- Held duplicate created / released / resolved
- Registration opened or closed
- Event finalized / completed
- (Church) Attendance encoded, summary viewed, first-timer registered via the public link, discipleship batch opened

---

## 5. Running dashboard features

### 5.1 Stat cards (running pool only)

1. **Running Events** — total running events, with sub-line "N open for registration".
2. **Registrations (running pool)** — total registrations across running events, with today's delta ("+18 today").
3. **Total Attendance** — total recorded attendance, with the **show rate** (attended ÷ registered).
4. **In Check-in Window Today** — how many running events are inside their attendance window right now, naming the next one ("JNT · opens 5:00 AM").

### 5.2 Event Statistics (full-width band, bottom row)

Series figures across recent Community Runs: registrations for the last 4 runs, average show rate, paper-encoded (staff-attested) walk-ins, first-time runners this month. (The chart form of this panel is DASH-3; the band ships first with the numbers.)

---

## 6. Church dashboard features

### 6.1 Stat cards (church pool only)

1. **Church Events This Month** — with sub-line "9 modules active".
2. **Church Participants (church pool)** — total participants on the church side, with the weekly delta ("+12 this week").
3. **Attendance Recorded Today** — with sub-line naming what was encoded ("3 services encoded").
4. **Unmarked Remaining** — participants still Unmarked across today's events, naming the largest source ("Sunday Service · AM"). This is the number ministry leaders chase at the end of a service day.

### 6.2 Participant History (church only; from decision D-11)

The church side monitors its own people's history, so the church dashboard carries a panel the running side does not have: **top attending participants in the church pool** — name, total attendance count, and the types of church events joined (e.g. "Juan Dela Cruz — 14 attendances · Services, Fellowship"). Each row links to that participant's church-side history. Running participants never appear here, and vice versa.

### 6.3 Church Categories

The nine modules as count chips — Services, Fellowship, Discipleship, Youth, Kids, Young Adult, Worship & Prayer, Church Administration, Special Occasions — each with its event count and linking into the Events · Church tab filtered by that module. (From the Phase 5 plan in `docs/ui-restructure-plan.md`.)

---

## 7. Data and implementation notes

- `DashboardController` is extended (or split per side) to serve the active side's payload only; the switcher swaps payloads, it does not blend them.
- All counts are **scoped by kind/pool**: running figures come from running events and the running participant pool; church figures from church modules and the church pool — consistent with the side-scoped identity work in Phase 2 (B-4). Until pool scoping lands, church figures must be computed from church-module records only, never from a global participant count.
- Needs Attention keeps the existing `buildActionItems` semantics for running; church items are a parallel builder with the same item shape (urgency, reason, action).
- The remembered side is persisted per user (user preference), defaulting to Running on first visit; `?side=` overrides for that visit only.
- Recent Activity needs a real source: either an activity log table or a derived union of timestamped records (registrations, attendances, held items, event status changes). The derived union is acceptable for DASH-1; a log table is the durable form.
- Accessibility/responsive: switcher is a proper tab control; tooltips are reachable by tap and keyboard focus, not hover alone; the grid stacks only at phone widths.

---

## 8. Build staging

- **DASH-1 (can ship early):** split switcher + remembered side, per-side stat cards, Needs Attention (existing running items + live counts), Coming Up per side, Recent Activity feed, Quick Actions, Church Categories panel, aligned grid layout.
- **DASH-2 (after the Phase 1 hold B-1 and the Phase 2 church build):** Needs Review group with held counts per side, church Needs Attention items tied to the new Registration/Attendance, Participant History panel.
- **DASH-3:** Event Statistics as a chart, calendar month navigation polish, and a decision on the admin dashboard stub (give it real admin widgets or remove the route).

**Dependencies:** DASH-2's review counts wait on the hold (B-1); church stat cards and history wait on side-scoped church data (B-4) and the church Registration build (B-5…B-8). DASH-1 has no blockers.

---

## 9. Acceptance checklist

- [ ] Switcher flips between two complete dashboards; no figure anywhere mixes running and church data.
- [ ] Reopening the dashboard lands on the last-viewed side; first visit lands on Running; `?side=church` opens Church without changing the remembered side.
- [ ] Running stat cards show: events (+ open count), registrations (+ today's delta), attendance + show rate, events in check-in window today.
- [ ] Church stat cards show: events this month (+ active modules), church participants (+ weekly delta), attendance recorded today, unmarked remaining.
- [ ] Needs Attention preserves all current running items and groups, now with live counts in reasons; church items appear on the church side.
- [ ] Needs Review shows the held count per side and links to the review queue; held registrations are excluded from all dashboard counts.
- [ ] Coming Up lists only the active side's events; "View all" lands on the matching Events view.
- [ ] Calendar sits in the right rail below Coming Up; today is highlighted; dotted days show name tooltips on hover/tap; two events show two dots; clicking a day opens that day's events.
- [ ] Recent Activity shows real, side-scoped events with timestamps (no empty feed).
- [ ] Church Participant History lists church-pool attendees with counts and event types joined; Church Categories chips link to filtered module views.
- [ ] Layout is the aligned row grid at desktop widths and stacks only at phone widths; empty states match §3.

---

## 10. Decisions recorded for the dashboard

| #   | Decision                                                                                                         | By              | Date       |
| --- | ---------------------------------------------------------------------------------------------------------------- | --------------- | ---------- |
| 1   | The dashboard will be **split**: one route, Running \| Church segmented switcher, each side a complete dashboard | Mark            | 2026-10-21 |
| 2   | Calendar placement: **right rail, below Coming Up**                                                              | Mark            | 2026-10-21 |
| 3   | Default side: **remember the last side viewed** (first visit: Running; `?side=` deep link overrides per visit)   | Mark            | 2026-10-21 |
| 4   | Calendar days with events show a **hover/tap tooltip listing the event names**                                   | Mark            | 2026-10-21 |
| 5   | Layout is an **aligned row grid**; independent stacked columns rejected ("like in queue")                        | Mark (feedback) | 2026-10-21 |
| 6   | Totals are never blended across sides; church gets a Participant History panel, running gets Event Statistics    | From D-10/D-11  | 2026-10-21 |

**References:** Master Project Document, Dashboard Addendum · `docs/ui-restructure-plan.md` Phase 5 · interactive prototype `~/workspace/wdems-dashboard-prototype/prototype-v3.html` (v1 single-page draft and v2 grid draft kept alongside).

---

## Implementation status snapshot — 2026-10-22

This dated snapshot reports current code status; it does not replace the
requirements or claim browser/release acceptance.

- Implemented locally: remembered Running/Church side, visit-only `?side=`
  override, side-scoped stat payloads and links, side-aware Quick Actions,
  Needs Review counts, Church unmarked-today actions, Coming Up, Church
  Categories, D-11 top-attendee history, and a Running Community Run
  statistics band.
- Implemented locally: derived Recent Activity from registrations, holds,
  attendance, event creation, and review resolutions; the feed is not a
  durable audit log and does not yet cover every lifecycle/verification
  event listed in §4.6.
- Implemented locally: month navigation, side-scoped event dates, multiple
  dots, event-name details on hover/focus/tap, and a date-filtered Events
  destination. Manual checks for tooltip edge positioning, keyboard flow,
  responsive layout, and visual alignment remain pending.
- Remaining: verify every stat/action destination against the final filtered
  source workflow; complete browser/accessibility acceptance; decide whether
  the Running statistics chart is a required DASH-3 deliverable or should be
  explicitly deferred.
- Automated verification snapshot: `php artisan test` — 980 passed, 3
  skipped (4,320 assertions); `corepack pnpm check`, Pint, and
  `git diff --check` pass. TypeScript has 11 diagnostics in five unrelated
  existing UI files plus a generated Wayfinder DashboardController route
  typing diagnostic; PHPStan remains non-clean with baseline/stale-suppression
  debt and DashboardController typing findings. Remote CI and production
  acceptance are unverified.
