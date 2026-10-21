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

---

### ISSUE-007 — `participants.address` column width mismatches validation

**Severity:** Medium (silent data loss in non-strict MySQL)
**Status:** Open
**Found in:** `app/Http/Requests/PublicRegistrationRequest.php`,
`database/migrations/2026_09_16_160315_create_participants_table.php`
**Related:** `docs/registration.md` §4, `docs/registration-development-plan.md`
(Phase 3 complete, Known issues carried forward)

### Symptoms

A participant enters an address longer than 255 characters but no
longer than 500. Client validation passes (the client mirrors the
server's `max:500` rule). The form submits. On the server, one of
two things happens depending on MySQL's `sql_mode`:

- **Non-strict mode:** MySQL silently truncates the value to 255
  characters. The registration succeeds and the participant's
  address is missing its tail. No error is surfaced.
- **Strict mode (Laravel's default):** MySQL raises a `Data too long`
  exception. The transaction rolls back. The user sees a generic 500
  page.

Both outcomes are wrong. The first loses data silently; the second
crashes the request when the validation said the input was fine.

### Root cause

Two rule sets disagree:

- `PublicRegistrationRequest::rules()` — `'address' => ['string', 'max:500']`
- `database/migrations/2026_09_16_160315_create_participants_table.php` —
  `$table->string('address')` (default 255)

Neither was wrong on its own. The mismatch was latent since the
public form was built — it only becomes reachable when a participant
types a long address. The `contact_number` fix in Block C
(`2026_10_02_110000_make_contact_number_nullable_on_participants_table.php`)
surfaced the same class of issue on a different column and prompted
this entry.

### Fix outline

Two options, both valid:

**Option A — widen the column.** Change `participants.address` to
`varchar(500)` in a new migration. Matches the validation rule, keeps
the 500-character ceiling the request already advertises.

**Option B — tighten the validation.** Change
`PublicRegistrationRequest::rules()` to `'max:255'`. Matches the
column, avoids a schema change. Loses 245 characters of legitimate
address room.

Recommendation: **Option A**. Addresses are legitimately long. The
500-character ceiling was chosen deliberately when the public form
was written; the column just never caught up. Widening is one
migration, no user-facing change.

### Decisions before implementing

- **Confirm the current column length** — the migration file says
  `string('address')` which is 255, but a prior migration may have
  altered it. Verify with `SHOW COLUMNS FROM participants`.
- **Backfill or migrate in place?** No existing data needs backfill —
  this is a widening operation. `ALTER TABLE` with no explicit
  default is enough.

### Verification when fixed

1. Submit a public registration with a 400-character address
2. Confirm the participant's stored address is 400 characters, not
   255
3. Confirm no exception is logged

---

### ISSUE-008 — No-email deduplication limitation is not surfaced in the form builder UI

**Severity:** Low (organizer's choice, just not fully informed)
**Status:** Open
**Found in:** `resources/js/pages/events/registration-form.tsx`,
`app/Http/Controllers/PublicRegistrationController.php`
**Related:** `docs/registration-development-plan.md` (Phase 3
complete, Known issues carried forward)

### Symptoms

An organizer unchecks "Required" for the email field in the form
builder, publishes the event, and shares the URL. Two participants
submit the same form with no email and the same name. Both
submissions succeed and produce two `Registration` rows and two
`Participant` rows — even though a required-email event would have
rejected the second submission as a duplicate.

There is no warning on the form builder page that unchecking
"Required" for email disables the D5 duplicate rule. The organizer
learns this only from reading
`PublicRegistrationController::store()`'s docblock or from
`docs/registration.md`.

### Root cause

The D5 duplicate check in `PublicRegistrationController::store()`
runs only when the submission carries an email:

    $emailProvided = is_string($email) && $email !== '';

    if ($emailProvided) {
        $alreadyRegistered = Registration::where('event_id', $event->id)
            ->whereHas('participant', fn ($q) => $q->where('email', $email))
            ->exists();

        if ($alreadyRegistered) {
            throw ValidationException::withMessages([...]);
        }
    }

When `$emailProvided` is false, both the duplicate check and the
`Participant::firstOrCreate` identity reuse are skipped. This is
correct behavior — an anonymous registration has no identity key,
so deduplication cannot apply. But it is invisible to the organizer
at the moment they make the decision that causes it.

### Fix outline

Add a helper note beneath the email row in the form builder's common
fields section. It only shows when the email toggle is unchecked:

    Note: With email optional, participants who do not provide an
    email cannot be deduplicated. Each submission creates a separate
    registration and participant.

Optional extensions:

- A confirmation step when the organizer unchecks email-required
  for the first time — "This disables duplicate detection for this
  event. Continue?"
- A small info icon next to the toggle with a tooltip carrying the
  same text.

Recommendation: the inline note alone. The confirmation step is
heavier than the decision warrants; the toggle is reversible until
Open Registration.

### Decisions before implementing

- **Where does the note live?** Below the email row only, or below
  the whole common-fields grid?
- **Does the same note apply to contact_number?** Contact number is
  not currently used for deduplication. No note needed there. But if
  the deduplication key ever expands (e.g. phone-based identity),
  this decision reopens.

### Verification when fixed

1. Open the form builder for a Draft event
2. Uncheck "Required" for email
3. Confirm the note appears beneath the email row
4. Re-check "Required"
5. Confirm the note disappears

---

## Change log addendum

- **2026-10-02** — ISSUE-007 and ISSUE-008 added under "Later
  additions". Both surfaced during Phase 3 close. Neither is fixed.
  ISSUE-007 is a latent schema-validation mismatch; ISSUE-008 is a
  UX gap in the form builder.

---

### ISSUE-009 — Duplicate-phone submissions return 500 in email-optional events

**Severity:** Medium (500 on a specific submission shape)
**Status:** Fixed — 2026-10-02 — commit cf97486
**Found in:** `app/Http/Controllers/PublicRegistrationController.php`,
`app/Models/Participant.php`
**Related:** `docs/participant-identity.md` (Status block, Known
gap), `docs/participant-identity-and-monitoring-plan.md` (Phase 5)

### Symptoms

An event is configured with email optional (`email => false` in
`registration_common_field_requirements`). A participant submits a
registration with a phone number and no email. A second
participant submits with the same phone number, also no email. The
second submission returns a 500 Internal Server Error instead of
a validation error.

The organizer sees nothing in the UI — the second participant's
browser shows a generic error page. No registration is created.
The participant's data is lost if they do not retry.

### Root cause

Two changes from the participant-identity build landed before the
controller that uses them:

- Phase 1 (`cf6d4df`) added the UNIQUE constraint on
  `participants.contact_number_normalized`.
- Phase 3 (`22e603c`) added
  `Participant::setContactNumberAttribute`, which populates the
  column via mutator, and `Participant::resolveFrom()`, which
  matches on the normalized phone.

But Phase 5 — the controller rewrite that actually calls
`resolveFrom()` — has not landed. The controller still uses the
pre-identity logic:

    if ($emailProvided) {
        $participant = Participant::firstOrCreate(
            ['email' => $email, 'first_name' => ..., 'last_name' => ...],
            $participantAttributes,
        );
    } else {
        // No email → no identity key. Always create a new row.
        $participant = Participant::create($participantAttributes);
    }

The `create()` branch tries to insert a new `Participant` row with
the same `contact_number_normalized` as the previous submission.
The UNIQUE index rejects it. No exception handler catches it. The
500 surfaces.

### Fix outline

Phase 5 of the identity plan. Replace both branches with a single
call:

    $participant = Participant::resolveFrom(
        $email,
        $validated['contact_number'] ?? null,
        $participantAttributes,
    );

`resolveFrom` matches on the normalized phone when email is absent,
finds the existing participant, and returns it. The subsequent
`Registration::create()` then collides with the
`(event_id, participant_id)` UNIQUE index — which the controller
already handles by way of the D5 duplicate check.

**No interim fix recommended.** The permanent fix is one commit
away. An interim try/catch would be removed by Phase 5.

### Verification when fixed

1. Configure an event with email optional
2. Submit with phone `09171234567`, no email
3. Submit again with the same phone, no email, different name
4. Confirm the second submission shows a validation error (not a 500)
5. Confirm only one `Participant` row exists for that phone

### Note on exposure window

Until Phase 5 lands, an organizer running an email-optional event
is exposed to this 500. The window is bounded by how many events
are configured that way. If any real participant submission is
expected before Phase 5 ships, the fix should be prioritized.

---

### ISSUE-010 — Legal layer is a UI demo; no server-side consent recording

**Severity:** High (compliance gap under the DPA)
**Status:** Open
**Found in:** `resources/js/pages/registrations/public.tsx`,
`resources/js/lib/demo-legal-content.ts`,
`app/Http/Requests/PublicRegistrationRequest.php`
**Related:** `docs/participant-identity.md` §4.4–4.5,
`docs/known-issues.md` (this entry)

### Symptoms

The public registration page renders a Privacy Notice and Terms &
Conditions in modals, plus a required consent checkbox. The checkbox
blocks submission via HTML5 validation. But:

- The consent is not recorded server-side. A participant who
  unchecks the box after clicking Submit, or who submits via a
  tool that bypasses HTML5, produces a `Registration` row with
  `consent_accepted_at = NULL` and `privacy_notice_version = NULL`.
- The Privacy Notice and Terms content is placeholder text with
  `[bracketed]` items. It has not been reviewed by a lawyer.
- Both documents are global — identical for every event. There is
  no per-event customization.
- There are no guardian fields for participants under 18. The `age`
  field is collected but nothing acts on it.
- The Privacy Notice and Terms have no stable version identifiers.
  If the content changes, there is no record of which version a
  prior participant accepted.

### Root cause

The demo was built to close the visible gap on the public page —
"the form looks like it collects consent" — while the schema
columns and the per-event content model were deferred to Phase 3.5.
`consent_accepted_at` and `privacy_notice_version` exist as nullable
columns (Phase 1 of the identity plan), but nothing writes them.

The demo text lives in `resources/js/lib/demo-legal-content.ts`.
Every `[bracketed]` item requires an organizer decision: organizer
name, contact email, retention period, refund policy, event name,
distance.

### Fix outline

Phase 3.5, not yet planned in a doc. Sketch:

1. **Schema.** `events.privacy_notice` and `events.terms` as text
   columns. Per-event, editable in the form builder.
2. **Form builder UI.** Two textareas on `registration-form.tsx`
   for editing the two documents.
3. **Public form.** Render the per-event content instead of the
   demo constants. Pass `consent_accepted` through the request.
4. **Request.** Add `consent_accepted` (boolean, required, must be
   `true`). Set `consent_accepted_at = now()` and
   `privacy_notice_version = <constant>` on the `Registration` row.
5. **Guardian fields.** When `age < 18`, render a conditional
   section asking for guardian name, guardian contact, and
   guardian consent. Store on `registrations` or a new
   `registration_guardians` table.
6. **Legal content.** The demo text is a starting point. Real
   content requires a lawyer's review — this is a Phase 3.5
   blocker, not a code issue.

### Decisions before implementing

- **Real legal content.** The placeholder text needs to be replaced
  by reviewed content before this is shared with real participants.
  This is the blocking decision.
- **Per-event vs. global.** Do all events share one Privacy Notice,
  or does each organizer provide their own? Depends on whether WDEMS
  is single-tenant (one organizer) or multi-tenant.
- **Guardian data location.** A `guardians` table, or fields on
  `registrations`? Affects whether guardian data is queryable
  across events.
- **Consent version scheme.** What constitutes a new version? Any
  edit to the notice, or an explicit "publish new version" action?
  This affects whether old consents remain valid after an edit.

### Verification when fixed

1. Configure an event with a custom Privacy Notice
2. Submit the public form with consent checked
3. Confirm the `Registration` row has a non-null
   `consent_accepted_at` and a matching `privacy_notice_version`
4. Submit with consent unchecked → rejected with a validation error
5. Submit with `age < 18` → guardian section renders and is
   required
6. Edit the notice text → new submissions record the new version,
   old records keep the old

---

### ISSUE-011 — `known-issues.md` pointer W-3 references a removed feature

**Severity:** Cosmetic (a documentation pointer)
**Status:** Open
**Found in:** `docs/known-issues.md` (Other known issues table,
row W-3)
**Related:** `docs/fixes.md` (Phase 2 FAQ removal, commit `f03f450`)

### Symptoms

The pointer table at the top of `known-issues.md` contains:

    | W-3 | FAQ entries cannot be edited through any UI |
          `docs/event-creation-wizard.md` §11.2 | Medium |

The FAQ feature was removed end-to-end in the registration Phase 2
build (commit `f03f450`). The `events.faq` column is preserved on
the schema but nothing reads or writes it. `event-creation-wizard.md`
§11.2 still describes the gap as if FAQ were live.

A reader following the pointer finds a section about a feature that
no longer exists.

### Root cause

The FAQ removal landed as part of Phase 2 of the registration build.
The pointer table was not updated — it predates the removal. The
`event-creation-wizard.md` file was not corrected either, because
its §11 section describes the state before the removal.

### Fix outline

Two edits, both small:

1. **Delete the W-3 row** from the pointer table in
   `known-issues.md`. The feature it references does not exist.
2. **Add a corrections block** to `event-creation-wizard.md`
   noting that §11.2 is stale. This is part of the larger
   doc-sweep work for that file (Tier 3 in the current doc plan),
   but the one-line correction can be appended independently.

Not urgent. The pointer table has fifteen rows; one stale row
among them does not mislead most readers.

### Decisions before implementing

None. Both edits are mechanical.

### Verification when fixed

    grep -n "W-3" docs/known-issues.md
    # Expected: no hits (or a "removed" note)

    grep -n "FAQ" docs/known-issues.md
    # Expected: no W-3 reference to a nonexistent feature

---

## Change log addendum

- **2026-10-02** — ISSUE-009 (duplicate-phone 500), ISSUE-010
  (legal layer is a demo), and ISSUE-011 (W-3 pointer stale)
  added under "Later additions". ISSUE-009 is an interim bug
  awaiting Phase 5. ISSUE-010 is a compliance gap awaiting Phase
  3.5 and legal content. ISSUE-011 is cosmetic.

---

### ISSUE-012 - Garbage contact_number and address accepted by public registration

**Severity:** Medium (data quality, public unauthenticated form)
**Status:** Fixed - 2026-10-10 - see FIX-027
**Found in:** `app/Http/Requests/PublicRegistrationRequest.php`,
`app/Http/Requests/WalkInRegistrationRequest.php`,
`resources/js/lib/public-registration-validation.ts`
**Related:** `docs/fixes.md` FIX-027,
`docs/participant-identity.md` §7 (the at-least-one rule is a
presence rule, not a validity rule)

### Symptoms

The public registration form at `/r/{slug}` accepted
`contact_number = "fgfdgfdgdfg"` and
`address = "dfdfdsfdsfdsfd"` on a submission with a valid email.
The values were stored raw. The same gap existed in the walk-in
dialog.

### Root cause

- `contact_number` rule was `['string', 'max:50']` — no format check.
- `address` rule was `['string', 'max:500']` — no quality check.
- The at-least-one identity rule (participant-identity.md §7) only
  fires when both email and phone are absent. A garbage phone with
  a valid email present never triggers it.
- The client-side validation mirrored the same length-only checks.

### Fix outline

New `App\Concerns\ContactAndAddressQualityRules` trait, mirrored in
`public-registration-validation.ts`. Applied to both public and
walk-in requests. See FIX-027 for the full shape.

### Verification when fixed

    php artisan test tests/Feature/ContactAddressQualityTest.php

Expected: 8 passed. The test file exercises both the public form
and the walk-in dialog against letters-in-phone, too-few-digits,
no-vowel address, no-letter address, and the landline-accepted
case.

---

### ISSUE-013 — Event start-time rule parsed naive datetimes as UTC

**Severity:** Medium (silent data quality issue)
**Status:** Fixed — 2026-10-12 — commit 9bfe7d4
**Found in:** `app/Concerns/EventValidationRules.php`,
`config/app.php`
**Related:** `docs/attendance-encoding-and-reporting.md` (time-based
gates), `docs/progress.md` 2026-10-12 entry

### Symptoms

A user in Manila (UTC+8) opened the event creation wizard and entered
a today-event whose local start time had already passed. The
wizard accepted it. The event was created and stored with a
start time in the past.

The window for the bug was roughly eight hours per day — every
today-event entered between midnight and 08:00 Manila time whose
`start_time` was between midnight and 08:00 local was accepted
regardless of whether that time had already passed.

No error message. No log entry. Silent.

### Root cause

`validateEventStartIsInFuture()` parsed the date and time with:

    $start = Date::parse("{$date} {$time}");

`Date::parse` interprets a naive datetime string in
`config('app.timezone')`. That config was hardcoded to `'UTC'` in
`config/app.php`, with no `APP_TIMEZONE` in `.env`, `.env.example`,
or `phpunit.xml`.

The client-side mirror
(`resources/js/lib/event-validation.ts::validateSchedule`) ran in
the browser and used the browser's timezone — Manila for this
user. The two checks disagreed by 8 hours in the "server thinks
the time is earlier than the client does" direction.

Concretely: at 10:00 Manila (= 02:00 UTC), a user entered a
today-event with `start_time = "08:00"`. The server parsed
"2026-10-12 08:00" as 08:00 UTC. `now()` was 02:00 UTC.
`08:00 > 02:00` — the check passed. But 08:00 Manila had already
happened two hours earlier.

The rule never false-rejected for UTC+8 (the server's wall clock
was always at or earlier in string terms), but it silently let
backdated events through.

### Fix applied

`config/app.php` now reads `env('APP_TIMEZONE', 'Asia/Manila')`.
Default changed from `'UTC'` to `'Asia/Manila'`.

`.env.example` gains `APP_TIMEZONE=Asia/Manila` so a fresh clone
copies the correct value.

The rule itself was not modified. The fix is at the config layer —
every `now()`, `Date::parse`, and `today()` call across the
application now measures in Manila time consistently with the
user's wall clock.

### Scope of the config change

- All datetime parsing across the app now uses `Asia/Manila`
- Existing local MySQL rows were written under UTC. They read 8
  hours ahead relative to their actual creation instant until
  regenerated. Dev-only. Tests use SQLite `:memory:` and start
  clean each run. Fix: `php artisan migrate:fresh --seed`
- The browser-client mirror remains timezone-agnostic. For a
  Manila user this is now consistent. For a user elsewhere, the
  server is authoritative and the client gives fast feedback —
  whichever rejects first wins. Pre-existing tradeoff, unchanged

### Verification when fixed

    php artisan tinker --execute="
    echo config('app.timezone') . PHP_EOL;
    echo now()->timezoneName . PHP_EOL;
    echo date_default_timezone_get() . PHP_EOL;
    "

Expected: `Asia/Manila` on all three lines.

    php artisan test

Expected: 3 skipped, 490 passed. No regressions.

---

## Later additions — audit 2026-10-19

Issues derived from `docs/audit-2026-10-19.md` plus one client
decision from 2026-10-16. M5 and P5 are **not** here — M5 was closed
by W5.1 (`begin()` message made non-revealing) and P5 was closed by
W2 (consent columns written at finalize). Both are recorded in the
audit's §9 and §2.3 respectively.

Full reference for issues 014–024:
`docs/audit-2026-10-19.md`. Issue 025:
`docs/discipleship.md` + client Q14 (2026-10-16).

---

## ISSUE-014 — Church events can be driven through the running-event lifecycle

**Severity:** High
**Status:** Open (top-5 patch P1, not applied)
**Found in:** `app/Models/Event.php` (`canOpenRegistration`,
`canEdit`, `canEditRegistrationForm`),
`app/Http/Controllers/EventController.php`,
`app/Http/Controllers/DashboardController.php`,
`app/Enums/ChurchEventStatus.php`
**Related:** `docs/audit-2026-10-19.md` §3 (H1)

### Symptoms

Any staff user can `POST /events/{id}/open-registration` on a
church event. `canOpenRegistration()` returns true because the event
is in `status = Draft` (the default church creation status) and has
a saved registration form. The event generates a public `/r/{slug}`
URL and can be walked through `registration_open → closed → ongoing
→ completed` while the UI reads `church_status`. The auto-advance
middleware will fire on it once its start moment passes.

`canEdit()`, `canEditRegistrationForm()`, and
`RegistrationFormController::update` have the same gap.

`ChurchEventStatus` has no `allowedTransitions()`. `EventController::update`
sets `church_status` with only enum validation, so `Upcoming →
Completed → Cancelled → Upcoming` jumps are accepted without audit.

The dashboard reinforces the confusion: church events permanently
sit at `Draft` and appear as "Draft — no registration form yet"
action items.

### Root cause

The two lifecycles share the `events` table but the running-event
guards predate the church-event schema. `canOpenRegistration()`
checks `status` and `registration_form_saved_at` only — no
`event_kind` guard.

### Fix outline

Add `! $this->isChurchEvent()` to every running-lifecycle guard
(`canEdit`, `canEditRegistrationForm`, `canOpenRegistration`,
`canCloseRegistration`, `canFinalize`, `canRecordAttendance`,
`canRecordAttendanceNow`). Add a corresponding `! $this->isRunningEvent()`
guard to any future church-only guards.

Add `ChurchEventStatus::allowedTransitions()` mirroring
`EventStatus::allowedTransitions()`. Wire `EventController::update`
through a workflow method that enforces it — same pattern as
`EventWorkflow::transition()` on the running side.

### Verification when fixed

New feature test file `tests/Feature/Events/ChurchEventKindGuardTest.php`:
- `POST open-registration` on a church event → 403
- `PUT update` on a church event cannot bypass a legal → illegal
  `church_status` transition
- All existing running-event lifecycle tests still pass

---

## ISSUE-015 — No role checks in any church-module controller

**Severity:** High
**Status:** Open (top-5 patch P9, not applied)
**Found in:** `app/Http/Controllers/ServiceController.php`,
`ServiceAttendanceController.php`, `FellowshipController.php`,
`FellowshipGroupController.php`, `FellowshipAttendanceController.php`,
`DiscipleshipController.php`, `DiscipleshipBatchController.php`,
`DiscipleshipEnrollmentController.php`, `DiscipleshipSessionController.php`,
`DiscipleshipAttendanceController.php`
**Related:** `docs/audit-2026-10-19.md` §4 (H2),
`docs/fellowship.md` (admits the gap in §4 and §8)

### Symptoms

Any authenticated staff user can create, update, or delete any church
record — service occurrences, fellowship events and groups and
attendance rows, discipleship batches, sessions, enrollments,
attendance matrix cells. Destructive actions include
`FellowshipController::destroy`, `ServiceController::destroyOccurrence`,
`DiscipleshipBatchController::destroy`,
`DiscipleshipEnrollmentController::store`.

Zero `isAdmin()` / `Gate::authorize()` / `$this->authorize()` hits
across all twelve church controllers.

The running-event side at least gates deletion by role plus OTP. The
church side gates nothing.

### Root cause

The church modules were built under "any authenticated staff can do
anything" as an implicit scope decision, recorded in
`docs/fellowship.md` §8 ("any authenticated user can perform any
action; there is no per-event ownership check") but never surfaced
as a security finding.

### Fix outline

Two layers, either of which closes the issue:

**Option A (minimum):** add `abort_unless(auth()->user()->isAdmin(), 403)`
to every `destroy` action across all twelve controllers. Non-destructive
writes (create, update, mark) remain staff-accessible.

**Option B (preferred):** introduce a per-module policy — at minimum
`FellowshipEventPolicy`, `ServiceOccurrencePolicy`,
`DiscipleshipProgramPolicy`. `destroy` requires admin; `update`
requires admin or the record's creator. Wire through
`Gate::authorize()` in each controller action.

Decision needed before implementing: is per-record ownership
desirable, or is admin-only destructive access sufficient?

### Verification when fixed

New feature test per module: staff cannot destroy, admin can. Existing
running-event authorization tests unchanged.

---

## ISSUE-016 — `AttendanceController::mark()` wipes `verified_at` on re-mark

**Severity:** Medium
**Status:** Open (top-5 patch P3, not applied)
**Found in:** `app/Http/Controllers/AttendanceController.php`
**Related:** `docs/audit-2026-10-19.md` §5 (M1),
`docs/attendance-code.md` §5 (verified_at semantics)

### Symptoms

A registration whose attendance was marked with a matching code has
`verified_at` set. If staff later re-marks the same row (e.g., edits
notes, changes status from Present to Late), the `updateOrCreate`
call passes `verified_at => null` because no code was submitted this
request. The end-of-event report's verified-vs-override distinction
is silently destroyed for that row.

### Root cause

`mark()` calls `updateOrCreate(..., ['verified_at' => $verifiedAt])`
where `$verifiedAt` is set only when a code was submitted and matched
this request. Every other path writes null.

### Fix outline

Read the existing `verified_at` before writing. Preserve it unless a
code was submitted this request. The rule: `verified_at` transitions
`null → timestamp` (on code match) or stays at its current value;
it never transitions `timestamp → null` via `mark()`.

### Verification when fixed

New test in `tests/Feature/Events/AttendanceVerificationTest.php`:
- Mark Present with code → `verified_at` set
- Re-mark Present (no code) → `verified_at` still set
- Re-mark Absent → `verified_at` still set (absence doesn't clear
  history)

---

## ISSUE-017 — Deferred walk-in on a dateless event returns 500

**Severity:** Medium
**Status:** Open
**Found in:** `app/Http/Controllers/AttendanceController.php`
**Related:** `docs/audit-2026-10-19.md` §6 (M2),
`docs/attendance-redesign.md` §6 (encoding modes)

### Symptoms

An admin with `?force_open=1` on an event that has no `event_date`
submits a deferred walk-in (single or bulk). The controller calls
`$event->event_date->copy()` unconditionally, and `$event->event_date`
is null. Fatal error — 500.

`force_open` bypasses `canRecordAttendanceNow()`, which is the only
guard requiring a non-null date. Without `force_open`, the same
event fails the time-window gate cleanly.

### Root cause

The deferred-mode time computation assumes `event_date` is populated.
Normal events always are; only `force_open` plus a null `event_date`
reaches the code path.

### Fix outline

At the top of the deferred branch (both single and bulk), check
`$event->event_date !== null`. If null, reject with a 422 message:
"Deferred encoding requires an event date. Set the event's date
before encoding."

### Verification when fixed

New test: admin with `force_open=1` on a dateless event, deferred
walk-in → 422, not 500. Single and bulk both covered.

---

## ISSUE-018 — OTP attempt counters are non-atomic

**Severity:** Medium
**Status:** Open
**Found in:** `app/Services/EventDeletion/EventDeletionOtpService.php`,
`app/Services/EventDeletion/RegistrationDeletionOtpService.php`,
`app/Services/TwoFactor/EmailTwoFactorService.php`
**Related:** `docs/audit-2026-10-19.md` §7 (M3),
`docs/event-deletion.md` §4.2, `docs/authentication.md` §4.2

### Symptoms

A user submitting concurrent wrong OTP guesses can exceed
`MAX_ATTEMPTS = 5` before lockout triggers. Each concurrent request
reads the same attempt count, increments locally, writes back — the
last write wins and the count reflects fewer attempts than were
submitted.

In practice the window is narrow (the browser can only submit so
fast), but a scripted client can exploit it. The cooldown itself is
server-enforced, so the resend floor holds; only the lockout ceiling
is vulnerable.

### Root cause

Read-modify-write pattern: `$payload = Cache::get($key); $payload['attempts'] += 1; Cache::put($key, $payload);`.
Not atomic.

### Fix outline

Two options:

- **Option A:** store `attempts` in a dedicated cache key incremented
  via `Cache::increment()`, which is atomic across all drivers. The
  OTP payload becomes read-only metadata.
- **Option B:** wrap the read-modify-write in `Cache::lock($key, 5)`.

Option A is simpler and driver-portable. Option B preserves the
single-key shape.

### Verification when fixed

New concurrency test (or a repeat of the existing concurrency suite
pattern in `tests/Integration/`): 6 concurrent wrong submissions on
a fresh OTP yield 5 attempts recorded and a locked state on the 6th.

---

## ISSUE-019 — Unique-collision races → raw 500 despite friendly pre-checks

**Severity:** Medium
**Status:** Open
**Found in:** `app/Http/Controllers/FellowshipAttendanceController.php`,
`app/Http/Controllers/DiscipleshipEnrollmentController.php`,
`app/Models/Participant.php` (`resolveFrom`)
**Related:** `docs/audit-2026-10-19.md` §8 (M4),
`docs/composite-identity.md` §7 (still live post-W7)

### Symptoms

Two operations that logically require uniqueness:
`FellowshipAttendanceController::store` (UNIQUE on
`(fellowship_event_id, participant_id)`),
`DiscipleshipEnrollmentController::store` (UNIQUE on
`(participant_id, program_id)`), and
`Participant::resolveFrom()` (used by walk-in and bulk-walk-in).
Each does an `exists()`-check-then-insert or a `firstOrCreate`.

When two requests race past the `exists()` check (both see "not
present") and both attempt the insert, the second insert throws
`QueryException` and returns a 500. The user sees a server error
where they should see a "this person is already registered" or
"this participant already exists" message.

### Root cause

No `try/catch` around the write.

### Fix outline

Catch `Illuminate\Database\UniqueConstraintViolationException`
(Laravel 10+) around each write. On catch, return the same
user-facing error the pre-check would have produced, or resolve to
the existing record where the semantics allow (`resolveFrom` can
re-query and return the now-existing row).

`RegistrationVerificationService::finalize()` already does this
(retries on `UniqueConstraintViolationException`). Mirror that
pattern.

### Verification when fixed

New test per controller: simulate the race by inserting the record
between the pre-check and the write (via a test double or by using a
DB transaction that commits the first row before the second
request's write). Assert 422 (or the existing-row resolution),
never 500.

---

## ISSUE-020 — `resolveFrom` ignores email/phone cross-identity conflict

**Severity:** Low
**Status:** Open
**Found in:** `app/Models/Participant.php` (`resolveFrom`)
**Related:** `docs/audit-2026-10-19.md` §10 (L1),
`docs/composite-identity.md` §7.3

### Symptoms

When `resolveFrom($email, $phone, $attrs)` is called with both an
email and a phone, and the email matches Participant A while the
phone matches Participant B (two different people), the method
matches by email and silently ignores the phone conflict.

`RegistrationVerificationService::finalize()` treats the identical
case as an identity conflict and refuses to merge. `resolveFrom`
does not.

Consequence: walk-in and bulk-walk-in intake can attach a phone
belonging to another participant's identity with no warning. The
stored row has A's email and B's phone. B's registration history
does not include this row.

### Root cause

The original `resolveFrom` was email-first by design; the phone
fallback was a secondary path. The composite rewrite preserved that
ordering. The cross-check that `finalize()` performs was never
mirrored.

### Fix outline

Two options, deliberate choice required:

- **Enforce:** before matching, look up both the email and the phone
  independently. If both exist and refer to different participants,
  throw `IdentityRequiredException` (or a new
  `CrossContactConflictException`). `resolveFrom` then behaves like
  `finalize()`. Walk-in and bulk path surfaces the conflict to the
  operator.
- **Document as intentional:** keep the current behavior, add a
  docblock to `resolveFrom` explaining that walk-in/bulk can attach
  a cross-identity phone, and add an informational row in the
  monitor when this occurs (analogous to `has_shared_phone`).

Enforcing is more correct but changes call-site behavior for existing
walk-in workflows. Decision needed before implementing.

### Verification when fixed

If enforcing: new test in `tests/Feature/Participants/ParticipantIdentityResolverTest.php`
covering the cross-contact conflict case for both email-first and
phone-first matches.

If documenting: no test change, only docblock + monitor flag test.

---

## ISSUE-021 — Event deletion dialog under-reports what will be destroyed

**Severity:** Low
**Status:** Open
**Found in:** `app/Models/Event.php` (`relatedRecordCounts`),
`app/Services/EventDeletion/RegistrationDeletionService.php` (comment)
**Related:** `docs/audit-2026-10-19.md` §10 (L2),
`docs/event-deletion.md` §5

### Symptoms

The admin event-deletion confirmation dialog lists counts of
event-options (removed with the schema — gone), registrations, and
attendances. It does not list `registration_verification_challenges`,
which are cascade-deleted when the event is deleted. An organizer
sees "0 registrations, 0 attendances" and confirms — but pending
verification challenges are silently destroyed.

`RegistrationDeletionService` (the single-registration delete path)
contains a comment "survives the FK becoming null." The
`attendance.registration_id` FK is `CASCADE`, not
`SET NULL` — the row is deleted, not nulled. The comment is wrong.

### Root cause

`relatedRecordCounts()` predates the verification-challenge table.
The comment predates the FK schema decision that landed with the
attendance redesign.

### Fix outline

Add `registration_verification_challenges` to
`Event::relatedRecordCounts()` with the same cascade-count shape as
the other entries. Update the deletion dialog template to render the
new count.

Fix the `RegistrationDeletionService` comment: "attendance row is
deleted via CASCADE on registration_id (see migration
`2026_09_16_160345`)."

### Verification when fixed

- A pending challenge on a to-be-deleted event appears in the count
  shown in the confirmation dialog.
- The `RegistrationDeletionService` comment reads correctly.

---

## ISSUE-022 — Recurring service create is non-transactional and unindexed

**Severity:** Low
**Status:** Open
**Found in:** `app/Http/Controllers/ServiceController.php`
(`storeRecurring`)
**Related:** `docs/audit-2026-10-19.md` §10 (L3),
`docs/services.md` (does not exist yet — Q1 unblocked its writing)

### Symptoms

Two concurrent recurring-batch requests on the same service type
can produce duplicate occurrences: both read `exists()` as false,
both proceed to insert. No unique index enforces the invariant.

A mid-loop failure (e.g., one date violates a constraint, or the
request is cancelled) leaves a partial batch — some occurrences
created, others missing, no rollback.

### Root cause

Read-then-write dedupe with no unique index and no surrounding
transaction.

### Fix outline

Two changes, both required:

- **Migration:** add `UNIQUE(service_type, occurrence_date)` to
  `service_occurrences`.
- **Controller:** wrap the loop in `DB::transaction()`. On
  `UniqueConstraintViolationException`, catch and skip (the pattern
  the loop already uses via `exists()`); the transaction still
  commits the successfully-inserted rows.

If the loop is long enough that transaction size is a concern,
switch to insert-or-skip semantics via `upsert` — but at
`max:52` occurrences the single transaction is fine.

### Verification when fixed

New test: two concurrent recurring-batch requests produce exactly
N occurrences, not 2N. Failure mid-loop produces 0 rows, not a
partial batch.

---

## ISSUE-023 — `editParticipant` mutates shared cross-event identity

**Severity:** Low
**Status:** Open
**Found in:** `app/Http/Controllers/RegistrationMonitorController.php`
(`editParticipant`)
**Related:** `docs/audit-2026-10-19.md` §10 (L4),
`docs/composite-identity.md` §7.4

### Symptoms

Any staff user on an open or closed event can edit the shared
`Participant` row (email, phone, name) via the monitor's edit
dialog. Because the participant row is shared across all events and
church modules for that person, the edit affects every event they
have ever registered for.

Documented as deliberate first-write-wins override, but it is not
guarded by any ownership check — any staff user can do it for any
event they have access to, which in the current model is every
event.

### Root cause

No policy layer; first-write-wins semantics applied broadly.

### Fix outline

Two options:

- **Restrict:** make `editParticipant` admin-only. Aligns with
  ISSUE-015's general church-controller auth gap; once a policy
  layer exists, this action is a natural customer.
- **Scope:** apply the edit to a per-event snapshot rather than the
  shared participant row. This is a larger schema change (registration
  has `submitted_email` and `submitted_contact_number` already —
  add `submitted_first_name`, `submitted_last_name`) and shifts
  `resolveFrom` to prefer submitted snapshots over participant row.

Option `Restrict` closes the immediate risk; `Scope` is the correct
long-term fix but is out of scope for the audit-patch series.

### Verification when fixed

Under `Restrict`: non-admin edit returns 403. Under `Scope`:
editing a participant on Event A does not change their display on
Event B.

---

## ISSUE-024 — Design-system informational violations

**Severity:** Low (cosmetic)
**Status:** Open
**Found in:** Multiple — see list in
`docs/design-system.md` §11
**Related:** `docs/audit-2026-10-19.md` §10 (L5)

### Symptoms

`scripts/audit-design.sh` passes all hard rules. Rule 5 (informational)
flags raw white utilities in 9 files. Spot-checks found:

- `registrations/monitor/index.tsx:68` and `show.tsx:378` —
  `bg-white/5` empty-state circles (should be `bg-secondary`)
- `attendance-filter-chips.tsx:88` and
  `events/attendance.tsx:558-569` — `border-white/10` chips
  (should be `border-border`)

These are design-system §11.7 and §11.1 violations respectively.
They render acceptably but drift from the intended token set.

### Root cause

Pre-§11 classes that were not swept when the design system was
codified on 2026-10-16.

### Fix outline

Sweep the 9 files flagged by Rule 5. Replace raw white utilities
with the corresponding semantic token per design-system §11. Run
`./scripts/audit-design.sh` after each file to confirm the informational
count drops.

Not urgent. Cosmetic. A design polish session, not a code fix.

### Verification when fixed

`./scripts/audit-design.sh` reports zero Rule-5 hits.

---

## ISSUE-025 — Discipleship progress rewrite (client Q14) not implemented

**Severity:** Medium
**Status:** Open
**Found in:** `app/Services/Discipleship/DiscipleshipProgressService.php`,
`app/Enums/DiscipleshipAttendanceStatus.php`
**Related:** `docs/discipleship.md` §6, client Q14 (2026-10-16),
`docs/audit-2026-10-19.md` §11 (D4)

### Symptoms

Client Q14 (2026-10-16) requested three behavioral changes to
Discipleship progress calculation:

1. Drop the "excused" attendance status; use Present and Absent only.
2. Expand the applicable-session window to all sessions in the batch,
   not bounded by enrollment date.
3. Count only "present" toward progress.

None are implemented. The shipped code still counts Present + Excused
as attended, and bounds applicable sessions to
`enrolled_at..today`.

### Root cause

Q14 was answered after the Discipleship Phase 5F/5G work shipped.
The enum still has three values, the service still uses the old
policy.

### Fix outline

Three coordinated changes plus a data migration:

1. **Enum:** remove `excused` from `DiscipleshipAttendanceStatus`.
2. **Service:** change `progress()` — attended = present only;
   applicable = every session in the batch.
3. **Data migration:** existing attendance rows with `excused` status
   need a decision. Options:
   - Convert to `absent` (preserves history as absence; recommended)
   - Delete the rows
   - Leave orphaned (breaks the enum constraint)
   Follow-up question already on the client: confirm the conversion
   choice and confirm mid-batch joiners show partial progress under
   the new rule.

### Verification when fixed

New test in `tests/Feature/Discipleship/DiscipleshipProgressTest.php`:
- Excused status no longer accepted (validation error)
- Applicable includes pre-enrollment sessions
- Attended counts Present only, ignores Absent
- Migration converts existing `excused` rows correctly

---

## Change log addendum

- **2026-10-19** — ISSUE-014 through ISSUE-025 added under "Later
  additions." Eleven from the audit (`docs/audit-2026-10-19.md`);
  one from client Q14. M5 and P5 excluded — both closed by
  shipped W-phase work (W5.1 and W2 respectively).


## Running Events audit addendum — 2026-10-21

Status correction: ISSUE-014 running/church lifecycle isolation, ISSUE-016 verified_at preservation, and ISSUE-017 dateless deferred attendance are fixed with regression coverage. This does not close unrelated church authorization, other OTP services, concurrency, or design-system findings. Live CAPTCHA and physical camera/scanner validation remain acceptance checks. The earlier reCAPTCHA-key blocker is resolved locally.

Current reference: [Running Events audit and contract](running-events-audit-2026-10-21.md).
This dated addendum supersedes conflicting Running Events statements above;
historical entries are retained under the append-only documentation policy.

---

## ISSUE-026 — PHPStan level 7 / Pint baseline debt

**Severity:** Low

**Status:** Open (baseline captured 2026-10-21)

**Problem:** PHPStan level 7 has never been green on `main` — 469 errors at
`5755c75`, +69 from the HOLD/pool work = 538 total. Pint (`laravel` preset)
has 68 style issues, all pre-existing files. None of the current work's files
fail Pint; the +69 PHPStan errors are annotation debt (missing generics,
missing `@property` docblocks, iterable value types).

**Impact:** CI cannot gate on PHPStan or Pint. Every PR adds debt without a
signal. Baseline was generated on 2026-10-21 to unblock the HOLD/pool PR; the
baseline must shrink, not grow.

**Action taken:** `phpstan-baseline.neon` generated and wired into
`phpstan.neon`. New code cannot add errors without updating the baseline.
Pint debt deferred to a separate chore PR.

**Follow-up priorities for the cleanup PR:**
1. `missingType.generics` on Model relations (~60 errors) — mechanical, high count.
2. `Access to undefined property ... Model::$xxx` (~150 errors) — add `@property` docblocks.
3. `Cannot call method toDateString() on string` (~40 errors) — date casts need typed PHPDoc.
4. Then run `./vendor/bin/pint` (safe auto-fix, ~68 files).

**Rule:** Do not run `pint` or un-baseline PHPStan as part of a feature PR —
the diff would bury the feature work.

---

## ISSUE-027 — Repair migration leaves `duplicate_of_registration_id` NULL in the interrupted-implementation branch

**Severity:** Low

**Status:** Open (logged 2026-10-21)

**Problem:** `2026_10_22_100200_repair_participant_pools_and_preserve_review_audits`
rewrites any `resolution_action = 'confirm_duplicate'` + `registration_status = 'cancelled'`
row to `registration_status = 'duplicate'` with
`duplicate_of_registration_id = held_candidate_registration_id`.

If `held_candidate_registration_id` was NULL at the time of the interrupted write,
the repaired row has status `duplicate` but no master pointer.

**Impact:** Zero rows on live. Live DB had 4 registrations, no shared history —
audit found no affected data. But other environments migrating from a mid-flight
interrupted state could produce orphan duplicate-status rows.

**Action taken:** None.

**Follow-up:** A NULL-guard query — count rows where
`registration_status = 'duplicate' AND duplicate_of_registration_id IS NULL` —
should be added to the next migration's pre-check.

---

## ISSUE-028 — Master §9.1 workspace-path references are stale after `docs/specs/` promotion

**Severity:** Low

**Status:** Open (logged 2026-10-21)

**Problem:** Master §9.1 references workspace paths like
`user/files/Church_Events_Update.md`, `wdems-composite-rule/RUNNING_EVENTS_UPDATE.md`.
Those files now live at `docs/specs/` in the repo. Master §9.1 was written
assuming the workspace layout; the promotion to `docs/specs/` (AI-CONTEXT §24,
2026-10-21) makes those paths wrong.

**Impact:** An AI session reading the Master doc will look for files in the
wrong location.

**Action taken:** None. Master doc is append-only. AI-CONTEXT §24 notes the
staleness for future readers.

**Follow-up:** Append a dated correction note to Master §9.1 in the next
docs-sync PR; do not silently rewrite §9.1.
