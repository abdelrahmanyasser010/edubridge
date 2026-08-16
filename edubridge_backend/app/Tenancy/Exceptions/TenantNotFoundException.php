<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

final class TenantNotFoundException extends RuntimeException
{
    public static function forHost(string $host): self
    {
        return new self('No active tenant is configured for host ['.$host.'].');
    }

    public static function forSchoolId(int $schoolId): self
    {
        return new self('No active tenant is configured for school ['.$schoolId.'].');
    }
}
