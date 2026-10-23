# Church Event Backfill

This command copies legacy Services, Fellowship, and Discipleship records
into the standard Church Event workflow. It does not delete or update
legacy attendance rows. Run the dry run, inspect its JSON report, and
resolve every conflict before allowing a write run.

## Prerequisites

Apply the additive migrations in a controlled deployment before running
the command:

- Add `events.discipleship_batch_id`.
- Add `discipleship_sessions.registration_event_id`.
- Create `discipleship_event_registration_links`.

The backfill command itself never runs migrations. Do not run it against a
database before reviewing the dry-run report and taking the normal database
backup.

## Commands

```powershell
php artisan church:backfill-events --dry-run
php artisan church:backfill-events --dry-run --module=services --report=storage\app\church-backfill-services.json
php artisan church:backfill-events --module=fellowship --limit=100 --after-id=0
php artisan church:backfill-events --module=discipleship
```

`--module` accepts `all`, `services`, `fellowship`, or `discipleship`.
`--limit` bounds the number of source records considered across the selected
module(s). `--after-id` applies an exclusive source ID lower bound to each
selected module. Since the IDs belong to different tables, use module-scoped
runs when resuming by ID. `--report` writes the same JSON report printed to
the console; its parent directory must already exist.

Dry run performs read-only queries and writes no application records.
Execution commits each source occurrence, gathering, or session in its own
transaction. A conflict rolls back that source unit, is included in the
report, and makes the command return a non-zero exit status. Successful
source units remain linked and can be skipped on a rerun.

## Mapping

### Services

Each `ServiceOccurrence` becomes one Church `Event` in category `Services`.
The Service type label becomes the Event name; date/time and creator map
directly. Leader, topic, and notes are retained in Event description. Each
member and first-timer row becomes a confirmed manual Registration of type
`Member` or `First-Timer`, respectively, with its participant, registration
time, encoder, and generated attendance code. Marked attendance retains
recorder, time, and notes.

### Fellowship

Each `FellowshipEvent` becomes one Church `Event` in category `Fellowship`.
Name, date/time, venue, creator, and description are retained; facilitator
and notes are appended to Event description. Each legacy attendance row
becomes a confirmed manual `Guest` Registration. Its group assignment maps
to `registrations.fellowship_group_id`. Marked attendance retains recorder,
time, and notes.

### Discipleship

Each `DiscipleshipSession` becomes one Church `Event` in category
`Discipleship`, linked to its retained batch through
`events.discipleship_batch_id`. Session topic becomes Event name, date maps
to Event date, notes map to description, and session creator is retained.

For every enrollment assigned to that session's batch and program, the
backfill creates one confirmed manual `Guest` Registration for the session.
This is intentionally one registration per enrollment/session pair, not one
registration per program enrollment for all time. Each pair is recorded in
`discipleship_event_registration_links`, linking the retained enrollment and
session to the generic Registration. Existing Discipleship attendance maps
to generic Attendance when representable.

Program and batch enrollment state is not copied into per-session
registration status: current status has no historical effective-date
timeline. Enrollment records and their status remain authoritative. An
enrollment whose `enrolled_at` postdates a session is included under the
approved batch/session mapping and called out in the report; this should be
reviewed as a historical-roster limitation.

## Attendance status policy

| Legacy status                                        | Generic Church workflow                                        | Preservation                                                                                               |
| ---------------------------------------------------- | -------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `present`                                            | Generic Attendance `present`                                   | Legacy row remains unchanged.                                                                              |
| `late`                                               | Generic Attendance `present`                                   | Original `late` remains in the legacy row and the report counts `late->present`.                           |
| `absent`                                             | Generic Attendance `absent`                                    | Legacy row remains unchanged.                                                                              |
| `excused`                                            | No generic Attendance row; therefore Unmarked in the Church UI | Exact legacy value, recorder, time, and notes remain in the legacy row; report counts `excused->unmarked`. |
| `unmarked`, null, or no Discipleship attendance cell | No generic Attendance row; Unmarked                            | The source row (if any) remains unchanged.                                                                 |

Unknown status values are conflicts, not silently converted. The generic
Church summary cannot distinguish Excused from Unmarked. The preserved
legacy rows remain the source of truth for that distinction.

## Safety and idempotence

- Existing Service and Fellowship `registration_event_id` links are reused
  as the completion marker. Discipleship sessions use the matching new link.
- Each source unit is locked and committed transactionally.
- The Discipleship link table has unique constraints for enrollment/session
  and registration, preventing duplicate mappings.
- Participants must exist in the Church pool and must not be provisional.
  Duplicate participant rows inside a source event are reported as conflicts;
  no participants are auto-merged.
- Legacy rows are retained. The new foreign keys use delete restrictions
  where removal could sever the source-to-Event or source-to-registration
  audit trail. Generic deletion workflows must account for those restrictions.
- Re-running after successful units skips linked sources. Resolve and review
  each reported conflict before rerunning the relevant module.

## Reviewing the report

The JSON report includes totals, per-source planned/migrated/already-linked
items, normalized attendance-status counts, warnings, and conflicts. Compare
the source and target counts by module before cutover. In particular, review
Excused-to-Unmarked conversions, enrollment/session date warnings, and any
source units with conflicts. A successful exit means no conflicts were
reported; it is not a substitute for reconciliation or product acceptance.

## New schema

- `events.discipleship_batch_id`: nullable, restricted foreign key to
  `discipleship_batches`.
- `discipleship_sessions.registration_event_id`: nullable unique,
  restricted foreign key to `events`.
- `discipleship_event_registration_links`: restricted foreign keys to
  enrollment, session, and Registration; unique enrollment/session and
  unique Registration mappings.

No legacy table is dropped or modified by this backfill.
