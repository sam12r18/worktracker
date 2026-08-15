<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkTrackerHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('worktracker.require_https', true) && app()->environment('production')) {
            abort_unless($request->isSecure(), 426, 'WorkTracker requires HTTPS. Configure trusted proxies when TLS terminates before Laravel.');
        }

        return $next($request);
    }
}
