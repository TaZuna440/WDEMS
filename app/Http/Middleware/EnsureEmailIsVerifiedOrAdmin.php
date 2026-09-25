<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureEmailIsVerifiedOrAdmin extends EnsureEmailIsVerified
{
    /**
     * Admins bypass email verification.
     *
     * Rationale: admins control the server and are trusted at a higher
     * level, mirroring the admin exemption inside EnsureDeviceIsTrusted.
     * Every other role must still verify their email before continuing.
     *
     * Signature matches the parent — Laravel's own middleware. If the
     * framework updates that signature, this override must follow.
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
