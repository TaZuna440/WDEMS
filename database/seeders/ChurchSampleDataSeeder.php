<?php

namespace Database\Seeders;

use App\Enums\ChurchEventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Church\RegistrationBridge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChurchSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $organizer = User::query()->firstOrCreate(
            ['email' => 'demo-church-organizer@example.test'],
            [
                'name' => 'Demo Church Organizer',
                'password' => Hash::make(Str::random(40)),
                'role' => 'admin',
            ],
        );

        $samples = [
            ['church_category' => 'Youth', 'activity' => 'Youth Camp', 'event_name' => 'Youth Camp (Sample)'],
            ['church_category' => 'Kids Ministry', 'activity' => 'Kids Service', 'event_name' => 'Kids Service (Sample)'],
            ['church_category' => 'Young Adult', 'activity' => 'GRC Fellowship', 'event_name' => 'GRC Fellowship (Sample)'],
        ];
        $people = [
            ['first_name' => 'Ari', 'last_name' => 'Santos', 'participant_type' => 'Member', 'attendance' => 'present'],
            ['first_name' => 'Bea', 'last_name' => 'Reyes', 'participant_type' => 'First-Timer', 'attendance' => 'absent'],
            ['first_name' => 'Caleb', 'last_name' => 'Garcia', 'participant_type' => 'Guest', 'attendance' => 'present'],
            ['first_name' => 'Dani', 'last_name' => 'Cruz', 'participant_type' => 'Member', 'attendance' => 'unmarked'],
        ];

        foreach ($samples as $sampleIndex => $sample) {
            $sampleDate = today()->addDays(7 + $sampleIndex);
            $event = Event::query()->firstOrCreate(
                [
                    'event_kind' => Event::KIND_CHURCH,
                    'church_category' => $sample['church_category'],
                    'church_activity_category' => $sample['activity'],
                    'event_name' => $sample['event_name'],
                ],
                [
                    'created_by' => $organizer->id,
                    'event_type' => EventType::Church,
                    'church_category' => $sample['church_category'],
                    'church_activity_category' => $sample['activity'],
                    'church_status' => ChurchEventStatus::Upcoming,
                    'event_date' => $sampleDate->toDateString(),
                    'start_time' => '10:00',
                    'venue' => 'Community Hall',
                    'venue_address' => 'Sample Barangay, Manila',
                    'status' => 'draft',
                ],
            );

            if ($event->event_date?->lt(today())) {
                $event->update(['event_date' => $sampleDate->toDateString()]);
            }

            foreach ($people as $personIndex => $personData) {
                $email = sprintf('demo-church-%d-%d@example.test', $sampleIndex + 1, $personIndex + 1);
                $phone = sprintf('+639170000%03d', ($sampleIndex * 4) + $personIndex + 1);
                $person = Participant::query()->firstOrCreate(
                    ['pool' => 'church', 'email' => $email],
                    [
                        'first_name' => $personData['first_name'],
                        'last_name' => $personData['last_name'],
                        'contact_number' => $phone,
                        'birthday' => today()->subYears(25 + $personIndex)->toDateString(),
                        'address' => 'Sample Barangay, Manila',
                    ],
                );
                $registration = Registration::query()->firstOrCreate(
                    ['event_id' => $event->id, 'participant_id' => $person->id],
                    [
                        'encoded_by' => $organizer->id,
                        'contact_attested_at' => now(),
                        'registration_status' => 'confirmed',
                        'source' => 'manual',
                        'participant_type' => $personData['participant_type'],
                        'registered_at' => now(),
                        'registration_date' => now(),
                        'attendance_code' => RegistrationBridge::code($event),
                        'submitted_email' => $email,
                        'submitted_contact_number' => $phone,
                    ],
                );

                if ($personData['attendance'] !== 'unmarked') {
                    $registration->attendance()->firstOrCreate(
                        [],
                        [
                            'attendance_status' => $personData['attendance'],
                            'attendance_time' => now(),
                            'recorded_by' => $organizer->id,
                        ],
                    );
                }
            }
        }
    }
}
