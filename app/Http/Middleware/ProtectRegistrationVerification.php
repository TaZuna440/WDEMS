<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ProtectRegistrationVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');
        if ($event instanceof Event) {
            abort_unless($event->isRunningEvent(), 404);
        }

        Inertia::encryptHistory();
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
