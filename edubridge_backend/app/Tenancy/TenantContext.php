<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\TenantNotResolvedException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function activate(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function active(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new TenantNotResolvedException;
    }

    public function schoolId(): int
    {
        return $this->tenant()->schoolId;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }
}
