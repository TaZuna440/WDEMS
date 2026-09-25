# WDEMS Event Creation — Developer Guide

**Audience:** Junior developers who use AI coding tools (Claude, Copilot,
Cursor) but do not yet know this codebase by heart.

**Purpose:** Tell you how events are created and edited, where every piece
lives, and — most importantly — which parts AI will try to "fix" and get
wrong.

**Read this before you ask AI to change anything under `EventController`,
the wizard components, or `EventValidationRules`.**

---

## 1. The 60-second overview

Creating an event in WDEMS is a **4-step wizard** that ends with a
`draft` event record. That record can then be edited, given options,
and moved through a workflow of statuses (see §7).

There are two entry points:

| Action | Route | What happens |
|---|---|---|
| Create | `GET /events/create` → `POST /events` | New `draft` event |
| Edit | `GET /events/{id}/edit` → `PUT /events/{id}` | Update an existing event |

Both use the **same four step components** — `BasicsStep`, `ScheduleStep`,
`VenueStep`, `ExtrasStep` — via a shared `Wizard` shell.

**Three things are deliberately NOT in the wizard:**

1. **Event Options** (shirt size, distance choice, etc.) — separate flow,
   see §7.
2. **FAQ entries** — seeded server-side with six defaults on create, never
   editable from the UI.
3. **Registration setup** — removed from the project. Old docs may still
   describe it; it is not in the code.

---

## 2. Where things live

### Backend

| File | What it does |
|---|---|
| `app/Http/Controllers/EventController.php` | All eight event actions: index, show, create, store, edit, update, configure, open/close registration |
| `app/Http/Requests/EventRequest.php` | FormRequest that pulls rules from the trait below |
| `app/Concerns/EventValidationRules.php` | The single source of truth for what makes an event valid |
| `app/Enums/EventType.php` | `community_run` \| `fun_run`, with `label()` |
| `app/Enums/EventStatus.php` | Seven states + `allowedTransitions()` + `canTransitionTo()` |
| `app/Models/Event.php` | Fillable list, casts, `distanceLabel()`, `canEdit()`, `canConfigure()`, etc. |
| `app/Services/EventWorkflow.php` | The status transition service |

### Backend — adjacent flows (not part of the wizard)

| File | What it does |
|---|---|
| `app/Http/Controllers/EventOptionController.php` | CRUD for event options (separate route prefix) |
| `app/Services/EventDeletion/EventDeletionService.php` | Deletes an event and its related records |

### Frontend — the wizard shell

| File | What it does |
|---|---|
| `resources/js/components/wizard.tsx` | Step state, validation, error jumping, submit |
| `resources/js/components/wizard-progress.tsx` | The progress bar with clickable visited steps |
| `resources/js/hooks/use-wizard-persistence.ts` | localStorage save/restore |

### Frontend — pages

| File | What it does |
|---|---|
| `resources/js/pages/events/create.tsx` | The create wizard page |
| `resources/js/pages/events/edit.tsx` | The edit wizard page |
| `resources/js/pages/events/index.tsx` | Event list |
| `resources/js/pages/events/show.tsx` | Single event view + workflow actions |
| `resources/js/pages/events/options.tsx` | Event options CRUD page |

### Frontend — step components

| File | Fields it owns |
|---|---|
| `resources/js/pages/events/step/BasicsStep.tsx` | `event_type`, `event_name`, `description` |
| `resources/js/pages/events/step/ScheduleStep.tsx` | `event_date`, `start_time`, `end_time`, `distance_value`, `distance_unit`, `course_url` |
| `resources/js/pages/events/step/VenueStep.tsx` | `venue`, `venue_address`, `venue_latitude`, `venue_longitude` |
| `resources/js/pages/events/step/ExtrasStep.tsx` | `rsvp_required`, `partners`, 9 accessibility flags |

### Frontend — field-level components

| File | What it does |
|---|---|
| `resources/js/components/date-picker.tsx` | Custom calendar popover, "Today" shortcut, clear button |
| `resources/js/components/time-picker.tsx` | Hour/min/AM-PM selects. **Minutes snap to 5-minute intervals** |
| `resources/js/components/distance-picker.tsx` | Whole + decimal comboboxes, KM/Miles toggle with live conversion |
| `resources/js/components/map-picker.tsx` | Dialog wrapper; lazy-loads the Leaflet map |
| `resources/js/components/map-picker-leaflet.tsx` | Actual map + Nominatim reverse geocoding |
| `resources/js/components/partner-editor.tsx` | Add/remove partner rows (name + type) |
| `resources/js/components/accessibility-grid.tsx` | 9 checkboxes in 4 groups |

### Database

| File | What it does |
|---|---|
| `database/migrations/2026_09_16_160221_create_events_table.php` | Base events table |
| `database/migrations/2026_09_16_160308_create_event_options_table.php` | Event options |
| `database/migrations/2026_09_17_181907_add_event_type_to_events_table.php` | Adds `event_type` column |
| `database/migrations/2026_09_21_093134_add_event_metadata_to_events_table.php` | Adds venue, partners, FAQ, accessibility, etc. |
| `database/migrations/2026_09_21_110517_replace_distance_label_with_value_and_unit_on_events.php` | Drops `distance_label`, adds `distance_value` + `distance_unit` |

---

## 3. The wizard flow

```
┌──────────────────────────────────────────────────────────┐
│ GET /events/create                                       │
│ EventController::create()                                │
│ → Renders events/create.tsx                              │
│ → Props: event_types (from EventType::cases())           │
└──────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────┐
│ Wizard component mounts                                  │
│ Reads localStorage key "wdems-wizard-create"             │
│ → If a snapshot exists (<24h), restore and prefill       │
│ → Otherwise start at step 0                              │
└──────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────┐
│ Step 1 — Basics                                          │
│ User enters event_type, event_name, description          │
│ "Next" → validates required fields client-side           │
│ → Advances to Step 2                                     │
│ → Saves snapshot to localStorage                         │
└──────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────┐
│ Step 2 — Schedule                                        │
│ User picks date, start/end times, distance, course URL   │
│ "Next" → Step 3                                          │
└──────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────┐
│ Step 3 — Venue                                           │
│ User enters venue name, picks location on map            │
│ → Coordinates + address auto-fill                        │
│ "Next" → Step 4                                          │
└──────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────┐
│ Step 4 — Extras                                          │
│ User sets RSVP flag, adds partners, toggles accessibility│
│ "Create Event" → POST /events                            │
└──────────────────────────────────────────────────────────┘
                          ↓
┌──────────────────────────────────────────────────────────┐
│ EventController::store()                                 │
│ → Validates via EventRequest (server-side)               │
│ → Creates event with status = draft                      │
│ → Seeds FAQ via defaultFaq()                             │
│ → Clears the localStorage snapshot                       │
│ → Redirects to /events                                   │
└──────────────────────────────────────────────────────────┘
```

**If the server rejects**, the wizard reads the error keys, finds the
first step whose `fields` array contains a failing key, and jumps there.
See §5 for what the rules are.

### Edit flow

Identical, except:

- `GET /events/{id}/edit` prefills the form from the event.
- `EventController::edit()` first calls `$event->canEdit()`. If false,
  returns 403.
- Storage key is `wdems-wizard-edit-{id}` — one draft per event.
- Submit is `PUT /events/{id}`.
- **`event_type` is not in the update payload.** It is set on creation
  and can never be changed. See Trap #2.

---

## 4. The four steps

### Step 1 — Basics

| Field | Required? | Notes |
|---|---|---|
| `event_type` | Yes | Dropdown: Community Run / Fun Run. **Locked in edit mode.** |
| `event_name` | Yes | 3–100 chars. Custom rules — see §5. |
| `description` | No | Max 2000 chars. Textarea. |

### Step 2 — Schedule

| Field | Required? | Notes |
|---|---|---|
| `event_date` | Yes | Custom date picker. |
| `start_time` | Yes | Custom time picker. 24-hour storage. |
| `end_time` | No | Same picker. Optional. |
| `distance_value` | Yes | 0.01 – 9999.99. Combo of whole + decimal. |
| `distance_unit` | Yes | `km` or `mi`. Toggle. Auto-converts when switched. |
| `course_url` | No | Valid URL. Max 500 chars. |

### Step 3 — Venue

| Field | Required? | Notes |
|---|---|---|
| `venue` | Yes | Short name. Max 255. |
| `venue_address` | Yes | Full address. Max 500. |
| `venue_latitude` | Yes | -90 to 90. Set via map picker. |
| `venue_longitude` | Yes | -180 to 180. Set via map picker. |

The map picker:

- Lazily loads Leaflet only when the dialog opens (saves ~154 KB on first paint).
- Uses OpenStreetMap tiles — no API key.
- Uses Nominatim for reverse geocoding. **Nominatim has a 1 req/sec rate limit** and the picker fails silently if it is exceeded. The user can always type the address manually.
- Defaults to Manila (14.5995, 120.9842) if no location is set yet.

### Step 4 — Extras

| Field | Required? | Notes |
|---|---|---|
| `rsvp_required` | No | Boolean checkbox. |
| `partners` | No | Array of `{name, type}`. See below. |
| 9 accessibility flags | No | Booleans, all default false. |

Partner types (from `EventValidationRules::partnerTypes()`):

`host`, `sponsor`, `food`, `beverage`, `medical`, `organization`,
`media`, `logistics`, `other`.

Accessibility flags, grouped in the UI:

| Group | Flags |
|---|---|
| Pace & Ability | `walkers_welcome`, `all_paces_welcome`, `all_ages_welcome`, `sweeper_present` |
| Physical Access | `stroller_friendly`, `wheelchair_accessible` |
| Companion & Support | `service_animals_allowed`, `leashed_pets_allowed` |
| Sensory | `quiet_space_available` |

---

## 5. Validation contract

All rules live in `app/Concerns/EventValidationRules.php`. This is the
**single source of truth**. The wizard validates only field emptiness
client-side; everything else is server-side.

| Field | Rule |
|---|---|
| `event_type` | `required`, enum `EventType` |
| `event_name` | `required`, 3–100 chars, plus custom rules (below) |
| `description` | `nullable`, string, max 2000 |
| `event_date` | `required`, valid date |
| `start_time` | `required`, format `H:i` |
| `end_time` | `nullable`, format `H:i` |
| `distance_value` | `required`, numeric, 0.01 – 9999.99 |
| `distance_unit` | `required`, in `km`,`mi` |
| `course_url` | `nullable`, URL, max 500 |
| `venue` | `required`, string, max 255 |
| `venue_address` | `required`, string, max 500 |
| `venue_latitude` | `required`, numeric, -90 to 90 |
| `venue_longitude` | `required`, numeric, -180 to 180 |
| `rsvp_required` | boolean |
| `partners` | nullable array |
| `partners.*.name` | required when partners present, string, max 255 |
| `partners.*.type` | required when partners present, in the 9-value list |
| 9 accessibility flags | boolean |

### The `event_name` rules (custom — do not simplify)

Five regexes layered on top of `required`, `min:3`, `max:100`:

| Rule | Rejects |
|---|---|
| `^[A-Za-z0-9]` | Names starting with punctuation or space |
| `[aeiouyAEIOUY]` | Names with no vowel — "dfdsfdsfd" |
| `[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]` | Names with no consonant — "aeiou" |
| `not_regex:/(.)\1\1/` | 3+ identical characters in a row — "aaaa" |

These exist to catch keyboard mashing before it becomes a real event.
They are deliberately inclusive (Y counts as a vowel, letter case is
ignored). See Trap #6.

---

## 6. The draft checkpoint (localStorage)

The wizard saves a snapshot on every step change and every keystroke, so
a user who refreshes the page does not lose their work.

**Storage keys:**

- Create flow: `wdems-wizard-create`
- Edit flow: `wdems-wizard-edit-{eventId}`

**Snapshot shape** (from `use-wizard-persistence.ts`):

```json
{
  "version": 1,
  "currentStep": 2,
  "data": { ...form fields... },
  "savedAt": "2026-09-25T14:02:11.000Z"
}
```

**Rules:**

- TTL: 24 hours. Older snapshots are ignored.
- Version guard: bumping `STORAGE_VERSION` invalidates every existing draft.
- The stored `currentStep` is actually the *furthest* step reached
  (`maxStep`), not the currently viewed step. This is deliberate — see
  Trap #5.
- The snapshot is **cleared** when the user successfully submits. It is
  not cleared on error.
- If `localStorage` is unavailable or quota is exceeded, the hook fails
  silently. The wizard still works, it just will not persist.

---

## 7. Adjacent flows (not part of the wizard)

These are separate routes and pages. They are related because they take
an event record and change it — but they are not the Event Creation flow.

### 7.1 Event Options

**Route prefix:** `/events/{event}/options`
**Controller:** `EventOptionController`
**Page:** `events/options.tsx`

Each option has:

| Field | Notes |
|---|---|
| `option_type` | Free-form string, e.g. `distance`, `shirt`, `add-on` |
| `option_name` | e.g. `5 KM`, `Large`, `Red` |
| `option_value` | Optional metadata |
| `is_required` | Boolean |
| `is_available` | Boolean, default true |

Options can only be added, edited, or removed while
`$event->canConfigure()` is true — i.e. while the event is in `draft`
or `configured` state. After `openRegistration()`, the options page
locks to read-only.

The `Mark as Configured` button appears on this page when the event is
in `draft`. Clicking it transitions the event to `configured` status.

**Options are not hardcoded.** Shirt, cap, medal, socks — none of these
are baked in. The organizer defines whatever options the event needs.

### 7.2 Event workflow

**Service:** `app/Services/EventWorkflow.php`
**Status enum:** `app/Enums/EventStatus.php`

The state machine:

```
draft → configured → registration_open → registration_closed → ongoing → completed

cancelled reachable from any non-terminal state
```

Transitions are enforced by `EventStatus::canTransitionTo()`. Attempting
an illegal transition throws `InvalidArgumentException` from
`EventWorkflow::transition()`.

**Where each transition is triggered:**

| Transition | Triggered by |
|---|---|
| `draft → configured` | `POST /events/{id}/configure` (from options page) |
| `configured → registration_open` | `POST /events/{id}/open-registration` (from event show page) |
| `registration_open → registration_closed` | `POST /events/{id}/close-registration` |
| `registration_closed → ongoing` | Not yet wired to any route |
| `ongoing → completed` | Not yet wired to any route |
| `* → cancelled` | Not yet wired to any route |

**Guards on the Event model:**

- `canEdit()` — `draft`, `configured`, `registration_open`, `registration_closed`
- `canConfigure()` — `draft`, `configured`
- `canOpenRegistration()` — only `configured`
- `canCloseRegistration()` — only `registration_open`
- `canRecordAttendance()` — `registration_open`, `registration_closed`, `ongoing`

---

## 8. Common tasks

### Add a new field to the wizard

1. **Schema.** New migration with `php artisan make:migration`. Never edit the base migrations.
2. **Model.** Add to `Event::$fillable` and to `casts()` if needed.
3. **Validation.** Add the rule to `EventValidationRules::eventRules()`.
4. **Controller.** Add the field to the `create`/`edit` payload, and to `store`/`update`'s explicit assignment list.
5. **Wizard step config.** Add the field name to the correct step's `fields` array in `create.tsx` and `edit.tsx`. If it is required, add to `requiredFields` too.
6. **Component.** Add the input to the matching `step/*.tsx` file.
7. **Type.** Add to the `FormData` type in that step file.
8. **Test.** Add coverage in `tests/Feature/Events/`.

**Do not** add the field to any `#[Fillable]` on the model if it holds security-sensitive data — use explicit assignment in the controller.

### Change the distance conversion factor

`resources/js/components/distance-picker.tsx`, the `KM_TO_MI` constant.
Keep it at 6 decimals of precision, matching the current
`0.621371`. The server stores the value it receives — it does not
re-derive the conversion.

### Add a new step to the wizard

1. Create `resources/js/pages/events/step/YourStep.tsx`.
2. Add an entry to the `STEPS` array in **both** `create.tsx` and `edit.tsx`.
3. Add a `case` to the `switch` inside the wizard `children` render function.
4. Add the field names to the new step's `fields` and `requiredFields`.
5. The `Wizard` component handles navigation — no other changes needed.

**Do not** modify `wizard.tsx` unless you are changing behavior shared by all steps.

### Add a new partner type

Two places, must be kept in sync:

1. `app/Concerns/EventValidationRules::partnerTypes()`
2. `resources/js/components/partner-editor.tsx` — `PARTNER_TYPES` array

If you add it to one and not the other, the frontend will offer a type the backend rejects (or the backend will allow a type the frontend cannot produce).

### Change the FAQ defaults

`EventController::defaultFaq()`. This is the only place FAQ is written.

**Note:** Existing events are not affected. Only newly created events
get the new FAQ.

---

## 9. Traps — things AI will try and get wrong

### Trap #1 — Adding FAQ inputs to the wizard

**What AI will try:** "Let the organizer edit the FAQ in the wizard."

**Why it's wrong:** There is no FAQ field in any of the four steps, and no
FAQ field in `EventController::update()`. FAQ is seeded once via
`defaultFaq()` on creation and then never touched by any UI. Adding a
wizard field means: new step component, new validation rule, new column
update in `store()` AND `update()`, new wizard step config in both
create.tsx and edit.tsx. It is not a small change.

**What to do instead:** If you are asked to "let users edit the FAQ,"
propose adding a dedicated FAQ section to `events/show.tsx` first — it
already renders FAQ entries. That is the natural place to edit them,
not the create wizard.

### Trap #2 — Making `event_type` editable

**What AI will try:** "Enable the Event Type dropdown in edit mode."

**Why it's wrong:** `EventController::update()` **does not include
`event_type` in the update array.** The dropdown is disabled in edit
mode by design (`isEdit` prop on `BasicsStep`). Even if you enable it
on the frontend, the backend will silently ignore the change.

**What to do instead:** If event type really needs to change, that is a
product decision. Add the field to the update array and drop the
`isEdit` lock on the dropdown — but understand that changing an event
from Community Run to Fun Run mid-flight has downstream implications
for the eventual participant registration model.

### Trap #3 — The dead `isEdit` ternary in `BasicsStep`

**What AI will try:** "Simplify the ternary — both branches say the same thing."

**Why it's right, but be careful:** The current code is:

```tsx
{isEdit
    ? 'Event type cannot be changed after creation.'
    : 'Event type cannot be changed after creation.'}
```

Both branches are identical — leftover from an earlier version. It is
harmless dead code. If you simplify to a single string, you are right.
But **check whether the `isEdit` prop is used elsewhere** in the file
before removing it. Right now, it is used for the disabled state on the
`Select` and this message. Removing the prop entirely would require
removing both uses.

**What to do instead:** Simplifying the ternary is fine. Removing the
`isEdit` prop is a separate, larger change.

### Trap #4 — Changing the time picker

**What AI will try:** "Let the user pick any minute value, not just multiples of 5."

**Why it's wrong:** The 5-minute snapping is intentional. It is enforced
by `const MINUTES = Array.from({ length: 12 }, (_, i) => i * 5);` and by
`Math.round(m / 5) * 5` in the parser. Users cannot enter 12:03 — it
becomes 12:05.

**What to do instead:** Ask first. If arbitrary minutes are needed,
change both the constant AND the parser. Do not change just one.

### Trap #5 — The misleading `currentStep` field name in the snapshot

**What AI will try:** "Fix the bug where the wizard opens at the wrong step after refresh."

**Why it might not be a bug:** `use-wizard-persistence.ts` names the
persisted field `currentStep`, but `wizard.tsx` actually saves `maxStep`
into it:

```tsx
save(maxStep, data);
```

The `save` function signature is `save(currentStep, data)`. So the field
is named `currentStep` but semantically contains the furthest step
reached. On refresh, the wizard opens at that furthest step. This is
deliberate — see the comment in `wizard.tsx`. Going back does not
reduce the checkpoint.

**What to do instead:** If you think this is broken, first ask whether
the observed behavior matches the intent. The naming is confusing but
the behavior is what was designed. If it truly is a bug, the fix is to
rename the field, not change the value.

### Trap #6 — Touching the `event_name` regex rules

**What AI will try:** "These regex rules are overly restrictive — replace them with a simpler `min:3` rule."

**Why it's wrong:** The five rules exist for a specific reason — they
catch keyboard mashing without over-restricting legitimate names. Any
fix must preserve all five checks:

1. Must start with letter or digit.
2. Must contain at least one vowel.
3. Must contain at least one consonant.
4. No 3+ identical chars in a row.
5. Length 3–100.

**What to do instead:** If a legitimate event name is being rejected,
find out which specific rule is firing and fix only that one. Do not
replace the whole block.

**Signal that AI is about to make this mistake:** It will describe the
regex block as "redundant" or "too clever." It is neither. It is
intentional.

### Trap #7 — Adding Options to the wizard

**What AI will try:** "The organizer should define event options (shirt sizes, distances) during creation."

**Why it's wrong:** Options are a separate flow. See §7.1. They have
their own controller, page, route prefix, and gating. The wizard
deliberately ends with a bare `draft` event that has no options yet.

**What to do instead:** Add options to the options page flow, not the
create wizard.

### Trap #8 — Touching the base migrations

**What AI will try:** "Add a column to the events table" → AI edits
`2026_09_16_160221_create_events_table.php`.

**Why it's wrong:** Same trap as `docs/authentication.md` Trap #5. The
base migration has already run. Editing it corrupts fresh installs and
does nothing on existing ones.

**What to do instead:** `php artisan make:migration add_your_column_to_events_table`.

### Trap #9 — Using `distance_label`

**What AI will try:** "Fetch the label directly from the `distance_label` column."

**Why it's wrong:** `distance_label` was **dropped** by migration
`2026_09_21_110517_replace_distance_label_with_value_and_unit_on_events.php`.
It is replaced by `distance_value` + `distance_unit`. A computed label
is available via `Event::distanceLabel()`, which the controller calls
when building the Inertia payload.

**What to do instead:** Use `$event->distanceLabel()`. Do not add a
`distance_label` column back.

### Trap #10 — Assuming `edit.tsx` gets all event types

**What AI will try:** "Add the full event types list to the edit page's dropdown."

**Why it's not obvious from the file:** In `edit.tsx`, `BasicsStep`
receives `eventTypes={[{ value: event.event_type, label: event.event_type_label }]}`
— a **single-element array** containing only the event's current type.
This is deliberate: it makes the dropdown show the current value but
offers no alternatives.

**What to do instead:** If you need the dropdown to show all types in
edit mode (for a "change type" feature), fetch them from the controller
side (`EventController::edit`) the same way `create()` does. That is a
product change, not a bug fix.

### Trap #11 — EventRequest authorization

**What AI will try:** "Add `authorize()` logic to `EventRequest`."

**Why this is a real issue (not a trap, but out of scope):** The request
class currently returns `true` from `authorize()`. There is no policy
preventing staff from editing events they did not create. This is a
known gap flagged elsewhere; it is not specific to event creation.

**What to do instead:** Do not fix this inside `EventRequest` in
isolation. It requires a decision about who owns an event — currently
`created_by` exists on the record but no authorization rule uses it.
Raise it separately.

---

## 10. Glossary

| Term | Plain meaning |
|---|---|
| **Wizard** | The 4-step create/edit flow. Not a Laravel Wizard — the component is `resources/js/components/wizard.tsx`. |
| **Step** | One page of the wizard. There are four. |
| **Checkpoint / maxStep** | The furthest step the user has reached. Saved in localStorage. |
| **Draft** | The initial status of a new event. |
| **Options** | Configurable choices the organizer defines for an event (shirt size, distance, etc.). Separate flow. |
| **FAQ** | Six hardcoded Q&A entries seeded on create. Not editable in any UI. |
| **`canEdit()`** | Event method. True while status is draft, configured, registration_open, or registration_closed. |
| **`canConfigure()`** | Event method. True only in draft and configured. |
| **Workflow** | The status transition service (`EventWorkflow`). |
| **Wayfinder** | Same as in `authentication.md` — generates `resources/js/routes/**` from PHP routes. Do not edit those files. |
| **Inertia** | Same as in `authentication.md` — bridge between Laravel and React. |
| **Leaflet** | The open-source map library. Lazy-loaded only when the map picker opens. |
| **Nominatim** | OpenStreetMap's free reverse-geocoding service. 1 request/sec. |

---

## 11. What the outdated documentation got wrong

The scope document titled "Event Creation — Current/Documented Scope"
contains several claims that the code contradicts. Corrections:

| Scope doc says | Code says |
|---|---|
| Partners have "contact information, role/responsibility" | Only `{name, type}` — see `PartnerEditor` and `EventValidationRules::partnerTypes()` |
| FAQ is part of event creation UI | FAQ is seeded server-side by `EventController::defaultFaq()`. No UI. |
| Event Options is part of event creation | Separate controller, page, and route prefix. |
| Registration Setup (Google Forms) is part of event creation | Removed from the project. See `docs/archive/`. |
| "Community Run + Fun Run workflows" | Only Community Run is implemented. Fun Run exists as a data value, no separate workflow. |
| `distance_label` was "outdated" | Correct — it was dropped by migration. The current columns are `distance_value` and `distance_unit`. |

Use `docs/event-creation.md` (this file) as the source of truth. Where
the older scope doc disagrees, this file wins.

---

## 12. Open items (known but not fixed)

1. **No authorization on event edit.** `EventRequest::authorize()` returns `true`. See Trap #11.
2. **The dead `isEdit` ternary in `BasicsStep`.** See Trap #3. Harmless.
3. **`docs/event-creation-wizard.md` is a historical record** and may not match the current code. Treat this file as the current reference.
4. **`registration_closed → ongoing` and `ongoing → completed` transitions are not wired to any route.** The state machine supports them; no UI triggers them yet.
5. **`* → cancelled` is not wired to any route either.**
6. **`attendances` page is present but I have not verified it end to end** — outside the scope of this doc.

---

## 13. When in doubt

1. **Read `EventValidationRules` before touching any form field.** That trait is the contract.
2. **Read `EventStatus` before adding workflow buttons.** That enum tells you which transitions are legal.
3. **Do not add fields to the wizard without adding them to the step's `fields` array.** Otherwise server errors will land on the wrong step.
4. **Do not trust AI on:** `EventValidationRules` (especially `event_name` regexes), `distance-picker.tsx` (conversion math), `time-picker.tsx` (5-minute snapping), or anything in `resources/js/routes/**`.

---

## Corrections

This section supersedes specific items in the body above. Where an item
below contradicts an earlier section, this section wins.

### §12 item 3 — `event-creation-wizard.md` is a live doc, not historical

The original text of §12 item 3 described `docs/event-creation-wizard.md`
as a "historical record" and said to treat this file
(`event-creation.md`) as the current reference.

That is wrong.

`docs/event-creation-wizard.md` is the **live feature doc** for the
event-creation wizard. As of September 25, 2026, it was rewritten to
full length — covering the schema extension, the model, the wizard
shell, checkpoint persistence, the four steps, the field components,
validation, the create flow, the edit flow, adjacent pages, and a list
of features still to build.

The relationship between the two docs is a division of labor, not a
hierarchy:

| Doc | Covers |
|---|---|
| `event-creation-wizard.md` | The wizard as a **feature** — what each step does, how the checkpoint persists, what each field component is, what is still to build |
| `event-creation.md` (this file) | The **code reference** — file map, validation rules table, list of things AI will break, corrections to the outdated scope doc |

Read both. Neither is subordinate to the other. Where they overlap,
they describe the same code from different angles.

### §6 — Checkpoint overlap with the wizard doc

§6 of this file describes the localStorage checkpoint at a summary
level. `event-creation-wizard.md` §4 covers the same feature in
detail — storage key naming, snapshot shape, restore/save/clear
behavior, the misleading `currentStep` field name in the JSON
payload, and the list of checkpoint refinements still to build.

If you are working on checkpoint behavior, read the wizard doc first.
§6 of this file remains accurate as a summary, but it is not the
complete picture.

### §12 item 4 — Workflow transitions

Unchanged. `registration_closed → ongoing`, `ongoing → completed`,
and `* → cancelled` still have no controller actions or routes.
See `event-creation-wizard.md` §11.6 for the same note from the
wizard doc's side.

### §12 item 6 — Attendance page

Unchanged. The attendance page exists in the tree but was not verified
end to end when this file was written.
