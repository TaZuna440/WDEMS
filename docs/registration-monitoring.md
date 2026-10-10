# WDEMS — Registration Monitoring

**Audience:** Whoever implements, modifies, or reasons about the
monitoring surface for open registrations.

**Purpose:** Describe the monitoring feature. This is a spec —
nothing here describes current code. When the feature lands, this
doc converts to a reference.

**Status:** Planned. No code exists yet.

**Related:**
- `docs/participant-identity.md` — the identity model that makes
  "returning participant" flags meaningful
- `docs/participant-identity-and-monitoring-plan.md` — the phased build plan
- `docs/registration.md` — the registration feature this monitors

---

## 1. The problem this solves

When an organizer opens registration, they have no way to see what is
happening. Submissions land in `registrations` and are visible only
via a direct query, or by clicking through to the attendance page —
which is a work-in-progress view, not designed for the monitoring
use case.

The organizer's questions during the registration window:

- How many people have registered so far?
- Who just registered? Is the pace picking up?
- Is this a returning participant or a first-timer?
- Did anything suspicious come in?

None of them are answerable today without opening a terminal.

## 2. The decision — two pages, one badge, five actions

Monitoring has three building blocks:

- **A landing page** listing every event with open registration. The
  organizer lands here the moment they click **Open Registration**.
- **A per-event page** showing the live feed of submissions, with
  filters, rate indicators, and per-registration actions.
- **A returning-participant badge** on each submission, resolved
  against the participant identity model.

Card-based layout, polling every 10 seconds, action menu per
submission, read-only after the registration window closes.

## 3. Where it lives — routes

| Route | Purpose |
|---|---|
| `GET /registrations/monitor` | Landing — all events with open registration |
| `GET /registrations/monitor/{event}` | Per-event feed |

Both under the existing `auth`, `verified.or.admin`, `device.trusted`
middleware group. Any logged-in staff member sees any event's
monitor — WDEMS is single-organizer, no per-creator scoping.

**Entry point after Open Registration.** The
`POST /events/{event}/open-registration` action currently redirects
back to the event show page. After this feature lands, it redirects
to `/registrations/monitor/{event}` instead. The organizer opens
registration and lands immediately on the live feed.

**Entry point from the queue.** The `/registrations` page — the
queue with three sections — currently lists open events without an
action. A **Monitor** link is added on each open-event row. Clicking
it goes to `/registrations/monitor/{event}`.

## 4. The landing page — `/registrations/monitor`

**Header:** Registration Monitor.

**Below:** a flat list of event cards, one per event with
`status = registration_open`. Sorted by most recent submission first
— the event that just got a submission rises to the top. Events with
no submissions sort by `registration_start` descending.

Each card:

    ┌────────────────────────────────────────────────┐
    │ Orca Community Run                    [ OPEN ] │
    │ 2026-10-31 · BGC                               │
    │                                                │
    │ 42 registered    +5 in the last hour           │
    │ Last submission: 3 minutes ago                 │
    │                                                │
    │                                    View →      │
    └────────────────────────────────────────────────┘

Fields:

- Event name
- Status pill — always `OPEN` on this page
- Event date and venue
- Total registration count
- Rolling rate for the last hour
- Time since last submission (relative)
- **View →** link to the per-event page

Clicking anywhere on the card goes to the per-event monitor.

**Empty state:** no open events.

    No events with open registration right now.

    Open registration for an event to see live submissions here.

    → Go to events

## 5. The per-event page — `/registrations/monitor/{event}`

Two rows of header, then the feed.

**Top row:**

    ← All monitors

    Orca Community Run                    [ OPEN ]
    2026-10-31 · BGC · 1 KM

**Second row — the stats strip:**

    42 registered · +5 in the last hour · +3 in the last 15 min
    Last updated: 2 seconds ago  [ Refresh ]

Fields:

- Total count
- Rolling 60-minute rate
- Rolling 15-minute rate
- Time since last refresh
- Manual **Refresh** button

A rate segment is hidden when it is zero. Never shows `+0 in the
last hour`. If both rates are zero, the strip shows only the total.

**Below the stats:** filter chips, then the feed.

## 6. Filter chips

Four chips, in a horizontal row:

    All (42)   New (28)   Returning (14)   Flagged (2)

- **All** — every registration, newest first
- **New** — registrations where the participant had no previous
  registration before this one
- **Returning** — registrations where the participant had at least
  one prior registration on any event
- **Flagged** — registrations where `flagged_at` is non-null

The count in parentheses updates on each poll. The active chip
underlines. Chip state is client-side only — no URL parameter, no
server round trip on filter change.

**Flagged** is hidden when the count is zero — an empty filter is
visual noise.

## 7. Cards

One card per registration, newest first. Two variants:

**New participant:**

    ┌────────────────────────────────────────────────┐
    │ Maria Santos                          NEW     │
    │ maria@example.com                              │
    │ 09:12:34 · 3 minutes ago                       │
    │                                                │
    │ Shirt: M · Emergency: +63917...                │
    │                                   [⋯]          │
    └────────────────────────────────────────────────┘

**Returning participant:**

    ┌────────────────────────────────────────────────┐
    │ Maria Santos                      RETURNING    │
    │ maria@example.com                              │
    │ 09:12:34 · 3 minutes ago                       │
    │                                                │
    │ Registered for 2 previous events                │
    │ Shirt: M                                        │
    │                                   [⋯]          │
    └────────────────────────────────────────────────┘

Fields:

- Full name
- Contact method — email if present, else phone
- Submission time — absolute and relative
- Badge — **NEW** or **RETURNING**
- For returning: a small line with the count and a link to the
  participant's history
- Custom field answers — comma-joined summary, truncated at two
  lines with a "show more" toggle if longer
- Action menu button (⋯) — top-right corner

**Same-phone warning.** If a participant's normalized phone matches
another participant already in this event's registrations, a small
warning icon appears next to the badge. Tooltip:
*"This phone number matches another registration."* This surfaces
shared-phone situations without blocking them.

## 8. Rate indicators

Two rolling windows, recomputed on each poll:

- **60-minute rate** — count of registrations where
  `created_at >= now() - 60 minutes`
- **15-minute rate** — same window, 15 minutes

Both scoped to the event. Not carried across events. A submission on
another event does not appear in this event's rates.

Rates are hidden when zero — see §5.

## 9. Refresh strategy

**Polling every 10 seconds.** A `setInterval` in the page component
that issues a `fetch` to the same URL with an
`X-Inertia-Partial-Data` header that requests only the feed data.
The page's header and event payload do not re-render.

**Pause on hidden tab.** The interval checks
`document.visibilityState` on each tick. When the tab is hidden, the
fetch is skipped. When the tab regains focus, an immediate refresh
fires. This is the standard pattern; avoids background traffic when
the organizer is elsewhere.

**Manual refresh.** A **Refresh** button next to the "Last updated"
line. Fires the same fetch as polling, but immediately. Disabled
while a fetch is in flight, re-enabled when it completes.

**Server payload.** The monitor endpoint returns the last 100
registrations plus the two rate counts. No pagination — if an event
gets more than 100 registrations while the organizer is watching,
the feed shows the newest 100 with a "Load earlier" button at the
bottom. Full history is available via the attendance page.

## 10. Actions — the ⋯ menu

Five actions, standard menu:

| Action | Effect | Confirmation |
|---|---|---|
| **Edit participant** | Modal to correct name, email, or phone. Display-only — does not re-resolve identity. | None |
| **Flag for review** | Toggles `flagged_at` on the registration. Badge appears on the card. | None |
| **Add note** | Free-form note attached to the registration, capped at 2000 chars. | None |
| **Export CSV** | Downloads all registrations for the event. | None |
| **Delete registration** | Removes the `Registration` row. Cascades to `field_responses`. Admin: direct. Staff: OTP. | Modal requiring the participant's last name typed to confirm |

**Edit participant** — the modal shows the participant's current
values. Save writes directly to the `Participant` row. Because the
identity rule is first-write-wins for resolution, an edit here is
the organizer overriding that rule deliberately. Editing an email
that already exists on another participant is rejected — the
identity model's UNIQUE constraint still applies.

**Delete registration** — mirrors the existing event deletion flow.
Admin path: `DELETE /registrations/{registration}` with a direct
confirmation. Staff path: OTP via
`RegistrationDeletionOtpService`, mirroring
`EventDeletionOtpService`. This is Phase 5 of the broader
registration plan; the monitor's delete action lands with that
phase, not with this one.

## 11. After close

When `status !== registration_open`:

- Header pill changes from `[ OPEN ]` to `[ CLOSED ]`
- A line appears: *"Closed 2026-10-29 18:00"* — uses
  `registration_end` if set, else the timestamp of the last
  transition to `registration_closed`
- Polling stops. The interval is cleared on mount when status is not
  open.
- The **Refresh** button stays but is disabled
- The ⋯ menu on each card greys out — no actions available after
  close
- The stats strip continues to show the final counts
- Filter chips remain functional (client-side)

The page becomes a **snapshot**. The final state of the registration
list, frozen. From here, the organizer's next step is the attendance
page.

## 12. Empty states

**Landing — no open events.** See §4.

**Per-event — no submissions yet:**

    No one has registered yet.

    Share the public link to get started.
    [ Copy public link ]

The **Copy public link** button copies `{app_url}/r/{slug}` to the
clipboard. Uses the existing `use-clipboard` hook. Shows a brief
"Copied" state on success.

**Per-event — filter yields nothing.** When a filter is active and
no rows match:

    No submissions match this filter.

Filters remain visible — the organizer can switch back to All.

## 13. Out of scope for v1

- **Real-time push.** No WebSockets, no Server-Sent Events, no
  Laravel Echo. Polling is the mechanism.
- **Live map.** No participant location visualization.
- **Analytics.** No demographic breakdown, no charts, no trends
  across events.
- **Bulk actions.** No multi-select, no bulk flag, no bulk delete.
- **Bulk export of multiple events.** CSV export is per-event.
- **Search.** No text search across the feed in v1. Filter chips
  only.
- **Custom filters.** No "registered between X and Y" filters.
- **Participant history page.** The "N previous events" link on
  returning cards points to a page that does not exist yet. It is
  deferred; the link target is stubbed for v1.
- **Notification of new submissions.** No email, no push. The
  organizer sees new submissions by watching the page or reloading.

## 14. Open items

- **Participant history page.** The returning badge links to a
  participant's history. That page does not exist. Options:
  build it in this feature, or stub the link with "Coming soon" and
  build it later. Recommendation: stub for v1.
- **Same-phone warning copy.** The tooltip text is provisional.
  Decided during implementation.
- **Flagged workflow.** What happens to a flagged submission? Is
  there a resolution state, or is the flag a simple toggle? For v1,
  toggle only — no resolve state.
- **CSV columns.** Which columns appear in the export — just the
  common fields, or the common fields plus all custom field
  answers? Recommendation: all of them, one column per custom
  field, header row uses the field label.
- **Edit participant scope.** Should the edit modal allow editing
  email and phone (identity-bearing) or only name and address
  (descriptive)? Recommendation: all four, with the email/phone
  edit subject to the UNIQUE constraint.

## Changelog

- **2026-10-02** — File created. Two-page monitoring, card layout,
  polling strategy, five actions, read-only after close, empty
  states, out-of-scope list.

---

## Status addendum (2026-10-10)

The `**Status:**` line at the top of this document reads:

    **Status:** Planned. No code exists yet.

That was accurate on 2026-10-02. It is no longer accurate. Per the
append-only rule (P7), the earlier text stands as a historical
record. This addendum corrects the current state.

### What actually shipped

Most of the spec is implemented. The claim in §3 that Open
Registration "currently redirects back to the event show page" is
wrong — it already redirects to the monitor. The claim in §3 that a
Monitor link "is added" on the queue is wrong — it is already there.

**Routes (§3).**

- `GET /registrations/monitor` — `registration-monitor.index`
- `GET /registrations/monitor/{event}` — `registration-monitor.show`

Both under `auth`, `verified.or.admin`, `device.trusted`.

- `EventController::openRegistration()` already redirects to
  `registration-monitor.show`.
- `registrations/index.tsx` already renders a Monitor link on
  open-event rows.

**Landing page (§4).** Shipped in
`resources/js/pages/registrations/monitor/index.tsx`. Cards with
event name, status pill, date, venue, total count, rolling 60-minute
rate, relative timestamp, and a link to the per-event page. Sorted
by most recent submission. Empty state.

**Per-event page (§5).** Shipped in
`resources/js/pages/registrations/monitor/show.tsx`. Top row header,
second-row stats strip (total, +last hour, +last 15 min), manual
Refresh button, "Updated N seconds ago" ticker, Copy public link
button.

**Filter chips (§6).** All / New / Returning shipped. Flagged is
NOT shipped — it needs the `flagged_at` column, deferred to Phase 8.
Chips are client-side only, no URL param.

**Cards (§7).** Name, contact method, absolute + relative
submission time, NEW/RETURNING badge, "registered for N previous
events" line on returning. Same-phone warning NOT shipped. Custom
field summaries NOT shipped.

**Rate indicators (§8).** 60-minute and 15-minute rolling windows,
both computed server-side.

**Polling (§9).** `usePolling` hook polls every 10 seconds via
`router.reload({ only })`. Pauses on hidden tab. Fires immediate
refresh on tab focus. Serializes in-flight requests. Manual refresh
button. Payload is limited to last 100 registrations.

**After close (§11).** Per-event page reachable when status is
`registration_open` OR `registration_closed`. `is_open` flag drives
the pill and the disabled state of the polling.

**Empty states (§12).** All three shipped: landing no-events,
per-event no-submissions, per-event filter-yields-nothing.

### What is genuinely still missing

**Same-phone warning (§7).** Not implemented. A submission whose
participant's normalized phone matches another participant already
registered for the same event does not currently show a warning.

**Custom field summaries on cards (§7).** Not implemented. The
card does not show the participant's custom field answers.

**Flagged filter (§6) and actions menu (§10).** Blocked on the
`flagged_at` and `notes` columns, which do not exist. Phase 8 of
the identity plan adds the schema and the actions.

**Delete registration (§10, last row).** Phase 9 of the identity
plan. Not shipped.

### Corrected spec claims

- §2 says "no code exists yet" — wrong, most of it exists.
- §3 says Open Registration still redirects to event show — wrong,
  it redirects to the monitor.
- §3 says the Monitor link is a future addition — wrong, it is
  already on the queue page.

### Cross-references

- `docs/participant-identity-and-monitoring-plan.md` — Phases 7
  and 8
- `docs/attendance-redesign.md` — the attendance surface the monitor
  feeds into
- `docs/progress.md` — the 2026-10-10 phase entries

---

## Same-phone warning shipped (2026-10-10)

The Status addendum above says same-phone warning was not shipped.
That was accurate when written. It has since shipped, with redefined
semantics.

### The semantic change

The spec §7 says the warning fires when a participant's **normalized
phone** matches another participant already registered for the same
event. That case cannot occur. The identity model enforces a UNIQUE
constraint on `participants.contact_number_normalized`, so two
participants cannot share a normalized PH mobile — the schema
blocks it at write time.

The reachable shared-phone case is a RAW `contact_number` string
that does not normalize — typically a landline ("02-8123-4567")
shared by family members. Landlines return null from the normalizer,
so the UNIQUE constraint permits them to coexist, and two family
members filling the form at the same registration desk will produce
two rows with the same raw string.

### What shipped

`RegistrationMonitorController::show()` now computes
`has_shared_phone` per registration. Detection groups every
participant in the feed by their raw `contact_number` string. Any
group with more than one member flags those participants.

The card renders a small amber warning triangle beside the badge when
the flag is true. Native `title` attribute for the tooltip. The
submission is not blocked — the warning is informational, matching
the spec's intent.

### Detector scope

- Only fires on raw-string matches within a single event's feed.
- Does not fire for participants whose `contact_number` is null or
  empty.
- Does not fire across events — two participants on different events
  sharing a phone are not flagged here.

### Verification

    tests/Feature/Events/RegistrationMonitorExtrasTest.php  (7 tests)

The landline test constructs two participants with
`contact_number = "02-8123-4567"` and asserts both get the flag.
Two negative tests confirm different raw phones and null phones do
not flag.

### Cross-references

- `docs/fixes.md` — no separate entry; this is a Phase 7 gap close,
  not a bug fix
- Commit: the same-phone warning commit
