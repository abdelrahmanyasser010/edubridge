<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreLoginTenantResolver
{
    protected TenantConnectionResolver $resolver;

    public function __construct(TenantConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tenant = $this->resolver->resolveByHost($request->getHost());
            $school = School::query()
                ->where('id', $tenant->schoolId)
                ->where('status', 'active')
                ->first();

            if ($school) {
                $request->attributes->set('resolved_school', $school);
                $request->attributes->set('school_code', $school->code);
                $request->merge(['school_code' => $school->code]);
            }
        } catch (TenantNotFoundException $e) {
            // Fallback: If host is not matched, let the request-provided school_code (if any) be used.
        }

        return $next($request);
    }
}
