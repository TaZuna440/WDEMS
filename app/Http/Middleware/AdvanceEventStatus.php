<?php

namespace App\Http\Middleware;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\EventWorkflow;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-advance an event from RegistrationClosed to Ongoing once its
 * start moment has passed.
 *
 * Runs on every authenticated request in the main route group. When
 * the request binds an Event whose status is RegistrationClosed and
 * whose event_date + start_time is in the past, the middleware calls
 * EventWorkflow::start() and logs the transition.
 *
 * Idempotent. A second request in the same state does nothing — the
 * status check on the second condition fails and the middleware
 * passes through. A race between two requests is harmless: the
 * workflow service rejects the illegal ongoing → ongoing transition,
 * the middleware catches InvalidArgumentException, and both requests
 * proceed.
 *
 * Order matters. `event.advance` runs after `auth` and
 * `device.trusted` — an unauthenticated request never flips a
 * status. Auto-advance is a side effect of app usage, not of the
 * public internet hitting a route.
 *
 * The trade-off, documented in docs/attendance-encoding-and-reporting.md
 * section 4.3: an event whose date has passed but was never visited
 * stays RegistrationClosed until someone opens a page that binds it.
 * This is not a bug. It is the cost of zero infrastructure. If this
 * proves insufficient, a scheduled task is a follow-up, not part of
 * this middleware.
 *
 * See SPEC-GAP-1 in the same doc — this middleware must call
 * EventWorkflow, never assign $event->status directly. Every
 * transition in the codebase goes through the workflow service so
 * illegal transitions are rejected at one place.
 */
class AdvanceEventStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event || ! $event->isRunningEvent()) {
            return $next($request);
        }

        if (! in_array($event->status, [EventStatus::RegistrationOpen, EventStatus::RegistrationClosed], true)) {
            return $next($request);
        }

        if ($event->event_date === null) {
            return $next($request);
        }

        $startAt = $event->event_date->copy()->startOfDay();

        if ($event->start_time !== null) {
            $startAt = $startAt->setTime(
                (int) $event->start_time->format('H'),
                (int) $event->start_time->format('i'),
            );
        }

        if (now()->lessThan($startAt)) {
            return $next($request);
        }

        $previousStatus = $event->status;
        try {
            app(EventWorkflow::class)->start($event);

            Log::info('event.status.auto_advanced', [
                'event_id' => $event->id,
                'from' => $previousStatus->value,
                'to' => EventStatus::Ongoing->value,
                'fired_at' => now()->toIso8601String(),
            ]);
        } catch (InvalidArgumentException) {
            // Another concurrent request already advanced the event,
            // or the state changed between our check and the
            // transition. Either way the state is what it should be.
            // Pass through silently — no log, no error.
        }

        return $next($request);
    }
}
