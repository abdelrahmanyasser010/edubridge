<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAppTypeFromRoute
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $appType): Response
    {
        $request->attributes->set('app_type', $appType);
        $request->merge(['app_type' => $appType]);

        return $next($request);
    }
}
