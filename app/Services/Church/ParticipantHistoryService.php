<?php

namespace App\Services\Church;

use App\Models\Participant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Builds the church-only, attended-event history for one church-pool person. */
class ParticipantHistoryService
{
    /** @return array<int, array{id:int, full_name:string, attendance_count:int, event_types:array<int, string>, history_url:string}> */
    public function topAttendees(int $limit = 5): array
    {
        $uniqueAttendance = $this->attendedSourceRows();

        $ranked = DB::query()->fromSub($uniqueAttendance, 'church_attendance')
            ->join('participants', 'participants.id', '=', 'church_attendance.participant_id')
            ->where('participants.pool', 'church')
            ->whereNull('participants.provisional_event_id')
            ->select('participants.id', 'participants.first_name_normalized', 'participants.last_name_normalized')
            ->selectRaw('COUNT(*) as attendance_count')
            ->groupBy('participants.id', 'participants.first_name_normalized', 'participants.last_name_normalized')
            ->orderByDesc('attendance_count')
            ->orderBy('participants.first_name_normalized')
            ->orderBy('participants.last_name_normalized')
            ->orderBy('participants.id')
            ->limit(max(0, $limit))
            ->get();

        $participants = Participant::query()->where('pool', 'church')->whereNull('provisional_event_id')
            ->whereIn('id', $ranked->pluck('id'))->get()->keyBy('id');

        $topAttendees = [];
        foreach ($ranked as $row) {
            $participant = $participants->get((int) $row->id);
            if (! $participant instanceof Participant) {
                continue;
            }
            $topAttendees[] = [
                'id' => $participant->id,
                'full_name' => $participant->fullName(),
                'attendance_count' => (int) $row->attendance_count,
                'event_types' => $this->presentEventTypes($participant),
                'history_url' => route('church.participants.history', $participant),
            ];
        }

        return $topAttendees;
    }

    /** @return array<int, string> */
    private function presentEventTypes(Participant $participant): array
    {
        $types = DB::table('registrations as registrations')
            ->join('events', 'events.id', '=', 'registrations.event_id')
            ->join('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->where('registrations.participant_id', $participant->id)
            ->where('registrations.registration_status', 'confirmed')
            ->where('events.event_kind', 'church')
            ->where('attendances.attendance_status', 'present')
            ->pluck('events.church_category')->filter()->all();

        $servicePresent = DB::table('service_attendance_members')
            ->where('participant_id', $participant->id)->where('attendance_status', 'present')->exists()
            || DB::table('service_attendance_first_timers')
                ->where('participant_id', $participant->id)->where('attendance_status', 'present')->exists();
        if ($servicePresent) {
            $types[] = 'Services';
        }
        if (DB::table('fellowship_attendances')->where('participant_id', $participant->id)->where('attendance_status', 'present')->exists()) {
            $types[] = 'Fellowship';
        }
        if (DB::table('discipleship_attendances as attendance')
            ->join('discipleship_enrollments as enrollments', 'enrollments.id', '=', 'attendance.enrollment_id')
            ->where('enrollments.participant_id', $participant->id)->where('attendance.status', 'present')->exists()) {
            $types[] = 'Discipleship';
        }

        return array_values(array_unique(array_map('strval', $types)));
    }

    /** @return array{participant: array{id:int, full_name:string}, attendance_count:int, event_types:array<int, string>, events:array<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string}>} */
    public function build(Participant $participant): array
    {
        abort_unless($participant->pool === 'church' && $participant->provisional_event_id === null, 404);

        $events = collect($this->attendedHistoryRows($participant))
            ->unique('history_key')
            ->sortByDesc(fn (array $row): string => ($row['event_date'] ?? '').' '.($row['attended_at'] ?? ''))
            ->values();

        $historyEvents = $events
            ->map(fn (array $row): array => $this->publicHistoryRow($row))
            ->values()
            ->all();

        return [
            'participant' => [
                'id' => $participant->id,
                'full_name' => $participant->fullName(),
            ],
            'attendance_count' => count($historyEvents),
            'event_types' => $this->eventTypes($historyEvents),
            'events' => $historyEvents,
        ];
    }

    /** @param array<string, mixed> $row
     * @return array{event_name:string, event_type:string, event_date:?string, attended_at:?string}
     */
    private function publicHistoryRow(array $row): array
    {
        $eventDate = $row['event_date'] ?? null;
        $attendedAt = $row['attended_at'] ?? null;

        return [
            'event_name' => is_string($row['event_name'] ?? null) ? $row['event_name'] : '',
            'event_type' => is_string($row['event_type'] ?? null) ? $row['event_type'] : 'Church Event',
            'event_date' => is_string($eventDate) ? $eventDate : null,
            'attended_at' => is_string($attendedAt) ? $attendedAt : null,
        ];
    }

    /** @param array<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string}> $events
     * @return array<int, string>
     */
    private function eventTypes(array $events): array
    {
        $types = [];
        foreach ($events as $event) {
            $types[$event['event_type']] = true;
        }

        return array_keys($types);
    }

    /** @return array<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string, history_key:string}> */
    private function attendedHistoryRows(Participant $participant): array
    {
        return array_merge(
            $this->eventRegistrations($participant)->all(),
            $this->legacyServiceAttendance($participant)->all(),
            $this->legacyFellowshipAttendance($participant)->all(),
            $this->discipleshipAttendance($participant)->all(),
        );
    }

    private function attendedSourceRows(): Builder
    {
        $events = DB::table('registrations as registrations')
            ->join('events', 'events.id', '=', 'registrations.event_id')
            ->join('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->where('registrations.registration_status', 'confirmed')
            ->where('events.event_kind', 'church')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('participants')
                    ->whereColumn('participants.id', 'registrations.participant_id')
                    ->where('participants.pool', 'church')
                    ->whereNull('participants.provisional_event_id');
            })
            ->where('attendances.attendance_status', 'present')
            ->selectRaw("registrations.participant_id, 'event' as source_type, events.id as source_id");

        $serviceMembers = DB::table('service_attendance_members as attendance')
            ->join('service_occurrences as occurrences', 'occurrences.id', '=', 'attendance.service_occurrence_id')
            ->whereNull('occurrences.registration_event_id')
            ->where('attendance.attendance_status', 'present')
            ->selectRaw("attendance.participant_id, 'service' as source_type, occurrences.id as source_id");

        $serviceFirstTimers = DB::table('service_attendance_first_timers as attendance')
            ->join('service_occurrences as occurrences', 'occurrences.id', '=', 'attendance.service_occurrence_id')
            ->whereNull('occurrences.registration_event_id')
            ->where('attendance.attendance_status', 'present')
            ->selectRaw("attendance.participant_id, 'service' as source_type, occurrences.id as source_id");

        $fellowship = DB::table('fellowship_attendances as attendance')
            ->join('fellowship_events as gatherings', 'gatherings.id', '=', 'attendance.fellowship_event_id')
            ->whereNull('gatherings.registration_event_id')
            ->where('attendance.attendance_status', 'present')
            ->selectRaw("attendance.participant_id, 'fellowship' as source_type, gatherings.id as source_id");

        $discipleship = DB::table('discipleship_attendances as attendance')
            ->join('discipleship_enrollments as enrollments', 'enrollments.id', '=', 'attendance.enrollment_id')
            ->where('attendance.status', 'present')
            ->selectRaw("enrollments.participant_id, 'discipleship' as source_type, attendance.session_id as source_id");

        $attendedRows = $events
            ->unionAll($serviceMembers)
            ->unionAll($serviceFirstTimers)
            ->unionAll($fellowship)
            ->unionAll($discipleship);

        return DB::query()->fromSub($attendedRows, 'attendance_sources')
            ->select('participant_id', 'source_type', 'source_id')
            ->groupBy('participant_id', 'source_type', 'source_id');
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string, history_key:string}> */
    private function eventRegistrations(Participant $participant): Collection
    {
        return DB::table('registrations as registrations')
            ->join('events', 'events.id', '=', 'registrations.event_id')
            ->join('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->where('registrations.participant_id', $participant->id)
            ->where('registrations.registration_status', 'confirmed')
            ->where('events.event_kind', 'church')
            ->where('attendances.attendance_status', 'present')
            ->get([
                'events.event_name',
                'events.church_category as event_type',
                'events.event_date',
                'attendances.attendance_time as attended_at',
                'events.id as source_id',
            ])
            ->map(fn (object $row): array => [
                'event_name' => (string) $row->event_name,
                'event_type' => (string) ($row->event_type ?: 'Church Event'),
                'event_date' => $row->event_date === null ? null : (string) $row->event_date,
                'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
                'history_key' => 'event:'.$row->source_id,
            ]);
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string, history_key:string}> */
    private function legacyServiceAttendance(Participant $participant): Collection
    {
        $members = DB::table('service_attendance_members as attendance')
            ->join('service_occurrences as occurrences', 'occurrences.id', '=', 'attendance.service_occurrence_id')
            ->where('attendance.participant_id', $participant->id)
            ->whereNull('occurrences.registration_event_id')
            ->whereIn('attendance.attendance_status', ['present', 'late'])
            ->get([
                'occurrences.service_type',
                'occurrences.occurrence_date as event_date',
                'attendance.recorded_at as attended_at',
                'occurrences.id as source_id',
            ]);

        $firstTimers = DB::table('service_attendance_first_timers as attendance')
            ->join('service_occurrences as occurrences', 'occurrences.id', '=', 'attendance.service_occurrence_id')
            ->where('attendance.participant_id', $participant->id)
            ->whereNull('occurrences.registration_event_id')
            ->whereIn('attendance.attendance_status', ['present', 'late'])
            ->get([
                'occurrences.service_type',
                'occurrences.occurrence_date as event_date',
                'attendance.recorded_at as attended_at',
                'occurrences.id as source_id',
            ]);

        return $members->concat($firstTimers)->map(fn (object $row): array => [
            'event_name' => $this->serviceLabel((string) $row->service_type),
            'event_type' => 'Services',
            'event_date' => $row->event_date === null ? null : (string) $row->event_date,
            'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
            'history_key' => 'service:'.$row->source_id,
        ]);
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string, history_key:string}> */
    private function legacyFellowshipAttendance(Participant $participant): Collection
    {
        return DB::table('fellowship_attendances as attendance')
            ->join('fellowship_events as gatherings', 'gatherings.id', '=', 'attendance.fellowship_event_id')
            ->where('attendance.participant_id', $participant->id)
            ->whereNull('gatherings.registration_event_id')
            ->whereIn('attendance.attendance_status', ['present', 'late'])
            ->get([
                'gatherings.name as event_name',
                'gatherings.event_date',
                'attendance.recorded_at as attended_at',
                'gatherings.id as source_id',
            ])
            ->map(fn (object $row): array => [
                'event_name' => (string) $row->event_name,
                'event_type' => 'Fellowship',
                'event_date' => $row->event_date === null ? null : (string) $row->event_date,
                'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
                'history_key' => 'fellowship:'.$row->source_id,
            ]);
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string, history_key:string}> */
    private function discipleshipAttendance(Participant $participant): Collection
    {
        return DB::table('discipleship_attendances as attendance')
            ->join('discipleship_enrollments as enrollments', 'enrollments.id', '=', 'attendance.enrollment_id')
            ->join('discipleship_sessions as sessions', 'sessions.id', '=', 'attendance.session_id')
            ->join('discipleship_batches as batches', 'batches.id', '=', 'sessions.batch_id')
            ->join('discipleship_programs as programs', 'programs.id', '=', 'batches.program_id')
            ->where('enrollments.participant_id', $participant->id)
            ->where('attendance.status', 'present')
            ->get([
                'programs.name as program_name',
                'batches.name as batch_name',
                'sessions.session_date as event_date',
                'sessions.topic',
                'attendance.recorded_at as attended_at',
                'sessions.id as source_id',
            ])
            ->map(fn (object $row): array => [
                'event_name' => trim((string) $row->program_name.' — '.(string) $row->batch_name.($row->topic ? ' — '.$row->topic : '')),
                'event_type' => 'Discipleship',
                'event_date' => $row->event_date === null ? null : (string) $row->event_date,
                'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
                'history_key' => 'discipleship:'.$row->source_id,
            ]);
    }

    private function serviceLabel(string $serviceType): string
    {
        return match ($serviceType) {
            'morning' => 'Morning Service',
            'midweek' => 'Midweek Service',
            'youth' => 'Youth Service',
            default => 'Other Service',
        };
    }
}
