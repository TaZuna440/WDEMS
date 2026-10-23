<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\ChurchSpecialistCatalog;
use Inertia\Inertia;
use Inertia\Response;

class ChurchSpecialistController extends Controller
{
    public function ministry(string $ministrySlug): Response
    {
        $ministry = ChurchSpecialistCatalog::ministry($ministrySlug);
        $eventsByActivity = Event::query()
            ->where('event_kind', Event::KIND_CHURCH)
            ->where('church_category', $ministry['church_category'])
            ->whereDate('event_date', '>=', today())
            ->whereIn('church_activity_category', array_values($ministry['activities']))
            ->withCount(['registrations as registration_count' => fn ($registrations) => $registrations->active()])
            ->get()
            ->groupBy('church_activity_category');
        $activities = collect($ministry['activities'])
            ->map(function (string $activityLabel, string $activitySlug) use ($eventsByActivity, $ministry) {
                $activityEvents = $eventsByActivity->get($activityLabel, collect());

                return [
                    'slug' => $activitySlug,
                    'label' => $activityLabel,
                    'event_count' => $activityEvents->count(),
                    'registration_count' => $activityEvents->sum('registration_count'),
                    'href' => route('events.index', [
                        'kind' => 'church',
                        'church_category' => $ministry['church_category'],
                        'church_activity' => $activityLabel,
                    ]),
                ];
            })
            ->values();
        $eventCount = $activities->sum('event_count');
        $registrationCount = $activities->sum('registration_count');

        return Inertia::render('events/church/specialist/ministry', [
            'ministry' => [
                'slug' => $ministrySlug,
                'label' => $ministry['label'],
                'event_count' => $eventCount,
                'registration_count' => $registrationCount,
            ],
            'activities' => $activities,
            'add_event_url' => route('events.create.church.category', $ministrySlug),
        ]);
    }

    public function category(string $ministrySlug, string $activitySlug): Response
    {
        $activity = ChurchSpecialistCatalog::activity($ministrySlug, $activitySlug);
        $query = Event::query()
            ->where('event_kind', Event::KIND_CHURCH)
            ->where('church_category', $activity['ministry']['church_category'])
            ->whereDate('event_date', '>=', today())
            ->where('church_activity_category', $activity['label']);
        $events = (clone $query)
            ->withCount(['registrations as registration_count' => fn ($registrations) => $registrations->active()])
            ->orderBy('event_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->event_name,
                'date' => $event->getRawOriginal('event_date'),
                'venue' => $event->venue,
                'registration_count' => $event->registration_count,
                'status' => $event->statusLabel(),
                'href' => route('events.show', $event),
            ]);

        return Inertia::render('events/church/specialist/category', [
            'ministry' => [
                'slug' => $ministrySlug,
                'label' => $activity['ministry']['label'],
                'href' => route('events.church.specialist.ministry', $ministrySlug),
            ],
            'activity' => [
                'slug' => $activitySlug,
                'label' => $activity['label'],
                'event_count' => $events->count(),
                'registration_count' => $events->sum('registration_count'),
            ],
            'events' => $events,
            'add_event_url' => route('events.create.church.category', [$ministrySlug, $activitySlug]),
        ]);
    }
}
