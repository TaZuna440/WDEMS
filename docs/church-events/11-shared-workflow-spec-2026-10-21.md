# Church modules — shared workflow specification

Date: 2026-10-21 (Asia/Manila).
Status: implementation specification; Church category and Youth submodule pages remain placeholders. Specialist module requirements below remain pending.

This records Mark's 2026-10-20 scope change supplied in the running-events
system update. Earlier documents remain intact. Existing Services, Fellowship,
and Discipleship functionality remains separate from this proposed expansion.

## Architectural boundary

Each new church event has `event_kind = church`. Its lifecycle uses
`ChurchEventStatus`: Upcoming, Ongoing, Completed, Cancelled. Never use a running
registration status to decide whether a church action is available. Every church
route, query, policy, nested resource lookup, export, and summary must enforce
church ownership. Running registration builders, public slugs, verification
challenges, attendance-code endpoints, reports, and finalization remain forbidden
for church events, even for administrators.

Use dedicated church controllers/services and church-specific payloads. Reuse
presentation primitives only when they do not embed running-event behavior.
The shared participants table is an identity directory, not permission to move
registrations or attendance between event kinds. Running provisional participants
cannot be automatically matched or selected into a church roster before an
explicit staff review/linking process exists.

## Common user journey

Category selection → Add Event → Event Details → Registration → Attendance
Record → Attendance Summary.

1. Category selection establishes module and event subtype before data entry.
2. Add Event collects name, date/time, location, and subtype-specific details.
   Show validation errors beside the fields and preserve input on failure.
3. Event Details is the review page and entry point to this event's registration,
   attendance, and summary. It displays church lifecycle status.
4. Registration maintains this church event's roster. Members, First-Timers,
   and Special Guests are the standard categories. Do not reuse the running
   five-step email/OTP registration wizard by implication.
5. Attendance Record marks participants from the event's own roster and retains
   recorder identity and recording time. Running Present/Absent simplification
   does not silently migrate existing church attendance semantics.
6. Attendance Summary aggregates only this event's roster and attendance, with
   category totals and participant details. Completed events are read-only;
   cancellation does not become completion.

Existing confirmed role model remains Admin/Staff (decision log Q21); this spec
introduces neither a four-role approval chain nor automatic event approvals.

## Church Administration

Categories: Church Planning and Ministry Meeting.

Church Planning uses the common event journey. Keep its planning artifacts
associated with its church event; do not create running registrations to support
planning. The existing decision log Q7 confirms Gantt-style timeline bars with
dependencies for the planning extension. This is additional module scope, not a
requirement to block basic roster/attendance work.

Ministry Meeting adds a ministry selection. Initial ministry labels supplied by
Mark: Worship, Technical, Multimedia, Social Media, Finance, Light, Kids, RYA.
Treat these as seed labels, not enum values that prevent later changes. Earlier
Q9 permits authorized staff to add/edit/delete ministries. Preserve references
when a ministry already has historical meetings; archive instead of cascading
away history. The current Admin/Staff policy defines authorized staff until a
more specific permission model is explicitly approved.

Acceptance checks:

- A meeting cannot be created without selecting its ministry.
- Renaming a ministry does not lose its meeting records.
- A planning or meeting roster never appears in a running report or lookup.
- Each event's attendance summary reflects only its own registrations.

## Special Occasions

Categories: Church Anniversary, Thanksgiving Celebration, Pastor's Appreciation
Month, Dedication, School of Leaders Graduation.

All use the shared journey. Additional details:

| Category                                      | Additional data                                                                      |
| --------------------------------------------- | ------------------------------------------------------------------------------------ |
| Dedication                                    | Mother's name, father's name, child's name, officiating pastor, ninongs/ninangs list |
| School of Leaders Graduation                  | Separate graduate lists for School of Leaders 1, 2, and 3                            |
| Pastor's Appreciation Month                   | One or more pastors being honored                                                    |
| Church Anniversary / Thanksgiving Celebration | Common event details                                                                 |

Dedication adds Ninongs/Ninangs to its roster presentation. Graduation adds
Graduates and preserves each graduate's SOL level. These are event roles;
creating a dedication must not implicitly create global participant identities
for every name in its descriptive fields. The implementation must distinguish
an event description from someone explicitly registered for attendance.

Existing decision log Q6 defines Documents as generated documents, not uploads
or external links. Q12 says budgets are recorded without an approval chain.
These extensions must not be invented as mandatory workflow stages.

Acceptance checks:

- Dedication displays all required family/pastor fields and supports multiple
  ninongs/ninangs without storing them in a single ambiguous text string.
- SOL levels retain independent graduate lists and counts.
- A participant cannot be accidentally moved between graduate levels by editing
  another event.
- Pastor's Appreciation supports more than one honoree.
- Registration and summary use the same category definitions for a given event.

## Worship & Prayer

Categories: Outdoor Prayer Meeting, Worship Night, Prayer & Fasting.

All use the common workflow and standard Members/First-Timers/Special Guests
categories. The earlier Q5 decision describes the existing Services/Fellowship
registration pattern as the reference. It does not authorize running OTP,
barcode, disclaimer, T-shirt, or public-link features for these modules.

Acceptance checks:

- Category selection is retained through event details, attendance, and summary.
- Changing a filter never brings another module's roster into this event.
- Attendance edits and exports use the same event authorization as the page.

## Data and implementation plan

Proposed model boundaries (design proposal, not applied migrations):

- Church module/subtype metadata belongs to a church event or a dedicated
  one-to-one church details record.
- Church roster rows reference one church event and one participant, with a
  validated category/role. Define overlapping-role semantics before imposing a
  uniqueness constraint on role records.
- Dedication details and honorees use child records where multiplicity exists.
- Graduate assignments record event, participant, and SOL level; do not infer
  graduation by mutating a running participant's registration.
- Attendance references a church roster row, never a running registration ID.
- Record create/update actors and timestamps. Preserve existing church module
  tables until an explicit migration strategy is approved.

Implement one vertical slice (category through summary) before adding module
extensions. Test URL tampering with running event IDs, cross-event nested IDs,
unauthenticated access, category mismatch, duplicate roster entries, completed
read-only behavior, and export/summary consistency. Keep running and church
regression suites green throughout.

## Questions required before church implementation

The supplied scope is sufficient to write this specification, but does not
settle these implementation choices:

- Whether the new church Registration step is staff-only or includes a public
  church-specific intake path. The running wizard must not be assumed.
- Whether roster categories are mutually exclusive or can overlap (for example,
  a member who is also a ninong or graduate).
- Exact church attendance statuses and lifecycle transition permissions for the
  new shared workflow, without changing existing modules by accident.
- Dedication multiplicity: one child per event or several children with separate
  parents/sponsors in one event.

These questions gate church implementation, not the running-event fixes.

---

## Implementation status clarification — 2026-10-22

The category and Youth submodule placeholder-page architecture remains
unchanged. The Church input-placeholder adjustment changes example text only.
Specialist Youth, Kids, Young Adult, Worship & Prayer, Special Occasions, and
Church Administration requirements remain separate implementation work.
Activity-specific event tagging, multi-person module roles, outreach/ministry
management, Gantt planning, and generated documents remain unbuilt; do not
infer their implementation from generic Church event support.

The client decision log answers the relevant Q1–Q21. Cell Group was removed
from scope (Q18); participant payment tracking/gateways are excluded (Q10/Q11);
the four-role approval workflow was rejected (Q21). Remaining decisions and
acceptance gates are tracked in `docs/spec-implementation-plan-2026-10-22.md`
and `docs/church-events/TASKS.md`.
