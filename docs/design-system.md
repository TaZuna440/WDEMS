# WDEMS — Design System

**Audience:** Whoever edits `resources/css/app.css`, any
`resources/js/pages/**/*.tsx`, or any `resources/js/components/**/*.tsx`.

**Purpose:** Define the visual contract for WDEMS. Every color,
opacity, and surface choice reads from the rules in this file. When a
page looks wrong, the fix is "which rule is being violated" — not
"tune a number until it looks right."

**Status:** Live. Written 2026-10-15 during the light-mode redesign.

---

## 1. The four-layer model

Every pixel on screen belongs to one of four layers. Each layer reads
from its own set of values. No layer reaches into another's.

    ┌──────────────────────────────────────────────┐
    │  LAYER 3 — Content                           │
    │    Text, icons, borders, controls.           │
    │    Reads from: --foreground per mode.        │
    │    Flips between light and dark.             │
    ├──────────────────────────────────────────────┤
    │  LAYER 2 — Glass surfaces                    │
    │    Sidebar, panels, tables, modals.          │
    │    Reads from: tier tint + backdrop-filter.  │
    │    Flips between light and dark.             │
    ├──────────────────────────────────────────────┤
    │  LAYER 1 — Image + overlay                   │
    │    The atmosphere.                           │
    │    Reads from: --app-bg-image +              │
    │                --app-bg-overlay.             │
    │    Identical in both modes.                  │
    ├──────────────────────────────────────────────┤
    │  LAYER 0 — Base gradient                     │
    │    Fallback if the image fails to load.      │
    │    Reads from: --app-bg-1/2/3.               │
    │    Flips between light and dark, but only    │
    │    visible when LAYER 1 is absent.           │
    └──────────────────────────────────────────────┘

**Rule:** a component on Layer 3 does not know whether Layer 2 exists
behind it. A component on Layer 2 does not know what Layer 1 is
showing. Layers compose by order, not by coordination.

---

## 2. Glass tiers

Not every glass surface is the same. There are three tiers, and each
has an opacity band, a purpose, and a set of members.

### Tier A — Structural surfaces

    Members:
      - Sidebar panel
      - Top header strip / floating controls
      - Modal backdrops
      - Anything the user needs to find no matter what is behind it

    Light tint band:  60% – 75%
    Dark tint band:   45% – 60%

Tier A surfaces are the "chrome" of the app. They must always be
readable. When in doubt, they are the surfaces that go one step more
opaque.

### Tier B — Content panels

    Members:
      - Main cards (Needs Attention, Coming Up, table wrappers)
      - Form sections
      - Report blocks
      - Any container holding primary content

    Light tint band:  50% – 65%
    Dark tint band:   32% – 45%

Tier B surfaces carry the reading experience. They need enough opacity
that text is legible against whatever is behind them, and enough
transparency that the atmosphere is visible. This is where the
"frosted glass over a photo" look lives.

### Tier C — Internal rows and items

    Members:
      - Event rows inside a table
      - Action items inside Needs Attention
      - List rows inside a card
      - Any element nested inside a Tier A or Tier B surface

    Tint:             transparent
    Tint:             transparent

Tier C adds no glass of its own. It inherits its parent's surface and
uses only `hover:bg-*` at low opacity for interaction feedback.
Stacking glass on glass produces a muddy, darker inner surface. This
is the current failure mode on `/registrations`.

**One tint per surface.** Never nest glass inside glass.

---

## 3. Mode contract — what flips, what stays

### What flips between light and dark

- Every `--color-*` and `--sidebar-*` semantic token
- `--lime-brand` (`#84CC16` light, `#B6FF3B` dark)
- Tier tint percentages
- `--foreground` (navy in light, white in dark)
- `--border` (visible grey in light, translucent white in dark)

### What does not flip

- The image (`--app-bg-image`)
- The overlay (`--app-bg-overlay` — same value in both modes)
- Text placed directly on the image (see Section 7)
- The `.glass-card`, `.glass-sidebar`, `.glass-header` recipes
  themselves — only their inputs flip

**Rule:** if a value changes between modes, it lives in either the
`:root` block or the `.dark` block. If it does not change, it lives
outside both.

---

## 4. Semantic colors

Semantic colors are not brand. They carry meaning. Each has a
light-mode value and a dark-mode value, and the meaning is preserved
across the flip.

| Semantic | Meaning | Light mode | Dark mode |
|---|---|---|---|
| **Brand / accent** | WDEMS identity, primary actions | `#84CC16` (lime) | `#B6FF3B` (lime) |
| **Success / Completed** | Finished, confirmed, verified | `bg-green-500/15 text-green-700` | `bg-green-500/15 text-green-500` |
| **Warning** | Attention needed, pending, urgent | see Section 5 | yellow family |
| **Danger** | Destructive, errors, overdue | `bg-destructive/15 text-destructive` | `bg-destructive/15 text-destructive` |
| **Neutral / Draft** | Inactive, not-yet-started | `bg-secondary text-foreground` | `bg-secondary text-foreground` |
| **Info / Type** | Category label, informational | `bg-accent/15 text-teal-700` | `bg-accent/15 text-accent` |

**Rule:** success is green in both modes. Brand is lime in both modes.
Danger is red in both modes. Only the **warning** semantic has a
different hue per mode (see Section 5).

---

## 5. The warning rule — yellow in dark, navy in light

The warning semantic is the one place where the mode changes the hue,
not just the shade. This is deliberate:

- **Dark mode:** yellow (`bg-yellow-500/*` / `text-yellow-500`). Yellow
  reads as warning against a dark navy background.
- **Light mode:** navy (`#13253B`, WDEMS navy). Yellow on light glass
  is low-contrast and reads as decoration, not as a warning. Navy
  carries the same "attention" weight without the contrast problem.

**Rule:** every yellow utility in the codebase must be paired with a
light-mode override. The pattern is:

    bg-[#13253B] text-white dark:bg-yellow-500/15 dark:text-yellow-500

Light value first (navy), dark override second (yellow). The `dark:`
prefix is not optional.

**Scope of this rule** — it applies to every occurrence of:

- Warning badges (`registration_closed`, `pending`)
- Warning dots (urgency indicators)
- Warning borders and border-lefts
- Warning text (`text-amber-*`, `text-yellow-*`)
- Warning buttons
- Warning panels (`bg-amber-500/*`)

Amber and yellow are the same warning family. Both follow this rule.

**Not in scope:** `text-amber-*` used for pure decoration in the
absence of a warning meaning. There are none in the codebase today.

---

## 6. Text on the image

Some text sits directly on the page image — not on a glass surface.
Page headings, hero subtitles, empty-state titles when they overlap
the image.

The image is a fixed dark scene. In light mode, `--foreground` is
navy — unreadable on dark. In dark mode, `--foreground` is white —
readable.

**Rule:** text placed directly on the image is white in both modes,
with a soft dark shadow.

The utilities:

    .text-on-image         white + shadow
    .text-on-image-muted   soft white + shadow

Both are defined in `resources/css/app.css` and are identical in
light and dark. They bypass the semantic tokens on purpose — the
image is a color context neither mode's tokens describe.

**When to use:** only for text that visually overlaps the raw image.
Everything inside a Tier A or Tier B surface uses normal
`--foreground` text.

**When not to use:** inside cards, on the sidebar, inside modals.
Text on glass reads from `--foreground`.

---

## 7. Tier assignments — current pages

Every page's primary surface must declare its tier. This is the
current audit.

| Surface | File | Tier |
|---|---|---|
| Sidebar panel | `ui/sidebar.tsx` | A |
| Top header strip | `app-sidebar-header.tsx` | A |
| Floating sidebar trigger | `app-sidebar-header.tsx` | A |
| Page hero heading | page body | Layer 3 (text-on-image) |
| Dashboard — Needs Attention card | `dashboard.tsx` | B |
| Dashboard — Coming Up card | `dashboard.tsx` | B |
| Dashboard — action row | `dashboard.tsx` | C |
| Events list — table wrapper | `events/index.tsx` | B |
| Events list — table row | `events/index.tsx` | C |
| Registrations — section cards | `registrations/index.tsx` | B |
| Attendance — table wrapper | `events/attendance.tsx` | B |
| Report — section cards | `events/report.tsx` | B |
| Monitor — feed cards | `registrations/monitor/show.tsx` | B |
| Any modal content | `ui/dialog.tsx`, `ui/sheet.tsx` | B |

**Rule:** when you build a new page or component, decide its tier
first. Tier A → Tier A opacity. Tier B → Tier B opacity. Tier C →
no tint. Never split the difference.

---

## 8. Anti-patterns

These are the mistakes that have actually happened in this codebase.
Each one is a rule violation.

### 8.1 Nested glass

A Tier C row inside a Tier B card that has its own tint. Result: the
row is darker than the card, and the eye reads them as separate
surfaces. The current `/registrations` page does this.

**Fix:** rows have no tint. Only `hover:bg-*`.

### 8.2 Yellow or amber without a light-mode override

`text-amber-400` in a class string with no `dark:` prefix. Result:
yellow on white — unreadable. Violates Section 5.

**Fix:** every yellow utility has a light value and a `dark:` value
on the same line.

### 8.3 Hard-coded hex outside the semantic set

`border-[#CBD7E3]` applied without checking whether the file already
has a `border-border` semantic. Result: two borders that are almost
the same color but not quite.

**Fix:** for anything expressible as a semantic token, use the token.
Reserve hex for one-off utilities like the sidebar trigger or the
warning-family override.

### 8.4 Text on glass with `.text-on-image`

Applying the on-image utility to text that's already inside a glass
panel. Result: white text on white glass in light mode.

**Fix:** `.text-on-image` is only for text that overlaps the raw
image. Inside a panel, use `text-foreground`.

### 8.5 Opacity drift

Setting `--glass-card-tint` per page to make one page look right.
Result: three pages look like three different applications. Violates
Section 2.

**Fix:** glass opacity is a global variable. If a specific page needs
a different look, that page's surface is at the wrong tier — move it
to Tier A or Tier C, don't change the number.

### 8.6 Unpaired light/dark

Any `bg-*` or `text-*` class that reads from a token that only exists
in one mode. Result: unreadable text in the other mode.

**Fix:** all semantic colors live in `:root` and `.dark` in
`resources/css/app.css`. Components use `bg-background`,
`text-foreground`, etc. — never raw hex per mode.

---

## 9. Enforcement

Three mechanisms keep the rules from drifting:

### 9.1 The audit script

    scripts/audit-design.sh

Runs three checks and exits non-zero if any fail:

1. Every yellow/amber utility has a `dark:` variant on the same line.
2. No `.text-on-image` usage outside page hero headings.
3. No `--glass-*-tint` declaration outside `resources/css/app.css`.

Run manually:

    ./scripts/audit-design.sh

Run before commit for any UI change.

### 9.2 The AI-CONTEXT trap list

`docs/AI-CONTEXT.md` gains a short section noting that this file
exists and that CSS edits must read it first. AI sessions that touch
colors read the design system before proposing changes.

### 9.3 Review checklist

Before any PR that touches `resources/css/app.css` or a `.tsx` file
with color classes, run:

    ./scripts/audit-design.sh
    npx tsc --noEmit

Both must pass. No exceptions for "just a small tweak."

---

## 10. What this document does not cover

- Typography (deferred — the system uses Tailwind defaults plus
  `--font-sans`)
- Spacing scale (deferred — Tailwind's default scale, no custom
  tokens)
- Motion and transitions (deferred — Tailwind defaults)
- Iconography (deferred — Lucide React, standard sizes)
- Responsive breakpoints (deferred — Tailwind defaults)

Those are visual concerns but separate from the color and surface
system. Each gets its own doc if the project grows to need it.

---

## 11. Interactive element surfaces

§6 covers text placed directly on the image. This section covers
the *other* elements that sit on the image or on light glass and
need explicit mode-aware surfaces: chips, anchor buttons, inputs,
checkboxes, status badges, and icon glyphs.

The root cause every rule below addresses: `body` is transparent
(Stage 2C), and the `body::before` pseudo-element paints the scene
image at `z-index: -1`. Any element that assumes a solid
`--background` behind it — or that uses a hardcoded white/black
utility — will either show the raw image through itself or
disappear when the mode flips.

### 11.1 Floating filter chips

Chips that sit on the raw image (not inside a glass card) need
their own surface. The pre-fix pattern — `border-white/10` plus
`text-muted-foreground` — is invisible in light mode because
neither token describes the image context.

**Active chip:**

    border-lime-brand/40 bg-lime-brand/25 text-lime-700
    dark:bg-lime-brand/20 dark:text-lime-brand

**Inactive chip:**

    border-border bg-card/70 text-foreground hover:bg-card

Lime-on-lime needed a light-mode pair: `text-lime-700` is the
readable dark-lime on the pale lime pill; `dark:text-lime-brand`
restores the bright lime for the dark mode pill.

**Where it applies:** `events/index.tsx`, `discipleship/show.tsx`,
`services/attendance.tsx`, `services/show.tsx`,
`attendance-filter-chips.tsx`, `registrations/monitor/show.tsx`.
Chips inside glass panels (e.g. the running-event attendance
filter chips) use the same classes — the pattern is safe both on
and off the image.

### 11.2 Raw anchor buttons

The `Button` component carries a mode-aware surface via the
outline variant. A raw `<a>` styled to look like a button does not
inherit it — it needs the surface written by hand.

**Rule:**

    inline-flex h-8 items-center rounded-md border border-border
    bg-card/80 px-3 text-xs font-medium text-foreground shadow-xs
    backdrop-blur-sm transition-colors hover:bg-card

`bg-card/80` gives the anchor the same translucent-white surface
as the outline Button; `backdrop-blur-sm` softens the image behind
it; `border-border` flips with the mode.

**Where it applies:** Export CSV buttons on `discipleship/show.tsx`
and `events/report.tsx`; the Refresh and Export buttons on
`registrations/monitor/show.tsx`; the Copy public link button in
the same file (with `border-lime-brand/40` instead of
`border-border`).

### 11.3 Form primitives

`Input`, `SelectTrigger`, and `Textarea` ship from shadcn with
`bg-transparent`. On a transparent body that means the raw image
shows through the field.

**Rule:** `bg-card` on the base class of every form input
primitive. `bg-transparent` is not used anywhere on `Input`,
`SelectTrigger`, or `Textarea` except for `file:bg-transparent`
on the file input's button, which is correct.

**Checkbox** uses `border-border bg-card` for the unchecked state.
The shadcn default (`border-input`, where `--input` is near-white
in light mode) renders nearly invisible on a light card.

**Where it applies:** `resources/js/components/ui/input.tsx`,
`select.tsx`, `textarea.tsx`, `checkbox.tsx`.

### 11.4 Outline Button variant

The `outline` variant uses `bg-card/80 backdrop-blur-sm` — not
`bg-background`. `--background` is transparent in this design
system; using it produces an invisible button on the image. The
current primitive recipe gives every outline Button a visible
surface without per-page overrides.

### 11.5 Status badges — bright colors need a light-mode pair

Bright `-400` Tailwind colors (`text-green-400`, `text-blue-400`,
and the like) read on dark surfaces but disappear on pale glass.

**Rule:** pair every bright status color with a dark-`-700` light
mode value:

    text-green-700 dark:text-green-400
    text-blue-700  dark:text-blue-400

The `bg-*-500/15` tint and `border-*-500/30` remain the same in
both modes — only the text color flips.

**Where it applies:** `STATUS_STYLES` maps in
`events/attendance.tsx` and `events/report.tsx`, the
`ACTIVE_STYLES` map in `attendance-filter-chips.tsx`, and
individual icon colors such as `CheckCircle2` in
`settings/security.tsx`.

**Note:** `text-destructive` is already mode-aware in this
codebase (it resolves to a red that works in both modes) and does
not need a pair.

### 11.6 Lime glyphs on lime-tinted circles

`text-lime-brand` on `bg-lime-brand/15` is bright lime on pale
lime — low contrast in light mode.

**Rule:**

    text-lime-700 dark:text-lime-brand

The circle background stays `bg-lime-brand/15`.

**Where it applies:** card icon circles on `services/index.tsx`,
`fellowship/index.tsx`, `discipleship/index.tsx`; empty-state
icon circles on the same pages and on `discipleship/batch-show.tsx`
and `discipleship/show.tsx`; the accessibility checkmark on
`events/show.tsx` (where the circle is `bg-lime-brand/20` but the
text rule is identical); the `Latest:` metadata accent on
`services/index.tsx`.

### 11.7 Empty-state icon circles

`bg-white/5` is a dark-mode-only tint. On a light card it produces
a nearly invisible circle.

**Rule:** `bg-secondary` for the circle. `--secondary` resolves to
`oklch(0.95 0.005 250)` in light mode and `oklch(0.269 0 0)` in
dark — a visible disc in both.

**Where it applies:** any empty state that renders a circular icon
holder on a glass-panel surface.

### 11.8 The common shape

Every rule above traces back to one of two failures:

1. **Token that assumes a solid `--background`.** `bg-transparent`,
   `bg-background`, `border-input` — these presume the element is
   over an opaque surface. In a design system whose body is
   transparent, they must be replaced with `bg-card`,
   `border-border`, or `bg-secondary`.

2. **Hardcoded white utility without a dark: pair.** `bg-white/5`,
   `border-white/10`, `border-white/5`, `hover:bg-white/10` — these
   read on the dark image in dark mode but vanish on the light
   glass surface in light mode. Replace with the semantic token
   (`bg-card`, `border-border`, `bg-secondary`) or add a light-mode
   counterpart.

Both classes are enforced by `scripts/audit-design.sh` Rules 4-6
plus the existing Rules 1-3. See §9.1.
## 12. Cross-references

| Document | Purpose |
|---|---|
| `docs/design-system.md` | This file — the visual contract |
| `resources/css/app.css` | The implementation |
| `docs/dev-workflow/README.md` | The general workflow rules |
| `docs/AI-CONTEXT.md` | Session bootstrap for AI tools |

---

## Changelog

- **2026-10-15** — File created. Codifies the layer model, glass
  tiers, mode contract, semantic colors, the yellow-to-navy warning
  rule, text-on-image usage, tier assignments, anti-patterns, and
  enforcement.


- **2026-10-16** — §11 added. Documents the interactive-element
  surface rules discovered during the light/dark sweep: chips,
  anchor buttons, form primitives, outline Button variant, status
  badges, lime glyphs, and empty-state circles. §11 "Cross-
  references" renumbered to §12. The audit's Rules 4-6 enforce the
  new rules.

---

## Corrections (2026-10-17)

### The background image is removed from the app shell

§1 described a four-layer model: base gradient, image + overlay,
glass surfaces, content. The image layer was global — every page
rendered a photographic scene behind the glass.

On 2026-10-17 the image was removed from the app shell. The
default `body::before` stack is now three layers: two radial
glows over a linear gradient. Both the gradient colors and the
glow opacity flip between light and dark mode, so the mode
toggle is now visibly distinct on every page.

The login page is the one exception. Its layout (`auth-login-layout.tsx`)
adds the `wdems-with-bg` class to `<body>`, which re-applies the
image layer for that page only. The login page also has its own
`login-background.png` inside the brand panel, independent of the
shell.

### §6 (text on the image) is now login-page-only

The `.text-on-image` and `.text-on-image-muted` utilities remain
defined in `app.css`. As of 2026-10-17 they have **zero usages**
anywhere in `resources/js/`. They are kept for the login page and
any future hero section that reintroduces a photographic background.

Regular pages use `text-foreground` (headings, back links) and
`text-muted-foreground` (subtitles, section labels) — the same
semantic tokens they use inside glass surfaces.

### §9.1's Rule 2 is retired

The audit script previously enforced Rule 2: page heroes on the
app layout must use `.text-on-image`. That rule's premise — heroes
sit on the shell image — is false. Rule 2 was removed from
`scripts/audit-design.sh` on 2026-10-17.

Rule numbers are not renumbered. The header comment in the audit
script explains the gap. Rules 1, 3, 4, 5, and 6 are unchanged.

### §11 is unaffected

The interactive-element surface rules (chips, anchor buttons, form
primitives, outline Button variant, status badges, lime glyphs,
empty-state circles) were already expressed via semantic tokens.
They continue to apply unchanged.
