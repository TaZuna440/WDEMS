<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $events = Event::with('creator:id,name')
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        $actionItems = $this->buildActionItems($events);
        $upcoming = $this->buildUpcoming($events);

        return Inertia::render('dashboard', [
            'actionItems' => $actionItems,
            'upcoming' => $upcoming,
            'recentActivity' => [],
        ]);
    }

    /**
     * @return array<int, array{id:int, event_name:string, event_date:?string, days_until:?int, status:string, status_label:string, urgency:string, reason:string, action:array{label:string, href:string}}>
     */
    private function buildActionItems($events): array
    {
        $items = [];

        foreach ($events as $event) {
            if (in_array($event->status, [EventStatus::Completed, EventStatus::Cancelled], true)) {
                continue;
            }

            $daysUntil = $event->event_date
                ? (int) now()->startOfDay()->diffInDays($event->event_date->startOfDay(), false)
                : null;

            $status = $event->status;

            [$urgency, $reason, $action] = match (true) {
                $daysUntil !== null && $daysUntil < 0
                    => [
                        'overdue',
                        'Event was '.abs($daysUntil).' day'.(abs($daysUntil) === 1 ? '' : 's').' ago, still in '.$status->label(),
                        ['label' => 'Mark as completed', 'href' => route('events.show', $event)],
                    ],

                $daysUntil !== null && $daysUntil <= 7 && $status === EventStatus::Draft
                    => [
                        'urgent',
                        'Event in '.$daysUntil.' day'.($daysUntil === 1 ? '' : 's').' — still in '.$status->label(),
                        $this->nextActionFor($event),
                    ],

                $status === EventStatus::Draft
                    => [
                        'action',
                        'Draft — no registration form yet',
                        ['label' => 'Configure event', 'href' => route('events.show', $event)],
                    ],


                $status === EventStatus::RegistrationOpen
                    => [
                        'waiting',
                        $daysUntil !== null
                            ? 'Registration open — '.$daysUntil.' day'.($daysUntil === 1 ? '' : 's').' to event'
                            : 'Registration open',
                        ['label' => 'View event', 'href' => route('events.show', $event)],
                    ],

                $status === EventStatus::RegistrationClosed
                    => [
                        'waiting',
                        $daysUntil !== null
                            ? 'Registration closed — '.$daysUntil.' day'.($daysUntil === 1 ? '' : 's').' to event'
                            : 'Registration closed',
                        ['label' => 'View event', 'href' => route('events.show', $event)],
                    ],

                $status === EventStatus::Ongoing
                    => [
                        'waiting',
                        'Event ongoing — mark complete when done',
                        ['label' => 'Mark as completed', 'href' => route('events.show', $event)],
                    ],

                default => [null, null, null],
            };

            if ($urgency === null) {
                continue;
            }

            $items[] = [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->format('M j, Y'),
                'days_until' => $daysUntil,
                'status' => $status->value,
                'status_label' => $status->label(),
                'urgency' => $urgency,
                'reason' => $reason,
                'action' => $action,
            ];
        }

        $order = ['overdue' => 0, 'urgent' => 1, 'action' => 2, 'waiting' => 3];
        usort($items, function ($a, $b) use ($order) {
            $diff = $order[$a['urgency']] - $order[$b['urgency']];
            if ($diff !== 0) {
                return $diff;
            }

            $aDays = $a['days_until'] ?? 99999;
            $bDays = $b['days_until'] ?? 99999;

            return $aDays <=> $bDays;
        });

        return $items;
    }

    /**
     * @return array<int, array{id:int, event_name:string, event_date:?string, days_until:?int, status:string, status_label:string}>
     */
    private function buildUpcoming($events): array
    {
        return $events
            ->filter(fn (Event $e) => $e->event_date && $e->event_date->isFuture())
            ->whereNotIn('status', [EventStatus::Completed, EventStatus::Cancelled])
            ->sortBy('event_date')
            ->take(3)
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'event_name' => $e->event_name,
                'event_date' => $e->event_date->format('M j, Y'),
                'days_until' => (int) now()->startOfDay()->diffInDays($e->event_date->startOfDay(), false),
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{label:string, href:string}
     */
    private function nextActionFor(Event $event): array
    {
        if ($event->status === EventStatus::Draft) {
            return ['label' => 'Configure event', 'href' => route('events.show', $event)];
        }

        return ['label' => 'Open registration', 'href' => route('events.show', $event)];
    }
}
