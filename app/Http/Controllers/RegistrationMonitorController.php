<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Registration;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationMonitorController extends Controller
{
    /**
     * Landing: every event currently accepting registrations.
     *
     * Sorted by most recent submission first — the event that just
     * received a registration rises to the top. Events with no
     * submissions sort by registration_start descending.
     *
     * Each card carries the total registration count and the
     * rolling 60-minute rate. The per-event page carries the 15-minute
     * rate; the landing page omits it to keep the card readable.
     */
    public function index(): Response
    {
        $events = Event::query()
            ->where('status', EventStatus::RegistrationOpen)
            ->get();

        $stats = Registration::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->selectRaw('event_id, MAX(created_at) as most_recent, COUNT(*) as total')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $cutoff = CarbonImmutable::now()->subHour();

        $mapped = $events
            ->map(function (Event $event) use ($stats, $cutoff) {
                $row = $stats->get($event->id);
                $mostRecent = $row?->most_recent;

                $lastHour = Registration::where('event_id', $event->id)
                    ->where('created_at', '>=', $cutoff)
                    ->count();

                return [
                    'id' => $event->id,
                    'event_name' => $event->event_name,
                    'event_date' => $event->event_date?->toDateString(),
                    'venue' => $event->venue,
                    'status' => $event->status->value,
                    'status_label' => $event->status->label(),
                    'total' => (int) ($row?->total ?? 0),
                    'last_hour' => $lastHour,
                    'most_recent_iso' => $mostRecent,
                    'most_recent_human' => $mostRecent
                        ? CarbonImmutable::parse($mostRecent)->diffForHumans()
                        : null,
                    '_sort' => $mostRecent
                        ?? $event->registration_start?->toIso8601String()
                        ?? '',
                ];
            })
            ->sortByDesc('_sort')
            ->values()
            ->map(function (array $row) {
                unset($row['_sort']);

                return $row;
            });

        return Inertia::render('registrations/monitor/index', [
            'events' => $mapped,
        ]);
    }

    /**
     * Per-event feed: last 100 registrations, rate windows, and the
     * event's registration state.
     *
     * Reachable when the event is registration_open OR
     * registration_closed. The second case is the read-only snapshot
     * after close (§11 of the monitoring spec). Draft, ongoing,
     * completed, and cancelled return 404 — the monitor is not the
     * right surface for those.
     */
    public function show(Event $event): Response
    {
        if (! in_array($event->status, [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true)) {
            abort(404);
        }

        $isOpen = $event->status === EventStatus::RegistrationOpen;

        // Rate windows. Both are rolling counts against created_at.
        // The 60-minute window is the same one the landing page uses;
        // the 15-minute window is the sharper "is the pace picking up"
        // signal on the detail page.
        $now = CarbonImmutable::now();
        $cutoffHour = $now->subHour();
        $cutoff15 = $now->subMinutes(15);

        $total = Registration::where('event_id', $event->id)->count();

        $lastHour = Registration::where('event_id', $event->id)
            ->where('created_at', '>=', $cutoffHour)
            ->count();

        $last15Min = Registration::where('event_id', $event->id)
            ->where('created_at', '>=', $cutoff15)
            ->count();

        // Last 100 registrations, newest first. Each participant
        // carries a count of their registrations on OTHER events —
        // the "returning" signal. One registration per (event,
        // participant) means this count equals the number of distinct
        // other events the participant has registered for.
        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->with(['participant' => function ($q) use ($event) {
                $q->withCount([
                    'registrations as other_events_count' => function ($q) use ($event) {
                        $q->where('event_id', '!=', $event->id);
                    },
                ]);
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $mapped = $registrations->map(function (Registration $r) {
            $participant = $r->participant;
            $otherCount = (int) ($participant?->other_events_count ?? 0);

            return [
                'id' => $r->id,
                'participant' => [
                    'id' => $participant?->id,
                    'full_name' => $participant?->fullName(),
                    'email' => $participant?->email,
                    'contact_number' => $participant?->contact_number,
                ],
                'created_at_iso' => $r->created_at?->toIso8601String(),
                'created_at_absolute' => $r->created_at?->format('H:i:s'),
                'created_at_human' => $r->created_at?->diffForHumans(),
                'is_returning' => $otherCount > 0,
                'other_events_count' => $otherCount,
            ];
        });

        return Inertia::render('registrations/monitor/show', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'registration_slug' => $event->registration_slug,
                'registration_start' => $event->registration_start?->toDateTimeString(),
                'registration_end' => $event->registration_end?->toDateTimeString(),
                'is_open' => $isOpen,
            ],
            'stats' => [
                'total' => $total,
                'last_hour' => $lastHour,
                'last_15min' => $last15Min,
            ],
            'registrations' => $mapped,
        ]);
    }
}
