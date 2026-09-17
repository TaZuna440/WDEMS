<?php

namespace App\Enums;

enum EventType: string
{
    case CommunityRun = 'community_run';
    case FunRun = 'fun_run';

    /**
     * Get all types as an array of values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::CommunityRun => 'Community Run',
            self::FunRun => 'Fun Run',
        };
    }
}