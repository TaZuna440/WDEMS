<?php

namespace App\Http\Controllers;

use App\Enums\ChurchCategory;
use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
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
    public function index(Request $request): Response
    {
        $kind = $request->query('kind', 'running');
        abort_unless(in_array($kind, ['running', 'church'], true), 404);
        $events = Event::query()
            ->where('event_kind', $kind)
            ->with('creator:id,name')
            ->when($kind === 'running', fn ($query) => $query->whereIn('status', [
                EventStatus::Draft->value,
                EventStatus::RegistrationOpen->value,
                EventStatus::RegistrationClosed->value,
                EventStatus::Ongoing->value,
            ]))
            ->when($kind === 'church', fn ($query) => $query
                ->whereIn('church_status', [ChurchEventStatus::Upcoming->value, ChurchEventStatus::Ongoing->value, ChurchEventStatus::Completed->value])
                ->whereIn('church_category', ChurchCategory::values()))
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        $map = function (Event $event): array {
            $churchStatus = ChurchEventStatus::tryFrom((string) $event->getRawOriginal('church_status'));

            return [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'status' => $event->isChurchEvent()
                    ? $churchStatus->value
                    : $event->status->value,
                'status_label' => $event->isChurchEvent()
                    ? $churchStatus->label()
                    : $event->status->label(),
                'creator' => $event->creator?->name,
                'church_category' => $event->church_category,
                'action_url' => $event->isChurchEvent()
                    ? route('church.registrations.index', $event)
                    : route('registration-monitor.show', $event),
            ];
        };

        return Inertia::render('registrations/index', [
            'kind' => $kind,
            'categories' => array_map(
                fn (ChurchCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ],
                ChurchCategory::cases(),
            ),
            'needsForm' => $events
                ->filter(fn (Event $e) => $e->isChurchEvent()
                    ? $e->registration_form_saved_at === null
                    : $e->status === EventStatus::Draft)
                ->map($map)
                ->values(),
            'open' => $events
                ->filter(fn (Event $e) => $e->isChurchEvent()
                    ? (bool) $e->church_registration_open
                    : $e->acceptsPublicRegistration())
                ->map($map)
                ->values(),
            'closed' => $events
                ->filter(fn (Event $e) => $e->isChurchEvent()
                    ? $e->registration_form_saved_at !== null && ! $e->church_registration_open
                    : $e->canReopenRegistration())
                ->map($map)
                ->values(),
        ]);
    }
}
