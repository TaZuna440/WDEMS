# WDEMS — As-Built Technical Report

## Evidence and accuracy note

This report describes the implementation present in `app/`, `routes/`, `database/`, and `resources/js/`. It does not rely on the `docs/` directory. The dependency manifest specifies `laravel/framework: ^13.17`, and the checked runtime reports Laravel Framework 13.31.0; therefore, the system should not be described as Laravel 11. The frontend dependencies specify React 19, TypeScript, and Inertia 3.

## 1. Executive Summary

WDEMS is a dual-module event management system for Community Runs and Church Events. Community Runs are the sole running-event type in the current event-type enum. Church Events are organized into nine categories: Services, Fellowship, Discipleship, Youth, Kids Ministry, Young Adult, Worship & Prayer, Special Occasions, and Church Administration.

The application unifies event setup, public registration, participant identity, attendance, reporting, and selected audit trails while retaining workflows tailored to each event kind. Its data model distinguishes event lifecycle and registration records from the shared participant identity, and explicitly separates Running and Church participant pools.

Church has both newer unified Event-based registration workflows and legacy Services, Fellowship, and Discipleship domain records. The bridge and backfill services link legacy history to Events without deleting the source records.

## 2. System Architecture

### Stack

- **Backend:** Laravel Framework 13 (runtime 13.31.0; Composer constraint `^13.17`), PHP application code, Eloquent ORM, and Laravel routing/middleware.
- **Frontend:** React 19 and TypeScript.
- **Server/client integration:** Inertia 3 (`inertiajs/inertia-laravel`, `@inertiajs/react`, and `@inertiajs/vite`).

### Request and rendering model

Laravel routes dispatch to controllers and services. Controllers return Inertia page responses with component names and props; the frontend resolves those names to React pages in `resources/js/pages/`. Inertia carries navigation and form submissions between the React interface and Laravel without requiring a separate, versioned application API for normal page workflows.

Specialized JSON endpoints do exist where useful, such as the public-registration similarity check. They supplement, rather than replace, the Inertia page flow.

The principal domain records are `Event`, `Participant`, `Registration`, and `Attendance`. Event kind differentiates Running from Church; a registration joins an event to a participant, and attendance is recorded against a registration. Legacy Church module models remain available for historical workflows and migration.

## 3. The Composite Identity Engine

### Participant identity

The `Participant` model owns normalized name and contact keys. Names are normalized for comparison, including case and whitespace; contact numbers have a canonical normalization routine. The exact identity lookup requires:

1. matching normalized first and last names, plus the normalized middle name and suffix; and
2. a matching identity contact: email **or** normalized phone.

When both contacts are available to the resolver, either match is sufficient. Public registration finalization uses the verified, chosen contact channel as the identity contact. A required but unverified contact is retained as a submission snapshot and is not promoted to identity. Matching participant records are treated as first-write-wins: a later submission does not silently overwrite the existing identity profile.

### Running/Church pool isolation

Every participant has a `pool` value (`running` or `church`). The model rejects other values, and identity queries explicitly scope matching to the event’s kind. Thus, a name/contact match in one pool is not an identity match in the other. The pool migration assigns existing participants to Church when their historical records associate them with Church activities.

### Exact duplicate prevention and organizer review

The registration service prevents a repeat registration for the same event/participant and checks exact identity before finalization. A separate advisory duplicate review compares similar names and shared birthdate/email/phone evidence. A likely match becomes a **Held** registration with a reason and candidate link; it is not automatically merged or confirmed.

Organizers can review held registrations and explicitly confirm a duplicate, confirm the person is separate, correct the details, or remove the submission. Resolution records include actor and time fields. A submission still matching an exact identity remains held. This approach treats similarity as a review signal, not proof that two records represent the same person.

## 4. Context-Aware Workflows

The event-creation wizard is selected by event kind and can also receive specialist activity options:

- **Community Run:** includes distance as a required schedule field and running accessibility fields.
- **Church:** omits distance from the visible schedule and removes distance values from the submitted payload. It captures a church category and, where applicable, a specialist activity.
- **Dedication:** when the Church category/activity identifies a dedication, the details step conditionally offers child, parent, and officiating pastor fields.
- **Youth Camp and children’s activities:** Youth Camp and Vacation Bible School conditionally expose emergency-contact and medical-note inputs plus a parental-consent control.
- **Class-oriented activity:** Discipleship and Vacation Bible School may show teacher and program-day/session fields.

The backend request validates event kind, required fields, category and specialist data, scheduling rules, and event-specific fields. The wizard is therefore a user-experience adaptation backed by server validation, rather than the only enforcement layer.

## 5. Security and Role-Based Access Control

### Authentication and access

Laravel Fortify provides the authentication and password-reset flows. `User` exposes `admin` role checks; Admin routes are nested inside the authenticated application route group and separately protected by the `admin` middleware. Staff users must meet the verified-email and trusted-device checks where those middleware are applied.

### Device Trust OTP

Email device verification is an opt-in user setting for staff; it is not accurately described as an unconditional challenge for every login. When enabled, a six-digit email code is sent, expires after ten minutes, permits a bounded number of attempts, and has a 60-second resend cooldown. Successful verification can issue a trusted-device cookie whose corresponding token hash is stored for later checks. Admins are explicitly exempt from the trusted-device challenge.

### Public registration verification

Public registration uses an event-scoped verification challenge before finalization. For events configured for email-only registration, the UI renders a Google reCAPTCHA widget and the backend verifies the token with Google, including checking the expected hostname. The code’s explicit widget rendering is visible in the frontend; the backend’s reCAPTCHA requirement is conditional on the event’s email-only registration mode.

Email verification codes have a **100-second resend cooldown** enforced by the backend. This is distinct from device-trust OTP and event-deletion OTP cooldowns.

### Destructive-action safeguards

- **Deleting a registration:** requires the authenticated user’s password. This deletes the event registration and its dependent answers/attendance but preserves the shared Participant record.
- **Deleting an event:** non-admin staff must request and verify an emailed six-digit OTP before deletion. The code expires after ten minutes, is limited to five attempts, and has a 60-second resend cooldown. Admins are explicitly denied the OTP endpoints and may use the direct event-delete endpoint.
- **Finalizing a running event:** the route is protected by Laravel’s `RequirePassword` middleware.

Public registration and verification actions also use throttling and challenge protections; event deletion OTP request/verification and device verification endpoints have dedicated rate limiters.

## 6. Audit and Traceability

The `audit_logs` table stores the action, actor type, optional user, polymorphic target, JSON details, IP address, and timestamps. `AuditLogger::log()` records user actions; `logSystem()` records system actions with a `system` actor and no user or IP.

Examples currently wired include event creation, event deletion, Admin user create/update/delete, sent email events, failed registration-confirmation email delivery, and automatic Running-event advancement. A global `MessageSent` listener records email recipients and subject as system audit details. Auto-advancement writes a system audit entry and a separate application log entry.

This is a **selected-action audit trail**, not a claim that every read or mutation in the application generates an audit record. The Admin audit-log page provides an interface for reviewing recorded entries.

## 7. The Legacy Bridge

### Explicit activation

`RegistrationBridge` activates an existing Services occurrence or Fellowship event into the unified Church `Event` model inside a database transaction. It locks the source row, returns the already-linked Event if a link exists, maps legacy event details and attendance into Event/Registration/Attendance records, and stores the resulting event ID on the source. Legacy records are not deleted.

### Backfill service

`ChurchEventBackfillService` additionally supports Services, Fellowship, and Discipleship sessions via the `church:backfill-events` command. It supports module selection, dry-run, bounded batches, an `after-id` cursor, and JSON reporting of planned work, migrations, warnings, status conversions, and conflicts.

The write path locks source rows and relevant participants, validates that each participant belongs to the Church pool, rejects provisional or duplicate participant rows, writes the new records transactionally, and links the source to the new Event. Already-linked sources are reported instead of migrated again. This supports safe repeat execution and concurrent-worker protection.

The bridge preserves the original legacy rows and links them to the new Event rather than deleting them. Standardized attendance mapping is intentionally lossy in representation for some statuses: `late` maps to `present`; `excused` and `unmarked` do not create an Attendance row. The exact legacy status remains available in the retained source history, while the backfill reports these conversions. Accordingly, the migration preserves source history but does not claim that every legacy status has an identical representation in the new attendance model.

## Implementation references

- Identity and registrations: [Participant](../../app/Models/Participant.php), [Registration](../../app/Models/Registration.php), [DuplicateReview](../../app/Services/Participants/DuplicateReview.php), [RegistrationVerificationService](../../app/Services/RegistrationVerification/RegistrationVerificationService.php)
- Event wizard: [create.tsx](../../resources/js/pages/events/create.tsx), [BasicsStep.tsx](../../resources/js/pages/events/step/BasicsStep.tsx), [ScheduleStep.tsx](../../resources/js/pages/events/step/ScheduleStep.tsx), [EventRequest](../../app/Http/Requests/EventRequest.php)
- Security: [web.php](../../routes/web.php), [EnsureDeviceIsTrusted](../../app/Http/Middleware/EnsureDeviceIsTrusted.php), [RegistrationVerificationService](../../app/Services/RegistrationVerification/RegistrationVerificationService.php), [EventDeletionOtpService](../../app/Services/EventDeletion/EventDeletionOtpService.php)
- Audit: [AuditLogger](../../app/Services/Audit/AuditLogger.php), [LogSentEmail](../../app/Listeners/LogSentEmail.php), [AdvanceEventStatus](../../app/Http/Middleware/AdvanceEventStatus.php)
- Legacy migration: [RegistrationBridge](../../app/Services/Church/RegistrationBridge.php), [ChurchEventBackfillService](../../app/Services/Church/ChurchEventBackfillService.php), [BackfillChurchEvents](../../app/Console/Commands/BackfillChurchEvents.php)
