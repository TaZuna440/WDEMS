<?php

namespace App\Enums;

/**
 * Church event categories.
 *
 * The nine categories confirmed by the client in
 * docs/church-events/02-product-scope-and-requirements.md CAT-01
 * (2026-10-16). Adding a category is a code change; if the client
 * later needs administrator-managed categories, these values
 * migrate into an `event_categories` table additively.
 *
 * Values are title-case strings matching the format already stored
 * in `events.church_category`. The column was introduced as a free
 * string by migration 2026_10_16_110000 and the existing
 * ChurchEventTest fixtures use the same title-case strings.
 *
 * CAT-05 ("Other" with a custom name) is PROPOSED in doc 02 and is
 * not part of this enum. Adding it later would require a separate
 * code path.
 */
enum ChurchCategory: string
{
    case Services = 'Services';
    case Fellowship = 'Fellowship';
    case Discipleship = 'Discipleship';
    case Youth = 'Youth';
    case KidsMinistry = 'Kids Ministry';
    case YoungAdult = 'Young Adult';
    case WorshipAndPrayer = 'Worship & Prayer';
    case SpecialOccasions = 'Special Occasions';
    case ChurchAdministration = 'Church Administration';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return $this->value;
    }
}
