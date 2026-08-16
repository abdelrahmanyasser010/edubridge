<?php

namespace App\Tenancy;

final readonly class Tenant
{
    public function __construct(
        public int $schoolId,
        public string $driver,
        public string $database,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $username = null,
        public ?string $secretRef = null,
        public array $options = [],
    ) {}
}
