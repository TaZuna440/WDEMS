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

