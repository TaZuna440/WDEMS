<?php

namespace App\Enums;

/**
 * Status for church events.
 *
 * Four values, not the ten-stage chain from the original Church
 * Event Management specification. Per client decision Q6
 * (2026-10-16): four stages match the dashboard mockup.
 *
 * Distinct from EventStatus (running events). Church events do
 * not use Draft, RegistrationOpen, or RegistrationClosed.
 */
enum ChurchEventStatus: string
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
