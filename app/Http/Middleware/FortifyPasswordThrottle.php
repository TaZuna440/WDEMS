<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class FortifyPasswordThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $limiter = match ($request->route()?->getName()) {
            'password.email' => 'forgot-password',
            'password.confirm.store' => 'confirm-password',
            default => null,
        };

        if ($limiter === null) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, $limiter);
    }
}
