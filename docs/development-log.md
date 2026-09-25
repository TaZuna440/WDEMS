# WDEMS — Development Log

**Project:** Workflow-Driven Event Management System (WDEMS)
**Primary event type:** Community Run
**Stack:** Laravel 13 · PHP 8.5 · Inertia 3 · React 19 · TypeScript · Tailwind 4 · MySQL · Pest · Fortify
**Last updated:** September 18, 2026

This document is the chronological development record. It covers what was built, when, why, and how — including decisions made, trade-offs considered, and open questions.

For architectural details, see `architecture.md`. For Google setup, see `google-integration.md`.

---

## Table of Contents

1. Project context and direction
2. Foundation layer (pre-existing)
3. Phase 1 — Delete Event with role-based OTP verification
4. Phase 2 — Production email delivery via Resend
5. Phase 3 — Google OAuth connection
6. Phase 4 — Google Form creation + question editor
7. Phase 5 — Sheet linkage (A2 approach)
8. Cross-cutting technical decisions
9. Test coverage
10. Development timeline
11. Open questions
12. Next phases
Appendices — Environment, external services, naming

---

## 1. Project context and direction

### 1.1 What WDEMS is

WDEMS is a workflow-driven event management system. Its primary target event type is the **Community Run** — a community-organized running event with the following lifecycle:

- Event setup and configuration
- Pre-registration
- Promotion and announcements
- Participant registration (via Google Forms, not WDEMS)
- Event-day attendance
- Post-run activity / follow-up
- Monitoring of participation and attendance history

A secondary event type (**Fun Run**) exists in the data model but does not currently need its own workflow.

### 1.2 Architectural pivot

The original design described WDEMS as a generic event management platform with inquiry → booking → scheduling → payment → closure. That design was narrowed during development based on organizer feedback:

- **Community Run** is now the primary implementation target
- **Participant registration** happens outside WDEMS via Google Forms
- **Attendance** stays inside WDEMS
- **Participant history** is derived, not stored as a counter
- **Post-run activity** (partner collaborations, etc.) is a later concern

This pivot is documented in the design authority: `aside.txt`.

### 1.3 Current state summary

| Layer | Status |
|---|---|
| Authentication (Fortify) | Complete |
| Role system (admin/staff) | Complete |
| Event CRUD | Complete |
| Event lifecycle (state machine) | Complete |
| Event configuration (options) | Complete |
| Delete Event with OTP verification | Complete |
| Production email (Resend) | Complete |
| Google OAuth connection | Complete |
| Google Form creation | Complete |
| Google Form question editor | Complete |
| Google Sheet linkage (A2) | Complete |
| Copy Registration Link sharing | Complete |
| Action Feed dashboard | Complete |
| Sheet response sync | Not started (Phase D) |
| Walk-in / paper registration | Not started (schema ready) |
| Attendance recording UI | Not started |
| Monitoring dashboard | Not started |

---

## 2. Foundation layer (pre-existing)

These pieces existed before the current development arc and remain unchanged. Listed so the full picture is visible.

### 2.1 Authentication

- **Package:** Laravel Fortify 1.37
- **Features enabled:** password reset, email verification
- **2FA:** disabled (feature exists in Fortify, not enabled)
- **Roles:** `admin`, `staff`
- **Post-login redirect:** role-based via `LoginResponse` singleton

### 2.2 Middleware

- `AdminMiddleware` — admin-only gate for `/admin/dashboard`
- `auth` + `verified` group — gates all event routes for both roles
- `HandleInertiaRequests` — shares Inertia context
- `HandleAppearance` — theme persistence

### 2.3 Event domain models

| Model | Table | Purpose |
|---|---|---|
| `Event` | `events` | Core event record |
| `EventOption` | `event_options` | Configurable event choices |
| `Participant` | `participants` | Person record (shared across events) |
| `Registration` | `registrations` | Participant ↔ Event link |
| `RegistrationOption` | `registration_options` | Chosen options per registration |
| `Attendance` | `attendances` | Event-day presence |

### 2.4 Foreign-key rules (critical)

| Relationship | On delete |
|---|---|
| `events.created_by` → `users` | RESTRICT |
| `event_options.event_id` → `events` | CASCADE |
| `registrations.event_id` → `events` | CASCADE |
| `registrations.participant_id` → `participants` | RESTRICT |
| `registration_options.registration_id` → `registrations` | CASCADE |
| `registration_options.event_option_id` → `event_options` | **RESTRICT** |
| `attendances.registration_id` → `registrations` | CASCADE |

The **RESTRICT** on `registration_options.event_option_id` is the single most important constraint in the schema. It prevents MySQL from blindly cascading an event delete.

### 2.5 Event lifecycle

    draft → configured → registration_open → registration_closed → ongoing → completed
    (cancelled reachable from any non-terminal state)

Guards on the `Event` model: `canEdit()`, `canConfigure()`, `canOpenRegistration()`, `canCloseRegistration()`.

### 2.6 Event pages (implemented in foundation)

1. Event List — `/events`
2. Create Event — `/events/create`
3. View Event — `/events/{event}`
4. Edit Event — `/events/{event}/edit`
5. Event Configuration — `/events/{event}/options`
6. Open/Close Registration — actions on View Event

### 2.7 Event type

Two values: `community_run`, `fun_run`. Required on creation, fixed thereafter.


---

## What was added since September 18, 2026

This section is a factual update to the log above. It does not rewrite
earlier sections. Where earlier sections disagree with the code, the
**Corrections** subsection at the end of this block wins.

### Authentication — email two-factor

Built after the earlier log entries. WDEMS now has:

- **Email 2FA on new devices.** Login is still Fortify (email +
  password). On top of that, staff users signing in from an unknown
  device receive a 6-digit code by email and must enter it.
- **Trusted devices.** A verified device can be remembered for 30 days
  via a signed cookie. Up to 10 devices per user.
- **Admin exemption.** Admins skip the challenge entirely — deliberate
  scope decision, enforced in `EnsureDeviceIsTrusted`.
- **Security settings page.** Password change, 2FA toggle, trusted-
  device list with per-device revoke. Lives at `/settings/security`.

**Does not use Fortify's TOTP 2FA.** Fortify's `twoFactorAuthentication`
feature is not enabled. The email 2FA is a custom service
(`App\Services\TwoFactor\EmailTwoFactorService`).

Documented in `docs/authentication.md`.

### Event Creation Wizard

Built after the earlier log entries. Event creation moved from a
single-page form to a 4-step wizard:

1. **Basics** — event type, name, description
2. **Schedule** — date, times, distance, course URL
3. **Venue** — name, address, map coordinates
4. **Extras** — RSVP, partners, 9 accessibility flags

Additional features:

- **Draft checkpoint.** Wizard state is saved to `localStorage` on
  every change, restored on page reload, expires after 24 hours.
- **Custom pickers** — DatePicker, TimePicker (5-minute snapping),
  DistancePicker (KM/Miles with live conversion), MapPicker (Leaflet +
  OpenStreetMap + Nominatim reverse geocoding).
- **Stronger event name validation.** Five regex rules layered on top
  of length checks to catch keyboard mashing.
- **17 new columns on the events table.** Venue coordinates, distance
  value + unit, course URL, RSVP flag, partners JSON, FAQ JSON, and
  nine accessibility booleans. The old `distance_label` column was
  dropped and replaced by computed `distance_value` + `distance_unit`.

Documented in `docs/event-creation-wizard.md` (feature view) and
`docs/event-creation.md` (code reference).

### Event Deletion

Built after the earlier log entries. Two paths:

- **Admin** — direct delete, no verification.
- **Staff** — must request and verify a 6-digit email OTP first.

Both paths funnel through the same ordered-delete service
(`App\Services\EventDeletion\EventDeletionService`), which deletes
attendances, registration options, registrations, and event options
in a strict order inside a transaction. Participants are never
touched — they are shared across events.

Documented in `docs/event-deletion.md`.

### Google integration — removed

Phases 3, 4, and 5 of the log above describe a Google integration
(OAuth, Form creation, Sheet linkage) that was designed and built, then
**removed** from the project. The registration flow is being rebuilt
without it.

For historical context, see:

- `docs/archive/phase-a-b-report.md`
- `docs/archive/google-integration.md`

### Documentation

Four subsystem guides were written, in a style aimed at junior
developers using AI tools:

| Doc | Covers |
|---|---|
| `docs/authentication.md` | Login, email 2FA, trusted devices, password management |
| `docs/event-creation.md` | Event creation code reference, options, workflow |
| `docs/event-creation-wizard.md` | Wizard as a feature: steps, checkpoint, field components |
| `docs/event-deletion.md` | Two delete paths, OTP flow, deletion algorithm |

`docs/architecture.md` was updated to correct the auth section and to
point at `authentication.md`. `README.md` was rewritten to remove
Google references and to point at all four guides.

---

## Corrections to earlier sections in this file

The following claims in the body above no longer match the code. They
are listed here rather than edited in place.

| Section | Earlier claim | Current reality |
|---|---|---|
| §1.1 | "Participant registration (via Google Forms, not WDEMS)" | Google integration removed. Registration flow being rebuilt. |
| §1.2 | "Participant registration happens outside WDEMS via Google Forms" | Same as above. |
| §1.3 | Google OAuth / Form / Sheet rows marked Complete | Removed. |
| §2.1 | "2FA: disabled (feature exists in Fortify, not enabled)" | Fortify TOTP still disabled, but WDEMS has custom email 2FA. |
| §2.2 | "`auth` + `verified` group — gates all event routes" | Now `auth` + `verified` + `device.trusted`. |
| Table of Contents | Lists 12 sections | Body only has §1 and §2. §3–§12 were never written. |

The TOC vs. body mismatch is a pre-existing fact — the log was never
completed past §2.7. This append does not attempt to fill those
sections in. It records what has been built since.

---

## See also

For a structured, dated changelog of every file changed per session,
see `progress.md`. This file (`development-log.md`) tells the story;
`progress.md` provides the audit trail.
