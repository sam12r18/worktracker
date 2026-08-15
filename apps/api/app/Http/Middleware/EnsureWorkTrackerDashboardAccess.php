<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkTrackerDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ((bool) config('worktracker.allow_any_authenticated_user', false)) {
            return $next($request);
        }

        $email = strtolower((string) ($user->email ?? ''));
        $allowed = config('worktracker.admin_emails', []);
        $isExplicitAdmin = (bool) (($user->getAttribute('is_worktracker_admin') ?? false) || ($user->getAttribute('is_admin') ?? false));

        abort_unless($isExplicitAdmin || ($email !== '' && in_array($email, $allowed, true)), 403, 'WorkTracker dashboard access is not allowed for this account.');

        return $next($request);
    }
}
