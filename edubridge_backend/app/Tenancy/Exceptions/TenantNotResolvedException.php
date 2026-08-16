<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

final class TenantNotResolvedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No tenant is active for the current execution context.');
    }
}
