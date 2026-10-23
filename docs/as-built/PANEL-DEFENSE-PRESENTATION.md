# WDEMS — 15-Minute Panel Defense Outline

**Suggested pacing:** approximately 13 minutes of presentation followed by 2 minutes for questions. The speaker notes are written as a concise talk track; adjust names and institutional context before presenting.

## Slide 1 — WDEMS: Unified Event Management

**Suggested time:** 0:45

**Bullet Points**

- WDEMS: event operations for Community Runs and Church Events
- One platform, distinct workflows
- Panel defense — [Presenter / Team] — [Date]

**Speaker Notes**

“Good [morning/afternoon]. We are presenting WDEMS, a dual-module event management system that brings Community Run and Church Event operations into one application. The platform unifies the underlying event, participant, and registration workflows while adapting the interface and validation to each event context.”

## Slide 2 — The Problem: Fragmented Operations and Duplicate Records

**Suggested time:** 1:15

**Bullet Points**

- Running and Church activities historically used separate operational records
- Different event types require different registration and attendance data
- Repeated registrations can create ambiguous participant histories
- Manual migration risks losing attendance context or creating duplicate records

**Speaker Notes**

“The core problem is not that every event should look the same. It is that different event operations need to coexist without fragmenting identity and history. The codebase reflects legacy Services, Fellowship, and Discipleship records alongside the newer unified Event model. Duplicate identities also need human judgment: a likely match is not necessarily the same person, so an automated merge would be unsafe.”

## Slide 3 — The Solution: Unified Dual-Module Architecture

**Suggested time:** 1:15

**Bullet Points**

- Running module: Community Run
- Church module: nine configured event categories
- Shared Event → Registration → Attendance structure for unified flows
- Separate Running and Church participant pools
- Context-specific rules preserve relevant operations

**Speaker Notes**

“WDEMS uses a unified event and registration structure where that provides consistent operations, while preserving context-specific behavior. Church categories include Services, Fellowship, Discipleship, Youth, Kids Ministry, Young Adult, Worship and Prayer, Special Occasions, and Church Administration. Participant pools ensure identity resolution in one module cannot silently merge a record from the other.”

## Slide 4 — Technology Stack and Request Architecture

**Suggested time:** 1:30

**Bullet Points**

- Laravel Framework 13 backend; Eloquent domain models
- React 19 and TypeScript frontend
- Inertia 3 connects controller responses to React pages
- Server-side routes and validation; targeted JSON endpoints where needed
- No separate versioned API required for standard page workflows

**Speaker Notes**

“The backend is Laravel, with Eloquent models and middleware enforcing domain and access rules. The frontend is React 19 with TypeScript. Inertia carries page data and navigation between Laravel controllers and React components, avoiding a separate application API for the standard interface while still allowing focused JSON endpoints, such as similarity checks. One accuracy note for the panel: the current manifest targets Laravel `^13.17`, and the checked runtime is 13.31.0—not Laravel 11.”

## Slide 5 — Composite Identity and Held Registrations

**Suggested time:** 1:45

**Bullet Points**

- Composite Identity: normalized name + verified identity contact
- Contact match is email or normalized phone
- Pool-scoped matching isolates Running from Church
- First-write-wins prevents intake data from silently rewriting identity
- Similarity candidates are Held for organizer review—not auto-merged

**Speaker Notes**

“The identity engine uses a composite key rather than email-only lookup. It compares normalized name fields and a matching email or normalized phone inside the event’s participant pool. Public registration uses the chosen verified channel as the identity contact; an unverified contact remains a registration snapshot. For probable duplicates, the system records a held candidate and the reasons for review. An organizer decides whether to confirm a duplicate, confirm separate people, correct details, or remove the submission.”

## Slide 6 — Context-Aware Wizard

**Suggested time:** 1:30

**Bullet Points**

- Community Run setup includes required distance and accessibility options
- Church setup omits distance and supports category/activity selection
- Dedication activities reveal optional family and officiant details
- Youth Camp / Vacation Bible School reveal emergency and medical fields
- Backend validation independently enforces event rules

**Speaker Notes**

“The wizard is context-aware rather than one oversized form. A Community Run includes distance and participant accessibility details. A Church Event omits running distance and can select a specialist activity. Dedication inputs appear only for that activity; Youth Camp and Vacation Bible School can collect emergency-contact and medical notes plus parental-consent status. These are not just visual changes: the backend validates the submitted event payload as well.”

## Slide 7 — Security, Authentication, and Destructive Actions

**Suggested time:** 1:45

**Bullet Points**

- Fortify authentication and Admin Role-Based Access Control
- Optional staff email Device Trust OTP; admins bypass the device challenge
- Public email-only registration uses reCAPTCHA verification
- Registration email code resend cooldown: 100 seconds
- Registration deletion requires password; staff event deletion requires OTP
- Finalization is password-gated

**Speaker Notes**

“Authentication is provided through Fortify, and Admin routes add an explicit role check. Device Trust is an opt-in email challenge for staff, not a mandatory prompt on every login; admins are exempt. Public email-only registration verifies reCAPTCHA server-side and enforces a 100-second email-code resend cooldown. Deleting an individual registration requires the current password and preserves the shared participant. Staff event deletion uses a separate email OTP flow; admins use the direct route. Finalizing a running event also requires password confirmation.”

## Slide 8 — Legacy Bridge and Idempotent Backfill

**Suggested time:** 1:45

**Bullet Points**

- Transactional bridge links legacy Services/Fellowship records to unified Events
- Backfill command also handles Discipleship sessions
- Dry-run, module selection, batch limit, cursor, and JSON report
- Source-row locks, validation, transactions, and source-to-event links
- Repeat runs detect already-linked sources; originals remain available

**Speaker Notes**

“The Legacy Bridge moves legacy operational data into the Event structure without deleting its source. Interactive activation handles Services and Fellowship. The backfill command also processes Discipleship sessions and provides a dry run and structured report before writes. It locks source rows, validates participant pool and uniqueness constraints, creates the related records transactionally, then stores a link. The source link makes reruns idempotent. Some legacy attendance statuses are mapped to the new status vocabulary; conversions are reported and original records remain.”

## Slide 9 — Operational Monitoring and Audit Trail

**Suggested time:** 1:15

**Bullet Points**

- Audit records include actor type, action, target, details, IP, and timestamp
- User actions include selected event and Admin-user changes
- System actions include email-sent and automatic event-advance records
- Admin audit-log interface
- Explicitly selected coverage—not every application action

**Speaker Notes**

“The audit subsystem is designed to make key operations traceable. Records identify user or system actors, capture targets and structured details, and retain IP and timestamp data when applicable. Examples include event creation/deletion, Admin-user changes, sent email events, and event auto-advancement. We should describe this accurately as selected-action coverage, rather than claiming every action or read is audited.”

## Slide 10 — Conclusion and Q&A

**Suggested time:** 2:00

**Bullet Points**

- Unified workflows with explicit context boundaries
- Composite identity with human-reviewed ambiguous matches
- Security controls applied at both route and service boundaries
- Legacy history linked, retained, and auditable
- Questions

**Speaker Notes**

“To conclude, WDEMS combines a unified operational core with clear Running and Church boundaries. It uses composite identity and organizer review instead of automatic merging, enforces context-specific security and validation, and migrates legacy records through a controlled, repeatable bridge. Thank you. We welcome your questions.”
