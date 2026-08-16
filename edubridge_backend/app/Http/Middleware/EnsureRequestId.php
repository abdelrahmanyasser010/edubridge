<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set(ApiResponse::REQUEST_ID_ATTRIBUTE, $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->headers->get('X-Request-Id');

        if (is_string($incoming) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::ulid();
    }
}
