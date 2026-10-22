<?php

namespace App\Http\Controllers;

use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reports landing — the global list of completed events.
 *
 * Phase 1 of the UI restructure. Lists every completed event across
 * both kinds and links each row to its end-of-event report at
 * /events/{id}/report. Phase 5 replaces this with a fuller
 * dashboard-style surface.
 */
class ReportIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate(['kind' => ['nullable', 'in:running,church']]);
        $events = Event::query()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('event_kind', Event::KIND_RUNNING)
                        ->where('status', EventStatus::Completed->value);
                })->orWhere(function ($q2) {
                    $q2->where('event_kind', Event::KIND_CHURCH)
                        ->where('church_status', ChurchEventStatus::Completed->value);
                });
            })
            ->when(isset($filters['kind']), fn ($query) => $query->where('event_kind', $filters['kind']))
            ->with('creator:id,name')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('reports/index', [
            'activeSide' => $filters['kind'] ?? null,
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'event_kind' => $event->event_kind,
                'event_name' => $event->event_name,
                'event_type_label' => $event->event_type->label(),
                'church_category' => $event->church_category,
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'creator' => $event->creator?->name,
            ]),
        ]);
    }
}
