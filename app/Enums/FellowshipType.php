<?php

namespace App\Enums;

/**
 * Fellowship types for church Fellowship gatherings.
 *
 * Six fixed values, per client mockup. Adding a type is a code
 * change; if the client later wants admin-manageable types, this
 * enum's values migrate into a `fellowship_types` table additively.
 *
 * Phase 4, per docs/church-events/07 section 3.5.
 */
enum FellowshipType: string
{
    case Family = 'family';
    case Church = 'church';
    case Pastors = 'pastors';
    case Leaders = 'leaders';
    case Womens = 'womens';
    case Mens = 'mens';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Family => 'Family Fellowship',
            self::Church => 'Church Fellowship',
            self::Pastors => 'Pastors Fellowship',
            self::Leaders => 'Leaders Fellowship',
            self::Womens => "Women's Fellowship",
            self::Mens => "Men's Fellowship",
        };
    }
}
