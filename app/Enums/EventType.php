<?php

namespace App\Enums;

enum EventType: string
{
    case CommunityRun = 'community_run';
    case FunRun = 'fun_run';
    case Church = 'church';

    /**
     * Get all types as an array of values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Running-event types only.
     *
     * Used by the running-event creation wizard, which must not
     * offer Church as an option. Church events are created
     * through a separate flow (Phase 2 onwards).
     *
     * @return array<int, self>
     */
    public static function runningTypes(): array
    {
        return [self::CommunityRun, self::FunRun];
    }

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::CommunityRun => 'Community Run',
            self::FunRun => 'Fun Run',
            self::Church => 'Church Event',
        };
    }
}
