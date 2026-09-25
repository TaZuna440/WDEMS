# Event Creation Wizard

**Last updated:** September 25, 2026
**Scope:** Event schema extension + multi-step creation wizard + field components + persistence
**Status:** Wizard, persistence, and field components are live. See §11 for open items.

---

## Overview

Event creation moved from a single-page form to a **4-step wizard** with:

- Persistent progress across browser refreshes (localStorage checkpoint)
- Structured venue coordinates (map picker with reverse geocoding)
- Required distance with KM/Miles conversion
- Partner management (hosts, sponsors, food, etc.)
- 9 accessibility flags
- Stronger validation on event names

The show, edit, and list pages were extended to display and edit the new fields.

Adjacent flows — **Event Options** and the **Event status workflow** — are
documented in `event-creation.md`, not here. This file is about the wizard
itself: its data model, its UI, and its persistence.

---

## 1. Event Schema Changes

### 1.1 New columns added to `events` table

| Column | Type | Default | Purpose |
|---|---|---|---|
| `venue_address` | varchar(500) | NULL | Full address resolved from map |
| `venue_latitude` | decimal(10,7) | NULL | Marker latitude |
| `venue_longitude` | decimal(10,7) | NULL | Marker longitude |
| `distance_value` | decimal(6,2) | NULL | Distance number |
| `distance_unit` | varchar(2) | `'km'` | `'km'` or `'mi'` |
| `course_url` | varchar(500) | NULL | External course link |
| `rsvp_required` | tinyint(1) | `0` | Hard RSVP vs soft signup |
| `partners` | json | NULL | `[{name, type}, ...]` |
| `faq` | json | NULL | `[{question, answer}, ...]` |
| `walkers_welcome` | tinyint(1) | `0` | Accessibility flag |
| `all_paces_welcome` | tinyint(1) | `0` | Accessibility flag |
| `all_ages_welcome` | tinyint(1) | `0` | Accessibility flag |
| `stroller_friendly` | tinyint(1) | `0` | Accessibility flag |
| `wheelchair_accessible` | tinyint(1) | `0` | Accessibility flag |
| `sweeper_present` | tinyint(1) | `0` | Accessibility flag |
| `service_animals_allowed` | tinyint(1) | `0` | Accessibility flag |
| `leashed_pets_allowed` | tinyint(1) | `0` | Accessibility flag |
| `quiet_space_available` | tinyint(1) | `0` | Accessibility flag |

### 1.2 Column removed

| Column | Reason |
|---|---|
| `distance_label` | Replaced by `distance_value` + `distance_unit` |

### 1.3 Migrations

Two migrations introduced these changes:

1. `2026_09_21_093134_add_event_metadata_to_events_table.php` — added 17 columns
2. `2026_09_21_110517_replace_distance_label_with_value_and_unit_on_events.php` — dropped `distance_label`, added `distance_value` + `distance_unit`

---

## 2. Event Model (`app/Models/Event.php`)

### 2.1 Added fillable fields

All 17 new columns added to `#[Fillable]`.

### 2.2 Added casts

| Field | Cast |
|---|---|
| `distance_value` | `decimal:2` |
| `distance_unit` | *(string, no cast)* |
| `partners` | `array` |
| `faq` | `array` |
| All 9 accessibility flags | `boolean` |
| `rsvp_required` | `boolean` |
| `venue_latitude` | `decimal:7` |
| `venue_longitude` | `decimal:7` |

### 2.3 Helper method — `distanceLabel()`

```php
public function distanceLabel(): ?string
{
    if ($this->distance_value === null) {
        return null;
    }

    $unit = $this->distance_unit === 'mi' ? 'Miles' : 'KM';
    $value = rtrim(rtrim(number_format((float) $this->distance_value, 2, '.', ''), '0'), '.');

    return "{$value} {$unit}";
}
```

Used by `EventController::index()` and `::show()` to build the
`distance_label` prop the frontend reads. The label is computed, not
stored.

### 2.4 Relations

| Method | Returns |
|---|---|
| `creator()` | `belongsTo(User::class, 'created_by')` |
| `eventOptions()` | `hasMany(EventOption::class)` |
| `registrations()` | `hasMany(Registration::class)` |

Plus `relatedRecordCounts()` — returns an array of counts used by the
show page's deletion confirmation.

### 2.5 Workflow guard methods

| Method | True when status is |
|---|---|
| `canEdit()` | `draft`, `configured`, `registration_open`, `registration_closed` |
| `canConfigure()` | `draft`, `configured` |
| `canOpenRegistration()` | `configured` |
| `canCloseRegistration()` | `registration_open` |
| `canRecordAttendance()` | `registration_open`, `registration_closed`, `ongoing` |

These guards are checked by the controller before allowing edits and by
the frontend before showing action buttons. See `event-creation.md` §7
for the workflow diagram.

---

## 3. The Wizard Shell

The wizard is a generic shell that renders one step at a time. It knows
nothing about events specifically — it operates on a `steps` array and
a `data` object.

**File:** `resources/js/components/wizard.tsx`

### 3.1 Props

```ts
type Props<T extends Record<string, unknown>> = {
    steps: WizardStepConfig[];
    storageKey: string;
    data: T;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel?: string;
    children: (stepId: string) => ReactNode;
};
```

The wizard does not know what fields exist. It only knows:

- Which step is current
- Which fields belong to each step
- Which fields are required (for client-side emptiness checks)
- How to advance, go back, and jump to a step
- Where to persist state

The caller (`create.tsx` / `edit.tsx`) supplies the step list, the form
state, and a `children` render function that returns the step component
for the current step ID.

### 3.2 Step configuration

Each step is a `WizardStepConfig`:

```ts
export type WizardStepConfig = WizardStep & {
    fields: string[];
    requiredFields?: string[];
};
```

- `fields` — every field name that belongs to this step. When the
  server returns validation errors, the wizard uses this to jump to
  the step that owns the failing field.
- `requiredFields` — a subset of `fields`. The wizard only checks
  emptiness for these. Everything else (URL format, enum values,
  numeric ranges) is enforced server-side.

### 3.3 Navigation

Three handlers on the shell:

- **`handleNext()`** — validates the current step's required fields
  client-side, then advances. If the new step is further than
  `maxStep`, `maxStep` is bumped.
- **`handleBack()`** — decrements `currentStep`. Does not touch
  `maxStep`.
- **`handleStepJump(index)`** — jumps directly to a step, provided
  `index <= maxReachedStep`. Any locked step's button is disabled.

State is tracked in two `useState` values:

```ts
const [maxStep, setMaxStep] = useState(snapshot?.currentStep ?? 0);
const [currentStep, setCurrentStep] = useState(snapshot?.currentStep ?? 0);
```

`maxStep` is the checkpoint. `currentStep` is where the user is
looking right now.

### 3.4 Error jumping

When the server returns validation errors, the wizard finds the first
step whose `fields` array contains one of the failing keys and jumps
there:

```ts
const failingIndex = steps.findIndex((s) =>
    s.fields.some((f) => f in errors),
);
if (failingIndex !== -1 && failingIndex !== currentStep) {
    setCurrentStep(failingIndex);
}
```

This is the reason every field name must appear in exactly one step's
`fields` array. If you add a field and forget to register it, server
errors for that field will land on whatever step happens to be
current — usually the wrong one.

### 3.5 Progress bar

**File:** `resources/js/components/wizard-progress.tsx`

Renders one pill per step, connected by thin lines. Each pill shows
either the step number or a checkmark (if visited). States:

| State | Visual |
|---|---|
| Current | Filled lime background, navy text |
| Visited (`index < maxReachedStep`) | Subtle white/5 background, checkmark icon |
| Reachable but not visited (`index <= maxReachedStep`) | White/5 background, muted text |
| Locked (`index > maxReachedStep`) | Opacity 50%, cursor-not-allowed, click disabled |

Clicking a reachable pill calls `onStepClick(index)`. The wizard's
`handleStepJump` then switches `currentStep` without touching
`maxStep`. Jumping backward is always allowed; jumping forward is
only allowed up to the furthest step previously reached.

---

## 4. Checkpoint Persistence

The wizard saves its state to `localStorage` so a user who refreshes
the page — or closes the tab and comes back within 24 hours — resumes
where they left off.

**File:** `resources/js/hooks/use-wizard-persistence.ts`

### 4.1 Storage keys

| Flow | Key |
|---|---|
| Create | `wdems-wizard-create` |
| Edit | `wdems-wizard-edit-{eventId}` |

The create and edit flows use separate keys so a user can have a
half-finished new event and a half-finished edit of an existing event
at the same time without collision.

### 4.2 Snapshot shape

```json
{
  "version": 1,
  "currentStep": 2,
  "data": { "...all form fields..." },
  "savedAt": "2026-09-25T14:02:11.000Z"
}
```

**Note on the field name:** the JSON key is `currentStep`, but the
wizard saves `maxStep` into it. See §4.5.

### 4.3 Restore behavior

On mount, the hook reads the snapshot lazily via a `useState` initializer.
If a valid snapshot exists, the wizard:

1. Sets `maxStep` and `currentStep` to `snapshot.currentStep`
2. Applies every key/value in `snapshot.data` via `setData`

A `isReady` flag prevents the initial save effect from firing before
the restore has completed. Without that guard, the wizard would
immediately overwrite the restored snapshot with the parent form's
empty defaults.

### 4.4 Save behavior

Every state change triggers a save:

```ts
useEffect(() => {
    if (!isReady) return;
    save(maxStep, data);
}, [maxStep, data, save, isReady]);
```

This fires on:

- Step advance (`maxStep` changes)
- Step back (`currentStep` changes but `maxStep` does not — so this
  save writes the same checkpoint but the latest `data`)
- Any keystroke in any field (`data` changes)

Saves are synchronous but silent — no user-visible indicator, no
toast, no debounce.

### 4.5 Field name is misleading

The hook's `save` signature is:

```ts
save(currentStep: number, data: T)
```

But the wizard calls it as:

```ts
save(maxStep, data)
```

So the persisted JSON field `currentStep` actually contains the
**furthest step reached**, not the currently viewed step. This is
deliberate — it means the checkpoint is never reduced by going back.
On restore, the wizard opens at the furthest step, not the last
viewed one.

The naming is confusing but the behavior is intentional. If you ever
refactor this, rename the field in the JSON payload to `maxStep` and
bump `STORAGE_VERSION` to invalidate every existing draft.

### 4.6 Version guard and TTL

Two safety valves:

- **`STORAGE_VERSION = 1`** — if you change the schema of the wizard
  data, bump this. All existing drafts become invalid and are ignored
  on read.
- **`MAX_AGE_MS = 24 hours`** — snapshots older than this are ignored.
  If the user comes back the next day, they start fresh.

Both checks happen inside `readSnapshot`, which fails silently on any
error (corrupt JSON, quota exceeded, browser storage unavailable).

### 4.7 Clear behavior

`clear()` is called only on successful submit, from inside
`handleSubmit`:

```ts
const handleSubmit = useCallback(() => {
    if (!validateCurrentStep()) return;
    clear();
    onSubmit();
}, [validateCurrentStep, clear, onSubmit]);
```

Note this clears **before** the request goes out. If the server rejects
the submission, the snapshot is gone. That is currently accepted —
the user has to re-enter the data — but see §11.

### 4.8 What is not yet implemented

The following are natural extensions that the checkpoint does not
currently do:

- **A "Resume draft" prompt.** If a snapshot is restored, the user
  is not told. The wizard silently loads the draft. Some users
  expect an explicit choice ("You have an unfinished event, resume?").
- **A visible discard button.** There is no UI for clearing the
  snapshot without submitting. A user who wants to start over must
  either clear browser storage manually or fill in the form and
  submit.
- **A "saved at" indicator.** Saves happen silently, so the user has
  no confirmation their work is preserved.
- **Server-side draft persistence.** Everything lives in the browser.
  If the user switches devices, their half-finished event does not
  follow them.
- **Cross-tab coordination.** Two browser tabs editing the same
  draft will overwrite each other's snapshot on every save.

None of these are bugs — the feature as built does exactly what it
was designed to do. They are the natural next steps if the checkpoint
is extended.

---

## 5. The Four Steps

Each step is a React component that receives `data`, `setData`, and
`errors`. The wizard shell calls the correct one via a `switch` on
`steps[currentStep].id`.

The step configuration — including `fields` and `requiredFields` —
lives in **`create.tsx`** and **`edit.tsx`**, not in the step
components themselves. If you add a field, you register it in both
places.

### 5.1 Step 1 — Basics

**Component:** `resources/js/pages/events/step/BasicsStep.tsx`

| Field | Type | Required | Notes |
|---|---|---|---|
| `event_type` | select | Yes | Locked in edit mode |
| `event_name` | text | Yes | 3–100 chars, custom regex rules (§7.3) |
| `description` | textarea | No | Max 2000 chars |

In create mode, `eventTypes` prop is the full list from
`EventType::cases()`. In edit mode, it is a single-element array
containing only the event's current type. This is why the dropdown
has one option in edit mode — see §9.

### 5.2 Step 2 — Schedule

**Component:** `resources/js/pages/events/step/ScheduleStep.tsx`

| Field | Type | Required | Notes |
|---|---|---|---|
| `event_date` | DatePicker | Yes | Custom calendar (§6.1) |
| `start_time` | TimePicker | Yes | 5-minute snapping (§6.2) |
| `end_time` | TimePicker | No | Optional |
| `distance_value` | DistancePicker | Yes | 0.01 – 9999.99 |
| `distance_unit` | toggle | Yes | `km` or `mi` |
| `course_url` | text (url) | No | Max 500 chars |

`distance_value` and `distance_unit` are owned by the same component
(`DistancePicker`). Changing the unit calls `setData` for both fields
in one handler.

### 5.3 Step 3 — Venue

**Component:** `resources/js/pages/events/step/VenueStep.tsx`

| Field | Type | Required | Notes |
|---|---|---|---|
| `venue` | text | Yes | Short name, max 255 |
| `venue_address` | text | Yes | Full address, max 500. Filled by map picker |
| `venue_latitude` | number | Yes | -90 to 90. Set only via map picker |
| `venue_longitude` | number | Yes | -180 to 180. Set only via map picker |

Latitude and longitude are not directly editable. They come from the
map picker dialog. The VenueStep shows the current coordinates as
read-only text when set.

### 5.4 Step 4 — Extras

**Component:** `resources/js/pages/events/step/ExtrasStep.tsx`

| Field | Type | Required | Notes |
|---|---|---|---|
| `rsvp_required` | checkbox | No | Boolean |
| `partners` | PartnerEditor | No | Array of `{name, type}` |
| 9 accessibility flags | AccessibilityGrid | No | All default false |

The `partners` array is validated server-side with two rules:

- `partners.*.name` — required if the partner entry exists
- `partners.*.type` — must be one of the 9 allowed values

The accessibility flags are grouped visually into four categories by
`AccessibilityGrid` (Pace & Ability, Physical Access, Companion &
Support, Sensory). Grouping is a UI concern only — the server treats
all nine as flat booleans.

---

## 6. Field Components

Each component is reusable, self-contained, and receives
`value`/`onChange` or `value`/`onChange`/`unit`. None of them touch
the server directly — they are all controlled inputs.

### 6.1 DatePicker

**File:** `resources/js/components/date-picker.tsx`

A custom calendar built from scratch — no `react-day-picker`.

- Popover dialog anchored to the trigger button
- Month/year navigation
- "Today" shortcut
- "Clear" X button when a date is set
- Closes on outside click or Escape
- Displays in `MMM d, yyyy` format (e.g. "Sep 25, 2026")
- Stores in ISO `yyyy-mm-dd`

### 6.2 TimePicker

**File:** `resources/js/components/time-picker.tsx`

Three Radix selects: hour (1–12), minute (00–55 in 5-minute
increments), and AM/PM.

- **Minutes snap to 5.** Only `0, 5, 10, ..., 55` are selectable.
  There is no way to enter `12:03`.
- Storage format is 24-hour `HH:MM` (e.g. `09:30`, `23:45`).
- Display format is 12-hour with period.
- A `parse()` function converts between the two, handling the
  midnight/noon edge cases (`12 AM` → `00:00`, `12 PM` → `12:00`).

### 6.3 DistancePicker

**File:** `resources/js/components/distance-picker.tsx`

Two `NumberCombobox` inputs separated by a decimal point, plus a
KM/Miles segmented toggle.

- **Whole** — 0 to 100, three digits max
- **Decimal** — 0 to 9, two digits max
- Combined value is rounded to 2 decimals
- Toggling the unit converts the current value live:
  - `KM_TO_MI = 0.621371`
  - Conversion rounds to 2 decimals
  - The whole/decimal pair is re-split after conversion

Each `NumberCombobox` is a filtered dropdown — clicking or focusing
opens a scrollable list of options, typing filters it, Enter selects
the first match, Escape closes.

If the user clears the whole part, the value is set to `null` (which
the server rejects — `distance_value` is required).

### 6.4 MapPicker / MapPickerLeaflet

**Files:**
- `resources/js/components/map-picker.tsx` — dialog wrapper
- `resources/js/components/map-picker-leaflet.tsx` — the actual map

Split into two files so the 154 KB Leaflet bundle is **lazy-loaded**
only when the dialog opens. The wrapper imports the inner component
via `React.lazy()` and wraps it in `<Suspense>`.

**Leaflet map:**

- Free stack — no API key required
- OpenStreetMap tiles
- Default center is Manila (`14.5995, 120.9842`) when no location
  is set
- Marker is placed on click
- Reverse geocoding uses Nominatim

**Nominatim integration:**

- Endpoint: `https://nominatim.openstreetmap.org/reverse`
- Zoom level 18, `addressdetails=1`
- The current coordinates are compared to the last query — identical
  coordinates are skipped (avoids hammering the API when the user
  clicks the same spot twice)
- **1 request per second rate limit** enforced by Nominatim. Exceeding
  it returns 403 and the geocode fails silently. The user can always
  type the address manually.
- The dialog shows a "Looking up address..." badge while a request
  is in flight.

### 6.5 PartnerEditor

**File:** `resources/js/components/partner-editor.tsx`

A list of partner rows. Each row has:

- A name text input
- A type select (9 options: host, sponsor, food, beverage, medical,
  organization, media, logistics, other)
- A remove button

Empty state shows a "No partners yet" prompt with an icon.

"Add Partner" appends a new row with `type: 'host'` as the default.

**The partner types list is duplicated** between this file and
`EventValidationRules::partnerTypes()`. Both must stay in sync.

### 6.6 AccessibilityGrid

**File:** `resources/js/components/accessibility-grid.tsx`

Nine checkboxes in four visually grouped sections. Each checkbox
takes a `field`, a `label`, and an optional `hint` (shown as small
grey text below the label).

The groups and their fields:

| Group | Fields |
|---|---|
| Pace & Ability | `walkers_welcome`, `all_paces_welcome`, `all_ages_welcome`, `sweeper_present` |
| Physical Access | `stroller_friendly`, `wheelchair_accessible` |
| Companion & Support | `service_animals_allowed`, `leashed_pets_allowed` |
| Sensory | `quiet_space_available` |

The `AccessibilityField` union type is exported and used by
`ExtrasStep` to type its `data` prop.

---

## 7. Validation

### 7.1 Client-side

Client validation is **empty-check only**. The wizard's
`validateCurrentStep()` looks at the current step's `requiredFields`
array and flags any that are `''`, `null`, `undefined`, or an empty
array. It does not check formats, ranges, or enums.

The error message is generated from the field name:

```
event_name → "Event name is required."
```

This runs on Next and on Submit. It does not run on field change.

### 7.2 Server-side

Server-side validation is the real gate. It lives in
`app/Concerns/EventValidationRules.php`, which is used by
`EventRequest`.

Full rule table is in `event-creation.md` §5. The short version:

- Required: `event_type`, `event_name`, `event_date`, `start_time`,
  `distance_value`, `distance_unit`, `venue`, `venue_address`,
  `venue_latitude`, `venue_longitude`
- Optional: `end_time`, `description`, `course_url`, `partners`,
  all 9 accessibility flags, `rsvp_required`
- Constrained: enums for `event_type` and `distance_unit`, ranges for
  coordinates and distance, URL format for `course_url`

### 7.3 Event name rules

Five regex rules layered on top of `required`, `min:3`, `max:100`:

| Rule | Rejects |
|---|---|
| `/^[A-Za-z0-9]/` | Names starting with punctuation or space |
| `/[aeiouyAEIOUY]/` | Names with no vowel — "dfdsfdsfd" |
| `/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/` | Names with no consonant — "aeiou" |
| `not_regex:/(.)\1\1/` | 3+ identical characters in a row — "aaaa" |
| Length | 3–100 chars |

These exist to catch keyboard mashing before it becomes a real event.
They are deliberately inclusive — Y counts as a vowel, case is
ignored. Any future change must preserve all five checks; see
`event-creation.md` Trap #6.

---

## 8. Create Flow

### 8.1 Entry

`GET /events/create` → `EventController::create()`

The controller fetches all `EventType::cases()` and passes them as
`event_types`:

```php
'event_types' => collect(EventType::cases())
    ->map(fn (EventType $type) => [
        'value' => $type->value,
        'label' => $type->label(),
    ]),
```

The page is `events/create.tsx`. It sets up the `useForm` hook with
empty defaults for every field, then renders the `Wizard`.

### 8.2 Step through

The wizard shell takes over. Each step renders its component. Client
emptiness checks run on Next. The localStorage snapshot saves after
every change.

### 8.3 Submit

`POST /events` → `EventController::store()`

The controller:

1. Validates via `EventRequest` (which uses `EventValidationRules`)
2. Creates the event with `status: EventStatus::Draft`
3. Seeds FAQ via the private `defaultFaq()` method
4. Redirects to `route('events.index')`

**Server-assigned fields, not from the form:**

- `created_by` — the current user's ID
- `status` — always `EventStatus::Draft`
- `faq` — six hardcoded entries; the wizard has no FAQ input

### 8.4 Error handling

If validation fails, Laravel returns the standard 422 response with
an `errors` object. Inertia re-renders the page. The wizard's error
effect finds the first step whose `fields` array contains a failing
key and jumps there.

Errors are then passed down to the step component, which displays
them below the relevant field via `<InputError>`.

### 8.5 The FAQ default

`EventController::defaultFaq()` returns six Q&A pairs about walking,
families, dogs, RSVP, what to bring, and where to meet. These are
stored once on creation and are **never editable through any UI**.
The show page renders them if present; the edit wizard does not touch
them.

This is a known asymmetry — see §11.

---

## 9. Edit Flow

`GET /events/{id}/edit` → `EventController::edit()`

Prefills from the event, but only if `$event->canEdit()` returns
true. Otherwise aborts with 403.

### 9.1 Differences from create

| Aspect | Create | Edit |
|---|---|---|
| Storage key | `wdems-wizard-create` | `wdems-wizard-edit-{id}` |
| Submit method | POST `/events` | PUT `/events/{id}` |
| `event_type` | Full dropdown, editable | Single-option dropdown, disabled |
| FAQ | Seeded on store | Not touched on update |

### 9.2 Why `event_type` is locked

The edit page passes only the event's current type:

```tsx
eventTypes={[
    {
        value: event.event_type,
        label: event.event_type_label,
    },
]}
```

The `Select` is also disabled via the `isEdit` prop.

Even if you enabled it on the frontend, **the backend would ignore
the change**: `EventController::update()` does not include
`event_type` in the update array. Changing event type mid-flight has
downstream implications for the participant registration model, so
this is a deliberate scope decision, not an oversight.

### 9.3 Edit guard on the server

```php
if (! $event->canEdit()) {
    abort(403, 'This event can no longer be edited.');
}
```

`canEdit()` is true while status is `draft`, `configured`,
`registration_open`, or `registration_closed`. After
`registration_closed → ongoing`, the edit endpoint returns 403.

The same guard is repeated in `update()` — both are checked, because
`edit()` and `update()` are separate requests.

---

## 10. Adjacent Pages

The Overview claims "the show, edit, and list pages were extended to
display and edit the new fields." Here is what that means concretely.

### 10.1 Event list — `events/index.tsx`

Shows one row per event with:

- Event name (links to show)
- Event type pill
- Event date
- Distance label
- Venue
- Status pill
- Creator name

Empty state prompts to create the first event.

### 10.2 Event show — `events/show.tsx`

Displays every field the wizard collected, plus the six seeded FAQ
entries and a workflow actions panel. Also includes a Danger Zone
with an event-deletion button.

The show page is not a wizard — it is a read-only view with action
buttons.

### 10.3 Event options — `events/options.tsx`

**Not part of the wizard.** Documented here only so you know where
to find it.

`GET /events/{id}/options` shows a table of options and an add form.
Options can only be added, edited, or removed while
`$event->canConfigure()` is true. The page also hosts the "Mark as
Configured" button that transitions the event from `draft` to
`configured`.

See `event-creation.md` §7 for the full options flow.

---

## 11. What still needs implementing

Items below are known but not yet done. They are recorded here so the
next person does not have to rediscover them.

### 11.1 Checkpoint refinements

The localStorage checkpoint works. The gaps are user-experience ones:

1. **No "resume draft" prompt.** The snapshot restores silently.
   Some users would prefer to be asked.
2. **No discard button.** No way to clear a draft without submitting.
3. **No "saved at" indicator.** Saves are invisible.
4. **`clear()` fires before submit.** If the server rejects, the
   draft is gone. Should probably clear only on success.
5. **No cross-tab coordination.** Two tabs of the same draft will
   clobber each other.

See §4.8 for detail on each.

### 11.2 FAQ editing

The six seeded FAQ entries cannot be edited through any UI. A natural
home for editing them is the show page, which already renders them.
An "Edit FAQ" button there would need:

- A new endpoint
- A validation rule for FAQ entries
- A form component

None of that exists yet.

### 11.3 Event type change

`event_type` is locked after creation, by design. If the requirement
changes, the fix touches:

- `EventController::update()` — add `event_type` to the update array
- `BasicsStep.tsx` — remove the `isEdit` disabled state
- `edit.tsx` — pass the full `event_types` list instead of a
  single-element array
- A decision about what happens to existing participants and
  registrations when the type flips

Not a small change.

### 11.4 Partner contact information

The scope document mentions partners having "contact information"
and "role/responsibility". Neither is stored. `partners` is
`{name, type}` only. If those fields are needed, they are a schema
change plus a form change plus a validation change.

### 11.5 Server-side draft persistence

Currently the draft lives only in the browser. If the wizard should
resume across devices, the snapshot needs a server table and a
sync endpoint. This is a larger feature, not a small fix.

### 11.6 Workflow transitions not wired to routes

`registration_closed → ongoing`, `ongoing → completed`, and
`* → cancelled` are all defined in `EventStatus::allowedTransitions()`
but have no controller actions or routes. See `event-creation.md`
§7.2 for the full transition table.

---

## 12. References

| Document | Covers |
|---|---|
| `authentication.md` | Login, 2FA, trusted devices |
| `architecture.md` | Whole-system overview |
| `event-creation.md` | Code reference for event creation, options, and workflow. **Read this for the file map, validation table, and the "things AI will break" list.** |
| This file (`event-creation-wizard.md`) | Wizard feature itself: schema, model, shell, steps, field components, persistence |

Where this file and `event-creation.md` overlap, they describe the
same code from different angles. This file is feature-focused; that
file is reference-focused.

---

## Changelog

- **2026-09-21** — Initial write-up. Covered the schema extension and
  the model changes. Ended mid-§2.3.
- **2026-09-25** — Completed §2.3, added §3 (wizard shell), §4
  (checkpoint persistence), §5 (the four steps), §6 (field
  components), §7 (validation), §8 (create flow), §9 (edit flow),
  §10 (adjacent pages), §11 (open items), and §12 (references).
