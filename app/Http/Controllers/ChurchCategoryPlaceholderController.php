<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Placeholder landing for the six church categories whose modules
 * ship in Phase 4 of the UI restructure. Until then, the category
 * card on the Events page routes here.
 *
 * The route constrains {category} to the six valid slugs; this
 * controller does not need to re-validate.
 */
class ChurchCategoryPlaceholderController extends Controller
{
    public function __invoke(string $category): Response
    {
        $labels = [
            'kids' => 'Kids Ministry',
            'young-adult' => 'Young Adult',
            'worship-prayer' => 'Worship & Prayer',
            'special-occasions' => 'Special Occasions',
            'church-administration' => 'Church Administration',
        ];

        return Inertia::render('events/church/placeholder', [
            'category' => $category,
            'categoryLabel' => $labels[$category] ?? 'Church Event',
        ]);
    }
}
