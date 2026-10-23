<?php

namespace Database\Seeders;

use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        if ($admin === null) {
            $index = 1;
            do {
                $email = $index === 1
                    ? 'presentation-admin@example.test'
                    : "presentation-admin-{$index}@example.test";
                $index++;
            } while (User::query()->where('email', $email)->exists());

            $admin = User::query()->create([
                'name' => 'Presentation Admin',
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);
        }

        $this->ensureStaffUsers();
        $this->seedRunningEvents($admin);
        $this->seedChurchEvents($admin);
    }

    private function ensureStaffUsers(): void
    {
        $index = 1;
        while (User::query()->where('role', 'staff')->count() < 2) {
            $email = "presentation-staff-{$index}@example.test";
            if (! User::query()->where('email', $email)->exists()) {
                User::query()->create([
                    'name' => "Presentation Staff {$index}",
                    'email' => $email,
                    'password' => Hash::make(Str::random(48)),
                    'role' => 'staff',
                    'email_verified_at' => now(),
                ]);
            }
            $index++;
        }
    }

    private function seedRunningEvents(User $admin): void
    {
        $events = [
            ['name' => 'Community Run - Riverside 5K', 'date' => 14, 'status' => EventStatus::RegistrationOpen],
            ['name' => 'Community Run - Sunrise Loop', 'date' => 21, 'status' => EventStatus::RegistrationOpen],
            ['name' => 'Community Run - Harbor Route', 'date' => 28, 'status' => EventStatus::RegistrationClosed],
            ['name' => 'Community Run - New Route Draft', 'date' => 35, 'status' => EventStatus::Draft],
        ];

        foreach ($events as $eventData) {
            $event = Event::query()->firstOrCreate(
                [
                    'event_kind' => Event::KIND_RUNNING,
                    'event_name' => $eventData['name'],
                ],
                [
                    'created_by' => $admin->id,
                    'event_type' => EventType::CommunityRun,
                    'event_date' => today()->addDays($eventData['date'])->toDateString(),
                    'start_time' => '06:00',
                    'end_time' => '09:00',
                    'distance_value' => 5,
                    'distance_unit' => 'km',
                    'venue' => 'Riverside Park',
                    'venue_address' => 'Riverside Avenue, Manila',
                    'status' => $eventData['status'],
                ],
            );

            if ($eventData['status'] === EventStatus::RegistrationOpen) {
                $this->seedRegistrations($event, $admin, 'running', 12);
            }
            if ($eventData['status'] === EventStatus::RegistrationClosed) {
                $this->seedRegistrations($event, $admin, 'running', 8);
            }
        }
    }

    private function seedChurchEvents(User $admin): void
    {
        $events = [
            ['category' => 'Services', 'activity' => 'Sunday Service', 'name' => 'Sunday Service - Community Gathering', 'open' => true],
            ['category' => 'Fellowship', 'activity' => 'Church Fellowship', 'name' => 'Church Fellowship - Welcome Lunch', 'open' => true],
            ['category' => 'Discipleship', 'activity' => 'Bible Study', 'name' => 'Bible Study - Foundations', 'open' => false],
            ['category' => 'Youth', 'activity' => 'Youth Camp', 'name' => 'Youth Camp - New Horizons', 'open' => true],
            ['category' => 'Kids Ministry', 'activity' => 'Kids Service', 'name' => 'Kids Service - Family Sunday', 'open' => false],
            ['category' => 'Young Adult', 'activity' => 'Young Adult Service', 'name' => 'Young Adult Service - Connect', 'open' => true],
            ['category' => 'Worship & Prayer', 'activity' => 'Worship Night', 'name' => 'Worship Night - Songs of Hope', 'open' => false],
            ['category' => 'Special Occasions', 'activity' => 'Dedication', 'name' => 'Dedication - Community Celebration', 'open' => true],
            ['category' => 'Church Administration', 'activity' => 'Church Planning', 'name' => 'Church Planning - Annual Meeting', 'open' => false],
        ];

        foreach ($events as $index => $eventData) {
            $event = Event::query()->firstOrCreate(
                [
                    'event_kind' => Event::KIND_CHURCH,
                    'church_category' => $eventData['category'],
                    'church_activity_category' => $eventData['activity'],
                    'event_name' => $eventData['name'],
                ],
                [
                    'created_by' => $admin->id,
                    'event_type' => EventType::Church,
                    'church_status' => ChurchEventStatus::Upcoming,
                    'church_registration_open' => $eventData['open'],
                    'event_date' => today()->addDays(7 + ($index * 3))->toDateString(),
                    'start_time' => '10:00',
                    'end_time' => '12:00',
                    'venue' => 'Main Church Hall',
                    'venue_address' => 'Central District, Manila',
                    'status' => EventStatus::Draft,
                ],
            );

            $this->seedRegistrations($event, $admin, 'church', 6);
        }
    }

    private function seedRegistrations(Event $event, User $admin, string $pool, int $count): void
    {
        $attendanceStatuses = ['present', 'present', 'absent', null, 'present', null, 'absent', 'present', null, 'present', 'absent', null];

        for ($index = 1; $index <= $count; $index++) {
            $email = "presentation-{$pool}-participant-{$index}@example.test";
            $participant = Participant::query()->firstOrCreate(
                ['pool' => $pool, 'email' => $email],
                [
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'contact_number' => sprintf('+63917%07d', $index),
                    'birthday' => today()->subYears(20 + ($index % 35))->toDateString(),
                    'address' => 'Central District, Manila',
                ],
            );
            $registration = Registration::query()->firstOrCreate(
                ['event_id' => $event->id, 'participant_id' => $participant->id],
                [
                    'encoded_by' => $admin->id,
                    'contact_attested_at' => now(),
                    'registration_status' => 'confirmed',
                    'source' => 'manual',
                    'participant_type' => $index % 4 === 0 ? 'first_timer' : 'member',
                    'registered_at' => now()->subDays($index),
                    'registration_date' => now()->subDays($index),
                    'submitted_email' => $email,
                    'submitted_contact_number' => $participant->contact_number,
                ],
            );

            $attendanceStatus = $attendanceStatuses[($index - 1) % count($attendanceStatuses)];
            if ($attendanceStatus !== null) {
                $registration->attendance()->firstOrCreate(
                    [],
                    [
                        'attendance_status' => $attendanceStatus,
                        'attendance_time' => now(),
                        'recorded_by' => $admin->id,
                    ],
                );
            }
        }
    }
}
