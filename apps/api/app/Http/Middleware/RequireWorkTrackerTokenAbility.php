<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkTrackerTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        // WorkTracker API is token-only. A Sanctum SPA/session cookie is not enough.
        abort_unless($user && $token && ! $token instanceof TransientToken, 401, 'A WorkTracker personal access token is required.');

        $allowed = collect($abilities)->contains(fn (string $ability): bool => $token->can($ability));
        abort_unless($allowed, 403, 'This token does not have the required WorkTracker ability.');

        return $next($request);
    }
}
