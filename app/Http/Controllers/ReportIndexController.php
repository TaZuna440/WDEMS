<?php

namespace App\Http\Controllers;

use App\Enums\ChurchCategory;
use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reports landing — the completed events for the selected event kind.
 *
 * Phase 1 of the UI restructure. Defaults to running events and links
 * each row to its end-of-event report. Phase 5 replaces this with a fuller
 * dashboard-style surface.
 */
class ReportIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $kind = $request->query('kind', 'running');
        abort_unless(in_array($kind, ['running', 'church'], true), 404);
        $events = Event::query()
            ->withCount(['registrations as confirmed_registrations_count' => fn ($query) => $query->active()
                ->whereHas('participant', fn ($participant) => $participant->where('pool', 'church'))])
            ->where('event_kind', $kind)
            ->when($kind === 'running', fn ($query) => $query->where('status', EventStatus::Completed->value))
            ->when($kind === 'church', fn ($query) => $query
                ->where('church_status', ChurchEventStatus::Completed->value)
                ->whereIn('church_category', ChurchCategory::values()))
            ->with('creator:id,name')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('reports/index', [
            'activeSide' => $kind,
            'categories' => array_map(
                fn (ChurchCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ],
                ChurchCategory::cases(),
            ),
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'event_kind' => $event->event_kind,
                'event_name' => $event->event_name,
                'event_type_label' => $event->event_type->label(),
                'church_category' => $event->church_category,
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'creator' => $event->creator?->name,
                'registered_count' => $event->isChurchEvent()
                    ? (int) $event->getAttribute('confirmed_registrations_count')
                    : null,
                'report_url' => $event->isRunningEvent()
                    ? route('events.report', $event)
                    : route('church.attendance', [$event, 'summary' => 1]),
            ]),
        ]);
    }
}
