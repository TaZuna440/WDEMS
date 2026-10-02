<?php

namespace App\Http\Middleware;

use App\Enums\EventStatus;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsOpen
{
    /**
     * Gate the public /r/{slug} route group.
     *
     * Renders a dedicated "registration closed" page with HTTP 200 in
     * three cases:
     *
     *   1. The bound event is not in RegistrationOpen status.
     *   2. The event has a registration_end timestamp that has passed.
     *   3. The route parameter is not an Event (defensive — the route
     *      always binds, but the middleware must not assume).
     *
     * 404 is wrong for a closed event — the URL is valid, the event is
     * just not accepting submissions. 403 is wrong — no auth exists to
     * fail. 200 with a clear message is the honest answer.
     *
     * Applies to both GET and POST. A POST to a closed event never
     * reaches PublicRegistrationController::store().
     */
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            return $next($request);
        }

        if ($event->status !== EventStatus::RegistrationOpen) {
            return $this->closed($request, $event);
        }

        if ($event->registration_end !== null && now()->isAfter($event->registration_end)) {
            return $this->closed($request, $event);
        }

        return $next($request);
    }

    /**
     * Render the closed page with 200. The payload is intentionally
     * minimal — event name and date so the page has something to show,
     * nothing that could leak draft information.
     */
    private function closed(Request $request, Event $event): Response
    {
        return Inertia::render('registrations/closed', [
            'event' => [
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
            ],
        ])->toResponse($request);
    }
}
