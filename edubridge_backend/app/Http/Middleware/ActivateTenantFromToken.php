<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class ActivateTenantFromToken
{
    public function __construct(
        private readonly TenantConnectionResolver $resolver,
        private readonly TenantConnectionManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || $token->school_id === null) {
            throw new UnauthorizedHttpException('Bearer', 'A school-scoped token is required.');
        }

        $tenant = $this->resolver->resolveBySchoolId((int) $token->school_id);
        $this->manager->activate($tenant);

        try {
            return $next($request);
        } finally {
            $this->manager->disconnect();
        }
    }
}
