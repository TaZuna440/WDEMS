<?php

namespace App\Services\Reporting;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the three payloads for the end-of-event report page.
 *
 * Feature C of docs/attendance-encoding-and-reporting.md.
 *
 * Three concerns:
 *   1. Summary -- did we match registration?
 *   2. Cross-event -- how does this event compare to others of the
 *      same type?
 *   3. Participants -- who showed up, and how often have they shown
 *      up before (same type)?
 *
 * All aggregates are scoped by the current event's event_type. A
 * participant's community-run history is separate from their fun-run
 * history by design -- see the spec's section 6.6.
 *
 * Uses the query builder for aggregate queries. Eloquent's hydration
 * cost is unnecessary and the raw SQL is easier to reason about when
 * counting with conditional filters.
 */
class ReportService
{
    /**
     * Build the full report payload for the given event.
     *
     * @return array<string, mixed>
     */
    public function build(Event $event): array
    {
        return [
            'event' => $this->eventPayload($event),
            'summary' => $this->summary($event),
            'cross_event' => $this->crossEvent($event),
            'participants' => $this->participants($event),
        ];
    }

    /**
     * Build the CSV body for the participants table.
     *
     * Flattens each participant's prior-appearance history into
     * additional columns: Prior Event 1, Prior Event 1 Date, Prior
     * Event 1 Status, Prior Event 2, etc. The column count is driven
     * by the participant with the deepest history in this event.
     * Participants with fewer prior appearances leave the unused
     * columns blank.
     *
     * Includes a UTF-8 BOM so Excel opens the file with the right
     * encoding -- same convention as
     * RegistrationMonitorController::exportCsv().
     */
    public function toCsv(Event $event): string
    {
        $participants = $this->participants($event);

        $maxHistory = 0;
        foreach ($participants as $p) {
            $maxHistory = max($maxHistory, count($p['history']));
        }

        $handle = fopen('php://temp', 'r+');

        $header = [
            'Name',
            'Contact',
            'Source',
            'Attendance',
            'Total appearances',
            'Last appearance',
        ];

        for ($i = 1; $i <= $maxHistory; $i++) {
            $header[] = "Prior Event {$i}";
            $header[] = "Prior Event {$i} Date";
            $header[] = "Prior Event {$i} Status";
        }

        fputcsv($handle, $header);

        foreach ($participants as $p) {
            $row = [
                $p['full_name'],
                $p['contact'] ?? '',
                $p['source'],
                $p['attendance_status'] ?? '',
                $p['total_appearances'],
                $p['last_appearance'] ?? '',
            ];

            for ($i = 0; $i < $maxHistory; $i++) {
                $entry = $p['history'][$i] ?? null;

                if ($entry === null) {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';

                    continue;
                }

                $row[] = $entry['event_name'];
                $row[] = $entry['event_date'];
                $row[] = $entry['status'];
            }

            fputcsv($handle, $row);
        }

        rewind($handle);
        $body = stream_get_contents($handle);
        fclose($handle);

        // UTF-8 BOM so Excel parses accented characters correctly.
        return "\xEF\xBB\xBF".$body;
    }

    /**
     * Event header data for the report page.
     *
     * @return array<string, mixed>
     */
    private function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_type' => $event->event_type->value,
            'event_type_label' => $event->event_type->label(),
            'event_date' => $event->event_date?->toDateString(),
            'venue' => $event->venue,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
        ];
    }

    /**
     * Section 1 -- this event's summary.
     *
     * "Registered" and "Attended" are form-scoped. "Walk-ins" is
     * scoped to walk_in and paper. "Total physical attendance" is
     * every Present/Late attendance row for this event regardless of
     * source -- safer than attended_form + walk_ins because a
     * walk-in created without mark_present has no attendance row.
     *
     * @return array<string, mixed>
     */
    private function summary(Event $event): array
    {
        // One conditional-aggregation query for the six top-line
        // counts. The leftJoin to attendances produces at most one
        // row per registration (unique on registration_id), so the
        // SUM(CASE WHEN) expressions count per registration.
        $counts = $event->registrations()
            ->leftJoin('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->selectRaw("
                SUM(CASE WHEN registrations.source = 'form' THEN 1 ELSE 0 END) as registered_form,
                SUM(CASE WHEN registrations.source IN ('walk_in', 'paper') THEN 1 ELSE 0 END) as walk_ins_encoded,
                SUM(CASE WHEN registrations.source = 'form' AND attendances.attendance_status IN ('present', 'late') AND attendances.verified_at IS NOT NULL THEN 1 ELSE 0 END) as attended_verified,
                SUM(CASE WHEN registrations.source = 'form' AND attendances.attendance_status IN ('present', 'late') AND attendances.verified_at IS NULL THEN 1 ELSE 0 END) as attended_override,
                SUM(CASE WHEN attendances.attendance_status IN ('present', 'late') THEN 1 ELSE 0 END) as total_attendance
            ")
            ->first();

        $registeredForm = (int) $counts->registered_form;
        $walkInsEncoded = (int) $counts->walk_ins_encoded;
        $attendedVerified = (int) $counts->attended_verified;
        $attendedOverride = (int) $counts->attended_override;
        $attendedForm = $attendedVerified + $attendedOverride;
        $totalAttendance = (int) $counts->total_attendance;

        // Status breakdown -- form-scoped. Sums to registeredForm.
        // COALESCE folds no-attendance rows into an '_unmarked' key
        // so they appear in the same map as the named statuses.
        $statusCounts = $event->registrations()
            ->where('registrations.source', 'form')
            ->leftJoin('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->selectRaw("COALESCE(attendances.attendance_status, '_unmarked') as status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statusBreakdown = [
            'present' => (int) ($statusCounts['present'] ?? 0),
            'late' => (int) ($statusCounts['late'] ?? 0),
            'excused' => (int) ($statusCounts['excused'] ?? 0),
            'absent' => (int) ($statusCounts['absent'] ?? 0),
            'unmarked' => (int) ($statusCounts['_unmarked'] ?? 0),
        ];

        return [
            'registered_form' => $registeredForm,
            'attended_verified' => $attendedVerified,
            'attended_override' => $attendedOverride,
            'attended_form_total' => $attendedForm,
            'walk_ins_encoded' => $walkInsEncoded,
            'total_attendance' => $totalAttendance,
            'show_rate' => $registeredForm > 0
                ? round(($attendedForm / $registeredForm) * 100, 1)
                : null,
            'verification_rate' => $attendedForm > 0
                ? round(($attendedVerified / $attendedForm) * 100, 1)
                : null,
            'no_shows' => $registeredForm - $attendedForm,
            'status_breakdown' => $statusBreakdown,
        ];
    }

    /**
     * Section 2 -- cross-event comparison.
     *
     * Every event of the same event_type and in (ongoing, completed)
     * status, all time. Aggregates per event, sorted date descending.
     * Draft, registration_open, registration_closed, and cancelled
     * events are excluded -- they have no meaningful attendance data.
     *
     * @return array<string, mixed>
     */
    private function crossEvent(Event $event): array
    {
        $type = $event->event_type->value;
        $typeLabel = $event->event_type->label();

        $rows = DB::table('events')
            ->leftJoin('registrations', 'registrations.event_id', '=', 'events.id')
            ->leftJoin('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->where('events.event_type', $type)
            ->whereIn('events.status', [
                EventStatus::Ongoing->value,
                EventStatus::Completed->value,
            ])
            ->selectRaw('events.id')
            ->selectRaw('events.event_name')
            ->selectRaw('DATE(events.event_date) as event_date')
            ->selectRaw("SUM(CASE WHEN registrations.source = 'form' THEN 1 ELSE 0 END) as registered")
            ->selectRaw("SUM(CASE WHEN registrations.source = 'form' AND attendances.attendance_status IN ('present', 'late') THEN 1 ELSE 0 END) as attended")
            ->groupBy('events.id', 'events.event_name', 'events.event_date')
            ->orderByDesc('events.event_date')
            ->orderByDesc('events.id')
            ->get();

        $mapped = $rows->map(function ($row) use ($event) {
            $registered = (int) $row->registered;
            $attended = (int) $row->attended;

            return [
                'id' => (int) $row->id,
                'event_name' => $row->event_name,
                'event_date' => $row->event_date,
                'registered' => $registered,
                'attended' => $attended,
                'show_rate' => $registered > 0
                    ? round(($attended / $registered) * 100, 1)
                    : null,
                'is_current' => (int) $row->id === $event->id,
            ];
        });

        // Summary stats computed in PHP -- the row count is small.
        $withRates = $mapped->filter(fn ($r) => $r['show_rate'] !== null);
        $averageRate = $withRates->isNotEmpty()
            ? round($withRates->avg('show_rate'), 1)
            : null;

        $best = $withRates->sortByDesc('show_rate')->first();
        $worst = $withRates->sortBy('show_rate')->first();

        // Attach delta against the average to every row that has a
        // rate. Null when either operand is null.
        $mappedWithDelta = $mapped->map(function ($r) use ($averageRate) {
            $r['delta'] = $r['show_rate'] !== null && $averageRate !== null
                ? round($r['show_rate'] - $averageRate, 1)
                : null;

            return $r;
        });

        return [
            'type_label' => $typeLabel,
            'total_events' => $mapped->count(),
            'average_show_rate' => $averageRate,
            'best' => $best ? [
                'event_name' => $best['event_name'],
                'show_rate' => $best['show_rate'],
            ] : null,
            'worst' => $worst ? [
                'event_name' => $worst['event_name'],
                'show_rate' => $worst['show_rate'],
            ] : null,
            'rows' => $mappedWithDelta->values()->toArray(),
        ];
    }

    /**
     * Section 3 -- participants in this event.
     *
     * One row per registration for this event, any source. Each row
     * carries the participant's total appearances in same-type
     * events, their last prior same-type appearance, and their
     * same-type attendance history.
     *
     * "Total appearances" includes the current event (if present or
     * late). "Last appearance" and "history" exclude it -- they
     * describe prior appearances only.
     *
     * @return array<int, array<string, mixed>>
     */
    private function participants(Event $event): array
    {
        $type = $event->event_type->value;

        // Base rows: every registration for this event.
        $rows = DB::table('registrations')
            ->join('participants', 'participants.id', '=', 'registrations.participant_id')
            ->leftJoin('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->where('registrations.event_id', $event->id)
            ->orderBy('participants.last_name')
            ->orderBy('participants.first_name')
            ->select([
                'registrations.id as registration_id',
                'registrations.source',
                'participants.id as participant_id',
                'participants.first_name',
                'participants.last_name',
                'participants.email',
                'participants.contact_number',
                'attendances.attendance_status',
            ])
            ->get();

        $participantIds = $rows->pluck('participant_id')->unique()->values()->all();

        // Same-type attendance history for every participant in this
        // event. One query, grouped in PHP. Ordered date descending
        // so the first row per participant is the most recent.
        $historyRows = DB::table('registrations')
            ->join('events', 'events.id', '=', 'registrations.event_id')
            ->join('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->whereIn('registrations.participant_id', $participantIds)
            ->where('events.event_type', $type)
            ->whereIn('events.status', [
                EventStatus::Ongoing->value,
                EventStatus::Completed->value,
            ])
            ->whereIn('attendances.attendance_status', ['present', 'late'])
            ->orderBy('registrations.participant_id')
            ->orderByDesc('events.event_date')
            ->orderByDesc('events.id')
            ->select([
                'registrations.participant_id',
                'events.id as event_id',
                'events.event_name',
                DB::raw('DATE(events.event_date) as event_date'),
                'attendances.attendance_status',
            ])
            ->get()
            ->groupBy('participant_id');

        return $rows->map(function ($row) use ($historyRows, $event) {
            $history = $historyRows->get($row->participant_id) ?? collect();

            $totalAppearances = $history->count();

            // Most recent prior -- first row in the desc-ordered
            // history that is not the current event.
            $prior = $history->first(
                fn ($h) => (int) $h->event_id !== $event->id
            );
            $lastAppearance = $prior?->event_date;

            // Every prior event the participant attended in this
            // type, current excluded.
            $priorHistory = $history
                ->filter(fn ($h) => (int) $h->event_id !== $event->id)
                ->map(fn ($h) => [
                    'event_name' => $h->event_name,
                    'event_date' => $h->event_date,
                    'status' => $h->attendance_status,
                ])
                ->values()
                ->toArray();

            return [
                'registration_id' => (int) $row->registration_id,
                'participant_id' => (int) $row->participant_id,
                'full_name' => trim("{$row->first_name} {$row->last_name}"),
                'contact' => $row->email ?? $row->contact_number,
                'source' => $row->source,
                'attendance_status' => $row->attendance_status,
                'total_appearances' => $totalAppearances,
                'last_appearance' => $lastAppearance,
                'history' => $priorHistory,
            ];
        })->values()->toArray();
    }
}
