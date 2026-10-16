<?php

namespace App\Enums;

enum EventType: string
{
    case CommunityRun = 'community_run';
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
     * After the Fun Run removal (2026-10-16), Community Run is the
     * only running-event type. Church is a separate kind, gated by
     * the event_kind discriminator column, and is not returned here.
     *
     * @return array<int, self>
     */
    public static function runningTypes(): array
    {
        return [self::CommunityRun];
    }

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::CommunityRun => 'Community Run',
            self::Church => 'Church Event',
        };
    }
}
