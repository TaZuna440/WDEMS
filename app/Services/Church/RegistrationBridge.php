<?php

namespace App\Services\Church;

use App\Enums\ServiceType;
use App\Models\Event;
use App\Models\FellowshipEvent;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ServiceOccurrence;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit, transactional activation. Legacy rows remain as immutable history. */
class RegistrationBridge
{
    public function activate(ServiceOccurrence|FellowshipEvent $legacy): Event
    {
        return DB::transaction(function () use ($legacy): Event {
            if ($legacy instanceof ServiceOccurrence) {
                $source = ServiceOccurrence::query()->whereKey($legacy->id)->lockForUpdate()->firstOrFail();
                $service = true;
            } else {
                $source = FellowshipEvent::query()->whereKey($legacy->id)->lockForUpdate()->firstOrFail();
                $service = false;
            }
            $registrationEventId = $source->getAttribute('registration_event_id');
            if ($registrationEventId !== null) {
                $existing = Event::query()->whereKey($registrationEventId)->first();
                if ($existing instanceof Event) {
                    return $existing;
                }

                throw (new ModelNotFoundException)->setModel(Event::class, [$registrationEventId]);
            }

            $serviceType = $service ? ServiceType::from($source->getRawOriginal('service_type')) : null;
            $eventName = $service ? $serviceType->label() : $source->getAttribute('name');
            $description = $service
                ? $this->serviceDescription($source)
                : $this->fellowshipDescription($source);
            $event = Event::forceCreate([
                'created_by' => $source->getAttribute('created_by'), 'event_kind' => 'church', 'event_type' => 'church',
                'church_category' => $service ? 'Services' : 'Fellowship', 'church_status' => 'upcoming',
                'event_name' => $eventName,
                'event_date' => $source->getRawOriginal($service ? 'occurrence_date' : 'event_date'),
                'start_time' => $source->getRawOriginal('start_time'), 'end_time' => $source->getRawOriginal('end_time'),
                'venue' => $service ? null : $source->getAttribute('venue'),
                'description' => $description,
                'registration_form_version' => 2, 'registration_common_field_requirements' => ['email' => true, 'contact_number' => false, 'address' => true],
            ]);
            $tracks = $service ? ['service_attendance_members' => 'Member', 'service_attendance_first_timers' => 'First-Timer'] : ['fellowship_attendances' => 'Guest'];
            foreach ($tracks as $table => $type) {
                foreach (DB::table($table)->where($service ? 'service_occurrence_id' : 'fellowship_event_id', $source->id)->orderBy('id')->get() as $history) {
                    $person = Participant::query()->whereKey($history->participant_id)->firstOrFail();
                    if ($person->pool !== 'church' || $person->provisional_event_id !== null
                        || $event->registrations()->where('participant_id', $person->id)->exists()) {
                        throw ValidationException::withMessages(['bridge' => 'Existing attendance contains conflicting participant records. Resolve the history before activating registration. No records were changed.']);
                    }
                    $row = Registration::create([
                        'event_id' => $event->id, 'participant_id' => $person->id, 'source' => 'manual',
                        'participant_type' => $type, 'registration_status' => 'confirmed',
                        'registered_at' => $history->created_at, 'encoded_by' => $history->recorded_by,
                        'fellowship_group_id' => $service ? null : $history->fellowship_group_id,
                        'attendance_code' => self::code($event),
                        'submitted_email' => $person->email, 'submitted_contact_number' => $person->contact_number,
                    ]);
                    if ($history->attendance_status !== null && $history->attendance_status !== 'unmarked') {
                        [$status, $notes] = $this->attendanceStatus($history->attendance_status, $history->notes);
                        if ($status !== null) {
                            $row->attendance()->create([
                                'attendance_status' => $status,
                                'attendance_time' => $history->recorded_at, 'recorded_by' => $history->recorded_by,
                                'notes' => $notes,
                            ]);
                        }
                    }
                }
            }
            $source->forceFill(['registration_event_id' => $event->id])->saveQuietly();

            return $event;
        });
    }

    public static function code(Event $event): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $token = '';
            for ($index = 0; $index < 8; $index++) {
                $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = substr($token, 0, 4).'-'.substr($token, 4);
        } while (Registration::where('event_id', $event->id)->where('attendance_code', $code)->exists());

        return $code;
    }

    private function serviceDescription(ServiceOccurrence $source): ?string
    {
        return collect([
            $source->service_leader ? 'Leader: '.$source->service_leader : null,
            $source->topic ? 'Topic: '.$source->topic : null,
            $source->notes,
        ])->filter()->implode("\n") ?: null;
    }

    private function fellowshipDescription(FellowshipEvent $source): ?string
    {
        return collect([
            $source->description,
            $source->facilitator ? 'Facilitator: '.$source->facilitator : null,
            $source->notes,
        ])->filter()->implode("\n\n") ?: null;
    }

    /** @return array{string|null, string|null} */
    private function attendanceStatus(string $legacyStatus, ?string $notes): array
    {
        if (in_array($legacyStatus, ['present', 'late'], true)) {
            return ['present', $notes];
        }
        if ($legacyStatus === 'absent') {
            return ['absent', $notes];
        }

        // Excused and unknown statuses cannot be truthfully reported as Absent.
        // Unmarked is represented by the absence of a new Attendance row; the
        // immutable legacy record continues to preserve its exact status/note.
        return [null, null];
    }
}
