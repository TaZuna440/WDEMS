<?php

namespace App\Support;

final class ChurchSpecialistCatalog
{
    /**
     * @var array<string, array{label: string, church_category: string, activities: array<string, string>}>
     */
    private const MINISTRIES = [
        'services' => [
            'label' => 'Services',
            'church_category' => 'Services',
            'activities' => [
                'sunday-service' => 'Sunday Service',
                'special-service' => 'Special Service',
            ],
        ],
        'fellowship' => [
            'label' => 'Fellowship',
            'church_category' => 'Fellowship',
            'activities' => [
                'church-fellowship' => 'Church Fellowship',
                'ministry-fellowship' => 'Ministry Fellowship',
            ],
        ],
        'discipleship' => [
            'label' => 'Discipleship',
            'church_category' => 'Discipleship',
            'activities' => [
                'bible-study' => 'Bible Study',
                'discipleship-class' => 'Discipleship Class',
                'leadership-training' => 'Leadership Training',
            ],
        ],
        'youth' => [
            'label' => 'Youth Ministry',
            'church_category' => 'Youth',
            'activities' => [
                'camp' => 'Youth Camp',
                'huddle' => 'Huddle',
                'campus-ministry' => 'Campus Ministry',
                'timothy-class' => 'Timothy Class',
            ],
        ],
        'kids' => [
            'label' => 'Kids Ministry',
            'church_category' => 'Kids Ministry',
            'activities' => [
                'kids-service' => 'Kids Service',
                'vacation-bible-school' => 'Vacation Bible School',
                'outreach-wawang-pulo' => 'Outreach · Wawang Pulo',
                'outreach-pasolo' => 'Outreach · Pasolo',
                'outreach-balubaran' => 'Outreach · Balubaran',
                'outreach-coloong' => 'Outreach · Coloong',
            ],
        ],
        'young-adult' => [
            'label' => 'Young Adult',
            'church_category' => 'Young Adult',
            'activities' => [
                'young-adult-service' => 'Young Adult Service',
                'seminar' => 'Seminar',
                'grc-care-group' => 'GRC Care Group',
                'grc-fellowship' => 'GRC Fellowship',
            ],
        ],
        'worship-prayer' => [
            'label' => 'Worship & Prayer',
            'church_category' => 'Worship & Prayer',
            'activities' => [
                'outdoor-prayer-meeting' => 'Outdoor Prayer Meeting',
                'worship-night' => 'Worship Night',
                'prayer-fasting' => 'Prayer & Fasting',
            ],
        ],
        'church-administration' => [
            'label' => 'Church Administration',
            'church_category' => 'Church Administration',
            'activities' => [
                'church-planning' => 'Church Planning',
                'ministry-meeting-worship' => 'Ministry Meeting · Worship',
                'ministry-meeting-technical' => 'Ministry Meeting · Technical',
                'ministry-meeting-multimedia' => 'Ministry Meeting · Multimedia',
                'ministry-meeting-social-media' => 'Ministry Meeting · Social Media',
                'ministry-meeting-finance-team' => 'Ministry Meeting · Finance Team',
                'ministry-meeting-light' => 'Ministry Meeting · Light',
                'ministry-meeting-kids' => 'Ministry Meeting · Kids',
                'ministry-meeting-young-adult' => 'Ministry Meeting · Young Adult',
            ],
        ],
        'special-occasions' => [
            'label' => 'Special Occasions',
            'church_category' => 'Special Occasions',
            'activities' => [
                'church-anniversary' => 'Church Anniversary',
                'thanksgiving-celebration' => 'Thanksgiving Celebration',
                'pastors-appreciation' => "Pastor's Appreciation",
                'dedication' => 'Dedication',
                'school-of-leaders-1' => 'School of Leaders 1',
                'school-of-leaders-2' => 'School of Leaders 2',
                'school-of-leaders-3' => 'School of Leaders 3',
            ],
        ],
    ];

    /**
     * @return array<string, array{label: string, church_category: string, activities: array<string, string>}>
     */
    public static function ministries(): array
    {
        return self::MINISTRIES;
    }

    /**
     * @return array{label: string, church_category: string, activities: array<string, string>}
     */
    public static function ministry(string $slug): array
    {
        $ministry = self::MINISTRIES[$slug] ?? null;

        abort_if($ministry === null, 404);

        return $ministry;
    }

    /**
     * @return array{ministry_slug: string, ministry: array{label: string, church_category: string, activities: array<string, string>}, label: string}
     */
    public static function activity(string $ministrySlug, string $activitySlug): array
    {
        $ministry = self::ministry($ministrySlug);
        $label = $ministry['activities'][$activitySlug] ?? null;

        abort_if($label === null, 404);

        return [
            'ministry_slug' => $ministrySlug,
            'ministry' => $ministry,
            'label' => $label,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function activityLabelsForChurchCategory(?string $churchCategory): array
    {
        foreach (self::MINISTRIES as $ministry) {
            if ($ministry['church_category'] === $churchCategory) {
                return array_values($ministry['activities']);
            }
        }

        return [];
    }
}
