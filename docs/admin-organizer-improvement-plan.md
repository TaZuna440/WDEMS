# WDEMS improvement plan for admins and organizers

Date: 2026-11-04
Status: proposed backlog; improvements below are not implemented by this document.
Basis: local administrator walkthrough on November 3–4 and inspection of the current automatic identity implementation.

## Objective and responsibilities

Make event setup, registration, attendance, and reporting dependable for both Running and Church events. Organizers must not identify duplicates, approve identity matches, or release identity holds.

- **Admin:** configure access, oversee events, inspect system decisions, monitor failures, and manage recovery.
- **Organizer:** prepare assigned events, open registration, check in attendees, correct permitted event information, and export results.
- **System:** validate intake, resolve sufficiently supported identities, keep uncertain identities separate, record decisions, and retry recoverable background work.

Organizer is a workflow responsibility in this plan. The inspected user screen currently exposes Admin and Staff roles; event-specific organizer permissions are a proposed enhancement, not an existing guarantee.

## Verified baseline and boundaries

The walkthrough successfully created a synthetic Running event, saved a custom registration question, opened registration, recorded a name-only walk-in as Present, read its automatic identity decision, and downloaded a report CSV. Church registration and attendance screens, user administration, and audit history were inspected. Existing Church records were not modified.

Automatic identity management reuses an unambiguous exact identity, protects public repeat recovery with contact verification, keeps uncertain matches separate, and records system decisions. It does not promise to eliminate every real-world duplicate. Name-only walk-ins cannot be safely linked to an existing person merely because names match.

Known inconsistency: the successful test walk-in is still flagged for organizer review on the dashboard, while its automatic identity history says no organizer review is required. Name-only intake still writes a review flag and review-related notes. The plan must address stored state and dashboard selection as well as wording.

This review did not verify production email delivery, a complete public registration journey, real database concurrency, peak load, backup restoration, or offline operation. Passing automated tests does not replace those checks.

## Delivery order

### Phase 1 — Remove obstacles before a supervised pilot

| ID | Improvement | Admin benefit | Organizer benefit | Acceptance criteria |
| --- | --- | --- | --- | --- |
| P1-01 | Finish the automatic identity workflow across quick walk-ins, dashboard queries, monitor links, and messages. Keep name-only identities separate and active. | Decision history agrees with operational state; no false review queue. | No duplicate-identification or approval tasks. | Exact verified repeats, shared family contacts, conflicting birthdays, ambiguous candidates, and name-only walk-ins have explicit tested outcomes. No new identity-related organizer task is created. Existing obsolete flags are handled by a narrowly scoped, previewable, repeat-safe migration or command that preserves records and history. |
| P1-02 | Restore mobile navigation and responsive attendance controls. | User administration, audit, and navigation remain reachable on a phone. | Check-in works on phones and tablets. | At 390px, 768px, and desktop widths, navigation can open and close; account actions are reachable; lookup and status controls are usable. Wide tables scroll inside their container rather than pushing essential controls off screen. Keyboard focus and accessible labels work. |
| P1-03 | Make event accessibility claims opt-in. | Published accommodations are intentional. | Event setup accurately describes the venue. | New drafts default to unchecked accommodations; preview shows chosen claims. Existing events are not silently rewritten. |
| P1-04 | Explain attendance availability and prevent dead-end navigation. | Consistent enforcement and fewer support requests. | Staff know when check-in opens and what to do next. | Event links reflect the same time/status rules as the server. Early, closed, completed, and unauthorized requests show an appropriate explanation and navigation. Server enforcement remains intact. Test event-day boundaries in the configured timezone. |

**Phase exit:** repeat the setup-to-check-in walkthrough on desktop and phone, with no manual identity-review task and no unexplained blocked action.

### Phase 2 — Make counts and event-day records trustworthy

| ID | Improvement | Admin benefit | Organizer benefit | Acceptance criteria |
| --- | --- | --- | --- | --- |
| P2-01 | Fix report-list registration totals and define shared reporting rules. | Comparable totals across dashboards, details, and exports. | Reliable event counts without opening every report. | Reconcile the same dataset across all surfaces. Define treatment of active, cancelled, duplicate, removed, and provisional records. Show zero rather than an unexplained dash when there are no registrations. |
| P2-02 | Add complete registration search and pagination. | Every eligible record is discoverable. | An attendee outside the latest 100 records can be found immediately. | Use a dataset exceeding 100 records; search finds older entries, pagination preserves filters, and exports clearly identify their scope. |
| P2-03 | Distinguish unmarked from absent consistently. | Reports separate missing data from confirmed absence. | Staff can identify people who have not yet checked in. | Running and Church screens explain all states. Existing records are not reclassified without a defined migration rule. Event completion uses an explicit, documented absence policy. |
| P2-04 | Add controlled registration-form revisions. | Traceability for form changes. | Typos and future questions can be corrected after opening. | Store the version used by each submission; retain historical answers; preview and confirm revisions. Do not retroactively add required answers to existing registrations. |
| P2-05 | Support direct event-section editing and clearer next steps. | Less setup support needed. | Small corrections require fewer steps. | Editing venue or schedule does not require traversing unrelated sections; validation remains equivalent. Successful creation leads to the event and its next available action. |

**Phase exit:** counts agree for a mixed-status dataset and an organizer can find, check in, and report on an older registration without assistance.

### Phase 3 — Improve oversight without adding organizer work

| ID | Improvement | Admin benefit | Organizer benefit | Acceptance criteria |
| --- | --- | --- | --- | --- |
| P3-01 | Make audit history readable and investigate repeated email log entries. | Clear actor, action, event, result, and time; useful troubleshooting. | Fewer ambiguous support requests. | Add name/event filters and human-readable summaries. Correlate email attempts with delivery results before concluding whether duplicate messages were sent; repeated logs alone are not evidence of duplicate delivery. |
| P3-02 | Define and enforce event assignments and permissions. | Controlled staff access and searchable user management. | Focus on assigned events and relevant actions. | Approve an Admin/Staff responsibility matrix, then enforce it server-side. Test direct unauthorized requests, not only hidden buttons. Organizers can complete their event workflow without user-management access. |
| P3-03 | Prefer archiving and recovery over routine permanent event deletion. | Recover from mistakes while preserving history. | Completed events remain available for reporting. | Define retention and restoration behavior; restrict irreversible deletion; verify reports and identity history after archive and restore. |
| P3-04 | Provide operational health visibility. | Detect failed scheduled work and notification delivery. | Clear feedback when a task is delayed or fails. | Show last successful runs and actionable failures; retry safely without duplicate registrations or confirmations. Technical failures belong to admin operations, never an organizer identity-review queue. |

## Technical approach

Reuse the current identity service, lifecycle rules, and role checks rather than adding independent matching logic to each screen. Keep read-only decision history separate from tasks requiring human action. Share count definitions and availability rules between API responses and interfaces. Introduce database changes with preview, backup, and repeat-safe execution where existing records are involved.

Deliver each backlog item as a focused change with relevant regression coverage and a short admin/organizer walkthrough. Do not combine the full plan into one large release. Phase 1 is the next implementation batch; later phases remain proposals.

## Pilot and release gates

1. Run formatting, static analysis, type checks, build, and the automated suite on the exact release commit; require GitHub CI success.
2. Repeat the public registration journey in staging using controlled contacts, including verification expiry, resend limits, repeat submission, and existing-code recovery. Confirm actual delivery with the configured provider.
3. Test concurrent registration and check-in on the production database engine: exact repeats, retry after timeout, simultaneous arrivals, and conflicting identities. Verify record counts and side effects; SQLite tests alone do not establish row-lock behavior.
4. Choose expected event size and simultaneous check-in volume from the planned pilot. Measure response times and errors at that load before setting a release threshold.
5. Rehearse backup restoration and deployment recovery, including the identity migration and scheduler. Document recovery steps and ownership.
6. Have one admin and one organizer complete separate task scripts on desktop and mobile. Record completion, errors, assistance needed, and time taken as the baseline for future comparison.

Pilot success requires zero manual duplicate-decision tasks, agreement between displayed and exported counts, working mobile navigation, no lost attendance after retries, and no unauthorized cross-event changes. Production rollout remains gated on the delivery, concurrency, and recovery checks above.

## Immediate next batch

Implement P1-01 through P1-04, then rerun the administrator walkthrough and an organizer-focused walkthrough. Keep this document updated with completion evidence, relevant commits, remaining limitations, and changes to the agreed scope.
