<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Youth module (Phase 4).
 *
 * The Youth hub lists five sub-modules. Only Youth Service has a
 * real page -- it reuses the Services module's per-type view at
 * /events/church/services/youth. The other four render a
 * placeholder until their own builds ship.
 */
class YouthController extends Controller
{
    /**
     * Youth hub -- five sub-module cards.
     */
    public function index(): Response
    {
        return Inertia::render('events/church/youth/index');
    }

    /**
     * Placeholder for the four Youth sub-modules not yet built.
     */
    public function placeholder(string $sub): Response
    {
        $labels = [
            'camp' => 'Youth Camp',
            'huddle' => 'Huddle',
            'campus-ministry' => 'Campus Ministry',
            'timothy-class' => 'Timothy Class',
        ];

        if (! array_key_exists($sub, $labels)) {
            abort(404);
        }

        return Inertia::render('events/church/youth/placeholder', [
            'sub' => $sub,
            'subLabel' => $labels[$sub],
        ]);
    }
}
