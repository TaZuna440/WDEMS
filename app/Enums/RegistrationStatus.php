<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Held = 'held';
    case Duplicate = 'duplicate';
    case Removed = 'removed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Held => 'Held for Review',
            self::Duplicate => 'Confirmed Duplicate',
            self::Removed => 'Removed',
        };
    }
}
