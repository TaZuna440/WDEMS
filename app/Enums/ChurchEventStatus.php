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

    /**
     * Legal transitions from the current status.
     *
     * Mirrors EventStatus::allowedTransitions() on the running-event
     * side. Every church-status write that passes through
     * EventController::update (Phase A task A.3) validates against
     * this list.
     *
     * Upcoming → [Ongoing, Cancelled]
     * Ongoing  → [Completed, Cancelled]
     * Completed and Cancelled are terminal — no outgoing transitions.
     *
     * Cancelled is reachable from any non-terminal state. The reverse
     * (Cancelled → anything) is not: cancellation is final.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Upcoming => [self::Ongoing, self::Cancelled],
            self::Ongoing => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [],
        };
    }

    /**
     * Whether the current status can transition to the target status.
     *
     * Same-status transitions (Upcoming → Upcoming) return false:
     * they are not in the allowed list, and in_array() correctly
     * rejects them. There is no special case for self-transition.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
