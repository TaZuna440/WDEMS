# WDEMS — Known Issues

A running backlog of problems identified during review. Each entry
records enough to fix the problem later without re-investigating.

**Purpose:** capture problems so work on them can be deferred without
losing context. When you pick up an issue, read the entry — do not
re-investigate from scratch.

**Not a bug tracker.** No assignees, no priorities in a project-tool
sense. Severity is rough guidance only.

**Append-only.** When an issue is fixed, add `**Status:** Fixed — <date>
— <one-line note>` under the title. Do not delete entries.

---

## How to add an entry

Use this template. Keep it short. If a section does not apply, write
"None" rather than omitting it.

    ## ISSUE-NNN — Short title

    **Severity:** Low | Medium | High | Critical
    **Status:** Open
    **Found in:** <file path or subsystem>
    **Related:** <link to subsystem doc + section, if any>

    ### Symptoms
    What a user or developer sees.

    ### Root cause
    The specific code or omission that causes it. Quote the file and
    line or the relevant method.

    ### Fix outline
    The shape of the change — no code, just the plan. Include decisions
    that must be made before implementation.

    ### Verification when fixed
    How to confirm the fix landed. Commands, browser steps, or test
    names.

---

## ISSUE-001 — Sidebar is not role-aware

**Severity:** Low (cosmetic — no security impact)
**Status:** Open
**Found in:** `resources/js/components/app-sidebar.tsx`,
`resources/js/components/nav-main.tsx`, `resources/js/types/navigation.ts`
**Related:** `docs/authentication.md` §2 (auth surface),
`docs/architecture.md` (React component architecture section)

### Symptoms

Every logged-in user — admin or staff — sees the same sidebar items.
The nav array is a hardcoded constant:

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Events', href: '/events', icon: CalendarDays },
    ];

If an admin-only feature is added later (audit log, user management,
system settings), it will appear to staff as well, and the only thing
stopping staff from using it will be the route-level middleware — which
produces a 403 page rather than a hidden link.

Concrete example from the session: **an audit log intended for admins
only would currently be visible to staff in the sidebar.**

### Root cause

Two structural gaps:

1. `NavItem` (in `types/navigation.ts`) has no way to express "visible
   to role X only". The type is:

       export type NavItem = {
           title: string;
           href: NonNullable<InertiaLinkProps['href']>;
           icon?: LucideIcon | null;
           isActive?: boolean;
       };

2. `NavMain` (in `nav-main.tsx`) hardcodes the group label
   `<SidebarGroupLabel>Platform</SidebarGroupLabel>` and renders one
   flat list. There is no mechanism for a second group, so admin-only
   items would be visually mixed with staff items.

The user's role **is** available everywhere — `HandleInertiaRequests`
shares `auth.user` and `auth.user.role` is part of the `User` type. So
the missing piece is purely on the frontend rendering side.

### Fix outline

Four small changes:

1. **Extend `NavItem`** with an optional `roles?: string[]` field.
   Items without `roles` are visible to everyone. Items with `roles`
   are visible only when the current user's role is in the list. This
   is the extensibility hook — every future role-gated item reuses it.

2. **Make the group label a prop** on `NavMain`. Currently hardcoded to
   `"Platform"`. Change to `label: string` so the sidebar can render
   two groups.

3. **Filter and split** in `app-sidebar.tsx`. Read
   `usePage().props.auth.user.role`, filter the item list, and render
   two `NavMain` groups: "Platform" (everyone) and "Admin" (admins
   only). Staff sees one group, admins see two.

4. **Decide what the "Admin" group contains today.**

### Decisions to make before implementing

Four decisions, none of which are code:

- **One group or two?** Two is recommended (matches the audit-log
  example). Requires step 2 above. One group with filtered items is
  simpler but visually mixes audiences.

- **What goes in the Admin group today?** Options: a single item
  linking to `/admin/dashboard` (makes the mechanism testable but the
  target page is currently a stub — see ISSUE-002), no items yet (the
  mechanism exists but is unverified), or a placeholder item (ships a
  broken link). Recommended: single item to `/admin/dashboard`.

- **What happens to `admin/dashboard.tsx`?** Keep the stub, add
  content, or delete it and drop the link. See ISSUE-003.

- **Does the "Dashboard" item change per role?** Today it always
  points at `/dashboard` (staff view) even for admins. Options: leave
  it (admins have two paths to two different dashboards via the same
  label — confusing), or make the URL dynamic (`/admin/dashboard` for
  admins, `/dashboard` for staff — cleaner).

### Verification when fixed

- Log in as admin → sidebar shows both "Platform" and "Admin" groups
- Log in as staff → sidebar shows only "Platform"
- Click the Admin group item as admin → lands on the admin page
- Direct URL `/admin/dashboard` as staff → still 403 (middleware
  unchanged)
- No console errors about missing role

---

## ISSUE-002 — Dashboard content is not role-aware

**Severity:** Low (functionally correct — both roles see a useful page)
**Status:** Open
**Found in:** `app/Http/Controllers/DashboardController.php` (line 13)
**Related:** `docs/architecture.md` (Layered Overview),
`docs/authentication.md` Trap #2

### Symptoms

Admin and staff both land on the same page after login. The URL
differs (`/admin/dashboard` vs `/dashboard`), but the rendered content
is identical — the action feed and "Coming Up" list are the same
component, the same props, the same layout.

The route-level admin gate (`AdminMiddleware`) is the only place
role has any effect on the dashboard.

### Root cause

`DashboardController::__invoke()` renders a fixed component:

    return Inertia::render('dashboard', [
        'actionItems' => $actionItems,
        'upcoming' => $upcoming,
        'recentActivity' => [],
    ]);

There is no branch on `$request->user()->isAdmin()`. Both routes in
`web.php` point to this same controller:

- `Route::get('dashboard', DashboardController::class)->name('dashboard')`
- `Route::get('admin/dashboard', DashboardController::class)->name('admin.dashboard')`

The route is correct. The controller ignores it.

### Fix outline

Depends on the answer to a product question:

**What does an admin need to see that a staff user should not?**

- **If "nothing yet":** keep the shared page. No change needed. This
  issue is informational until there is real admin content.

- **If "role-specific sections on the same page":** add an `is_admin`
  prop to the Inertia payload and render sections conditionally in
  `dashboard.tsx`.

- **If "a fully separate admin page":** branch on role inside
  `DashboardController::__invoke()` and render `admin/dashboard` for
  admins, `dashboard` for staff. This is the only option that makes
  the existing `admin/dashboard.tsx` file meaningful (see ISSUE-003).

### Decisions to make before implementing

- **What is the admin-only content?** Audit log, user list, system
  metrics, cross-event analytics, nothing yet? Without an answer, this
  issue cannot be closed — a fix would just be two pages that look
  identical.

- **Shared props or separate controllers?** If the eventual pages share
  data (e.g. both show upcoming events), a single controller with a
  role branch is simpler. If the pages share nothing, two controllers
  and two routes are cleaner.

### Verification when fixed

- Log in as admin → sees admin content
- Log in as staff → does not see admin content
- Direct URL as the wrong role → 403 (middleware) or a redirect to
  the correct page

---

## ISSUE-003 — `admin/dashboard.tsx` is dead code

**Severity:** Cosmetic (confusing, not broken)
**Status:** Open
**Found in:** `resources/js/pages/admin/dashboard.tsx`
**Related:** `docs/authentication.md` Trap #2, ISSUE-002

### Symptoms

A file exists at `resources/js/pages/admin/dashboard.tsx` with a
15-line component. No controller anywhere in the codebase renders it.

If a developer asks AI to "add a widget to the admin dashboard," the
AI will almost certainly edit this file — and the change will have no
effect on the running application. The route still renders
`dashboard`, not `admin/dashboard`.

### Root cause

The file was created (probably as part of an early admin-dashboard
plan) but `DashboardController` was never updated to render it. The
route exists, the middleware exists, the page component exists —
but the controller does not point at it.

Verification command:

    grep -rn "admin/dashboard" app/ resources/js/
    grep -rn "Inertia::render" app/Http/Controllers/DashboardController.php

The first command finds the route definition and the file itself, and
the sidebar reference (if ISSUE-001 is fixed). The second command shows
the controller always renders `dashboard`.

### Fix outline

Three options, all valid:

1. **Delete the file** and drop any references to it. Cleanest if
   there is no plan for a separate admin dashboard.

2. **Wire it up** by branching in `DashboardController::__invoke()`.
   Requires a content decision first (ISSUE-002).

3. **Leave it but add a header comment** explaining that the file is
   not rendered yet. Least preferred — dead code with a comment is
   still dead code.

### Decisions to make before implementing

Same as ISSUE-002: what does the admin dashboard actually contain?

### Verification when fixed

- If deleted: `grep -rn "admin/dashboard" app/ resources/js/` finds
  only the route definition (or nothing).
- If wired: log in as admin → URL is `/admin/dashboard`, browser
  network tab shows `component: "admin/dashboard"` in the Inertia
  response.

---

## ISSUE-004 — No authorization policy on event edit

**Severity:** High (any authenticated staff user can edit any event)
**Status:** Open
**Found in:** `app/Http/Requests/EventRequest.php` (line 18),
`app/Http/Controllers/EventController.php` (edit, update)
**Related:** `docs/event-creation.md` Trap #11

### Symptoms

Any logged-in staff user can open `/events/{id}/edit` for any event in
the system — including events created by other staff — and save
changes. There is no ownership check, no role check, no policy.

The route middleware (`auth`, `verified`, `device.trusted`) confirms
the user is a real, verified staff member. It does not confirm the
user is allowed to edit **this specific event**.

### Root cause

`EventRequest::authorize()` returns `true`:

    public function authorize(): bool
    {
        return true;
    }

No policy is registered for the `Event` model. Laravel's default
behavior when no policy exists is to permit — the gate has nothing to
check against.

`EventController::edit()` and `update()` check `$event->canEdit()`,
but that is a **status** guard (is the event in an editable state),
not an **ownership** guard (is this user allowed to edit it). A staff
user editing another staff user's draft event will pass `canEdit()`
because the event is in `draft` status.

The `created_by` column exists on `events` and records who created
each event, but nothing in the codebase reads it for authorization.

### Fix outline

This is the biggest RBAC gap in the system and the one the sidebar
work does not address.

1. **Decide the ownership model.** Options:
   - Any staff can edit any event (current behavior — deliberate?)
   - Only the creator can edit their own events
   - Only the creator or an admin can edit
   - Staff can edit their own; admins can edit anyone's

2. **Implement the chosen model.** The natural place is a Laravel
   policy (`EventPolicy`) registered in `AppServiceProvider`. The
   policy methods (`view`, `update`, `delete`) would consult
   `$user->id === $event->created_by` and/or `$user->isAdmin()`.

3. **Enforce it.** Replace the `authorize(): true` in `EventRequest`
   with a call to the policy, or use `Gate::authorize()` in the
   controller. Both work; pick one and be consistent.

4. **Cover the same decision for options and workflow routes.**
   `EventOptionController` and the workflow actions
   (`configure`, `openRegistration`, `closeRegistration`) have the
   same gap. Fixing only the edit endpoint leaves an inconsistent
   surface.

### Decisions to make before implementing

- **Ownership model** (from step 1 above). This is a product decision
  — no code change can be proposed without it.
- **What about events created before the change?** All existing events
  have a `created_by` value. If the model is "only creator can edit,"
  admins need a way to reassign ownership or override. If admins
  always override, this is moot.

### Verification when fixed

- Create event A as staff user 1
- Log in as staff user 2, try to edit A → 403 or redirect
- Log in as admin, try to edit A → succeeds
- Log in as staff user 1, edit A → succeeds
- Add a feature test to `tests/Feature/Events/` covering the above

---

## Other known issues (pointers only)

These are documented in full in their subsystem guides. Listed here so
this file works as a master index. If you fix one, add a pointer entry
under it or move it up into a full entry.

| # | Title | Where | Severity |
|---|---|---|---|
| A-1 | SecurityTest.php skips 3 tests silently | `docs/authentication.md` Trap #6 | Low |
| A-2 | Flash `status` not shared to Inertia | `docs/authentication.md` Open #3 | Cosmetic |
| A-3 | Device-token hash claim in architecture.md wrong | Fixed via `architecture.md` corrections | — |
| A-4 | Mail queued class sent synchronously | `docs/authentication.md` Trap #9 | Low |
| E-1 | Dead `isEdit` ternary in BasicsStep | `docs/event-creation.md` Trap #3 | Cosmetic |
| E-2 | Workflow transitions not wired to routes | `docs/event-creation.md` Open #4 | Low |
| E-3 | No test coverage on event CRUD controllers | `docs/event-creation.md` §8.2 | Low |
| D-1 | No try/catch in `EventDeletionController::destroy()` | `docs/event-deletion.md` Open #1 | Low |
| D-2 | `locked` detection uses string-match on error text | `docs/event-deletion.md` Trap #1 | Medium |
| D-3 | `RESEND_COOLDOWN_SECONDS` duplicated across PHP/TS | `docs/event-deletion.md` Trap #3 | Cosmetic |
| D-4 | No autoFocus on OTP input in deletion dialog | `docs/event-deletion.md` Open #4 | Cosmetic |
| W-1 | Checkpoint has no "resume draft" prompt | `docs/event-creation-wizard.md` §11.1 | Low |
| W-2 | Checkpoint `clear()` fires before submit | `docs/event-creation-wizard.md` §11.1 | Low |
| W-3 | FAQ entries cannot be edited through any UI | `docs/event-creation-wizard.md` §11.2 | Medium |
| W-4 | Partner contact info not stored | `docs/event-creation-wizard.md` §11.4 | Low |
| W-5 | No server-side draft persistence | `docs/event-creation-wizard.md` §11.5 | Low |

---

## Change log

- **2026-09-25** — File created. ISSUE-001 through ISSUE-004 added.
  Other known issues indexed with pointers to subsystem docs.

---

## Later additions

Issues added after the file was first written. The main body above is
preserved as-is. New issues go here, in the order they are found.

### ISSUE-005 — Email verification gate was inactive

**Severity:** High
**Status:** Fixed — 2026-09-25
**Found in:** `app/Models/User.php`
**Related:** `docs/authentication.md` §Email verification

**Symptoms.** Unverified users could reach `/dashboard`, `/events`,
and every other route inside the `auth, verified, device.trusted`
middleware group. The `verified` middleware was a no-op.

Not visible in practice because every user in the system is created
with `email_verified_at => now()` — the factory default, and the
manual Tinker snippet in `README.md` sets it explicitly. Only a user
with a null `email_verified_at` would hit the bug, and no current code
path produces that user.

**Root cause.** `app/Models/User.php` did not implement
`Illuminate\Contracts\Auth\MustVerifyEmail`. Laravel's `verified`
middleware checks whether the authenticated user is an instance of
that interface before enforcing the gate. Because `User` was not an
instance, the middleware short-circuited and passed every request
through.

The trait is provided by `Illuminate\Foundation\Auth\User`, but the
interface has to be declared on the class explicitly.

**Fix applied.** Two lines on `app/Models/User.php`:

    use Illuminate\Contracts\Auth\MustVerifyEmail;
    class User extends Authenticatable implements MustVerifyEmail

No other changes to the model, no changes to any controller,
`config/fortify.php`, `routes/web.php`, or the frontend.

**Diagnostic.** The regression test was written and run **before** the
fix. On the unfixed code, 2 of 4 tests failed — the middleware returned
200 OK instead of redirecting to `verification.notice`. After the
fix, all 4 pass. Auth suite went from 38 passed to 42 passed, no
regressions.

**Verification.**

    php artisan test tests/Feature/Auth/EmailVerificationGatingTest.php
    php artisan test tests/Feature/Auth

First command: 4 passed. Second: 42 passed, 118 assertions.

---

## Change log addendum

- **2026-09-25** — ISSUE-005 added under "Later additions". Email
  verification gate was inactive. Fixed by adding
  `implements MustVerifyEmail` to `User`. Regression test added.

---

### ISSUE-006 — Logout cleared the trusted-device cookie

**Severity:** Low (usability, no security impact)
**Status:** Fixed — 2026-09-25
**Found in:** `app/Providers/AppServiceProvider.php`
**Related:** `docs/authentication.md` §Email 2FA — Logout cleanup

**Symptoms.** Every logout forced a 2FA challenge on the next login.
Users had to re-enter a 6-digit code every single time they signed
back in, even on the same browser, even on the same day, even after
ticking "Remember this device for 30 days".

**Root cause.** `configureLogoutCleanup()` listened for the `Logout`
event and queued `Cookie::forget('wdems_trusted_device')`. The intent
was to prevent a shared browser from leaking trust to the next user.
But the middleware that reads the cookie already checks trust on a
**per-user** basis (`EmailTwoFactorService::hasTrustedDevice()` only
matches entries in the current user's `trusted_devices` array). A
cookie left behind is useless to another user. The listener added no
security — only friction.

**Fix applied.**

- Removed `configureLogoutCleanup()` from `AppServiceProvider`
- Removed its call from `boot()`
- Removed the four now-unused imports (`EmailTwoFactorService`,
  `Logout`, `Cookie`, `Event`)
- Inverted `tests/Feature/Auth/LogoutDeviceTrustTest.php` — it now
  asserts the cookie is *not* cleared on logout

**No changes to:** `EnsureDeviceIsTrusted`, `EmailTwoFactorService`,
the settings page, or any route. The trust-list-based check was always
the real defense and remains untouched.

**Verification.**

    php artisan test tests/Feature/Auth/LogoutDeviceTrustTest.php
    php artisan test tests/Feature/Auth

First command: 1 passed (assertion inverted). Second: pending Block 3
of the same session — expected 44 passed, no regressions.

**Manual verification.** Log in on a browser, tick "Remember this
device", complete the OTP. Log out. Log in again on the same browser.
No 2FA challenge. Redirect to dashboard directly.

---

## Change log addendum

- **2026-09-25** — ISSUE-006 added under "Later additions". Logout no
  longer clears the trusted-device cookie. Fixed in
  `AppServiceProvider`.
