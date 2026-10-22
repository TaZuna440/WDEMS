<?php

namespace App\Services\Church;

use App\Models\Participant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Builds the church-only, attended-event history for one church-pool person. */
class ParticipantHistoryService
{
    /** @return array{participant: array{id:int, full_name:string}, attendance_count:int, event_types:array<int, string>, events:array<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string}>} */
    public function build(Participant $participant): array
    {
        abort_unless($participant->pool === 'church' && $participant->provisional_event_id === null, 404);

        $events = collect()
            ->concat($this->eventRegistrations($participant))
            ->concat($this->legacyServiceAttendance($participant))
            ->concat($this->legacyFellowshipAttendance($participant))
            ->concat($this->discipleshipAttendance($participant))
            ->sortByDesc(fn (array $row): string => ($row['event_date'] ?? '').' '.($row['attended_at'] ?? ''))
            ->values();

        return [
            'participant' => [
                'id' => $participant->id,
                'full_name' => $participant->fullName(),
            ],
            'attendance_count' => $events->count(),
            'event_types' => $events->pluck('event_type')->unique()->values()->all(),
            'events' => $events->all(),
        ];
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string}> */
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
            ])
            ->map(fn (object $row): array => [
                'event_name' => (string) $row->event_name,
                'event_type' => (string) ($row->event_type ?: 'Church Event'),
                'event_date' => $row->event_date === null ? null : (string) $row->event_date,
                'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
            ]);
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string}> */
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
            ]);

        return $members->concat($firstTimers)->map(fn (object $row): array => [
            'event_name' => $this->serviceLabel((string) $row->service_type),
            'event_type' => 'Services',
            'event_date' => $row->event_date === null ? null : (string) $row->event_date,
            'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
        ]);
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string}> */
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
            ])
            ->map(fn (object $row): array => [
                'event_name' => (string) $row->event_name,
                'event_type' => 'Fellowship',
                'event_date' => $row->event_date === null ? null : (string) $row->event_date,
                'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
            ]);
    }

    /** @return Collection<int, array{event_name:string, event_type:string, event_date:?string, attended_at:?string}> */
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
            ])
            ->map(fn (object $row): array => [
                'event_name' => trim((string) $row->program_name.' — '.(string) $row->batch_name.($row->topic ? ' — '.$row->topic : '')),
                'event_type' => 'Discipleship',
                'event_date' => $row->event_date === null ? null : (string) $row->event_date,
                'attended_at' => $row->attended_at === null ? null : (string) $row->attended_at,
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
