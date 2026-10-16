<?php

namespace App\Enums;

/**
 * Service types for church Services.
 *
 * Four fixed values, per client mockup. Adding a type is a code
 * change; if the client later wants admin-manageable types, this
 * enum's values migrate into a `service_types` table additively.
 *
 * Phase 3, per docs/church-events/06 decision D3 (2026-10-16).
 */
enum ServiceType: string
{
    case Morning = 'morning';
    case Midweek = 'midweek';
    case Youth = 'youth';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning Service',
            self::Midweek => 'Midweek Service',
            self::Youth => 'Youth Service',
            self::Other => 'Other Service',
        };
    }
}
