# Event Creation Wizard

**Last updated:** September 21, 2026
**Scope:** Event schema extension + multi-step creation wizard
**Status:** Complete

---

## Overview

Event creation moved from a single-page form to a **4-step wizard** with:

- Persistent progress across browser refreshes
- Structured venue coordinates (map picker)
- Required distance with KM/Miles conversion
- Partner management (hosts, sponsors, food, etc.)
- 9 accessibility flags
- Stronger validation on event names

The show, edit, and list pages were extended to display and edit the new fields.

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

### 2.3 New helper method

```php
public function distanceLabel(): ?string
