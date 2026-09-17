<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Configured = 'configured';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get all statuses as an array of values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the states this status can transition to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Configured, self::Cancelled],
            self::Configured => [self::RegistrationOpen, self::Cancelled],
            self::RegistrationOpen => [self::RegistrationClosed, self::Cancelled],
            self::RegistrationClosed => [self::Ongoing, self::Cancelled],
            self::Ongoing => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [],
        };
    }

    /**
     * Check if this status can transition to another.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Configured => 'Configured',
            self::RegistrationOpen => 'Registration Open',
            self::RegistrationClosed => 'Registration Closed',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}