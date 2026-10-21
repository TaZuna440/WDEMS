<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    /**
     * Registration queue — the organizer's daily workflow view.
     *
     * Phase 1 of the registration plan: lists events grouped by their
     * registration state. Phase 3 adds the "Create Form" and
     * "Open Registration" actions to this page.
     *
     * Running events remain in this queue while ongoing. Their intake
     * state is independent of the event lifecycle and can be toggled.
     */
    public function index(): Response
    {
        $events = Event::query()
            ->where('event_kind', Event::KIND_RUNNING)
            ->with('creator:id,name')
            ->whereIn('status', [
                EventStatus::Draft,
                EventStatus::RegistrationOpen,
                EventStatus::RegistrationClosed,
                EventStatus::Ongoing,
            ])
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        $map = fn (Event $event) => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->toDateString(),
            'venue' => $event->venue,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
            'creator' => $event->creator?->name,
        ];

        return Inertia::render('registrations/index', [
            'needsForm' => $events
                ->filter(fn (Event $e) => $e->status === EventStatus::Draft)
                ->map($map)
                ->values(),
            'open' => $events
                ->filter(fn (Event $e) => $e->acceptsPublicRegistration())
                ->map($map)
                ->values(),
            'closed' => $events
                ->filter(fn (Event $e) => $e->canReopenRegistration())
                ->map($map)
                ->values(),
        ]);
    }
}
