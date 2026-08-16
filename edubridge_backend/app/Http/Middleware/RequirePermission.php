<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! Gate::allows($permission)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return $next($request);
    }
}
