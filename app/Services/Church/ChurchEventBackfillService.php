<?php

namespace App\Services\Church;

use App\Enums\ServiceType;
use App\Models\DiscipleshipAttendance;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipSession;
use App\Models\Event;
use App\Models\FellowshipAttendance;
use App\Models\FellowshipEvent;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ServiceAttendanceFirstTimer;
use App\Models\ServiceAttendanceMember;
use App\Models\ServiceOccurrence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Exception\InvalidArgumentException;

class ChurchEventBackfillService
{
    private const MODULES = ['services', 'fellowship', 'discipleship'];

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $module = 'all',
        bool $dryRun = false,
        ?int $limit = null,
        ?int $afterId = null,
    ): array {
        if ($module !== 'all' && ! in_array($module, self::MODULES, true)) {
            throw new InvalidArgumentException('Module must be all, services, fellowship, or discipleship.');
        }

        $report = [
            'dry_run' => $dryRun,
            'totals' => [
                'considered' => 0,
                'planned_events' => 0,
                'planned_registrations' => 0,
                'planned_attendances' => 0,
                'migrated' => 0,
                'already_linked' => 0,
                'skipped' => 0,
                'conflicts' => 0,
            ],
            'items' => [],
            'conflicts' => [],
            'status_conversions' => [],
            'warnings' => [],
        ];

        $modules = $module === 'all' ? self::MODULES : [$module];
        $remaining = $limit;
        foreach ($modules as $name) {
            if ($remaining === 0) {
                break;
            }
            $sources = $this->sources($name, $afterId, $remaining);
            foreach ($sources as $source) {
                $remaining = $remaining === null ? null : $remaining - 1;
                $report['totals']['considered']++;
                $this->process($name, $source, $dryRun, $report);
            }
        }

        $report['totals']['conflicts'] = count($report['conflicts']);

        return $report;
    }

    /**
     * @return array<int, ServiceOccurrence|FellowshipEvent|DiscipleshipSession>
     */
    private function sources(string $module, ?int $afterId, ?int $limit): array
    {
        $query = match ($module) {
            'services' => ServiceOccurrence::query(),
            'fellowship' => FellowshipEvent::query(),
            'discipleship' => DiscipleshipSession::query(),
            default => throw new InvalidArgumentException('Unsupported church module.'),
        };

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderBy('id')->get()->all();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function process(
        string $module,
        ServiceOccurrence|FellowshipEvent|DiscipleshipSession $source,
        bool $dryRun,
        array &$report,
    ): void {
        $existingEventId = $source->getAttribute('registration_event_id');
        if ($existingEventId !== null) {
            $report['totals']['already_linked']++;
            $report['items'][] = [
                'module' => $module,
                'source_id' => $source->getKey(),
                'status' => 'already_linked',
                'event_id' => (int) $existingEventId,
            ];

            return;
        }

        if ($dryRun) {
            try {
                $plan = $this->plan($source);
                $this->validatePlan($plan);
            } catch (ValidationException|ModelNotFoundException|QueryException|\DomainException $exception) {
                $this->conflict($report, $module, $source, $exception->getMessage());

                return;
            }

            $plannedAttendances = $this->recordPlan($module, $source, $plan, $report);
            $report['totals']['skipped']++;
            $report['items'][] = [
                'module' => $module,
                'source_id' => $source->getKey(),
                'status' => 'planned',
                'registrations' => count($plan['registrations']),
                'attendances' => $plannedAttendances,
            ];

            return;
        }

        try {
            $result = DB::transaction(fn (): array => $this->persist($source));
        } catch (ValidationException|ModelNotFoundException|QueryException|\DomainException $exception) {
            $this->conflict($report, $module, $source, $exception->getMessage());

            return;
        }

        $plan = $result['plan'];
        $plannedAttendances = $this->recordPlan($module, $source, $plan, $report);
        $report['totals']['migrated']++;
        $report['items'][] = [
            'module' => $module,
            'source_id' => $source->getKey(),
            'status' => 'migrated',
            'event_id' => $result['event_id'],
            'registrations' => count($plan['registrations']),
            'attendances' => $plannedAttendances,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $plan
     */
    private function recordPlan(string $module, Model $source, array $plan, array &$report): int
    {
        $plannedAttendances = count(array_filter(
            $plan['registrations'],
            fn (array $registration) => $registration['attendance'] !== null,
        ));
        $report['totals']['planned_events']++;
        $report['totals']['planned_registrations'] += count($plan['registrations']);
        $report['totals']['planned_attendances'] += $plannedAttendances;

        foreach ($plan['status_conversions'] as $conversion => $count) {
            $report['status_conversions'][$conversion] = ($report['status_conversions'][$conversion] ?? 0) + $count;
        }
        foreach ($plan['warnings'] as $warning) {
            $report['warnings'][] = [
                'module' => $module,
                'source_id' => $source->getKey(),
                'message' => $warning,
            ];
        }

        return $plannedAttendances;
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(ServiceOccurrence|FellowshipEvent|DiscipleshipSession $source): array
    {
        if ($source instanceof ServiceOccurrence) {
            return $this->servicePlan($source);
        }
        if ($source instanceof FellowshipEvent) {
            return $this->fellowshipPlan($source);
        }

        return $this->discipleshipPlan($source);
    }

    /**
     * @return array{
     *   event: array<string, mixed>,
     *   registrations: array<int, array<string, mixed>>,
     *   status_conversions: array<string, int>,
     *   warnings: array<int, string>
     * }
     */
    private function servicePlan(ServiceOccurrence $source): array
    {
        $serviceType = ServiceType::from((string) $source->getRawOriginal('service_type'));
        $tracks = [
            [ServiceAttendanceMember::class, 'Member'],
            [ServiceAttendanceFirstTimer::class, 'First-Timer'],
        ];
        $registrations = [];
        $conversions = [];
        foreach ($tracks as [$model, $participantType]) {
            foreach ($model::query()->where('service_occurrence_id', $source->id)->orderBy('id')->get() as $row) {
                [$attendance, $conversion] = $this->attendanceData(
                    $row->getRawOriginal('attendance_status') === null
                        ? 'unmarked'
                        : (string) $row->getRawOriginal('attendance_status'),
                    $this->rawDateTime($row, 'recorded_at'),
                    $row->recorded_by,
                    $row->notes,
                );
                if ($conversion !== null) {
                    $conversions[$conversion] = ($conversions[$conversion] ?? 0) + 1;
                }
                $registrations[] = $this->registrationPlan(
                    $row->participant_id,
                    $participantType,
                    $this->rawDateTime($row, 'created_at'),
                    $row->recorded_by,
                    null,
                    $attendance,
                );
            }
        }

        return [
            'event' => [
                'created_by' => $source->created_by,
                'event_name' => $serviceType->label(),
                'event_date' => $source->getRawOriginal('occurrence_date'),
                'start_time' => $source->getRawOriginal('start_time'),
                'end_time' => $source->getRawOriginal('end_time'),
                'description' => collect([
                    $source->service_leader ? 'Leader: '.$source->service_leader : null,
                    $source->topic ? 'Topic: '.$source->topic : null,
                    $source->notes,
                ])->filter()->implode("\n") ?: null,
                'church_category' => 'Services',
            ],
            'registrations' => $registrations,
            'status_conversions' => $conversions,
            'warnings' => [],
        ];
    }

    /**
     * @return array{
     *   event: array<string, mixed>,
     *   registrations: array<int, array<string, mixed>>,
     *   status_conversions: array<string, int>,
     *   warnings: array<int, string>
     * }
     */
    private function fellowshipPlan(FellowshipEvent $source): array
    {
        $registrations = [];
        $conversions = [];
        foreach (FellowshipAttendance::query()->where('fellowship_event_id', $source->id)->orderBy('id')->get() as $row) {
            [$attendance, $conversion] = $this->attendanceData(
                $row->getRawOriginal('attendance_status') === null
                    ? 'unmarked'
                    : (string) $row->getRawOriginal('attendance_status'),
                $this->rawDateTime($row, 'recorded_at'),
                $row->recorded_by,
                $row->notes,
            );
            if ($conversion !== null) {
                $conversions[$conversion] = ($conversions[$conversion] ?? 0) + 1;
            }
            $registrations[] = $this->registrationPlan(
                $row->participant_id,
                'Guest',
                $this->rawDateTime($row, 'created_at'),
                $row->recorded_by,
                $row->fellowship_group_id,
                $attendance,
            );
        }

        return [
            'event' => [
                'created_by' => $source->created_by,
                'event_name' => $source->name,
                'event_date' => $source->getRawOriginal('event_date'),
                'start_time' => $source->getRawOriginal('start_time'),
                'end_time' => $source->getRawOriginal('end_time'),
                'venue' => $source->venue,
                'description' => collect([
                    $source->description,
                    $source->facilitator ? 'Facilitator: '.$source->facilitator : null,
                    $source->notes,
                ])->filter()->implode("\n\n") ?: null,
                'church_category' => 'Fellowship',
            ],
            'registrations' => $registrations,
            'status_conversions' => $conversions,
            'warnings' => [],
        ];
    }

    /**
     * @return array{
     *   event: array<string, mixed>,
     *   registrations: array<int, array<string, mixed>>,
     *   status_conversions: array<string, int>,
     *   warnings: array<int, string>
     * }
     */
    private function discipleshipPlan(DiscipleshipSession $source): array
    {
        $batch = DiscipleshipBatch::query()->findOrFail($source->batch_id);
        $enrollments = DiscipleshipEnrollment::query()
            ->where('batch_id', $batch->id)
            ->where('program_id', $batch->program_id)
            ->orderBy('id')
            ->get();
        $legacyAttendances = DiscipleshipAttendance::query()
            ->where('session_id', $source->id)
            ->get()
            ->keyBy('enrollment_id');
        $registrations = [];
        $conversions = [];
        $warnings = [];
        foreach ($enrollments as $enrollment) {
            $legacyAttendance = $legacyAttendances->get($enrollment->id);
            [$attendance, $conversion] = $legacyAttendance === null
                ? [null, null]
                : $this->attendanceData(
                    (string) $legacyAttendance->getRawOriginal('status'),
                    $this->rawDateTime($legacyAttendance, 'recorded_at'),
                    $legacyAttendance->recorded_by,
                    $legacyAttendance->notes,
                );
            if ($conversion !== null) {
                $conversions[$conversion] = ($conversions[$conversion] ?? 0) + 1;
            }
            $enrolledAt = $this->rawDateTime($enrollment, 'enrolled_at');
            $sessionDate = (string) $source->getRawOriginal('session_date');
            if ($enrolledAt !== null && substr($enrolledAt, 0, 10) > $sessionDate) {
                $warnings[] = "Enrollment {$enrollment->id} postdates this session; included because enrollment history has no effective-date timeline.";
            }
            $registrations[] = $this->registrationPlan(
                $enrollment->participant_id,
                'Guest',
                $enrolledAt ?? $this->rawDateTime($enrollment, 'created_at'),
                $enrollment->created_by,
                null,
                $attendance,
                [
                    'enrollment_id' => $enrollment->id,
                    'session_id' => $source->id,
                ],
            );
        }

        return [
            'event' => [
                'created_by' => $source->created_by,
                'event_name' => $source->topic,
                'event_date' => $source->getRawOriginal('session_date'),
                'description' => $source->notes,
                'church_category' => 'Discipleship',
                'discipleship_batch_id' => $batch->id,
            ],
            'registrations' => $registrations,
            'status_conversions' => $conversions,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array{attendance_status: string, attendance_time: string|null, recorded_by: int|null, notes: string|null}|null  $attendance
     * @param  array{enrollment_id: int, session_id: int}|null  $discipleshipLink
     * @return array<string, mixed>
     */
    private function registrationPlan(
        int $participantId,
        string $participantType,
        ?string $registeredAt,
        ?int $encodedBy,
        ?int $fellowshipGroupId,
        ?array $attendance,
        ?array $discipleshipLink = null,
    ): array {
        return [
            'participant_id' => $participantId,
            'participant_type' => $participantType,
            'registered_at' => $registeredAt,
            'encoded_by' => $encodedBy,
            'fellowship_group_id' => $fellowshipGroupId,
            'attendance' => $attendance,
            'discipleship_link' => $discipleshipLink,
        ];
    }

    /**
     * @return array{
     *   array{attendance_status: string, attendance_time: string|null, recorded_by: int|null, notes: string|null}|null,
     *   string|null
     * }
     */
    private function attendanceData(string $legacyStatus, ?string $recordedAt, ?int $recordedBy, ?string $notes): array
    {
        return match ($legacyStatus) {
            'present' => [[
                'attendance_status' => 'present',
                'attendance_time' => $recordedAt,
                'recorded_by' => $recordedBy,
                'notes' => $notes,
            ], null],
            'late' => [[
                'attendance_status' => 'present',
                'attendance_time' => $recordedAt,
                'recorded_by' => $recordedBy,
                'notes' => $notes,
            ], 'late->present'],
            'absent' => [[
                'attendance_status' => 'absent',
                'attendance_time' => $recordedAt,
                'recorded_by' => $recordedBy,
                'notes' => $notes,
            ], null],
            'excused' => [null, 'excused->unmarked'],
            'unmarked', '' => [null, 'unmarked->unmarked'],
            default => throw new \DomainException("Unsupported legacy attendance status [{$legacyStatus}]."),
        };
    }

    private function rawDateTime(Model $model, string $attribute): ?string
    {
        $value = $model->getRawOriginal($attribute);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function validatePlan(array &$plan, bool $lockParticipants = false): void
    {
        $participantIds = [];
        foreach ($plan['registrations'] as $registration) {
            $participantIds[] = $registration['participant_id'];
        }
        $participantIds = array_values(array_unique($participantIds));
        $participantsQuery = Participant::query()
            ->whereIn('id', $participantIds)
            ->where('pool', 'church')
            ->whereNull('provisional_event_id');
        if ($lockParticipants) {
            $participantsQuery->lockForUpdate();
        }
        $participants = $participantsQuery->get()->keyBy('id');
        $seen = [];
        foreach ($plan['registrations'] as &$registration) {
            $participant = $participants->get($registration['participant_id']);
            if ($participant === null) {
                throw ValidationException::withMessages([
                    'participant' => "Participant {$registration['participant_id']} is missing, provisional, or outside the church pool.",
                ]);
            }
            if (isset($seen[$participant->id])) {
                throw ValidationException::withMessages([
                    'participant' => "Participant {$participant->id} appears more than once in one source event.",
                ]);
            }
            $seen[$participant->id] = true;
            $registration['submitted_email'] = $participant->email;
            $registration['submitted_contact_number'] = $participant->contact_number;
        }
        unset($registration);
    }

    /**
     * @return array{event_id: int, plan: array<string, mixed>}
     */
    private function persist(ServiceOccurrence|FellowshipEvent|DiscipleshipSession $source): array
    {
        if ($source instanceof ServiceOccurrence) {
            $lockedSource = ServiceOccurrence::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
        } elseif ($source instanceof FellowshipEvent) {
            $lockedSource = FellowshipEvent::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
        } else {
            $lockedSource = DiscipleshipSession::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
        }
        if ($lockedSource->getAttribute('registration_event_id') !== null) {
            throw ValidationException::withMessages(['source' => 'This source record was linked by another backfill worker.']);
        }

        $plan = $this->plan($lockedSource);
        $this->validatePlan($plan, lockParticipants: true);
        $event = Event::forceCreate($plan['event'] + [
            'event_kind' => Event::KIND_CHURCH,
            'event_type' => 'church',
            'church_status' => 'upcoming',
            'registration_form_version' => 2,
            'registration_common_field_requirements' => [
                'email' => true,
                'contact_number' => false,
                'address' => true,
            ],
        ]);

        foreach ($plan['registrations'] as $data) {
            $registration = Registration::create([
                'event_id' => $event->id,
                'participant_id' => $data['participant_id'],
                'source' => 'manual',
                'participant_type' => $data['participant_type'],
                'fellowship_group_id' => $data['fellowship_group_id'],
                'registration_status' => 'confirmed',
                'registered_at' => $data['registered_at'],
                'encoded_by' => $data['encoded_by'],
                'attendance_code' => RegistrationBridge::code($event),
                'submitted_email' => $data['submitted_email'],
                'submitted_contact_number' => $data['submitted_contact_number'],
            ]);

            if ($data['attendance'] !== null) {
                $registration->attendance()->create($data['attendance']);
            }

            if ($data['discipleship_link'] !== null) {
                DB::table('discipleship_event_registration_links')->insert([
                    'enrollment_id' => $data['discipleship_link']['enrollment_id'],
                    'session_id' => $data['discipleship_link']['session_id'],
                    'registration_id' => $registration->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $lockedSource->forceFill(['registration_event_id' => $event->id])->saveQuietly();

        return ['event_id' => $event->id, 'plan' => $plan];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function conflict(array &$report, string $module, Model $source, string $message): void
    {
        $report['conflicts'][] = [
            'module' => $module,
            'source_id' => $source->getKey(),
            'message' => $message,
        ];
        $report['items'][] = [
            'module' => $module,
            'source_id' => $source->getKey(),
            'status' => 'conflict',
        ];
    }
}
