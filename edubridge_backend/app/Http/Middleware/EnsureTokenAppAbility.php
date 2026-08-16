<?php

namespace App\Http\Middleware;

use App\Support\Exceptions\AppAccessDeniedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenAppAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();
        if (! $user) {
            throw new AppAccessDeniedException('Access denied: Unauthenticated.');
        }

        // Check if the token has the wildcard '*' ability
        if ($user->tokenCan('*')) {
            return $next($request);
        }

        // Check if the token has at least one of the specified abilities
        foreach ($abilities as $ability) {
            if ($user->tokenCan($ability)) {
                return $next($request);
            }
        }

        throw new AppAccessDeniedException('Access denied: Insufficient permissions for this application.');
    }
}
